<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use InteractsWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // La lista de administradores de arranque viene de la configuración,
        // así que se fija aquí: la suite no depende del .env local.
        config(['crm.admin_emails' => ['admin@crm.test']]);

        $this->seedRoles();
    }

    /**
     * Administrador de arranque, resuelto por el Gate::before de
     * AppServiceProvider: no necesita rol para pasar los chequeos, por eso es
     * la vía de recuperación cuando los permisos están mal configurados.
     */
    private function admin(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'email' => 'admin@crm.test',
            'is_active' => true,
            'email_verified_at' => now(),
        ], $attributes));
    }

    /**
     * Cuenta sin ningún rol. El correo lo deja la factory para que un test
     * pueda crear varias sin chocar.
     */
    private function regularUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'is_active' => true,
            'email_verified_at' => now(),
        ], $attributes));
    }

    public function test_bootstrap_admin_can_list_users(): void
    {
        $this->actingAs($this->admin())
            ->get('/users')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Users/Index')
                ->where('can.create', true)
                ->has('users.data', 1)
            );
    }

    public function test_regular_user_cannot_reach_the_user_module(): void
    {
        $intruder = $this->regularUser();

        $this->actingAs($intruder);

        $this->get('/users')->assertForbidden();
        $this->get('/users/create')->assertForbidden();
        $this->get("/users/{$intruder->id}")->assertForbidden();
        $this->get("/users/{$intruder->id}/edit")->assertForbidden();
    }

    public function test_regular_user_cannot_write_user_records(): void
    {
        $admin = $this->admin();
        $this->actingAs($this->regularUser());

        $this->post('/users', [
            'name' => 'Intruso',
            'email' => 'intruso@crm.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertForbidden();

        $this->patch("/users/{$admin->id}", [
            'name' => 'Renombrado',
            'email' => $admin->email,
        ])->assertForbidden();

        $this->patch("/users/{$admin->id}/status/activate")->assertForbidden();
        $this->delete("/users/{$admin->id}")->assertForbidden();
    }

    public function test_a_user_without_roles_has_no_permissions(): void
    {
        $user = $this->regularUser();

        $this->assertFalse($user->can('viewAny', User::class));
        $this->assertFalse($user->can('create', User::class));
        $this->assertSame([], $user->getAllPermissions()->pluck('name')->all());
    }

    public function test_bootstrap_admin_passes_the_gate_without_any_role(): void
    {
        $admin = $this->admin();

        $this->assertSame([], $admin->getRoleNames()->all());
        $this->assertTrue($admin->can('viewAny', User::class));
        $this->assertTrue($admin->can('create', User::class));
        $this->assertTrue($admin->can('delete', $this->regularUser()));
    }

    public function test_the_seller_role_holds_no_administrative_permission(): void
    {
        $seller = $this->userWithRoles(['Vendedor']);

        $this->assertFalse($seller->can('viewAny', User::class));
        $this->assertFalse($seller->can('create', User::class));
    }

    public function test_the_manager_role_can_manage_users_but_not_delete_them(): void
    {
        $manager = $this->userWithRoles(['Gerente']);
        $target = $this->regularUser();

        $this->assertTrue($manager->can('viewAny', User::class));
        $this->assertTrue($manager->can('create', User::class));
        $this->assertTrue($manager->can('update', $target));
        $this->assertTrue($manager->can('changeStatus', $target));
        $this->assertFalse($manager->can('delete', $target));
    }

    public function test_manager_cannot_reach_the_delete_route_even_with_a_crafted_request(): void
    {
        $manager = $this->userWithRoles(['Gerente']);
        $target = $this->regularUser();

        $this->actingAs($manager)
            ->delete("/users/{$target->id}")
            ->assertForbidden();

        $this->assertModelExists($target);
    }

    public function test_admin_can_create_a_user(): void
    {
        $this->actingAs($this->admin())
            ->post('/users', [
                'name' => 'Nuevo Colaborador',
                'email' => 'Nuevo@CRM.test',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'is_active' => true,
                'email_verified' => true,
            ])
            ->assertRedirect();

        $created = User::where('email', 'nuevo@crm.test')->firstOrFail();

        $this->assertSame('Nuevo Colaborador', $created->name);
        $this->assertTrue($created->isActive());
        $this->assertNotNull($created->email_verified_at);
    }

    public function test_a_new_user_receives_the_default_role(): void
    {
        $this->actingAs($this->admin())
            ->post('/users', [
                'name' => 'Nuevo Colaborador',
                'email' => 'nuevo@crm.test',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'is_active' => true,
            ])
            ->assertRedirect();

        $created = User::where('email', 'nuevo@crm.test')->firstOrFail();

        $this->assertSame(
            [config('roles.default_role')],
            $created->getRoleNames()->all(),
        );
    }

    public function test_editing_a_user_cannot_change_the_status_without_the_permission(): void
    {
        // Un rol que edita cuentas pero no las activa ni desactiva. Ninguno
        // de los sembrados tiene esa forma, así que se construye aquí para
        // probar que el campo lo cuida users.change_status y no users.update.
        $role = Role::create(['name' => 'Editor', 'guard_name' => 'web']);
        $role->syncPermissions(['users.view', 'users.update']);

        $actor = $this->regularUser();
        $actor->syncRoles($role);

        $target = $this->regularUser(['name' => 'Objetivo']);

        $this->actingAs($actor)
            ->patch("/users/{$target->id}", [
                'name' => 'Objetivo',
                'email' => $target->email,
                'is_active' => false,
            ])
            ->assertForbidden();

        $this->assertTrue($target->refresh()->isActive());
    }

    public function test_admin_can_update_a_user_without_touching_the_password(): void
    {
        $target = $this->regularUser(['name' => 'Nombre Antiguo']);
        $originalHash = $target->password;

        $this->actingAs($this->admin())
            ->patch("/users/{$target->id}", [
                'name' => 'Nombre Nuevo',
                'email' => $target->email,
                'email_verified' => true,
            ])
            ->assertRedirect();

        $target->refresh();

        $this->assertSame('Nombre Nuevo', $target->name);
        $this->assertSame($originalHash, $target->password);
    }

    public function test_admin_can_update_the_password_of_a_user(): void
    {
        $target = $this->regularUser();

        $this->actingAs($this->admin())
            ->patch("/users/{$target->id}", [
                'name' => $target->name,
                'email' => $target->email,
                'password' => 'nueva-clave-123',
                'password_confirmation' => 'nueva-clave-123',
            ])
            ->assertRedirect();

        $this->assertTrue(Hash::check('nueva-clave-123', $target->refresh()->password));
    }

    public function test_admin_cannot_deactivate_their_own_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patch("/users/{$admin->id}/status/deactivate")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertTrue($admin->refresh()->isActive());
    }

    public function test_admin_cannot_deactivate_the_last_active_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patch("/users/{$admin->id}/status/deactivate")
            ->assertSessionHas('error', __('users.cannot_change_own_status'));
    }

    public function test_admin_can_deactivate_and_reactivate_another_user(): void
    {
        $admin = $this->admin();
        $target = $this->regularUser();

        $this->actingAs($admin)->patch("/users/{$target->id}/status/deactivate");
        $this->assertFalse($target->refresh()->isActive());

        $this->actingAs($admin)->patch("/users/{$target->id}/status/activate");
        $this->assertTrue($target->refresh()->isActive());
    }

    public function test_a_deactivated_user_keeps_a_role_but_cannot_sign_in(): void
    {
        $seller = $this->userWithRoles(['Vendedor'], ['is_active' => false]);

        $this->assertSame(['Vendedor'], $seller->getRoleNames()->all());

        $this->post('/login', [
            'email' => $seller->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->delete("/users/{$admin->id}")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertModelExists($admin);
    }

    public function test_admin_can_delete_another_user(): void
    {
        $admin = $this->admin();
        $target = $this->regularUser();

        $this->actingAs($admin)
            ->delete("/users/{$target->id}")
            ->assertRedirect('/users');

        $this->assertModelMissing($target);
    }

    public function test_listing_can_be_filtered_by_search_and_status(): void
    {
        $admin = $this->admin();
        $this->regularUser(['name' => 'Ana Ruiz', 'email' => 'ana@crm.test']);
        $this->regularUser(['name' => 'Bruno Díaz', 'email' => 'bruno@crm.test', 'is_active' => false]);

        $this->actingAs($admin)
            ->get('/users?search=bruno')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.search', 'bruno')
                ->has('users.data', 1)
                ->where('users.data.0.email', 'bruno@crm.test')
            );

        $this->actingAs($admin)
            ->get('/users?status=inactive')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.email', 'bruno@crm.test')
            );
    }

    public function test_guest_cannot_reach_the_user_module(): void
    {
        $this->get('/users')->assertRedirect('/login');
    }

    public function test_the_listing_shows_the_roles_of_each_account(): void
    {
        $admin = $this->admin();
        $this->userWithRoles(['Vendedor'], ['email' => 'vendedor@crm.test', 'name' => 'Vendedor Uno']);

        $this->actingAs($admin)
            ->get('/users?search=vendedor')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.roles.0.name', 'Vendedor')
            );
    }
}
