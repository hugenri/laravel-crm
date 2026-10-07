<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

/**
 * Autorización y reglas del módulo de cotizaciones. Tres invariantes que
 * una petición armada a mano podría romper:
 *
 *  - Los totales y el folio nunca llegan del navegador: el formulario los
 *    rechaza y la única fuente es el calculador.
 *  - El contacto y la oportunidad pertenecen al cliente de la cotización;
 *    el filtro del formulario Vue es comodidad, no la regla.
 *  - Enviada, la cotización ya no se edita: quotes.update solo alcanza un
 *    borrador, aunque el usuario tenga todos los demás permisos.
 */
class QuoteManagementTest extends TestCase
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
     * sin depender de una matriz puntual. revokePermissionTo solo quita
     * permisos directos, no los heredados del rol.
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
     * El cuerpo que envian las pantallas de alta y de edicion.
     *
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'client_id' => Client::factory()->create()->id,
            'contact_id' => '',
            'opportunity_id' => '',
            'user_id' => User::factory()->create(['is_active' => true])->id,
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(15)->toDateString(),
            'tax_rate' => '16.00',
            'notes' => 'Propuesta sujeta a confirmación del cliente.',
            'items' => [
                ['description' => 'Desarrollo de la plataforma', 'quantity' => '2', 'unit_price' => '1000.50'],
                ['description' => 'Soporte mensual', 'quantity' => '1', 'unit_price' => '500.25'],
            ],
        ], $overrides);
    }

    // -------------------------------------------------------------------
    // Autorización
    // -------------------------------------------------------------------

    public function test_a_guest_cannot_reach_the_quotes_module(): void
    {
        $this->get('/quotes')->assertRedirect('/login');
        $this->get('/quotes/create')->assertRedirect('/login');
        $this->post('/quotes', $this->payload())->assertRedirect('/login');
    }

    public function test_the_roles_without_the_view_permission_cannot_list_quotes(): void
    {
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);

        $this->actingAs($user)->get('/quotes')->assertForbidden();
    }

    public function test_only_the_administrator_can_delete_a_quote(): void
    {
        $quote = Quote::factory()->create();

        $this->actingAs($this->manager())
            ->delete("/quotes/{$quote->id}")
            ->assertForbidden();

        $this->actingAs($this->seller())
            ->delete("/quotes/{$quote->id}")
            ->assertForbidden();

        $this->actingAs($this->admin())
            ->delete("/quotes/{$quote->id}")
            ->assertRedirect('/quotes');

        $this->assertDatabaseMissing('quotes', ['id' => $quote->id]);
    }

    public function test_a_manager_and_a_seller_can_create_quotes(): void
    {
        foreach ([$this->manager(), $this->seller()] as $user) {
            $this->actingAs($user)
                ->get('/quotes/create')
                ->assertOk();

            $this->actingAs($user)
                ->post('/quotes', $this->payload())
                ->assertRedirect()
                ->assertSessionHasNoErrors();
        }

        $this->assertDatabaseCount('quotes', 2);
    }

    public function test_the_create_screen_offers_the_defaults_and_the_active_accounts(): void
    {
        Client::factory()->create(['name' => 'Cliente ejemplo']);
        Client::factory()->inactive()->create(['name' => 'Cliente retirado']);
        User::factory()->create(['is_active' => true, 'name' => 'Responsable activo']);
        User::factory()->create(['is_active' => false, 'name' => 'Responsable dado de baja']);

        $seller = $this->seller();

        $this->actingAs($seller)
            ->get('/quotes/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Quotes/Create')
                ->has('clients', 1)
                ->where('clients.0.name', 'Cliente ejemplo')
                // El vendedor que abre la pantalla tambien es una cuenta activa,
                // asi que el desplegable lo ofrece junto al responsable de prueba.
                ->has('users', 2)
                ->where('users', fn ($users): bool => collect($users)
                    ->pluck('name')
                    ->contains('Responsable dado de baja') === false)
                ->where('taxRate', '16.00')
                ->where('issueDate', now()->toDateString())
                ->where('validUntil', now()->addDays((int) config('quotes.validity_days'))->toDateString())
            );
    }

    public function test_the_change_status_route_requires_the_dedicated_permission(): void
    {
        $quote = Quote::factory()->create();

        $restricted = $this->userWithAllPermissionsExcept('quotes.change_status');

        $this->actingAs($restricted)
            ->patch("/quotes/{$quote->id}/status/enviada")
            ->assertForbidden();

        $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'status' => 'borrador']);
    }

    public function test_the_pdf_route_requires_the_dedicated_permission(): void
    {
        $quote = Quote::factory()->create();

        $restricted = $this->userWithAllPermissionsExcept('quotes.pdf');

        $this->actingAs($restricted)
            ->get("/quotes/{$quote->id}/pdf")
            ->assertForbidden();
    }

    // -------------------------------------------------------------------
    // Alta
    // -------------------------------------------------------------------

    public function test_a_quote_is_created_as_a_draft_with_a_generated_folio(): void
    {
        $response = $this->actingAs($this->admin())
            ->post('/quotes', $this->payload());

        $response->assertRedirect()->assertSessionHasNoErrors();

        $quote = Quote::query()->firstOrFail();

        $this->assertSame('borrador', $quote->status);
        $this->assertSame('COT-'.str_pad((string) $quote->id, 6, '0', STR_PAD_LEFT), $quote->folio);
    }

    public function test_each_quote_gets_its_own_folio(): void
    {
        $this->actingAs($this->admin())->post('/quotes', $this->payload());
        $this->actingAs($this->admin())->post('/quotes', $this->payload());

        $folios = Quote::query()->orderBy('id')->pluck('folio')->all();

        $this->assertCount(2, array_unique($folios));
    }

    public function test_the_totals_are_computed_by_the_server_from_the_items(): void
    {
        $this->actingAs($this->admin())
            ->post('/quotes', $this->payload())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // 2 x 1000.50 + 1 x 500.25 = 2501.25; el 16% son 400.20.
        $this->assertDatabaseHas('quotes', [
            'subtotal' => 2501.25,
            'tax_rate' => 16.00,
            'tax_amount' => 400.20,
            'total' => 2901.45,
        ]);
    }

    public function test_the_items_are_stored_with_their_amount_and_a_sequential_position(): void
    {
        $this->actingAs($this->admin())
            ->post('/quotes', $this->payload())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $quote = Quote::query()->firstOrFail();

        $this->assertSame(
            [
                ['position' => 1, 'description' => 'Desarrollo de la plataforma', 'amount' => 2001.00],
                ['position' => 2, 'description' => 'Soporte mensual', 'amount' => 500.25],
            ],
            $quote->items()
                ->get()
                ->map(fn (QuoteItem $item): array => [
                    'position' => (int) $item->position,
                    'description' => $item->description,
                    'amount' => (float) $item->amount,
                ])
                ->all(),
        );
    }

    public function test_fractional_quantities_keep_their_decimals(): void
    {
        $this->actingAs($this->admin())
            ->post('/quotes', $this->payload([
                'items' => [
                    ['description' => 'Consultoría', 'quantity' => '2.5', 'unit_price' => '1000.10'],
                ],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // 2.5 x 1000.10 = 2500.25, que con un float seria 2500.2499999999995.
        $this->assertDatabaseHas('quotes', ['subtotal' => 2500.25, 'total' => 2900.29]);
    }

    public function test_the_amounts_are_normalized_to_two_decimals(): void
    {
        $this->actingAs($this->admin())
            ->post('/quotes', $this->payload([
                'items' => [
                    ['description' => 'Licencia', 'quantity' => '3', 'unit_price' => '12,500.50'],
                ],
                'tax_rate' => '16',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('quotes', [
            'subtotal' => 37501.50,
            'tax_rate' => 16.00,
            'tax_amount' => 6000.24,
            'total' => 43501.74,
        ]);
    }

    public function test_the_default_tax_rate_is_used_when_none_is_sent(): void
    {
        $this->actingAs($this->admin())
            ->post('/quotes', $this->payload([
                'tax_rate' => '',
                'items' => [
                    ['description' => 'Licencia', 'quantity' => '1', 'unit_price' => '1000.00'],
                ],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('quotes', ['tax_rate' => 16.00, 'tax_amount' => 160.00, 'total' => 1160.00]);
    }

    public function test_a_quote_can_be_created_without_tax(): void
    {
        $this->actingAs($this->admin())
            ->post('/quotes', $this->payload([
                'tax_rate' => '0',
                'items' => [
                    ['description' => 'Donación', 'quantity' => '1', 'unit_price' => '500.00'],
                ],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('quotes', ['tax_rate' => 0.00, 'tax_amount' => 0.00, 'total' => 500.00]);
    }

    public function test_the_generated_fields_are_rejected_in_the_ordinary_form(): void
    {
        foreach (['folio', 'status', 'subtotal', 'tax_amount', 'total'] as $field) {
            $this->actingAs($this->admin())
                ->post('/quotes', $this->payload([$field => '999']))
                ->assertSessionHasErrors($field);
        }

        $this->assertDatabaseCount('quotes', 0);
    }

    public function test_the_item_amount_and_position_are_rejected_in_the_ordinary_form(): void
    {
        $this->actingAs($this->admin())
            ->post('/quotes', $this->payload([
                'items' => [
                    ['description' => 'Licencia', 'quantity' => '1', 'unit_price' => '100.00', 'amount' => '1.00'],
                ],
            ]))
            ->assertSessionHasErrors('items.0.amount');

        $this->actingAs($this->admin())
            ->post('/quotes', $this->payload([
                'items' => [
                    ['description' => 'Licencia', 'quantity' => '1', 'unit_price' => '100.00', 'position' => 7],
                ],
            ]))
            ->assertSessionHasErrors('items.0.position');

        $this->assertDatabaseCount('quotes', 0);
    }

    public function test_a_quote_needs_at_least_one_item(): void
    {
        $this->actingAs($this->admin())
            ->post('/quotes', $this->payload(['items' => []]))
            ->assertSessionHasErrors('items');

        $this->assertDatabaseCount('quotes', 0);
    }

    public function test_an_item_needs_a_description_a_quantity_and_a_price(): void
    {
        $this->actingAs($this->admin())
            ->post('/quotes', $this->payload([
                'items' => [
                    ['description' => '', 'quantity' => '', 'unit_price' => ''],
                ],
            ]))
            ->assertSessionHasErrors(['items.0.description', 'items.0.quantity', 'items.0.unit_price']);

        $this->assertDatabaseCount('quotes', 0);
    }

    public function test_a_quantity_of_zero_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post('/quotes', $this->payload([
                'items' => [
                    ['description' => 'Licencia', 'quantity' => '0', 'unit_price' => '100.00'],
                ],
            ]))
            ->assertSessionHasErrors('items.0.quantity');

        $this->assertDatabaseCount('quotes', 0);
    }

    public function test_a_negative_unit_price_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post('/quotes', $this->payload([
                'items' => [
                    ['description' => 'Licencia', 'quantity' => '1', 'unit_price' => '-100.00'],
                ],
            ]))
            ->assertSessionHasErrors('items.0.unit_price');

        $this->assertDatabaseCount('quotes', 0);
    }

    public function test_the_contact_must_belong_to_the_selected_client(): void
    {
        $otherClient = Client::factory()->create();
        $contact = Contact::factory()->create(['client_id' => $otherClient->id]);

        $this->actingAs($this->admin())
            ->post('/quotes', $this->payload(['contact_id' => $contact->id]))
            ->assertSessionHasErrors('contact_id');

        $this->assertDatabaseCount('quotes', 0);
    }

    public function test_the_opportunity_must_belong_to_the_selected_client(): void
    {
        $otherClient = Client::factory()->create();
        $opportunity = Opportunity::factory()->forClient($otherClient)->create();

        $this->actingAs($this->admin())
            ->post('/quotes', $this->payload(['opportunity_id' => $opportunity->id]))
            ->assertSessionHasErrors('opportunity_id');

        $this->assertDatabaseCount('quotes', 0);
    }

    public function test_the_contact_and_the_opportunity_of_the_same_client_are_accepted(): void
    {
        $client = Client::factory()->create();
        $contact = Contact::factory()->create(['client_id' => $client->id]);
        $opportunity = Opportunity::factory()->forClient($client)->create();

        $this->actingAs($this->admin())
            ->post('/quotes', $this->payload([
                'client_id' => $client->id,
                'contact_id' => $contact->id,
                'opportunity_id' => $opportunity->id,
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('quotes', [
            'client_id' => $client->id,
            'contact_id' => $contact->id,
            'opportunity_id' => $opportunity->id,
        ]);
    }

    public function test_a_contact_and_an_opportunity_are_optional(): void
    {
        $this->actingAs($this->admin())
            ->post('/quotes', $this->payload(['contact_id' => '', 'opportunity_id' => '']))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('quotes', ['contact_id' => null, 'opportunity_id' => null]);
    }

    public function test_an_inactive_user_cannot_be_the_responsible(): void
    {
        $this->actingAs($this->admin())
            ->post('/quotes', $this->payload([
                'user_id' => User::factory()->create(['is_active' => false])->id,
            ]))
            ->assertSessionHasErrors('user_id');

        $this->assertDatabaseCount('quotes', 0);
    }

    public function test_an_issue_date_in_the_future_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post('/quotes', $this->payload([
                'issue_date' => now()->addDay()->toDateString(),
                'valid_until' => now()->addDays(20)->toDateString(),
            ]))
            ->assertSessionHasErrors('issue_date');

        $this->assertDatabaseCount('quotes', 0);
    }

    public function test_a_validity_before_the_issue_date_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post('/quotes', $this->payload([
                'issue_date' => now()->toDateString(),
                'valid_until' => now()->subDay()->toDateString(),
            ]))
            ->assertSessionHasErrors('valid_until');

        $this->assertDatabaseCount('quotes', 0);
    }

    public function test_the_contact_and_the_opportunity_are_required_to_exist(): void
    {
        $this->actingAs($this->admin())
            ->post('/quotes', $this->payload([
                'client_id' => Client::factory()->create()->id,
                'contact_id' => Contact::factory()->create()->id,
                'opportunity_id' => Opportunity::factory()->create()->id,
            ]))
            ->assertSessionHasErrors(['contact_id', 'opportunity_id']);

        $this->assertDatabaseCount('quotes', 0);
    }

    // -------------------------------------------------------------------
    // Edición
    // -------------------------------------------------------------------

    public function test_a_draft_quote_can_be_edited(): void
    {
        $quote = Quote::factory()->withItems(1)->create();
        $newClient = Client::factory()->create();

        $this->actingAs($this->admin())
            ->patch("/quotes/{$quote->id}", $this->payload([
                'client_id' => $newClient->id,
                'items' => [
                    ['description' => 'Diseño de interfaz', 'quantity' => '1', 'unit_price' => '8000.00'],
                ],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('quotes', [
            'id' => $quote->id,
            'client_id' => $newClient->id,
            'subtotal' => 8000.00,
            'tax_amount' => 1280.00,
            'total' => 9280.00,
        ]);

        // El detalle se reemplaza por completo: lo que habia deja de existir.
        $this->assertDatabaseCount('quote_items', 1);
        $this->assertDatabaseHas('quote_items', [
            'quote_id' => $quote->id,
            'description' => 'Diseño de interfaz',
            'amount' => 8000.00,
        ]);
    }

    public function test_editing_keeps_the_folio(): void
    {
        $quote = Quote::factory()->withItems(1)->create();
        $folio = $quote->folio;

        $this->actingAs($this->admin())
            ->patch("/quotes/{$quote->id}", $this->payload())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame($folio, $quote->fresh()->folio);
    }

    public function test_a_sent_quote_can_no_longer_be_edited(): void
    {
        $quote = Quote::factory()->sent()->withItems(1)->create();
        $total = $quote->total;

        // Se usa el gerente y no el administrador de arranque porque este
        // se salta el Gate (crm.admin_emails) y devolvería todo permitido.
        $this->actingAs($this->manager())
            ->get("/quotes/{$quote->id}/edit")
            ->assertForbidden();

        $this->actingAs($this->manager())
            ->patch("/quotes/{$quote->id}", $this->payload())
            ->assertForbidden();

        $this->assertSame($total, $quote->fresh()->total);
    }

    public function test_an_accepted_quote_can_no_longer_be_edited(): void
    {
        $quote = Quote::factory()->accepted()->create();

        $this->actingAs($this->manager())
            ->get("/quotes/{$quote->id}/edit")
            ->assertForbidden();

        $this->actingAs($this->manager())
            ->patch("/quotes/{$quote->id}", $this->payload())
            ->assertForbidden();
    }

    public function test_the_update_form_also_rejects_the_generated_fields(): void
    {
        $quote = Quote::factory()->withItems(1)->create();

        $this->actingAs($this->admin())
            ->patch("/quotes/{$quote->id}", $this->payload([
                'status' => 'aceptada',
                'total' => '1.00',
            ]))
            ->assertSessionHasErrors(['status', 'total']);

        $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'status' => 'borrador']);
    }

    // -------------------------------------------------------------------
    // Cambio de estado
    // -------------------------------------------------------------------

    public function test_a_manager_can_mark_a_quote_as_sent(): void
    {
        $quote = Quote::factory()->create();

        $this->actingAs($this->manager())
            ->patch("/quotes/{$quote->id}/status/enviada")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'status' => 'enviada']);
    }

    public function test_a_sent_quote_can_be_accepted_or_rejected(): void
    {
        $accepted = Quote::factory()->sent()->create();
        $rejected = Quote::factory()->sent()->create();

        $this->actingAs($this->manager())
            ->patch("/quotes/{$accepted->id}/status/aceptada")
            ->assertRedirect();

        $this->actingAs($this->manager())
            ->patch("/quotes/{$rejected->id}/status/rechazada")
            ->assertRedirect();

        $this->assertDatabaseHas('quotes', ['id' => $accepted->id, 'status' => 'aceptada']);
        $this->assertDatabaseHas('quotes', ['id' => $rejected->id, 'status' => 'rechazada']);
    }

    public function test_a_transition_that_does_not_exist_is_refused(): void
    {
        $quote = Quote::factory()->create();

        $this->actingAs($this->manager())
            ->patch("/quotes/{$quote->id}/status/aceptada")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'status' => 'borrador']);
    }

    public function test_an_accepted_quote_is_final(): void
    {
        $quote = Quote::factory()->accepted()->create();

        $this->actingAs($this->manager())
            ->patch("/quotes/{$quote->id}/status/rechazada")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'status' => 'aceptada']);
    }

    public function test_an_expired_quote_can_go_back_to_draft(): void
    {
        $quote = Quote::factory()->create();

        $this->actingAs($this->manager())
            ->patch("/quotes/{$quote->id}/status/vencida")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($this->manager())
            ->patch("/quotes/{$quote->id}/status/borrador")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'status' => 'borrador']);
    }

    public function test_a_status_outside_the_catalog_is_not_found(): void
    {
        $quote = Quote::factory()->create();

        $this->actingAs($this->manager())
            ->patch("/quotes/{$quote->id}/status/inexistente")
            ->assertNotFound();

        $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'status' => 'borrador']);
    }

    public function test_the_quote_page_offers_only_the_reachable_states(): void
    {
        $quote = Quote::factory()->create();

        $this->actingAs($this->manager())
            ->get("/quotes/{$quote->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Quotes/Show')
                ->where('availableStatuses', ['enviada', 'vencida'])
                ->where('can.changeStatus', true)
            );

        $this->actingAs($this->manager())
            ->get('/quotes/'.Quote::factory()->accepted()->create()->id)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('availableStatuses', [])
            );
    }

    // -------------------------------------------------------------------
    // Vencimiento derivado de la fecha de vigencia
    // -------------------------------------------------------------------

    public function test_a_pending_quote_whose_validity_passed_is_shown_as_expired(): void
    {
        $expired = Quote::factory()->sent()->expired()->create();
        $current = Quote::factory()->sent()->create();

        $this->assertTrue($expired->isExpired());
        $this->assertSame('vencida', $expired->display_status);
        $this->assertSame('enviada', $expired->status);

        $this->assertFalse($current->isExpired());
        $this->assertSame('enviada', $current->display_status);
    }

    public function test_an_accepted_quote_never_looks_expired(): void
    {
        $quote = Quote::factory()->accepted()->expired()->create();

        $this->assertFalse($quote->isExpired());
        $this->assertSame('aceptada', $quote->display_status);
    }

    public function test_the_listing_shows_the_effective_state_of_an_expired_quote(): void
    {
        $quote = Quote::factory()->sent()->expired()->create();

        $this->actingAs($this->manager())
            ->get('/quotes')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('quotes.data.0.status', 'enviada')
                ->where('quotes.data.0.display_status', 'vencida')
            );
    }

    // -------------------------------------------------------------------
    // Listado y filtros
    // -------------------------------------------------------------------

    public function test_the_listing_searches_by_folio_and_by_client(): void
    {
        $target = Quote::factory()->forClient(Client::factory()->create(['name' => 'Grupo']))
            ->create();
        Quote::factory()->forClient(Client::factory()->create(['name' => 'Otra empresa']))->create();

        $this->actingAs($this->manager())
            ->get('/quotes?search='.$target->folio)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Quotes/Index')
                ->has('quotes.data', 1)
                ->where('quotes.data.0.id', $target->id)
            );

        $this->actingAs($this->manager())
            ->get('/quotes?search=Grupo')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('quotes.data', 1)
                ->where('quotes.data.0.id', $target->id)
            );
    }

    public function test_the_listing_filters_by_status_client_and_responsible(): void
    {
        $client = Client::factory()->create();
        $user = User::factory()->create(['is_active' => true]);
        $target = Quote::factory()->accepted()->forClient($client)->responsible($user)->create();

        Quote::factory()->create();
        Quote::factory()->accepted()->create();

        $query = http_build_query([
            'status' => 'aceptada',
            'client_id' => $client->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($this->manager())
            ->get("/quotes?{$query}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('quotes.data', 1)
                ->where('quotes.data.0.id', $target->id)
            );
    }

    public function test_the_viewer_sees_the_whole_listing_regardless_of_responsibility(): void
    {
        Quote::factory()->create();
        Quote::factory()->create();

        $this->actingAs($this->manager())
            ->get('/quotes')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('quotes.data', 2)
            );
    }

    public function test_the_listing_can_be_sorted_by_the_configured_columns(): void
    {
        $later = Quote::factory()->create(['issue_date' => now()->toDateString()]);
        $earlier = Quote::factory()->create(['issue_date' => now()->subDays(10)->toDateString()]);

        $this->actingAs($this->manager())
            ->get('/quotes?sort=issue_date&direction=asc')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('sortable', config('quotes.sortable'))
                ->where('quotes.data.0.id', $earlier->id)
                ->where('quotes.data.1.id', $later->id)
            );
    }

    // -------------------------------------------------------------------
    // Borrado y las salvaguardas que implica
    // -------------------------------------------------------------------

    public function test_deleting_a_quote_removes_its_items_and_keeps_the_commercial_records(): void
    {
        $client = Client::factory()->create();
        $contact = Contact::factory()->create(['client_id' => $client->id]);
        $opportunity = Opportunity::factory()->forClient($client)->create();
        $user = User::factory()->create(['is_active' => true]);
        $quote = Quote::factory()
            ->forClient($client)
            ->withContact($contact)
            ->forOpportunity($opportunity)
            ->responsible($user)
            ->withItems(3)
            ->create();

        $this->assertDatabaseCount('quote_items', 3);

        $this->actingAs($this->admin())
            ->delete("/quotes/{$quote->id}")
            ->assertRedirect('/quotes');

        $this->assertDatabaseMissing('quotes', ['id' => $quote->id]);
        $this->assertDatabaseCount('quote_items', 0);
        $this->assertModelExists($client);
        $this->assertModelExists($contact);
        $this->assertModelExists($opportunity);
        $this->assertModelExists($user);
    }

    public function test_a_client_with_quotes_cannot_be_deleted(): void
    {
        $client = Client::factory()->create();
        Quote::factory()->forClient($client)->create();

        $this->actingAs($this->admin())
            ->delete("/clients/{$client->id}")
            ->assertRedirect("/clients/{$client->id}")
            ->assertSessionHas('error', __('clients.has_quotes'));

        $this->assertModelExists($client);
    }

    public function test_a_contact_with_quotes_cannot_be_deleted(): void
    {
        $client = Client::factory()->create();
        $contact = Contact::factory()->create(['client_id' => $client->id]);
        Quote::factory()->forClient($client)->withContact($contact)->create();

        $this->actingAs($this->admin())
            ->delete("/contacts/{$contact->id}")
            ->assertRedirect()
            ->assertSessionHas('error', __('contacts.has_quotes'));

        $this->assertModelExists($contact);
    }

    public function test_an_opportunity_with_quotes_cannot_be_deleted(): void
    {
        $opportunity = Opportunity::factory()->create();
        Quote::factory()->forOpportunity($opportunity)->create();

        $this->actingAs($this->admin())
            ->delete("/opportunities/{$opportunity->id}")
            ->assertRedirect()
            ->assertSessionHas('error', __('opportunities.has_quotes'));

        $this->assertModelExists($opportunity);
    }

    // -------------------------------------------------------------------
    // PDF
    // -------------------------------------------------------------------

    public function test_the_pdf_is_downloaded_with_the_folio_in_its_name(): void
    {
        $quote = Quote::factory()->create();

        $response = $this->actingAs($this->manager())->get("/quotes/{$quote->id}/pdf");

        $response->assertOk();
        $response->assertDownload("cotizacion-{$quote->folio}.pdf");

        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_the_pdf_can_be_downloaded_from_a_sent_quote(): void
    {
        $quote = Quote::factory()->sent()->create();

        $this->actingAs($this->seller())
            ->get("/quotes/{$quote->id}/pdf")
            ->assertOk();
    }

    // -------------------------------------------------------------------
    // Integraciones
    // -------------------------------------------------------------------

    public function test_the_client_page_lists_its_quotes_when_the_user_can_view_them(): void
    {
        $client = Client::factory()->create();
        $quote = Quote::factory()->forClient($client)->create();
        Quote::factory()->create();

        $this->actingAs($this->manager())
            ->get("/clients/{$client->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Clients/Show')
                ->has('quotes', 1)
                ->where('quotes.0.id', $quote->id)
            );
    }

    public function test_the_client_page_hides_quotes_from_those_who_cannot_view_them(): void
    {
        $client = Client::factory()->create();
        Quote::factory()->forClient($client)->create();

        $user = $this->userWithAllPermissionsExcept('quotes.view');

        $this->actingAs($user)
            ->get("/clients/{$client->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('quotes', null)
            );
    }

    public function test_the_dashboard_counts_only_the_pending_quotes(): void
    {
        Quote::factory()->create();
        Quote::factory()->sent()->create();
        Quote::factory()->accepted()->create();
        Quote::factory()->rejected()->create();
        Quote::factory()->expired()->create();

        $this->actingAs($this->manager())
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('quotes.pending', 3)
            );
    }

    public function test_the_dashboard_hides_the_quotes_from_those_who_cannot_view_them(): void
    {
        Quote::factory()->create();

        $user = $this->userWithAllPermissionsExcept('quotes.view');

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('quotes', null)
            );
    }
}
