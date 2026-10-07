<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Client;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

/**
 * El dashboard es de solo lectura y su única regla real es de permisos: cada
 * bloque se arma solo si la cuenta puede consultar el módulo y, si no, la
 * prop llega en null. No hay permiso propio de dashboard: un rol sin ninguno
 * de los tres view igual entra y no ve nada del pipeline.
 */
class DashboardTest extends TestCase
{
    use InteractsWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['crm.admin_emails' => ['admin@crm.test']]);

        $this->seedRoles();
    }

    private ?User $admin = null;

    private function admin(): User
    {
        return $this->admin ??= $this->userWithRoles(['Administrador'], ['email' => 'admin@crm.test']);
    }

    private function manager(): User
    {
        return $this->userWithRoles(['Gerente']);
    }

    private function seller(): User
    {
        return $this->userWithRoles(['Vendedor']);
    }

    /**
     * Permisos directos de todo el catálogo menos uno. Bajar el rol no sirve:
     * los heredados por rol sobreviven a revokePermissionTo.
     */
    private function userWithAllPermissionsExcept(string $without): User
    {
        $user = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $user->givePermissionTo(array_values(array_diff(
            array_keys(config('roles.permissions')),
            [$without]
        )));

        return $user->fresh();
    }

    /**
     * Carga el dashboard y devuelve su aserción Inertia, para que el test
     * inspeccione las props o siga encadenando where().
     */
    private function dashboardFor(User $user): AssertableInertia
    {
        $page = null;

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(function (AssertableInertia $inertia) use (&$page) {
                $page = $inertia;
            });

        return $page;
    }

    /**
     * Lee una prop suelta de la página: AssertableInertia no tiene getter y
     * hay tests que necesitan el valor para ramificar, no solo asertarlo.
     */
    private function prop(AssertableInertia $page, string $key): mixed
    {
        return $page->toArray()['props'][$key] ?? null;
    }

    // -------------------------------------------------------------------
    // Acceso
    // -------------------------------------------------------------------

    public function test_a_guest_is_redirected_to_the_login_screen(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_any_authenticated_account_reaches_the_dashboard(): void
    {
        // Sin rol no hay ningún permiso del pipeline: el panel se abre con
        // los datos de la cuenta y nada más.
        $withoutRoles = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($withoutRoles)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Dashboard')
                ->where('account.email', $withoutRoles->email)
                ->where('clients', null)
                ->where('opportunities', null)
                ->where('activities', null)
                ->where('prospects', null)
            );
    }

    public function test_an_inactive_account_is_turned_away(): void
    {
        $inactive = User::factory()->create([
            'is_active' => false,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($inactive)
            ->get('/dashboard')
            ->assertRedirect('/login');
    }

    public function test_an_unverified_account_is_sent_to_the_email_verification(): void
    {
        $unverified = User::factory()->unverified()->create(['is_active' => true]);

        $this->actingAs($unverified)->get('/dashboard')->assertRedirect('/email/verify');
    }

    // -------------------------------------------------------------------
    // Clientes
    // -------------------------------------------------------------------

    public function test_the_client_block_counts_the_active_and_the_inactive(): void
    {
        Client::factory()->count(3)->create();
        Client::factory()->inactive()->count(2)->create();

        $this->dashboardFor($this->admin())
            ->where('clients.active', 3)
            ->where('clients.inactive', 2);
    }

    public function test_the_client_block_lists_the_most_recent_clients_first(): void
    {
        $older = Client::factory()->create(['name' => 'Cliente antiguo']);
        $newer = Client::factory()->create(['name' => 'Cliente reciente']);

        $older->forceFill(['created_at' => now()->subMonth()])->save();

        $this->dashboardFor($this->admin())
            ->has('clients.recent', 2)
            ->where('clients.recent.0.name', 'Cliente reciente')
            ->where('clients.recent.1.name', 'Cliente antiguo')
            ->where('clients.recent.0.status_label', 'Activo')
            ->where('clients.recent.0.type_label', 'Empresa');
    }

    public function test_an_account_without_the_client_permission_receives_no_client_block(): void
    {
        Client::factory()->count(2)->create();

        $this->dashboardFor($this->userWithAllPermissionsExcept('clients.view'))
            ->where('clients', null);
    }

    // -------------------------------------------------------------------
    // Oportunidades y ventas
    // -------------------------------------------------------------------

    public function test_the_opportunity_block_counts_open_won_and_lost(): void
    {
        Opportunity::factory()->count(4)->create();
        Opportunity::factory()->count(3)->won()->create();
        Opportunity::factory()->count(2)->lost()->create();

        $this->dashboardFor($this->admin())
            ->where('opportunities.open', 4)
            ->where('opportunities.won', 3)
            ->where('opportunities.lost', 2);
    }

    public function test_the_won_opportunities_count_as_sales_and_their_amounts_are_summed(): void
    {
        Opportunity::factory()->won()->create(['amount' => '100000.00']);
        Opportunity::factory()->won()->create(['amount' => '25000.50']);
        Opportunity::factory()->lost()->create(['amount' => '900000.00']);
        Opportunity::factory()->create(['amount' => '500000.00']);

        $this->dashboardFor($this->admin())
            ->where('opportunities.won', 2)
            ->where('opportunities.wonAmount', '125000.50');
    }

    public function test_the_open_list_shows_the_closest_closing_opportunities(): void
    {
        $far = Opportunity::factory()->create([
            'name' => 'Cierra en diciembre',
            'expected_close_date' => '2026-12-20',
        ]);
        $soon = Opportunity::factory()->create([
            'name' => 'Cierra en octubre',
            'expected_close_date' => '2026-10-20',
        ]);
        $withoutDate = Opportunity::factory()->create([
            'name' => 'Sin fecha de cierre',
            'expected_close_date' => null,
        ]);

        $this->dashboardFor($this->admin())
            ->has('opportunities.openList', 3)
            ->where('opportunities.openList.0.id', $soon->id)
            ->where('opportunities.openList.1.id', $far->id)
            ->where('opportunities.openList.2.id', $withoutDate->id);
    }

    public function test_an_account_without_the_opportunity_permission_receives_no_opportunity_block(): void
    {
        Opportunity::factory()->won()->create(['amount' => '100000.00']);

        $this->dashboardFor($this->userWithAllPermissionsExcept('opportunities.view'))
            ->where('opportunities', null);
    }

    // -------------------------------------------------------------------
    // Etapas
    // -------------------------------------------------------------------

    public function test_the_stage_summary_only_counts_open_opportunities(): void
    {
        Opportunity::factory()->count(2)->stage('nueva')->create();
        Opportunity::factory()->count(3)->stage('negociacion')->create();
        Opportunity::factory()->won()->create(['stage' => 'ganada']);
        Opportunity::factory()->lost()->create(['stage' => 'perdida']);

        $page = $this->dashboardFor($this->admin());

        $openStages = config('opportunities.open_stages');

        $summary = array_fill_keys($openStages, 0);
        $summary['nueva'] = 2;
        $summary['negociacion'] = 3;

        $page->where('opportunities.byStage', array_map(
            fn (string $stage): array => [
                'stage' => $stage,
                'label' => config('opportunities.stages')[$stage],
                'count' => $summary[$stage],
            ],
            $openStages
        ));
    }

    public function test_the_stage_summary_keeps_the_catalog_order_and_uses_the_configuration(): void
    {
        $page = $this->dashboardFor($this->admin());

        $expected = array_map(
            fn (string $stage): array => [
                'stage' => $stage,
                'label' => config('opportunities.stages')[$stage],
                'count' => 0,
            ],
            config('opportunities.open_stages')
        );

        $page->where('opportunities.byStage', $expected);
    }

    // -------------------------------------------------------------------
    // Actividades
    // -------------------------------------------------------------------

    public function test_the_activity_block_counts_every_status(): void
    {
        Activity::factory()->count(5)->status('pendiente')->create();
        Activity::factory()->count(8)->status('completada')->create();
        Activity::factory()->count(2)->status('cancelada')->create();

        $this->dashboardFor($this->admin())
            ->where('activities.pending', 5)
            ->where('activities.completed', 8)
            ->where('activities.cancelled', 2);
    }

    public function test_the_pending_list_shows_the_oldest_ones_first(): void
    {
        $newer = Activity::factory()->status('pendiente')->create([
            'description' => 'Seguimiento reciente',
            'activity_date' => '2026-10-20 09:00',
        ]);
        $older = Activity::factory()->status('pendiente')->create([
            'description' => 'Seguimiento atrasado',
            'activity_date' => '2026-10-01 09:00',
        ]);

        $this->dashboardFor($this->admin())
            ->has('activities.pendingList', 2)
            ->where('activities.pendingList.0.id', $older->id)
            ->where('activities.pendingList.0.description', 'Seguimiento atrasado')
            ->where('activities.pendingList.0.type_label', 'Llamada')
            ->where('activities.pendingList.1.id', $newer->id);
    }

    public function test_the_pending_list_leaves_out_the_activities_that_are_not_pending(): void
    {
        Activity::factory()->count(3)->status('completada')->create();
        Activity::factory()->status('cancelada')->create();

        $this->dashboardFor($this->admin())
            ->where('activities.pendingList', [])
            ->has('activities.recent', 4);
    }

    public function test_the_recent_list_comes_from_the_newest_activities_in_any_status(): void
    {
        $oldest = Activity::factory()->status('completada')->create([
            'description' => 'La mas antigua',
            'activity_date' => '2026-10-01 09:00',
        ]);
        $newest = Activity::factory()->status('pendiente')->create([
            'description' => 'La mas reciente',
            'activity_date' => '2026-10-25 09:00',
        ]);

        $this->dashboardFor($this->admin())
            ->has('activities.recent', 2)
            ->where('activities.recent.0.id', $newest->id)
            ->where('activities.recent.0.status_label', 'Pendiente')
            ->where('activities.recent.1.id', $oldest->id)
            ->where('activities.recent.1.status_label', 'Completada');
    }

    public function test_the_activity_lists_carry_the_client_and_the_opportunity(): void
    {
        $opportunity = Opportunity::factory()->create();
        $activity = Activity::factory()->forOpportunity($opportunity)->create([
            'status' => 'pendiente',
        ]);

        $this->dashboardFor($this->admin())
            ->where('activities.pendingList.0.id', $activity->id)
            ->where('activities.pendingList.0.client.id', $opportunity->client_id)
            ->where('activities.pendingList.0.client.name', $opportunity->client->name)
            ->where('activities.pendingList.0.opportunity.id', $opportunity->id)
            ->where('activities.pendingList.0.opportunity.name', $opportunity->name)
            ->where('activities.pendingList.0.user.id', $activity->user_id);
    }

    public function test_an_account_without_the_activity_permission_receives_no_activity_block(): void
    {
        Activity::factory()->count(3)->create();

        $this->dashboardFor($this->userWithAllPermissionsExcept('activities.view'))
            ->where('activities', null);
    }

    // -------------------------------------------------------------------
    // Permisos de la matriz real
    // -------------------------------------------------------------------

    public function test_an_administrator_and_a_manager_see_the_three_blocks(): void
    {
        foreach ([$this->admin(), $this->manager()] as $user) {
            $page = $this->dashboardFor($user);

            foreach (['clients', 'opportunities', 'activities'] as $block) {
                if ($this->prop($page, $block) === null) {
                    $this->fail("el bloque {$block} no llego a {$user->email}");
                }
            }
        }
    }

    public function test_a_seller_sees_exactly_what_its_permissions_allow(): void
    {
        // El vendedor hoy tiene los tres view y ve el resumen completo; no
        // puede borrar, y eso lo aplican las pantallas del módulo.
        $seller = $this->seller();

        $this->assertTrue($seller->can('clients.view'));
        $this->assertTrue($seller->can('opportunities.view'));
        $this->assertTrue($seller->can('activities.view'));

        $page = $this->dashboardFor($seller);

        $this->assertNotNull($this->prop($page, 'clients'));
        $this->assertNotNull($this->prop($page, 'opportunities'));
        $this->assertNotNull($this->prop($page, 'activities'));
    }

    public function test_the_dashboard_exposes_no_data_of_a_module_the_account_cannot_consult(): void
    {
        // Solo el módulo de clientes: los otros dos bloques deben ser null
        // aunque la base esté llena de oportunidades y actividades.
        $onlyClients = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $onlyClients->givePermissionTo('clients.view');

        Client::factory()->count(2)->create();

        // Cuelgan de un cliente existente para que el total siga en dos y
        // una fuga no se confunda con registros extra.
        $client = Client::first();
        Opportunity::factory()->forClient($client)->won()->create(['amount' => '100000.00']);
        Activity::factory()->forClient($client)->count(3)->create();

        $page = $this->dashboardFor($onlyClients);

        $this->assertSame(2, $this->prop($page, 'clients')['active']);
        $this->assertNull($this->prop($page, 'opportunities'));
        $this->assertNull($this->prop($page, 'activities'));

        // La página serializada tampoco debe llevar importes ni nombres.
        $this->actingAs($onlyClients)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('100000.00')
            ->assertDontSee('Oportunidades abiertas');
    }

    // -------------------------------------------------------------------
    // Base de datos vacía
    // -------------------------------------------------------------------

    public function test_the_dashboard_works_on_an_empty_database(): void
    {
        $this->dashboardFor($this->admin())
            ->where('clients.active', 0)
            ->where('clients.inactive', 0)
            ->where('clients.recent', [])
            ->where('opportunities.open', 0)
            ->where('opportunities.won', 0)
            ->where('opportunities.lost', 0)
            ->where('opportunities.wonAmount', '0.00')
            ->where('opportunities.openList', [])
            ->where('activities.pending', 0)
            ->where('activities.completed', 0)
            ->where('activities.cancelled', 0)
            ->where('activities.pendingList', [])
            ->where('activities.recent', []);
    }

    public function test_the_lists_are_capped(): void
    {
        Client::factory()->count(12)->create();
        Opportunity::factory()->count(12)->create();
        Activity::factory()->count(12)->status('pendiente')->create();
        Activity::factory()->count(12)->create();

        $page = $this->dashboardFor($this->admin());

        $this->assertCount(5, $this->prop($page, 'clients')['recent']);
        $this->assertCount(5, $this->prop($page, 'opportunities')['openList']);
        $this->assertCount(5, $this->prop($page, 'activities')['pendingList']);
        $this->assertCount(5, $this->prop($page, 'activities')['recent']);
    }
}
