<?php

namespace Tests\Feature;

use App\Actions\Prospects\ChangeProspectStatus;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Prospect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

/**
 * Autorización y reglas del módulo de prospectos. Tres invariantes:
 *
 *  1. Cada acción responde a su permiso: editar no califica ni convierte,
 *     así que change_status y convert tienen ruta y policy propias.
 *  2. "convertido" solo lo escribe la conversión, que además no se repite.
 *  3. La conversión es indivisible: cliente, contacto y prospecto se
 *     escriben en una transacción y un fallo a mitad no deja nada.
 */
class ProspectManagementTest extends TestCase
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
     * El payload del alta. Lleva estado a propósito: entrar al embudo ya
     * contactado es legítimo, siempre que no sea "convertido".
     *
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ana Torres',
            'company' => 'Torres y Asociados',
            'phone' => '5512345678',
            'email' => 'Ana.Torres@Example.COM',
            'source' => 'referido',
            'user_id' => $this->seller()->getKey(),
            'status' => 'nuevo',
            'follow_up_date' => '2026-10-15',
            'notes' => 'Pidio que le llamen por la manana.',
        ], $overrides);
    }

    /**
     * El payload de la pantalla de conversión: mitad cliente, mitad contacto.
     *
     * @return array<string, mixed>
     */
    private function conversionPayload(array $overrides = []): array
    {
        return array_merge([
            'client_name' => 'Torres y Asociados',
            'client_type' => 'empresa',
            'rfc' => 'TAA010101ABC',
            'address' => 'Av. Insurgentes 100',
            'client_notes' => 'Cliente potential del anillo comercial.',
            'first_name' => 'Ana',
            'last_name' => 'Torres',
            'position' => 'Directora',
            'phone' => '5512345678',
            'email' => 'ana.torres@example.com',
        ], $overrides);
    }

    // -----------------------------------------------------------------
    // Consulta
    // -----------------------------------------------------------------

    public function test_the_listing_requires_the_view_permission(): void
    {
        $prospect = Prospect::factory()->create();

        $this->actingAs($this->userWithAllPermissionsExcept('prospects.view'))
            ->get(route('prospects.index'))
            ->assertForbidden();

        $this->actingAs($this->seller())
            ->get(route('prospects.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Prospects/Index')
                ->has('prospects.data', 1)
                ->where('prospects.data.0.id', $prospect->id)
                ->has('statuses')
                ->has('sources')
                ->has('sortable')
                ->has('users')
                ->where('can.changeStatus', true)
                ->where('can.convert', true)
                ->where('can.delete', false)
            );
    }

    public function test_the_listing_searches_and_filters_the_funnel(): void
    {
        $user = $this->seller();

        $wanted = Prospect::factory()->responsible($user)->create([
            'name' => 'Carlos Mendoza',
            'company' => 'Mendoza Servicios',
            'source' => 'llamada',
            'status' => 'calificado',
        ]);

        Prospect::factory()->responsible($user)->create([
            'name' => 'Otra Persona',
            'company' => 'Otra Empresa',
            'source' => 'evento',
            'status' => 'nuevo',
        ]);

        // Por nombre.
        $this->actingAs($user)
            ->get(route('prospects.index', ['search' => 'Mendoza']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('prospects.data', 1)
                ->where('prospects.data.0.id', $wanted->id)
                ->where('filters.search', 'Mendoza')
            );

        // Por correo: la busqueda cubre nombre, empresa, telefono y correo.
        $this->actingAs($user)
            ->get(route('prospects.index', ['search' => $wanted->email]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('prospects.data', 1)
            );

        // Por estado y fuente, combinados.
        $this->actingAs($user)
            ->get(route('prospects.index', ['status' => 'calificado', 'source' => 'llamada']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('prospects.data', 1)
                ->where('prospects.data.0.id', $wanted->id)
            );

        // Un estado que no existe deja el listado vacio, no un error.
        $this->actingAs($user)
            ->get(route('prospects.index', ['status' => 'inventado']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('prospects.data', 0));
    }

    public function test_the_listing_only_sorts_by_whitelisted_columns(): void
    {
        $user = $this->seller();

        Prospect::factory()->count(2)->responsible($user)->create();

        // Una columna fuera de la lista blanca se descarta y se vuelve al
        // orden por defecto, en lugar de llegar a la consulta.
        $this->actingAs($user)
            ->get(route('prospects.index', ['sort' => 'password']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.sort', config('prospects.default_sort'))
                ->where('filters.direction', 'desc')
            );

        $this->actingAs($user)
            ->get(route('prospects.index', ['sort' => 'name', 'direction' => 'asc']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.sort', 'name')
                ->where('filters.direction', 'asc')
            );
    }

    public function test_the_record_requires_the_view_permission(): void
    {
        $prospect = Prospect::factory()->create();

        $this->actingAs($this->userWithAllPermissionsExcept('prospects.view'))
            ->get(route('prospects.show', $prospect))
            ->assertForbidden();

        $this->actingAs($this->seller())
            ->get(route('prospects.show', $prospect))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Prospects/Show')
                ->where('prospect.id', $prospect->id)
                ->where('prospect.status_label', 'Nuevo')
                ->where('prospect.source_label', config('prospects.sources')[$prospect->source])
                ->where('convertedStatus', 'convertido')
                ->where('can.update', true)
                ->where('can.delete', false)
            );
    }

    // -----------------------------------------------------------------
    // Alta
    // -----------------------------------------------------------------

    public function test_the_create_screen_and_store_require_the_create_permission(): void
    {
        $withoutPermission = $this->userWithAllPermissionsExcept('prospects.create');
        $user = $this->seller();

        $this->actingAs($withoutPermission)
            ->get(route('prospects.create'))
            ->assertForbidden();

        $this->actingAs($withoutPermission)
            ->post(route('prospects.store'), $this->payload(['user_id' => $user->getKey()]))
            ->assertForbidden();

        $this->assertDatabaseCount('prospects', 0);
    }

    public function test_the_create_screen_offers_only_the_manually_selectable_statuses(): void
    {
        $this->actingAs($this->seller())
            ->get(route('prospects.create'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Prospects/Create')
                ->where('initialStatus', 'nuevo')
                ->where('statuses', [
                    'nuevo' => 'Nuevo',
                    'contactado' => 'Contactado',
                    'calificado' => 'Calificado',
                    'no_interesado' => 'No interesado',
                ])
                ->missing('statuses.convertido')
            );
    }

    public function test_a_prospect_is_registered_with_the_data_that_was_confirmed(): void
    {
        $user = $this->seller();

        $this->actingAs($user)
            ->post(route('prospects.store'), $this->payload(['user_id' => $user->getKey()]))
            ->assertRedirect();

        $prospect = Prospect::firstOrFail();

        $this->assertSame('Ana Torres', $prospect->name);
        $this->assertSame('Torres y Asociados', $prospect->company);
        $this->assertSame('referido', $prospect->source);
        $this->assertSame('nuevo', $prospect->status);
        $this->assertSame($user->getKey(), $prospect->user_id);
        $this->assertNull($prospect->client_id);
        $this->assertNull($prospect->contact_id);

        // El correo se guarda normalizado, igual que en el modulo de clientes.
        $this->assertSame('ana.torres@example.com', $prospect->email);
    }

    public function test_the_store_accepts_a_prospect_that_arrives_already_contacted(): void
    {
        $user = $this->seller();

        $this->actingAs($user)
            ->post(route('prospects.store'), $this->payload([
                'user_id' => $user->getKey(),
                'status' => 'contactado',
            ]))
            ->assertRedirect();

        $this->assertSame('contactado', Prospect::firstOrFail()->status);
    }

    public function test_the_store_rejects_an_invalid_payload(): void
    {
        $user = $this->seller();

        $inactive = User::factory()->create(['is_active' => false]);

        $this->actingAs($user)
            ->post(route('prospects.store'), [
                'name' => '',
                'source' => 'inventada',
                'user_id' => $inactive->getKey(),
                'follow_up_date' => 'no-es-una-fecha',
                'email' => 'esto-no-es-un-correo',
            ])
            ->assertSessionHasErrors(['name', 'source', 'user_id', 'follow_up_date', 'email']);

        $this->assertDatabaseCount('prospects', 0);
    }

    public function test_the_store_rejects_the_converted_status(): void
    {
        $this->actingAs($this->seller())
            ->post(route('prospects.store'), $this->payload(['status' => 'convertido']))
            ->assertSessionHasErrors('status');

        $this->assertDatabaseCount('prospects', 0);
    }

    public function test_the_store_rejects_the_fields_that_only_the_conversion_writes(): void
    {
        $client = Client::factory()->create();
        $contact = Contact::factory()->create(['client_id' => $client->getKey()]);

        $this->actingAs($this->seller())
            ->post(route('prospects.store'), $this->payload([
                'client_id' => $client->getKey(),
                'contact_id' => $contact->getKey(),
            ]))
            ->assertSessionHasErrors(['client_id', 'contact_id']);

        $this->assertDatabaseCount('prospects', 0);
    }

    // -----------------------------------------------------------------
    // Edición
    // -----------------------------------------------------------------

    public function test_the_edit_screen_and_update_require_the_update_permission(): void
    {
        $prospect = Prospect::factory()->create();
        $withoutPermission = $this->userWithAllPermissionsExcept('prospects.update');

        $this->actingAs($withoutPermission)
            ->get(route('prospects.edit', $prospect))
            ->assertForbidden();

        $this->actingAs($withoutPermission)
            ->patch(route('prospects.update', $prospect), $this->payload())
            ->assertForbidden();

        $this->assertDatabaseHas('prospects', ['id' => $prospect->id, 'name' => $prospect->name]);
    }

    public function test_a_prospect_is_updated_without_touching_the_status(): void
    {
        $prospect = Prospect::factory()->qualified()->create();

        // El formulario de edicion ni siquiera envia el estado: la regla lo
        // prohibe, y mover el embudo es la accion de prospects.change_status.
        $payload = $this->payload(['name' => 'Ana Torres Vidal']);
        unset($payload['status']);

        $this->actingAs($this->seller())
            ->patch(route('prospects.update', $prospect), $payload)
            ->assertRedirect(route('prospects.show', $prospect));

        $prospect->refresh();

        $this->assertSame('Ana Torres Vidal', $prospect->name);
        $this->assertSame('calificado', $prospect->status);
    }

    public function test_the_update_rejects_a_payload_that_carries_a_status(): void
    {
        $prospect = Prospect::factory()->create();

        // No es que se ignore en silencio: una peticion con el estado se rechaza
        // entera, para que un cliente mal construido no crea que lo esta moviendo.
        $this->actingAs($this->seller())
            ->patch(route('prospects.update', $prospect), $this->payload([
                'name' => 'Ana Torres Vidal',
                'status' => 'no_interesado',
            ]))
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('prospects', [
            'id' => $prospect->id,
            'name' => $prospect->name,
            'status' => 'nuevo',
        ]);
    }

    public function test_the_update_rejects_the_converted_status_outright(): void
    {
        $prospect = Prospect::factory()->create();

        $this->actingAs($this->seller())
            ->patch(route('prospects.update', $prospect), $this->payload(['status' => 'convertido']))
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('prospects', [
            'id' => $prospect->id,
            'status' => 'nuevo',
        ]);
    }

    public function test_the_update_requires_an_active_responsible(): void
    {
        $prospect = Prospect::factory()->create();
        $inactive = User::factory()->create(['is_active' => false]);

        $this->actingAs($this->seller())
            ->patch(route('prospects.update', $prospect), $this->payload(['user_id' => $inactive->getKey()]))
            ->assertSessionHasErrors('user_id');
    }

    // -----------------------------------------------------------------
    // Estado
    // -----------------------------------------------------------------

    public function test_the_status_route_requires_the_change_status_permission(): void
    {
        $prospect = Prospect::factory()->create();
        $withoutPermission = $this->userWithAllPermissionsExcept('prospects.change_status');

        $this->actingAs($withoutPermission)
            ->patch(route('prospects.status', [$prospect->id, 'calificado']))
            ->assertForbidden();

        $this->assertDatabaseHas('prospects', ['id' => $prospect->id, 'status' => 'nuevo']);
    }

    public function test_the_status_moves_the_prospect_through_the_funnel(): void
    {
        $prospect = Prospect::factory()->create();

        $this->actingAs($this->manager())
            ->patch(route('prospects.status', [$prospect->id, 'calificado']))
            ->assertRedirect(route('prospects.show', $prospect));

        $this->assertDatabaseHas('prospects', ['id' => $prospect->id, 'status' => 'calificado']);
    }

    public function test_the_status_route_cannot_reach_converted(): void
    {
        $prospect = Prospect::factory()->create();

        // La ruta lo limita con whereIn sobre los estados elegibles, de modo que
        // un "convertido" a mano se responde con un 404 y no llega a la accion.
        $this->actingAs($this->admin())
            ->patch(route('prospects.status', [$prospect->id, 'convertido']))
            ->assertNotFound();

        $this->assertDatabaseHas('prospects', ['id' => $prospect->id, 'status' => 'nuevo']);
    }

    public function test_a_converted_prospect_leaves_the_funnel(): void
    {
        $prospect = Prospect::factory()->converted()->create();

        $this->actingAs($this->manager())
            ->patch(route('prospects.status', [$prospect->id, 'calificado']))
            ->assertForbidden();

        $this->assertDatabaseHas('prospects', [
            'id' => $prospect->id,
            'status' => 'convertido',
        ]);
    }

    public function test_the_status_action_refuses_a_status_outside_the_catalog(): void
    {
        $prospect = Prospect::factory()->create();

        $this->expectException(\DomainException::class);

        app(ChangeProspectStatus::class)->handle($prospect, 'convertido');
    }

    // -----------------------------------------------------------------
    // Borrado
    // -----------------------------------------------------------------

    public function test_only_the_administrator_can_delete_a_prospect(): void
    {
        $prospect = Prospect::factory()->create();

        $this->actingAs($this->manager())
            ->delete(route('prospects.destroy', $prospect))
            ->assertForbidden();

        $this->assertDatabaseHas('prospects', ['id' => $prospect->id]);

        $this->actingAs($this->admin())
            ->delete(route('prospects.destroy', $prospect))
            ->assertRedirect(route('prospects.index'));

        $this->assertDatabaseMissing('prospects', ['id' => $prospect->id]);
    }

    public function test_a_converted_prospect_cannot_be_deleted(): void
    {
        // Aunque el administrador tenga prospects.delete: el registro dice de
        // donde salio el cliente y es la unica traza de la conversion.
        $prospect = Prospect::factory()->converted()->create();

        $this->actingAs($this->admin())
            ->from(route('prospects.show', $prospect))
            ->delete(route('prospects.destroy', $prospect))
            ->assertRedirect(route('prospects.show', $prospect))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('prospects', ['id' => $prospect->id]);
    }

    // -----------------------------------------------------------------
    // Conversión
    // -----------------------------------------------------------------

    public function test_the_conversion_screen_and_store_require_the_convert_permission(): void
    {
        $prospect = Prospect::factory()->create();
        $withoutPermission = $this->userWithAllPermissionsExcept('prospects.convert');

        $this->actingAs($withoutPermission)
            ->get(route('prospects.convert.create', $prospect))
            ->assertForbidden();

        $this->actingAs($withoutPermission)
            ->post(route('prospects.convert.store', $prospect), $this->conversionPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('clients', 0);
        $this->assertDatabaseCount('contacts', 0);
        $this->assertDatabaseHas('prospects', ['id' => $prospect->id, 'status' => 'nuevo']);
    }

    public function test_the_conversion_screen_prefills_from_the_prospect(): void
    {
        $prospect = Prospect::factory()->create([
            'name' => 'Ana Torres',
            'company' => 'Torres y Asociados',
            'phone' => '5512345678',
            'email' => 'ana.torres@example.com',
        ]);

        $this->actingAs($this->seller())
            ->get(route('prospects.convert.create', $prospect))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Prospects/Convert')
                ->where('initialClientType', 'empresa')
                ->where('prefill.client_name', 'Torres y Asociados')
                ->where('prefill.first_name', 'Ana')
                ->where('prefill.last_name', 'Torres')
                ->where('prefill.phone', '5512345678')
                ->where('prefill.email', 'ana.torres@example.com')
            );
    }

    public function test_the_conversion_creates_the_client_the_contact_and_keeps_the_prospect(): void
    {
        $prospect = Prospect::factory()->create([
            'name' => 'Ana Torres',
            'follow_up_date' => '2026-10-15',
        ]);

        $this->actingAs($this->manager())
            ->post(route('prospects.convert.store', $prospect), $this->conversionPayload())
            ->assertRedirect(route('prospects.show', $prospect));

        $client = Client::firstOrFail();
        $contact = Contact::firstOrFail();

        // Cliente: activo y con los datos que se confirmaron, no los del prospecto.
        $this->assertSame('Torres y Asociados', $client->name);
        $this->assertSame('empresa', $client->type);
        $this->assertSame('TAA010101ABC', $client->rfc);
        $this->assertSame('activo', $client->status);

        // Contacto: creado por CreateContact y promovido por SetPrimaryContact.
        $this->assertTrue($contact->is_primary);
        $this->assertSame($client->getKey(), $contact->client_id);
        $this->assertSame('Ana', $contact->first_name);
        $this->assertSame('Torres', $contact->last_name);
        $this->assertSame(1, $client->contacts()->where('is_primary', true)->count());

        // Prospecto: conserva sus datos y queda convertido, no borrado.
        $prospect->refresh();

        $this->assertSame('convertido', $prospect->status);
        $this->assertTrue($prospect->isConverted());
        $this->assertSame($client->getKey(), $prospect->client_id);
        $this->assertSame($contact->getKey(), $prospect->contact_id);
        $this->assertSame('Ana Torres', $prospect->name);

        // El proximo paso se agenda sobre el cliente, no sobre el prospecto.
        $this->assertNull($prospect->follow_up_date);
    }

    public function test_the_conversion_creates_no_opportunities_or_activities(): void
    {
        $prospect = Prospect::factory()->create();

        $this->actingAs($this->manager())
            ->post(route('prospects.convert.store', $prospect), $this->conversionPayload())
            ->assertRedirect();

        $this->assertDatabaseCount('opportunities', 0);
        $this->assertDatabaseCount('activities', 0);
    }

    public function test_the_conversion_cannot_be_repeated(): void
    {
        $prospect = Prospect::factory()->create();

        $this->actingAs($this->manager())
            ->post(route('prospects.convert.store', $prospect), $this->conversionPayload())
            ->assertRedirect();

        $clientId = Prospect::firstOrFail()->client_id;

        // El segundo intento se rechaza antes de la accion: el prospecto ya no
        // admite conversion, aunque quien lo intente tenga el permiso.
        $this->actingAs($this->manager())
            ->post(route('prospects.convert.store', $prospect), $this->conversionPayload([
                'rfc' => 'TBB020202ABC',
            ]))
            ->assertForbidden();

        // Un solo cliente: el segundo intento no crea un duplicado.
        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseCount('contacts', 1);
        $this->assertSame($clientId, Prospect::firstOrFail()->client_id);
    }

    public function test_the_conversion_screen_is_closed_once_the_prospect_is_converted(): void
    {
        // El factory construye el cliente y el contacto que genero la conversion
        // original, de modo que esos dos registros ya existen.
        $prospect = Prospect::factory()->converted()->create();
        $clientId = $prospect->client_id;

        $this->actingAs($this->manager())
            ->get(route('prospects.convert.create', $prospect))
            ->assertForbidden();

        $this->actingAs($this->manager())
            ->post(route('prospects.convert.store', $prospect), $this->conversionPayload())
            ->assertForbidden();

        // No se crea un segundo cliente para el mismo origen.
        $this->assertDatabaseCount('clients', 1);
        $this->assertSame($clientId, Prospect::firstOrFail()->client_id);
    }

    public function test_the_conversion_rolls_back_when_the_contact_cannot_be_created(): void
    {
        $prospect = Prospect::factory()->create();

        $this->actingAs($this->manager())
            ->post(route('prospects.convert.store', $prospect), $this->conversionPayload([
                // El cliente se crearia bien; el contacto es lo que falla, y es lo
                // que demuestra que la transaccion abarca las tres escrituras.
                'first_name' => str_repeat('a', 200),
            ]))
            ->assertSessionHasErrors('first_name');

        $this->assertDatabaseCount('clients', 0);
        $this->assertDatabaseCount('contacts', 0);
        $this->assertDatabaseHas('prospects', ['id' => $prospect->id, 'status' => 'nuevo']);
        $this->assertNull($prospect->fresh()->client_id);
    }

    public function test_the_conversion_rejects_an_rfc_that_is_already_taken(): void
    {
        Client::factory()->create(['rfc' => 'TAA010101ABC']);

        $prospect = Prospect::factory()->create();

        $this->actingAs($this->manager())
            ->post(route('prospects.convert.store', $prospect), $this->conversionPayload())
            ->assertSessionHasErrors('rfc');

        $this->assertDatabaseCount('contacts', 0);
        $this->assertDatabaseHas('prospects', ['id' => $prospect->id, 'status' => 'nuevo']);
    }

    public function test_the_conversion_applies_the_rfc_rule_of_the_chosen_client_type(): void
    {
        $prospect = Prospect::factory()->create();

        // Un RFC de empresa no vale para una persona fisica: lo decide el tipo,
        // con la misma regla que usa el alta de clientes.
        $this->actingAs($this->manager())
            ->post(route('prospects.convert.store', $prospect), $this->conversionPayload([
                'client_type' => 'persona',
                'rfc' => 'TAA010101ABC',
            ]))
            ->assertSessionHasErrors('rfc');

        $this->assertDatabaseCount('clients', 0);
    }

    public function test_the_conversion_validates_the_payload(): void
    {
        $prospect = Prospect::factory()->create();

        $this->actingAs($this->manager())
            ->post(route('prospects.convert.store', $prospect), [
                'client_name' => '',
                'client_type' => 'inventado',
                'first_name' => '',
                'email' => 'esto-no-es-un-correo',
            ])
            ->assertSessionHasErrors(['client_name', 'client_type', 'first_name', 'email']);

        $this->assertDatabaseCount('clients', 0);
        $this->assertDatabaseCount('contacts', 0);
    }

    // -----------------------------------------------------------------
    // Dashboard
    // -----------------------------------------------------------------

    public function test_the_dashboard_counts_new_prospects_and_hides_the_block_without_permission(): void
    {
        Prospect::factory()->count(3)->create(['status' => 'nuevo']);
        Prospect::factory()->create(['status' => 'calificado']);
        Prospect::factory()->converted()->create();

        $this->actingAs($this->seller())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Dashboard')
                ->where('prospects.new', 3)
                ->where('prospects.open', 4)
                ->where('prospects.qualified', 1)
            );

        $this->actingAs($this->userWithAllPermissionsExcept('prospects.view'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('prospects', null));
    }
}
