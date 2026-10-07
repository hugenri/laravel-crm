<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

/**
 * Autorización y reglas del módulo de clientes.
 *
 * La FK rechaza el delete, pero la acción tiene que rechazarlo antes para
 * dar un mensaje y no una página de error.
 */
class ClientManagementTest extends TestCase
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

    /**
     * En caché: el correo es único y varios tests piden el administrador más
     * de una vez.
     */
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
     * Campos que envía la pantalla de alta, con el status incluido.
     *
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'empresa',
            'name' => 'Comercializadora del Sur',
            'rfc' => 'GSO101010ABC',
            'phone' => '55 1234 5678',
            'email' => 'contacto@comercializadora.test',
            'address' => 'Avenida Reforma 100',
            'status' => 'activo',
            'notes' => null,
        ], $overrides);
    }

    /**
     * Campos de la pantalla de edición. Sin status a propósito: cambiarlo es
     * otra acción, protegida por clients.change_status.
     *
     * @return array<string, mixed>
     */
    private function editPayload(array $overrides = []): array
    {
        $payload = $this->payload($overrides);

        unset($payload['status']);

        return $payload;
    }

    public function test_a_client_can_be_created(): void
    {
        $response = $this->actingAs($this->admin())
            ->post('/clients', $this->payload());

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('clients', [
            'name' => 'Comercializadora del Sur',
            'rfc' => 'GSO101010ABC',
            'status' => 'activo',
        ]);
    }

    public function test_the_rfc_is_stored_in_uppercase_without_surrounding_spaces(): void
    {
        $this->actingAs($this->admin())
            ->post('/clients', $this->payload(['rfc' => '  gso101010abc  ']));

        // "gso101010abc" y "GSO101010ABC" son el mismo contribuyente: si se
        // guarda tal cual, el índice único no sirve de nada.
        $this->assertDatabaseHas('clients', ['rfc' => 'GSO101010ABC']);
    }

    public function test_the_rfc_is_optional(): void
    {
        $this->actingAs($this->admin())
            ->post('/clients', $this->payload(['rfc' => null]))
            ->assertRedirect();

        $this->assertDatabaseHas('clients', ['rfc' => null]);
    }

    public function test_an_enterprise_cannot_register_a_thirteen_character_rfc(): void
    {
        $this->actingAs($this->admin())
            ->post('/clients', $this->payload(['rfc' => 'GODE561231GR8']))
            ->assertSessionHasErrors('rfc');

        $this->assertDatabaseCount('clients', 0);
    }

    public function test_an_individual_cannot_register_a_twelve_character_rfc(): void
    {
        $this->actingAs($this->admin())
            ->post('/clients', $this->payload(['type' => 'persona', 'rfc' => 'GSO101010ABC']))
            ->assertSessionHasErrors('rfc');

        $this->assertDatabaseCount('clients', 0);
    }

    public function test_an_individual_rfc_is_accepted_for_a_person(): void
    {
        $this->actingAs($this->admin())
            ->post('/clients', $this->payload(['type' => 'persona', 'rfc' => 'GODE561231GR8']))
            ->assertRedirect();

        $this->assertDatabaseHas('clients', ['rfc' => 'GODE561231GR8']);
    }

    public function test_an_rfc_with_an_impossible_month_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post('/clients', $this->payload(['rfc' => 'GSO109910ABC']))
            ->assertSessionHasErrors('rfc');

        $this->assertDatabaseCount('clients', 0);
    }

    public function test_an_rfc_with_an_impossible_day_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post('/clients', $this->payload(['rfc' => 'GSO101045ABC']))
            ->assertSessionHasErrors('rfc');

        $this->assertDatabaseCount('clients', 0);
    }

    public function test_the_rfc_must_be_unique(): void
    {
        Client::factory()->withRfc('GSO101010ABC')->create();

        $this->actingAs($this->admin())
            ->post('/clients', $this->payload())
            ->assertSessionHasErrors('rfc');

        $this->assertSame(1, Client::count());
    }

    public function test_a_client_can_be_edited(): void
    {
        $client = Client::factory()->create(['name' => 'Nombre anterior']);

        $this->actingAs($this->admin())
            ->patch("/clients/{$client->id}", $this->editPayload(['name' => 'Nombre corregido']))
            ->assertRedirect();

        $this->assertDatabaseHas('clients', ['id' => $client->id, 'name' => 'Nombre corregido']);
    }

    public function test_editing_a_client_does_not_wipe_the_fields_the_form_omitted(): void
    {
        $client = Client::factory()->withRfc('GSO101010ABC')->create(['phone' => '55 1234 5678']);

        $this->actingAs($this->admin())->patch("/clients/{$client->id}", [
            'type' => 'empresa',
            'name' => 'Nombre corregido',
        ]);

        $client->refresh();

        $this->assertSame('GSO101010ABC', $client->rfc);
        $this->assertSame('55 1234 5678', $client->phone);
    }

    public function test_the_status_cannot_be_changed_through_the_edit_form(): void
    {
        // clients.change_status es distinto de clients.update: aceptar el
        // campo aquí permitiría saltárselo.
        $client = Client::factory()->create(['status' => 'activo']);

        $this->actingAs($this->manager())
            ->patch("/clients/{$client->id}", $this->payload(['status' => 'inactivo']))
            ->assertSessionHasErrors('status');

        $this->assertSame('activo', $client->refresh()->status);
    }

    public function test_the_status_can_be_changed_by_its_own_action(): void
    {
        $client = Client::factory()->create(['status' => 'activo']);

        $this->actingAs($this->manager())
            ->patch("/clients/{$client->id}/status", ['status' => 'inactivo'])
            ->assertRedirect();

        $this->assertSame('inactivo', $client->refresh()->status);
    }

    public function test_an_unknown_status_is_refused(): void
    {
        $client = Client::factory()->create(['status' => 'activo']);

        $this->actingAs($this->manager())
            ->patch("/clients/{$client->id}/status", ['status' => 'archivado'])
            ->assertSessionHasErrors('status');

        $this->assertSame('activo', $client->refresh()->status);
    }

    public function test_a_client_without_contacts_can_be_deleted(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($this->admin())
            ->delete("/clients/{$client->id}")
            ->assertRedirect('/clients');

        $this->assertModelMissing($client);
    }

    public function test_a_client_with_contacts_cannot_be_deleted(): void
    {
        $client = Client::factory()->create();
        $client->contacts()->create(['first_name' => 'Ana', 'is_primary' => true]);

        $this->actingAs($this->admin())
            ->delete("/clients/{$client->id}")
            ->assertRedirect()
            ->assertSessionHas('error');

        // Los contactos siguen ahí: nada se borró en cascada.
        $this->assertModelExists($client);
        $this->assertSame(1, $client->contacts()->count());
    }

    public function test_a_manager_and_a_seller_cannot_delete_a_client(): void
    {
        foreach ([$this->manager(), $this->seller()] as $user) {
            $client = Client::factory()->create();

            $this->actingAs($user)
                ->delete("/clients/{$client->id}")
                ->assertForbidden();

            $this->assertModelExists($client);
        }
    }

    public function test_a_seller_cannot_change_the_status(): void
    {
        $client = Client::factory()->create(['status' => 'activo']);

        $this->actingAs($this->seller())
            ->patch("/clients/{$client->id}/status", ['status' => 'inactivo'])
            ->assertForbidden();

        $this->assertSame('activo', $client->refresh()->status);
    }

    public function test_a_seller_can_create_and_edit_a_client(): void
    {
        $seller = $this->seller();

        $this->actingAs($seller)
            ->post('/clients', $this->payload())
            ->assertRedirect();

        $client = Client::firstOrFail();

        $this->actingAs($seller)
            ->patch("/clients/{$client->id}", $this->editPayload(['name' => 'Corregido por el vendedor']))
            ->assertRedirect();

        $this->assertDatabaseHas('clients', ['id' => $client->id, 'name' => 'Corregido por el vendedor']);
    }

    public function test_an_account_without_the_permission_cannot_open_the_module(): void
    {
        // Los tres roles sembrados llegan a clientes, así que el que prueba el
        // 403 lleva un solo permiso ajeno.
        $user = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->givePermissionTo('roles.view');

        $this->assertTrue($user->can('roles.view'));
        $this->assertFalse($user->can('clients.view'));

        $this->actingAs($user)->get('/clients')->assertForbidden();
        $this->actingAs($user)->get('/clients/create')->assertForbidden();
    }

    public function test_a_deactivated_account_loses_access_immediately(): void
    {
        $client = Client::factory()->create();

        $seller = $this->seller();
        $seller->update(['is_active' => false]);

        $this->actingAs($seller)->get("/clients/{$client->id}")->assertRedirect(route('login'));
    }

    public function test_the_listing_can_be_searched_filtered_and_sorted(): void
    {
        Client::factory()->create(['name' => 'Grupo Alfa', 'status' => 'activo', 'type' => 'empresa']);
        Client::factory()->person()->inactive()->create(['name' => 'Persona Beta', 'type' => 'persona']);

        $this->actingAs($this->admin())
            ->get('/clients?search=Alfa')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Clients/Index')
                ->has('clients.data', 1)
                ->where('clients.data.0.name', 'Grupo Alfa')
            );

        $this->actingAs($this->admin())
            ->get('/clients?status=inactivo')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('clients.data', 1)
                ->where('clients.data.0.name', 'Persona Beta')
            );

        $this->actingAs($this->admin())
            ->get('/clients?sort=name&direction=asc')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('clients.data.0.name', 'Grupo Alfa')
                ->where('filters.sort', 'name')
                ->where('filters.direction', 'asc')
            );
    }

    public function test_the_listing_falls_back_to_the_default_sort_for_an_unknown_column(): void
    {
        // La columna nunca llega a la consulta: antes se valida contra la
        // lista blanca de config/clients.php.
        Client::factory()->create(['name' => 'Alfa']);

        $this->actingAs($this->admin())
            ->get('/clients?sort='.urlencode('name; drop table clients'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.sort', config('clients.default_sort'))
            );
    }

    public function test_the_search_matches_the_rfc_and_the_email(): void
    {
        Client::factory()->create(['rfc' => 'GSO101010ABC', 'email' => 'ventas@alfa.test', 'name' => 'Alfa']);

        $this->actingAs($this->admin())
            ->get('/clients?search=GSO101010')
            ->assertInertia(fn ($page) => $page->has('clients.data', 1));

        $this->actingAs($this->admin())
            ->get('/clients?search=ventas@alfa.test')
            ->assertInertia(fn ($page) => $page->has('clients.data', 1));
    }

    public function test_the_detail_page_reports_the_contacts_and_their_permissions(): void
    {
        $client = Client::factory()->create();
        $client->contacts()->create(['first_name' => 'Ana', 'last_name' => 'Lopez', 'is_primary' => true]);
        $client->contacts()->create(['first_name' => 'Luis', 'last_name' => 'Ramos']);

        $this->actingAs($this->manager())
            ->get("/clients/{$client->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Clients/Show')
                ->has('contacts', 2)
                ->where('primaryContact.first_name', 'Ana')
                ->where('can.update', true)
                // El gerente gestiona contactos pero nunca los borra.
                ->where('can.contacts.create', true)
                ->where('can.contacts.delete', false)
                ->where('can.contacts.setPrimary', true)
                ->where('can.delete', false)
            );
    }

    public function test_the_labels_come_from_the_configuration(): void
    {
        $client = Client::factory()->company()->create();

        $this->assertSame('Empresa', $client->type_label);
        $this->assertSame('Activo', $client->status_label);
        $this->assertTrue($client->isActive());

        $client->update(['status' => 'inactivo']);

        $this->assertSame('Inactivo', $client->status_label);
        $this->assertFalse($client->isActive());
    }

    public function test_the_labels_travel_with_the_json_for_the_interface(): void
    {
        $client = Client::factory()->person()->inactive()->create();

        // La interfaz lee estos labels en vez de repetir el mapeo: el texto
        // solo vive en config/clients.php.
        $this->assertSame('Persona', $client->toArray()['type_label']);
        $this->assertSame('Inactivo', $client->toArray()['status_label']);
    }
}
