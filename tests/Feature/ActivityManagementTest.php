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
 * Autorización y reglas del negocio del módulo de actividades.
 *
 * La actividad siempre pertenece a un cliente y su oportunidad, si existe,
 * al mismo cliente. Un cliente o una oportunidad con actividades no se borra.
 */
class ActivityManagementTest extends TestCase
{
    use InteractsWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['crm.admin_emails' => ['admin@crm.test']]);

        $this->seedRoles();
    }

    // En caché: el correo es único y varios tests piden el administrador más de una vez.
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
     * revokePermissionTo no toca los permisos heredados por rol.
     */
    private function userWithAllPermissionsExcept(string $without): User
    {
        $names = array_keys(config('roles.permissions'));

        $user = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $user->givePermissionTo(array_values(array_diff($names, [$without])));

        return $user->fresh();
    }

    /**
     * El payload que envían las pantallas de alta y edición.
     *
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'llamada',
            'client_id' => Client::factory()->create()->id,
            'opportunity_id' => '',
            'user_id' => User::factory()->create(['is_active' => true])->id,
            'activity_date' => '2026-10-05 09:30',
            'status' => 'pendiente',
            'description' => 'Se confirmo la cita para revisar la propuesta.',
        ], $overrides);
    }

    // -------------------------------------------------------------------
    // Autorización
    // -------------------------------------------------------------------

    public function test_a_guest_cannot_reach_the_activities_module(): void
    {
        $activity = Activity::factory()->create();

        $this->get('/activities')->assertRedirect('/login');
        $this->get('/activities/create')->assertRedirect('/login');
        $this->get("/activities/{$activity->id}")->assertRedirect('/login');
        $this->get("/activities/{$activity->id}/edit")->assertRedirect('/login');
    }

    public function test_an_account_without_roles_is_refused_everywhere(): void
    {
        $activity = Activity::factory()->create();
        $withoutRoles = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($withoutRoles)->get('/activities')->assertForbidden();
        $this->actingAs($withoutRoles)->get('/activities/create')->assertForbidden();
        $this->actingAs($withoutRoles)->get("/activities/{$activity->id}")->assertForbidden();
        $this->actingAs($withoutRoles)->get("/activities/{$activity->id}/edit")->assertForbidden();
        $this->actingAs($withoutRoles)
            ->delete("/activities/{$activity->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('activities', ['id' => $activity->id]);
    }

    public function test_viewing_and_creating_activities_require_their_permission(): void
    {
        $activity = Activity::factory()->create();
        $payload = $this->payload();

        $withoutView = $this->userWithAllPermissionsExcept('activities.view');

        $this->actingAs($withoutView)->get('/activities')->assertForbidden();
        $this->actingAs($withoutView)->get("/activities/{$activity->id}")->assertForbidden();

        // Crear es un permiso aparte: sin activities.create no se da de alta,
        // aunque sí se pueda listar.
        $withoutCreate = $this->userWithAllPermissionsExcept('activities.create');

        $this->actingAs($withoutCreate)->get('/activities')->assertOk();
        $this->actingAs($withoutCreate)->get("/activities/{$activity->id}")->assertOk();
        $this->actingAs($withoutCreate)->get('/activities/create')->assertForbidden();
        $this->actingAs($withoutCreate)->post('/activities', $payload)->assertForbidden();

        $this->assertDatabaseCount('activities', 1);
    }

    public function test_updating_and_deleting_activities_require_their_permission(): void
    {
        $activity = Activity::factory()->create();
        $payload = $this->payload(['client_id' => $activity->client_id]);

        $withoutUpdate = $this->userWithAllPermissionsExcept('activities.update');

        $this->actingAs($withoutUpdate)
            ->patch("/activities/{$activity->id}", $payload)
            ->assertForbidden();
        $this->actingAs($withoutUpdate)->get("/activities/{$activity->id}/edit")->assertForbidden();

        $withoutDelete = $this->userWithAllPermissionsExcept('activities.delete');

        $this->actingAs($withoutDelete)
            ->delete("/activities/{$activity->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('activities', ['id' => $activity->id]);
    }

    public function test_a_seller_manages_activities_but_does_not_delete_them(): void
    {
        $seller = $this->seller();
        $payload = $this->payload(['user_id' => $seller->id]);

        $this->actingAs($seller)->get('/activities')->assertOk();

        $this->actingAs($seller)
            ->post('/activities', $payload)
            ->assertRedirect();

        $activity = Activity::sole();

        $this->actingAs($seller)->get("/activities/{$activity->id}")->assertOk();
        $this->actingAs($seller)
            ->patch("/activities/{$activity->id}", $payload)
            ->assertRedirect();

        $this->actingAs($seller)
            ->delete("/activities/{$activity->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('activities', ['id' => $activity->id]);
    }

    public function test_a_manager_deletes_activities_and_a_list_reports_the_ability(): void
    {
        $manager = $this->manager();
        $activity = Activity::factory()->create();

        $this->actingAs($manager)
            ->delete("/activities/{$activity->id}")
            ->assertRedirect('/activities');

        $this->assertDatabaseMissing('activities', ['id' => $activity->id]);

        $activity = Activity::factory()->create();

        $this->actingAs($manager)
            ->get('/activities')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('can.create', true)
                ->where('can.update', true)
                ->where('can.delete', true)
            );
    }

    public function test_the_list_hides_the_update_action_from_a_seller(): void
    {
        $this->actingAs($this->seller())
            ->get('/activities')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('can.create', true)
                ->where('can.update', true)
                ->where('can.delete', false)
            );
    }

    // -------------------------------------------------------------------
    // Alta
    // -------------------------------------------------------------------

    public function test_an_activity_is_registered_against_a_client(): void
    {
        $client = Client::factory()->create();
        $responsible = User::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin())
            ->post('/activities', $this->payload([
                'client_id' => $client->id,
                'user_id' => $responsible->id,
            ]))
            ->assertRedirect();

        $activity = Activity::sole();

        $this->assertSame('llamada', $activity->type);
        $this->assertTrue($activity->client->is($client));
        $this->assertTrue($activity->user->is($responsible));
        $this->assertNull($activity->opportunity);
        $this->assertSame('pendiente', $activity->status);
        $this->assertStringContainsString('Se confirmo la cita', $activity->description);
    }

    public function test_the_optional_opportunity_must_belong_to_the_selected_client(): void
    {
        $client = Client::factory()->create();
        $other = Client::factory()->create();
        $opportunity = Opportunity::factory()->create(['client_id' => $client->id]);

        $this->actingAs($this->admin())
            ->post('/activities', $this->payload([
                'client_id' => $client->id,
                'opportunity_id' => $opportunity->id,
            ]))
            ->assertRedirect();

        $this->assertTrue(Activity::sole()->opportunity->is($opportunity));

        // El cruce se rechaza y la actividad no se crea.
        $this->actingAs($this->admin())
            ->from('/activities/create')
            ->post('/activities', $this->payload([
                'client_id' => $other->id,
                'opportunity_id' => $opportunity->id,
            ]))
            ->assertRedirect('/activities/create')
            ->assertSessionHasErrors('opportunity_id');

        $this->assertDatabaseCount('activities', 1);
    }

    public function test_an_activity_can_be_registered_without_an_opportunity(): void
    {
        $this->actingAs($this->admin())
            ->post('/activities', $this->payload(['opportunity_id' => '']))
            ->assertRedirect();

        $this->assertNull(Activity::sole()->opportunity);
    }

    public function test_the_client_the_responsible_and_the_type_are_required(): void
    {
        $this->actingAs($this->admin())
            ->from('/activities/create')
            ->post('/activities', [
                'type' => '',
                'client_id' => '',
                'user_id' => '',
                'activity_date' => '2026-10-05 09:30',
                'status' => 'pendiente',
                'description' => '',
            ])
            ->assertSessionHasErrors(['type', 'client_id', 'user_id', 'description']);

        $this->assertDatabaseCount('activities', 0);
    }

    public function test_the_type_the_status_and_the_description_are_validated_against_the_configuration(): void
    {
        $this->actingAs($this->admin())
            ->from('/activities/create')
            ->post('/activities', $this->payload([
                'type' => 'teletransporte',
                'status' => 'aplazada',
            ]))
            ->assertSessionHasErrors(['type', 'status']);

        $this->assertDatabaseCount('activities', 0);
    }

    public function test_the_description_is_required_and_limited(): void
    {
        $this->actingAs($this->admin())
            ->from('/activities/create')
            ->post('/activities', $this->payload(['description' => '']))
            ->assertSessionHasErrors('description');

        $this->actingAs($this->admin())
            ->from('/activities/create')
            ->post('/activities', $this->payload(['description' => str_repeat('a', 5001)]))
            ->assertSessionHasErrors('description');

        $this->assertDatabaseCount('activities', 0);
    }

    public function test_the_responsible_has_to_be_active(): void
    {
        $inactiveUser = User::factory()->create(['is_active' => false]);

        $this->actingAs($this->admin())
            ->from('/activities/create')
            ->post('/activities', $this->payload(['user_id' => $inactiveUser->id]))
            ->assertSessionHasErrors('user_id');

        $this->assertDatabaseCount('activities', 0);
    }

    // -------------------------------------------------------------------
    // Pantalla de alta
    // -------------------------------------------------------------------

    public function test_the_create_screen_preselects_the_client_and_the_opportunity_of_the_ficha(): void
    {
        $client = Client::factory()->create();
        $opportunity = Opportunity::factory()->create(['client_id' => $client->id]);

        $this->actingAs($this->admin())
            ->get("/activities/create?client_id={$client->id}&opportunity_id={$opportunity->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('initialClientId', $client->id)
                ->where('initialOpportunityId', $opportunity->id)
                ->where('initialStatus', config('activities.initial_status'))
            );
    }

    public function test_the_create_screen_preselects_the_opportunity_and_infers_its_client(): void
    {
        $opportunity = Opportunity::factory()->create();

        $this->actingAs($this->admin())
            ->get("/activities/create?opportunity_id={$opportunity->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('initialClientId', $opportunity->client_id)
                ->where('initialOpportunityId', $opportunity->id)
            );
    }

    public function test_the_create_screen_ignores_an_incompatible_preselection(): void
    {
        $client = Client::factory()->create();
        $opportunity = Opportunity::factory()->create(['client_id' => Client::factory()->create()->id]);

        $this->actingAs($this->admin())
            ->get("/activities/create?client_id={$client->id}&opportunity_id={$opportunity->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('initialClientId', $client->id)
                ->where('initialOpportunityId', null)
            );
    }

    public function test_the_create_screen_offers_only_active_clients_and_responsible_users(): void
    {
        $activeClient = Client::factory()->create(['name' => 'Distribuidora Activa']);
        Client::factory()->create(['name' => 'Distribuidora Inactiva', 'status' => 'inactivo']);

        $activeUser = User::factory()->create(['name' => 'Responsable Activo', 'is_active' => true]);
        User::factory()->create(['name' => 'Responsable Inactivo', 'is_active' => false]);

        // Cuenta con los dos permisos del módulo y sin rol, para que la lista
        // de responsables sea exactamente las dos creadas más ella.
        $viewer = User::factory()->create([
            'name' => 'Ana Vega',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $viewer->givePermissionTo(['activities.view', 'activities.create']);

        $this->actingAs($viewer)
            ->get('/activities/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('clients', 1)
                ->where('clients.0.id', $activeClient->id)
                ->where('clients.0.name', 'Distribuidora Activa')
                ->missing('clients.1')
                ->has('users', 2)
                ->where('users.0.id', $viewer->id)
                ->where('users.0.name', 'Ana Vega')
                ->where('users.1.id', $activeUser->id)
                ->where('users.1.name', 'Responsable Activo')
                ->missing('users.2')
            );
    }

    // -------------------------------------------------------------------
    // Consulta
    // -------------------------------------------------------------------

    public function test_the_detail_page_shows_the_activity_with_its_relations(): void
    {
        $opportunity = Opportunity::factory()->create();
        $activity = Activity::factory()->forOpportunity($opportunity)->create();

        $this->actingAs($this->admin())
            ->get("/activities/{$activity->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('activity.id', $activity->id)
                ->where('activity.client_id', $opportunity->client_id)
                ->where('activity.opportunity_id', $opportunity->id)
                ->where('activity.user_id', $activity->user_id)
                ->where('activity.type_label', config('activities.types')[$activity->type])
                ->where('activity.status_label', config('activities.statuses')[$activity->status])
            );
    }

    public function test_the_list_searches_by_description_and_client_name(): void
    {
        $client = Client::factory()->create(['name' => 'Distribuidora del Norte']);
        $matching = Activity::factory()->for($client, 'client')->create([
            'description' => 'Se agendo la renovacion del seguro.',
        ]);
        $other = Activity::factory()->for($client, 'client')->create([
            'description' => 'Se entrego la factura del mes.',
        ]);

        $this->actingAs($this->admin())
            ->get('/activities?search=renovacion')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('activities.data', 1)
                ->where('activities.data.0.id', $matching->id)
            );

        $this->actingAs($this->admin())
            ->get('/activities?search=Distribuidora')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('activities.data', 2)
            );

        $this->actingAs($this->admin())
            ->get('/activities?search=seguro')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('activities.data', 1)
            );

        $this->assertNotNull($other);
    }

    public function test_the_list_filters_by_type_status_responsible_client_and_opportunity(): void
    {
        $client = Client::factory()->create();
        $otherClient = Client::factory()->create();
        $opportunity = Opportunity::factory()->create(['client_id' => $client->id]);
        $responsible = User::factory()->create(['is_active' => true]);

        $call = Activity::factory()->for($client, 'client')->responsible($responsible)->create([
            'type' => 'llamada',
            'status' => 'pendiente',
            'activity_date' => '2026-10-01 10:00',
        ]);
        $email = Activity::factory()->for($otherClient, 'client')->create([
            'type' => 'correo',
            'status' => 'completada',
            'activity_date' => '2026-10-02 10:00',
        ]);
        $meeting = Activity::factory()
            ->forOpportunity($opportunity)
            ->responsible($responsible)
            ->create([
                'type' => 'reunion',
                'status' => 'cancelada',
                'activity_date' => '2026-10-03 10:00',
            ]);

        $this->actingAs($this->admin())
            ->get('/activities?type=llamada')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('activities.data', 1)
                ->where('activities.data.0.id', $call->id)
            );

        $this->actingAs($this->admin())
            ->get('/activities?status=completada')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('activities.data', 1)
                ->where('activities.data.0.id', $email->id)
            );

        $this->actingAs($this->admin())
            ->get("/activities?user_id={$responsible->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('activities.data', 2)
            );

        $this->actingAs($this->admin())
            ->get("/activities?client_id={$client->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('activities.data', 2)
            );

        $this->actingAs($this->admin())
            ->get("/activities?opportunity_id={$opportunity->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('activities.data', 1)
                ->where('activities.data.0.id', $meeting->id)
            );
    }

    public function test_the_list_filters_by_date_range(): void
    {
        $early = Activity::factory()->create(['activity_date' => '2026-10-01 09:00']);
        $late = Activity::factory()->create(['activity_date' => '2026-10-20 09:00']);

        $this->actingAs($this->admin())
            ->get('/activities?date_from=2026-10-01&date_to=2026-10-10')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('activities.data', 1)
                ->where('activities.data.0.id', $early->id)
            );

        $this->actingAs($this->admin())
            ->get('/activities?date_from=2026-10-15')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('activities.data', 1)
                ->where('activities.data.0.id', $late->id)
            );
    }

    public function test_the_list_sorts_by_the_allowed_columns_and_ignores_the_rest(): void
    {
        $newer = Activity::factory()->create(['activity_date' => '2026-10-10 09:00']);
        $older = Activity::factory()->create(['activity_date' => '2026-10-01 09:00']);

        $this->actingAs($this->admin())
            ->get('/activities?sort=activity_date&direction=desc')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('activities.data.0.id', $newer->id)
            );

        $this->actingAs($this->admin())
            ->get('/activities?sort=activity_date&direction=asc')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('activities.data.0.id', $older->id)
            );

        $this->actingAs($this->admin())
            ->get('/activities?sort=description&direction=asc')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.sort', config('activities.default_sort'))
            );

        $this->actingAs($this->admin())
            ->get('/activities?sort=activity_date&direction=sideways')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.direction', 'desc')
            );
    }

    public function test_the_list_paginates(): void
    {
        Activity::factory()->count(15)->create();

        config(['crm.per_page' => 10]);

        $this->actingAs($this->admin())
            ->get('/activities')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('activities.data', 10)
                ->where('activities.total', 15)
                ->where('activities.current_page', 1)
            );

        $this->actingAs($this->admin())
            ->get('/activities?page=2')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('activities.data', 5)
                ->where('activities.current_page', 2)
            );
    }

    public function test_the_list_rejects_filters_that_do_not_exist(): void
    {
        $this->actingAs($this->admin())
            ->get('/activities?client_id=999999')
            ->assertSessionHasErrors('client_id');
    }

    // -------------------------------------------------------------------
    // Edición
    // -------------------------------------------------------------------

    public function test_an_activity_is_updated_with_its_relations(): void
    {
        $activity = Activity::factory()->create();
        $opportunity = Opportunity::factory()->create(['client_id' => $activity->client_id]);
        $responsible = User::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin())
            ->patch("/activities/{$activity->id}", [
                'type' => 'reunion',
                'client_id' => $activity->client_id,
                'opportunity_id' => $opportunity->id,
                'user_id' => $responsible->id,
                'activity_date' => '2026-11-02 15:45',
                'status' => 'completada',
                'description' => 'Reunion de cierre con el area de compras.',
            ])
            ->assertRedirect("/activities/{$activity->id}");

        $activity->refresh();

        $this->assertSame('reunion', $activity->type);
        $this->assertSame('completada', $activity->status);
        $this->assertTrue($activity->opportunity->is($opportunity));
        $this->assertTrue($activity->user->is($responsible));
        $this->assertSame('2026-11-02 15:45:00', $activity->activity_date->format('Y-m-d H:i:s'));
    }

    public function test_an_update_cannot_move_the_opportunity_to_another_client(): void
    {
        $activity = Activity::factory()->create();
        $opportunity = Opportunity::factory()->create(['client_id' => Client::factory()->create()->id]);

        $this->actingAs($this->admin())
            ->from("/activities/{$activity->id}/edit")
            ->patch("/activities/{$activity->id}", $this->payload([
                'client_id' => $activity->client_id,
                'opportunity_id' => $opportunity->id,
            ]))
            ->assertSessionHasErrors('opportunity_id');

        $this->assertNull($activity->refresh()->opportunity_id);
    }

    public function test_the_opportunity_can_be_detached_on_update(): void
    {
        $activity = Activity::factory()->forOpportunity()->create();

        $this->actingAs($this->admin())
            ->patch("/activities/{$activity->id}", $this->payload([
                'client_id' => $activity->client_id,
                'opportunity_id' => '',
            ]))
            ->assertRedirect();

        $this->assertNull($activity->refresh()->opportunity_id);
    }

    public function test_the_edit_screen_keeps_the_inactive_client_of_the_activity_on_the_options(): void
    {
        $activity = Activity::factory()->for(
            Client::factory()->create(['status' => 'inactivo']),
            'client'
        )->create();

        $this->actingAs($this->admin())
            ->get("/activities/{$activity->id}/edit")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('activity.id', $activity->id)
                ->where('activity.opportunity_id', $activity->opportunity_id)
            );
    }

    // -------------------------------------------------------------------
    // Borrado y protección del historial
    // -------------------------------------------------------------------

    public function test_deleting_an_activity_only_removes_that_record(): void
    {
        $activity = Activity::factory()->create();
        $client = $activity->client;
        $responsible = $activity->user;

        $this->actingAs($this->manager())
            ->delete("/activities/{$activity->id}")
            ->assertRedirect('/activities');

        $this->assertDatabaseMissing('activities', ['id' => $activity->id]);
        $this->assertDatabaseHas('clients', ['id' => $client->id]);
        $this->assertDatabaseHas('users', ['id' => $responsible->id]);
    }

    public function test_a_client_with_activities_cannot_be_deleted(): void
    {
        $client = Client::factory()->create();
        Activity::factory()->for($client, 'client')->create();

        $this->actingAs($this->admin())
            ->delete("/clients/{$client->id}")
            ->assertRedirect("/clients/{$client->id}")
            ->assertSessionHas('error');

        $this->assertDatabaseHas('clients', ['id' => $client->id]);
    }

    public function test_an_opportunity_with_activities_cannot_be_deleted(): void
    {
        $opportunity = Opportunity::factory()->create();
        Activity::factory()->for($opportunity, 'opportunity')->create();

        $this->actingAs($this->admin())
            ->delete("/opportunities/{$opportunity->id}")
            ->assertRedirect("/opportunities/{$opportunity->id}")
            ->assertSessionHas('error');

        $this->assertDatabaseHas('opportunities', ['id' => $opportunity->id]);
    }

    public function test_deleting_the_opportunity_leaves_the_client_untouched(): void
    {
        $opportunity = Opportunity::factory()->create();
        $client = $opportunity->client;

        $this->actingAs($this->admin())
            ->delete("/opportunities/{$opportunity->id}")
            ->assertRedirect('/opportunities');

        $this->assertDatabaseMissing('opportunities', ['id' => $opportunity->id]);
        $this->assertDatabaseHas('clients', ['id' => $client->id]);
    }

    // -------------------------------------------------------------------
    // Integración con las fichas de cliente y oportunidad
    // -------------------------------------------------------------------

    public function test_the_client_ficha_lists_its_activities(): void
    {
        $client = Client::factory()->create();
        $activity = Activity::factory()->for($client, 'client')->create();
        Activity::factory()->create();

        $this->actingAs($this->admin())
            ->get("/clients/{$client->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('activities', 1)
                ->where('activities.0.id', $activity->id)
                ->where('can.activities.view', true)
                ->where('can.activities.create', true)
            );
    }

    public function test_the_client_ficha_omits_activities_without_the_view_permission(): void
    {
        $client = Client::factory()->create();
        Activity::factory()->for($client, 'client')->create();
        $user = $this->userWithAllPermissionsExcept('activities.view');

        $this->actingAs($user)
            ->get("/clients/{$client->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('activities', null)
                ->where('can.activities.view', false)
            );
    }

    public function test_the_opportunity_ficha_lists_its_activities(): void
    {
        $opportunity = Opportunity::factory()->create();
        $activity = Activity::factory()->for($opportunity, 'opportunity')->create();
        Activity::factory()->create();

        $this->actingAs($this->admin())
            ->get("/opportunities/{$opportunity->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('activities', 1)
                ->where('activities.0.id', $activity->id)
                ->where('can.activities.view', true)
                ->where('can.activities.create', true)
            );
    }

    public function test_the_opportunity_ficha_omits_activities_without_the_view_permission(): void
    {
        $opportunity = Opportunity::factory()->create();
        Activity::factory()->for($opportunity, 'opportunity')->create();
        $user = $this->userWithAllPermissionsExcept('activities.view');

        $this->actingAs($user)
            ->get("/opportunities/{$opportunity->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('activities', null)
                ->where('can.activities.view', false)
            );
    }

    public function test_an_activity_registered_from_a_ficha_lands_in_both_of_them(): void
    {
        $opportunity = Opportunity::factory()->create();

        $this->actingAs($this->admin())
            ->post('/activities', $this->payload([
                'client_id' => $opportunity->client_id,
                'opportunity_id' => $opportunity->id,
            ]))
            ->assertRedirect();

        $activity = Activity::sole();

        $this->actingAs($this->admin())
            ->get("/clients/{$opportunity->client_id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('activities', 1)
                ->where('activities.0.id', $activity->id)
            );

        $this->actingAs($this->admin())
            ->get("/opportunities/{$opportunity->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('activities', 1)
                ->where('activities.0.id', $activity->id)
            );
    }
}
