<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

/**
 * Autorización y reglas del módulo de oportunidades.
 *
 * El invariante status/stage no se contradice ni siquiera con una petición
 * armada a mano: por eso los formularios comunes rechazan status y cerrar y
 * reabrir vive tras su propio permiso.
 */
class OpportunityManagementTest extends TestCase
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
     * Permisos directos de todo el catálogo menos uno, para aislar una regla
     * de autorización: bajar un rol existente no sirve porque los permisos
     * heredados por rol no los toca revokePermissionTo.
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
     * El payload que envían las pantallas de alta y edición. El status va
     * fuera a propósito: lo deriva el backend a partir de la etapa.
     *
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Renovación anual del contrato',
            'client_id' => Client::factory()->create()->id,
            'contact_id' => '',
            'user_id' => User::factory()->create(['is_active' => true])->id,
            'amount' => '125000.50',
            'stage' => 'propuesta',
            'expected_close_date' => '2026-12-31',
            'notes' => 'El cliente pidió una propuesta antes de diciembre.',
        ], $overrides);
    }

    // -------------------------------------------------------------------
    // Autorización
    // -------------------------------------------------------------------

    public function test_a_guest_cannot_reach_the_opportunities_module(): void
    {
        $this->get('/opportunities')->assertRedirect('/login');
        $this->get('/opportunities/create')->assertRedirect('/login');
        $this->post('/opportunities')->assertRedirect('/login');
    }

    public function test_the_roles_without_the_view_permission_cannot_list_opportunities(): void
    {
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);

        $this->actingAs($user)->get('/opportunities')->assertForbidden();
    }

    public function test_only_the_administrator_can_delete_an_opportunity(): void
    {
        $opportunity = Opportunity::factory()->create();

        $this->actingAs($this->manager())
            ->delete("/opportunities/{$opportunity->id}")
            ->assertForbidden();

        $this->actingAs($this->seller())
            ->delete("/opportunities/{$opportunity->id}")
            ->assertForbidden();

        $this->actingAs($this->admin())
            ->delete("/opportunities/{$opportunity->id}")
            ->assertRedirect('/opportunities');

        $this->assertDatabaseMissing('opportunities', ['id' => $opportunity->id]);
    }

    public function test_a_manager_and_a_seller_can_create_and_edit_opportunities(): void
    {
        foreach ([$this->manager(), $this->seller()] as $user) {
            $this->actingAs($user)
                ->get('/opportunities/create')
                ->assertOk();

            $response = $this->actingAs($user)->post('/opportunities', $this->payload());
            $response->assertRedirect();
            $response->assertSessionHasNoErrors();
        }

        $this->assertDatabaseCount('opportunities', 2);
    }

    public function test_the_create_screen_sends_active_clients_as_proper_objects(): void
    {
        $active = Client::factory()->create(['name' => 'Cliente ejemplo']);
        Client::factory()->inactive()->create(['name' => 'Cliente retirado']);

        $this->actingAs($this->seller())
            ->get('/opportunities/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Opportunities/Create')
                ->has('clients', 1)
                ->where('clients.0.id', $active->id)
                ->where('clients.0.name', 'Cliente ejemplo')
            );
    }

    // -------------------------------------------------------------------
    // Alta
    // -------------------------------------------------------------------

    public function test_an_opportunity_can_be_created_with_its_status_derived_from_the_stage(): void
    {
        $this->actingAs($this->admin())
            ->post('/opportunities', $this->payload())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('opportunities', [
            'name' => 'Renovación anual del contrato',
            'stage' => 'propuesta',
            'status' => 'abierta',
        ]);
    }

    public function test_the_amount_is_normalized_to_two_decimals(): void
    {
        $this->actingAs($this->admin())
            ->post('/opportunities', $this->payload(['amount' => '12,500.50']))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('opportunities', ['amount' => 12500.50]);
    }

    public function test_the_amount_is_optional(): void
    {
        $this->actingAs($this->admin())
            ->post('/opportunities', $this->payload(['amount' => '']))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('opportunities', ['amount' => null]);
    }

    public function test_a_negative_amount_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post('/opportunities', $this->payload(['amount' => '-1']))
            ->assertSessionHasErrors('amount');
    }

    public function test_the_status_field_is_rejected_in_the_ordinary_form(): void
    {
        $this->actingAs($this->admin())
            ->post('/opportunities', $this->payload(['status' => 'ganada']))
            ->assertSessionHasErrors('status');

        $this->assertDatabaseCount('opportunities', 0);
    }

    public function test_a_closing_stage_is_not_accepted_by_the_ordinary_form(): void
    {
        $this->actingAs($this->admin())
            ->post('/opportunities', $this->payload(['stage' => 'ganada']))
            ->assertSessionHasErrors('stage');

        $this->assertDatabaseCount('opportunities', 0);
    }

    public function test_the_expected_close_date_is_required_while_the_opportunity_is_open(): void
    {
        $this->actingAs($this->admin())
            ->post('/opportunities', $this->payload(['expected_close_date' => '']))
            ->assertSessionHasErrors('expected_close_date');

        $this->assertDatabaseCount('opportunities', 0);
    }

    public function test_the_contact_must_belong_to_the_selected_client(): void
    {
        $otherClient = Client::factory()->create();
        $contact = Contact::factory()->create(['client_id' => $otherClient->id]);

        $this->actingAs($this->admin())
            ->post('/opportunities', $this->payload(['contact_id' => $contact->id]))
            ->assertSessionHasErrors('contact_id');

        $this->assertDatabaseCount('opportunities', 0);
    }

    public function test_an_inactive_user_cannot_be_the_responsible(): void
    {
        $this->actingAs($this->admin())
            ->post('/opportunities', $this->payload([
                'user_id' => User::factory()->create(['is_active' => false])->id,
            ]))
            ->assertSessionHasErrors('user_id');

        $this->assertDatabaseCount('opportunities', 0);
    }

    // -------------------------------------------------------------------
    // Embudo y responsable
    // -------------------------------------------------------------------

    public function test_the_default_stage_is_nueva(): void
    {
        $this->actingAs($this->admin())
            ->post('/opportunities', $this->payload(['stage' => config('opportunities.initial_stage')]))
            ->assertRedirect();

        $this->assertDatabaseHas('opportunities', ['status' => 'abierta']);
    }

    // -------------------------------------------------------------------
    // Edición
    // -------------------------------------------------------------------

    public function test_an_opportunity_can_be_edited(): void
    {
        $opportunity = Opportunity::factory()->create();
        $newClient = Client::factory()->create();
        $contact = Contact::factory()->create(['client_id' => $newClient->id]);

        $this->actingAs($this->admin())
            ->patch("/opportunities/{$opportunity->id}", [
                'name' => 'Contrato revisado',
                'client_id' => $newClient->id,
                'contact_id' => $contact->id,
                'user_id' => $opportunity->user_id,
                'amount' => '99000.25',
                'stage' => 'negociacion',
                'expected_close_date' => '2027-03-15',
                'notes' => 'Nuevos términos acordados.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('opportunities', [
            'id' => $opportunity->id,
            'name' => 'Contrato revisado',
            'client_id' => $newClient->id,
            'contact_id' => $contact->id,
            'amount' => 99000.25,
            'stage' => 'negociacion',
            'status' => 'abierta',
            'expected_close_date' => '2027-03-15 00:00:00',
        ]);
    }

    public function test_the_update_form_also_rejects_the_status_field(): void
    {
        $opportunity = Opportunity::factory()->create();

        $this->actingAs($this->admin())
            ->patch("/opportunities/{$opportunity->id}", $this->payload([
                'client_id' => $opportunity->client_id,
                'user_id' => $opportunity->user_id,
                'status' => 'ganada',
            ]))
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('opportunities', ['id' => $opportunity->id, 'status' => 'abierta']);
    }

    public function test_moving_out_of_a_closing_stage_reopens_the_opportunity(): void
    {
        $opportunity = Opportunity::factory()->lost()->create();

        $this->actingAs($this->manager())
            ->patch("/opportunities/{$opportunity->id}", [
                'name' => $opportunity->name,
                'client_id' => $opportunity->client_id,
                'contact_id' => $opportunity->contact_id ?? '',
                'user_id' => $opportunity->user_id,
                'amount' => $opportunity->amount,
                'stage' => 'propuesta',
                'expected_close_date' => '2026-12-31',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('opportunities', [
            'id' => $opportunity->id,
            'stage' => 'propuesta',
            'status' => 'abierta',
        ]);
    }

    // -------------------------------------------------------------------
    // Cambio de estado (cerrar y reabrir)
    // -------------------------------------------------------------------

    public function test_a_manager_can_mark_an_opportunity_as_won(): void
    {
        $opportunity = Opportunity::factory()->create();

        $this->actingAs($this->manager())
            ->patch("/opportunities/{$opportunity->id}/status/ganada")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('opportunities', [
            'id' => $opportunity->id,
            'stage' => 'ganada',
            'status' => 'ganada',
        ]);
    }

    public function test_a_manager_can_mark_an_opportunity_as_lost(): void
    {
        $opportunity = Opportunity::factory()->create();

        $this->actingAs($this->manager())
            ->patch("/opportunities/{$opportunity->id}/status/perdida")
            ->assertRedirect();

        $this->assertDatabaseHas('opportunities', [
            'id' => $opportunity->id,
            'stage' => 'perdida',
            'status' => 'perdida',
        ]);
    }

    public function test_reopening_keeps_the_stage_and_returns_the_status_to_open(): void
    {
        $opportunity = Opportunity::factory()->lost()->create();

        $this->actingAs($this->manager())
            ->patch("/opportunities/{$opportunity->id}/status/abierta")
            ->assertRedirect();

        $this->assertDatabaseHas('opportunities', [
            'id' => $opportunity->id,
            'stage' => 'perdida',
            'status' => 'abierta',
        ]);
    }

    public function test_an_invalid_status_is_rejected(): void
    {
        $opportunity = Opportunity::factory()->create();

        $this->actingAs($this->manager())
            ->patch("/opportunities/{$opportunity->id}/status/inexistente")
            ->assertNotFound();

        $this->assertDatabaseHas('opportunities', ['id' => $opportunity->id, 'status' => 'abierta']);
    }

    public function test_the_change_status_route_requires_the_dedicated_permission(): void
    {
        $opportunity = Opportunity::factory()->create();

        // Todos los permisos salvo change_status, para aislar esa regla sin
        // depender de una matriz puntual.
        $restricted = $this->userWithAllPermissionsExcept('opportunities.change_status');

        $this->actingAs($restricted)
            ->patch("/opportunities/{$opportunity->id}/status/ganada")
            ->assertForbidden();

        $this->assertDatabaseHas('opportunities', ['id' => $opportunity->id, 'status' => 'abierta']);
    }

    // -------------------------------------------------------------------
    // Listado y filtros
    // -------------------------------------------------------------------

    public function test_the_listing_searches_by_name(): void
    {
        $target = Opportunity::factory()->create(['name' => 'Expansión a Monterrey']);
        $other = Opportunity::factory()->create(['name' => 'Mantenimiento mensual']);

        $this->actingAs($this->manager())
            ->get('/opportunities?search=Monterrey')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Opportunities/Index')
                ->has('opportunities.data', 1)
                ->where('opportunities.data.0.id', $target->id)
            );
    }

    public function test_the_listing_filters_by_stage_and_status(): void
    {
        Opportunity::factory()->create(['stage' => 'nueva']);
        $won = Opportunity::factory()->won()->create();

        $this->actingAs($this->manager())
            ->get('/opportunities?stage=ganada&status=ganada')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('opportunities.data', 1)
                ->where('opportunities.data.0.id', $won->id)
            );
    }

    public function test_the_viewer_sees_the_whole_listing_regardless_of_responsibility(): void
    {
        $other = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        Opportunity::factory()->create();
        Opportunity::factory()->create();

        $this->actingAs($this->manager())
            ->get('/opportunities')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('opportunities.data', 2)
            );
    }

    // -------------------------------------------------------------------
    // El borrado conserva las entidades relacionadas
    // -------------------------------------------------------------------

    public function test_deleting_an_opportunity_does_not_touch_the_client_contact_or_user(): void
    {
        $client = Client::factory()->create();
        $contact = Contact::factory()->create(['client_id' => $client->id]);
        $user = User::factory()->create(['is_active' => true]);
        $opportunity = Opportunity::factory()->forClient($client)->withContact($contact)->create();
        $opportunity->update(['user_id' => $user->id]);

        $this->actingAs($this->admin())
            ->delete("/opportunities/{$opportunity->id}")
            ->assertRedirect('/opportunities');

        $this->assertDatabaseMissing('opportunities', ['id' => $opportunity->id]);
        $this->assertModelExists($client);
        $this->assertModelExists($contact);
        $this->assertModelExists($user);
    }

    public function test_a_client_with_opportunities_cannot_be_deleted(): void
    {
        $client = Client::factory()->create();
        Opportunity::factory()->forClient($client)->create();

        $this->actingAs($this->admin())
            ->delete("/clients/{$client->id}")
            ->assertRedirect("/clients/{$client->id}")
            ->assertSessionHas('error', __('clients.has_opportunities'));

        $this->assertModelExists($client);
    }

    // -------------------------------------------------------------------
    // Integración con la ficha de cliente
    // -------------------------------------------------------------------

    public function test_the_client_page_lists_its_opportunities_when_the_user_can_view_them(): void
    {
        $client = Client::factory()->create();
        $opportunity = Opportunity::factory()->forClient($client)->create();

        $this->actingAs($this->manager())
            ->get("/clients/{$client->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('opportunities', 1)
                ->where('opportunities.0.id', $opportunity->id)
            );
    }

    public function test_the_client_page_hides_opportunities_from_those_who_cannot_view_them(): void
    {
        $client = Client::factory()->create();
        Opportunity::factory()->forClient($client)->create();

        $user = $this->userWithAllPermissionsExcept('opportunities.view');

        $this->actingAs($user)
            ->get("/clients/{$client->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('opportunities', null)
            );
    }
}
