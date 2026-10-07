<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

/**
 * La asignación de roles es el paso donde ocurriría una escalada de
 * privilegios, así que estos tests son peticiones construidas a mano y no
 * clics de interfaz: ocultar un selector en Vue no es un control, lo único
 * que protege el sistema es el servidor.
 */
class UserRoleAssignmentTest extends TestCase
{
    use InteractsWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['crm.admin_emails' => ['admin@crm.test']]);

        $this->seedRoles();
    }

    private function admin(): User
    {
        return User::factory()->create([
            'email' => 'admin@crm.test',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function target(): User
    {
        return User::factory()->create([
            'email' => 'objetivo@crm.test',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    public function test_an_administrator_can_assign_several_roles_at_once(): void
    {
        $admin = $this->admin();
        $target = $this->target();

        $this->actingAs($admin)
            ->patch("/users/{$target->id}", [
                'name' => $target->name,
                'email' => $target->email,
                'roles' => [$this->role('Vendedor')->id, $this->role('Gerente')->id],
            ])
            ->assertRedirect();

        $this->assertEqualsCanonicalizing(
            ['Vendedor', 'Gerente'],
            $target->refresh()->getRoleNames()->all(),
        );
    }

    public function test_the_permissions_of_several_roles_are_added_together(): void
    {
        $admin = $this->admin();
        $target = $this->target();

        $this->actingAs($admin)->patch("/users/{$target->id}", [
            'name' => $target->name,
            'email' => $target->email,
            'roles' => [$this->role('Vendedor')->id, $this->role('Gerente')->id],
        ]);

        // Vendedor es subconjunto estricto de Gerente: la unión de los dos
        // es Gerente entero, con los permisos de las fases 3 a 7 y el de
        // reportes de la fase 10.
        $this->assertEqualsCanonicalizing(
            $this->role('Gerente')->permissions->pluck('name')->all(),
            $target->refresh()->getAllPermissions()->pluck('name')->all(),
        );

        $this->assertEqualsCanonicalizing([
            'users.view',
            'users.create',
            'users.update',
            'users.change_status',
            'clients.view',
            'clients.create',
            'clients.update',
            'clients.change_status',
            'contacts.view',
            'contacts.create',
            'contacts.update',
            'contacts.set_primary',
            'opportunities.view',
            'opportunities.create',
            'opportunities.update',
            'opportunities.change_status',
            'prospects.view',
            'prospects.create',
            'prospects.update',
            'prospects.change_status',
            'prospects.convert',
            'activities.view',
            'activities.create',
            'activities.update',
            'activities.delete',
            'quotes.view',
            'quotes.create',
            'quotes.update',
            'quotes.change_status',
            'quotes.pdf',
            'reports.view',
        ], $target->refresh()->getAllPermissions()->pluck('name')->all());
    }

    public function test_roles_can_be_removed(): void
    {
        $admin = $this->admin();
        $target = $this->userWithRoles(['Gerente', 'Vendedor'], ['email' => 'objetivo@crm.test']);

        $this->actingAs($admin)
            ->patch("/users/{$target->id}", [
                'name' => $target->name,
                'email' => $target->email,
                'roles' => [$this->role('Vendedor')->id],
            ])
            ->assertRedirect();

        $this->assertSame(['Vendedor'], $target->refresh()->getRoleNames()->all());
    }

    public function test_a_manager_cannot_assign_roles_even_with_a_crafted_request(): void
    {
        $manager = $this->userWithRoles(['Gerente']);
        $target = $this->target();
        $originalName = $target->name;

        // El gerente tiene users.update, así que en lo demás la petición es
        // válida: solo puede faltar users.assign_roles.
        $this->actingAs($manager)
            ->patch("/users/{$target->id}", [
                'name' => 'Escalado',
                'email' => $target->email,
                'roles' => [$this->role('Administrador')->id],
            ])
            ->assertForbidden();

        $target->refresh();

        $this->assertSame([], $target->getRoleNames()->all());
        $this->assertSame($originalName, $target->name);
    }

    public function test_a_seller_cannot_assign_roles(): void
    {
        $seller = $this->userWithRoles(['Vendedor']);
        $target = $this->target();

        $this->actingAs($seller)
            ->patch("/users/{$target->id}", [
                'name' => $target->name,
                'email' => $target->email,
                'roles' => [$this->role('Administrador')->id],
            ])
            ->assertForbidden();

        $this->assertSame([], $target->refresh()->getRoleNames()->all());
    }

    public function test_removing_every_role_is_also_refused_without_the_permission(): void
    {
        $manager = $this->userWithRoles(['Gerente']);
        $target = $this->userWithRoles(['Vendedor'], ['email' => 'objetivo@crm.test']);

        // Un arreglo vacío también es un cambio: quita todos los roles, así
        // que pide el mismo permiso.
        $this->actingAs($manager)
            ->patch("/users/{$target->id}", [
                'name' => $target->name,
                'email' => $target->email,
                'roles' => [],
            ])
            ->assertForbidden();

        $this->assertSame(['Vendedor'], $target->refresh()->getRoleNames()->all());
    }

    public function test_a_manager_may_still_edit_the_basic_data(): void
    {
        $manager = $this->userWithRoles(['Gerente']);
        $target = $this->target();

        $this->actingAs($manager)
            ->patch("/users/{$target->id}", [
                'name' => 'Nombre Corregido',
                'email' => $target->email,
            ])
            ->assertRedirect();

        $this->assertSame('Nombre Corregido', $target->refresh()->name);
    }

    public function test_an_administrator_cannot_change_their_own_roles(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patch("/users/{$admin->id}", [
                'name' => $admin->name,
                'email' => $admin->email,
                'roles' => [$this->role('Vendedor')->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('error', __('users.cannot_change_own_roles'));

        $this->assertSame([], $admin->refresh()->getRoleNames()->all());
    }

    public function test_a_role_that_does_not_exist_is_rejected(): void
    {
        $admin = $this->admin();
        $target = $this->target();

        $this->actingAs($admin)
            ->from("/users/{$target->id}/edit")
            ->patch("/users/{$target->id}", [
                'name' => $target->name,
                'email' => $target->email,
                'roles' => [9999],
            ])
            ->assertSessionHasErrors('roles.0');

        $this->assertSame([], $target->refresh()->getRoleNames()->all());
    }

    public function test_the_edit_screen_only_offers_roles_to_those_who_may_assign_them(): void
    {
        $admin = $this->admin();
        $target = $this->target();

        $this->actingAs($admin)
            ->get("/users/{$target->id}/edit")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Users/Edit')
                ->where('can.assignRoles', true)
                ->has('roles')
            );

        $manager = $this->userWithRoles(['Gerente'], ['email' => 'gerente@crm.test']);

        $this->actingAs($manager)
            ->get("/users/{$target->id}/edit")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('can.assignRoles', false)
            );
    }

    public function test_the_new_user_screen_offers_the_default_role_to_the_administrator(): void
    {
        $this->actingAs($this->admin())
            ->get('/users/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Users/Create')
                ->has('roles')
            );
    }
}
