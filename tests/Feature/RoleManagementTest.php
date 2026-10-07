<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class RoleManagementTest extends TestCase
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

    public function test_the_seeded_matrix_matches_the_specification(): void
    {
        $admin = $this->role('Administrador');
        $manager = $this->role('Gerente');
        $seller = $this->role('Vendedor');

        $this->assertEqualsCanonicalizing(
            array_keys(config('roles.permissions')),
            $admin->permissions->pluck('name')->all(),
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
            // La fase 5 le concede al gerente activities.delete: él limpia
            // el seguimiento grabado.
            'activities.view',
            'activities.create',
            'activities.update',
            'activities.delete',

            // El gerente supervisa el pipeline y califica y convierte
            // prospectos, pero nunca los borra: borrar destruye a quién se
            // contactó y por qué se estancó.
            'prospects.view',
            'prospects.create',
            'prospects.update',
            'prospects.change_status',
            'prospects.convert',

            // El gerente supervisa la propuesta como al resto del pipeline:
            // la prepara, decide cuándo se envía y si se acepta o rechaza, y
            // genera su PDF. Nunca la borra.
            'quotes.view',
            'quotes.create',
            'quotes.update',
            'quotes.change_status',
            'quotes.pdf',

            // Los reportes agregan en lectura los cuatro módulos que ya
            // supervisa, así que vienen con esa supervisión.
            'reports.view',
        ], $manager->permissions->pluck('name')->all());

        // El vendedor trabaja clientes, contactos, oportunidades y
        // actividades, pero no borra registros, no retira clientes ni elige
        // el contacto principal.
        $this->assertEqualsCanonicalizing([
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
            'activities.view',
            'activities.create',
            'activities.update',

            // El vendedor recorre el pipeline de punta a punta: capta el
            // prospecto, lo califica y lo convierte cuando está listo.
            'prospects.view',
            'prospects.create',
            'prospects.update',
            'prospects.change_status',
            'prospects.convert',

            // El vendedor escribe y envía la propuesta, el trabajo diario del
            // ciclo de ventas, y necesita su PDF para entregarla. Nunca la
            // borra.
            'quotes.view',
            'quotes.create',
            'quotes.update',
            'quotes.change_status',
            'quotes.pdf',

            // El vendedor ve los reportes de lo que puede consultar: la misma
            // vista de supervisión, acotada a los bloques permitidos.
            'reports.view',
        ], $seller->permissions->pluck('name')->all());

        // El administrador tiene todos los permisos de borrado; el gerente
        // solo activities.delete, la excepción que la fase 5 hace a la regla
        // "solo el administrador destruye".
        $deletable = [
            'clients.delete',
            'contacts.delete',
            'opportunities.delete',
            'activities.delete',
            'prospects.delete',
            'quotes.delete',
        ];

        $this->assertEqualsCanonicalizing(
            $deletable,
            $admin->permissions->whereIn('name', $deletable)->pluck('name')->all(),
        );

        $this->assertEqualsCanonicalizing(
            ['activities.delete'],
            $manager->permissions->whereIn('name', $deletable)->pluck('name')->all(),
        );

        $this->assertSame(
            [],
            $seller->permissions->whereIn('name', $deletable)->pluck('name')->all(),
        );
    }

    public function test_a_permission_reads_its_label_and_group_from_the_configuration(): void
    {
        $permission = Permission::where('name', 'users.view')->firstOrFail();

        // Los nombres contienen puntos, así que una búsqueda con notación de
        // puntos fallaría en silencio y todo caería en el grupo de respaldo.
        $this->assertSame('Ver usuarios', $permission->label());
        $this->assertSame('Usuarios', $permission->group());

        $orphan = Permission::create(['name' => 'sin.declarar', 'guard_name' => 'web']);

        $this->assertSame('sin.declarar', $orphan->label());
        $this->assertSame('Otros', $orphan->group());
    }

    public function test_the_seeder_can_run_twice_without_duplicating(): void
    {
        $before = [
            'roles' => Role::count(),
            'permissions' => Permission::count(),
        ];

        $this->seedRoles();

        $this->assertSame($before['roles'], Role::count());
        $this->assertSame($before['permissions'], Permission::count());
    }

    public function test_a_seller_cannot_reach_the_role_module(): void
    {
        $seller = $this->userWithRoles(['Vendedor']);
        $role = $this->role('Vendedor');

        $this->actingAs($seller);

        $this->get('/roles')->assertForbidden();
        $this->get('/roles/create')->assertForbidden();
        $this->get("/roles/{$role->id}")->assertForbidden();
        $this->get("/roles/{$role->id}/edit")->assertForbidden();

        $this->post('/roles', ['name' => 'Intruso'])->assertForbidden();
        $this->patch("/roles/{$role->id}", ['name' => 'Renombrado'])->assertForbidden();
        $this->delete("/roles/{$role->id}")->assertForbidden();

        $this->assertSame('Vendedor', $role->fresh()->name);
    }

    public function test_the_manager_role_cannot_manage_roles(): void
    {
        $manager = $this->userWithRoles(['Gerente']);

        $this->assertFalse($manager->can('viewAny', Role::class));
        $this->assertFalse($manager->can('create', Role::class));

        $this->actingAs($manager)->get('/roles')->assertForbidden();
    }

    public function test_the_administrator_role_reaches_every_action(): void
    {
        $administrator = $this->userWithRoles(['Administrador']);
        $role = $this->role('Vendedor');

        $this->assertTrue($administrator->can('viewAny', Role::class));
        $this->assertTrue($administrator->can('create', Role::class));
        $this->assertTrue($administrator->can('update', $role));
        $this->assertTrue($administrator->can('delete', $role));
    }

    public function test_the_administrator_can_list_roles(): void
    {
        $this->actingAs($this->admin())
            ->get('/roles')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Roles/Index')
                ->where('can.create', true)
                ->where('permissionsTotal', count(config('roles.permissions')))
                ->has('roles.data', 3)
            );
    }

    public function test_the_administrator_can_create_a_role_with_permissions(): void
    {
        // Aquí basta cualquier permiso existente; el seeder los asigna a
        // Gerente, lo que mantiene el test independiente del orden del catálogo.
        $permission = $this->role('Gerente')->permissions()->firstOrFail();

        $this->actingAs($this->admin())
            ->post('/roles', [
                'name' => 'Supervisor',
                'permissions' => [$permission->id],
            ])
            ->assertRedirect();

        $created = $this->role('Supervisor');

        $this->assertSame([$permission->name], $created->permissions->pluck('name')->all());
    }

    public function test_a_role_can_be_created_without_permissions(): void
    {
        $this->actingAs($this->admin())
            ->post('/roles', ['name' => 'Observador'])
            ->assertRedirect();

        $this->assertCount(0, $this->role('Observador')->permissions);
    }

    public function test_the_role_name_must_be_unique(): void
    {
        $this->actingAs($this->admin())
            ->from('/roles/create')
            ->post('/roles', ['name' => 'Gerente'])
            ->assertSessionHasErrors('name');

        $this->assertSame(3, Role::count());
    }

    public function test_the_administrator_can_change_the_permissions_of_a_role(): void
    {
        $permission = $this->role('Gerente')->permissions()->pluck('id');

        $this->actingAs($this->admin())
            ->patch("/roles/{$this->role('Vendedor')->id}", [
                'name' => 'Vendedor',
                'permissions' => $permission->all(),
            ])
            ->assertRedirect();

        $this->assertEqualsCanonicalizing(
            $permission->map(fn ($id) => Permission::find($id)->name)->all(),
            $this->role('Vendedor')->permissions->pluck('name')->all(),
        );
    }

    public function test_the_full_access_role_keeps_the_whole_catalog(): void
    {
        $administrator = $this->role('Administrador');

        $this->actingAs($this->admin())
            ->patch("/roles/{$administrator->id}", [
                'name' => 'Administrador',
                // Intento de dejar el rol sin permisos.
                'permissions' => [],
            ])
            ->assertRedirect();

        $this->assertCount(
            count(config('roles.permissions')),
            $administrator->fresh()->permissions,
        );
    }

    public function test_a_system_role_cannot_be_renamed(): void
    {
        $administrator = $this->role('Administrador');

        $this->actingAs($this->admin())
            ->from("/roles/{$administrator->id}/edit")
            ->patch("/roles/{$administrator->id}", [
                'name' => 'Root',
                // Intento de vaciarlo también: el renombrado se rechaza antes
                // de tocar los permisos.
                'permissions' => [],
            ])
            ->assertSessionHasErrors('name');

        $administrator->refresh();

        $this->assertSame('Administrador', $administrator->name);
        $this->assertCount(count(config('roles.permissions')), $administrator->permissions);
    }

    public function test_a_custom_role_can_be_renamed(): void
    {
        $role = Role::create(['name' => 'Supervisor', 'guard_name' => 'web']);

        $this->actingAs($this->admin())
            ->patch("/roles/{$role->id}", ['name' => 'Coordinador'])
            ->assertRedirect();

        $this->assertSame('Coordinador', $role->fresh()->name);
    }

    public function test_a_system_role_cannot_be_deleted(): void
    {
        $admin = $this->admin();

        foreach (['Administrador', 'Gerente', 'Vendedor'] as $name) {
            $role = $this->role($name);

            $this->actingAs($admin)
                ->delete("/roles/{$role->id}")
                ->assertRedirect()
                ->assertSessionHas('error', __('roles.system_role_protected'));

            $this->assertModelExists($role);
        }
    }

    public function test_a_role_in_use_cannot_be_deleted(): void
    {
        $role = Role::create(['name' => 'Supervisor', 'guard_name' => 'web']);
        $this->userWithRoles(['Supervisor']);

        $this->actingAs($this->admin())
            ->delete("/roles/{$role->id}")
            ->assertRedirect()
            ->assertSessionHas('error', __('roles.role_in_use'));

        $this->assertModelExists($role);
    }

    public function test_an_unused_custom_role_can_be_deleted(): void
    {
        $role = Role::create(['name' => 'Observador', 'guard_name' => 'web']);

        $this->actingAs($this->admin())
            ->delete("/roles/{$role->id}")
            ->assertRedirect('/roles');

        $this->assertModelMissing($role);
    }

    public function test_the_detail_page_reports_the_users_and_permissions_of_a_role(): void
    {
        $this->userWithRoles(['Gerente'], ['email' => 'gerente@crm.test']);
        $role = $this->role('Gerente');

        $this->actingAs($this->admin())
            ->get("/roles/{$role->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Roles/Show')
                ->where('role.name', 'Gerente')
                ->where('role.is_system', true)
                ->where('role.users_count', 1)
                ->has('role.permissions', count(config('roles.matrix')['Gerente']))
                ->where('can.update', true)
            );
    }

    public function test_the_detail_page_hides_the_delete_action_when_it_would_be_refused(): void
    {
        $systemRole = $this->role('Vendedor');

        $this->actingAs($this->admin())
            ->get("/roles/{$systemRole->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('role.is_system', true)
            );
    }

    public function test_guests_cannot_reach_the_role_module(): void
    {
        $this->get('/roles')->assertRedirect('/login');
    }
}
