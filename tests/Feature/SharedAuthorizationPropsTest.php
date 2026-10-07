<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

/**
 * Las props compartidas son la costura entre el Gate y Vue: si un nombre de
 * permiso deja de llegar a la interfad, los botones desaparecen en silencio y
 * nadie se entera hasta que un usuario lo reporta, por eso aquí se fija su
 * forma.
 */
class SharedAuthorizationPropsTest extends TestCase
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

    public function test_the_shared_props_list_the_permissions_held_by_the_account(): void
    {
        $manager = $this->userWithRoles(['Gerente']);

        $this->actingAs($manager)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.roles', ['Gerente'])
                ->where('auth.user.email', $manager->email)
                ->has('auth.permissions', count(config('roles.matrix')['Gerente']))
            );
    }

    public function test_a_seller_only_receives_the_module_permissions_plus_reports(): void
    {
        $this->actingAs($this->userWithRoles(['Vendedor']))
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.roles', ['Vendedor'])
                ->where('auth.permissions', [
                    'clients.view',
                    'clients.create',
                    'clients.update',
                    'contacts.view',
                    'contacts.create',
                    'contacts.update',
                    'opportunities.view',
                    'opportunities.create',
                    'opportunities.update',
                    'opportunities.change_status',
                    // El orden es el del catalogo de config/roles.php, que es el
                    // orden en que el seeder crea los permisos.
                    'activities.view',
                    'activities.create',
                    'activities.update',
                    'prospects.view',
                    'prospects.create',
                    'prospects.update',
                    'prospects.change_status',
                    'prospects.convert',
                    'quotes.view',
                    'quotes.create',
                    'quotes.update',
                    'quotes.change_status',
                    'quotes.pdf',
                    'reports.view',
                ])
            );
    }

    public function test_an_account_without_roles_receives_empty_lists(): void
    {
        $withoutRoles = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($withoutRoles)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.roles', [])
                ->where('auth.permissions', [])
            );
    }

    public function test_the_guest_layout_receives_empty_authorization_lists(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.user', null)
                ->where('auth.roles', [])
                ->where('auth.permissions', [])
            );
    }

    public function test_the_role_form_receives_the_permission_catalog(): void
    {
        $this->actingAs($this->admin())
            ->get('/roles/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Roles/Create')
                ->has('permissions')
                ->where('permissions.0.group', 'Usuarios')
                ->has('permissions.0.permissions', 6)
            );
    }

    public function test_the_role_edit_screen_receives_the_current_selection(): void
    {
        $role = $this->role('Gerente');

        $this->actingAs($this->admin())
            ->get("/roles/{$role->id}/edit")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Roles/Edit')
                ->where('role.name', 'Gerente')
                ->where('role.is_system', true)
                ->where('role.is_full_access', false)
                ->has('role.permissions', count(config('roles.matrix')['Gerente']))
            );
    }

    public function test_the_full_access_role_is_flagged_in_the_edit_screen(): void
    {
        $role = $this->role('Administrador');

        $this->actingAs($this->admin())
            ->get("/roles/{$role->id}/edit")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('role.is_full_access', true)
            );
    }
}
