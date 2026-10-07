<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

/**
 * Módulo de contactos, con el peso en la regla "un solo contacto principal
 * por cliente": es la invariante que parece correcta hasta que dos peticiones
 * se solapan, así que se comprueba desde fuera, por los endpoints.
 */
class ContactManagementTest extends TestCase
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

    public function test_a_contact_can_be_created_for_a_client(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($this->manager())
            ->post("/clients/{$client->id}/contacts", [
                'first_name' => 'Ana',
                'last_name' => 'Lopez',
                'position' => 'Compradora',
                'phone' => '55 1111 2222',
                'email' => 'ana@comercializadora.test',
                'notes' => null,
            ])
            ->assertRedirect("/clients/{$client->id}");

        $this->assertDatabaseHas('contacts', [
            'client_id' => $client->id,
            'first_name' => 'Ana',
            'last_name' => 'Lopez',
        ]);

        $this->assertFalse($client->contacts()->firstOrFail()->is_primary);
    }

    public function test_the_contact_email_is_stored_in_lowercase(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($this->manager())->post("/clients/{$client->id}/contacts", [
            'first_name' => 'Ana',
            'email' => '  ANA@Comercializadora.TEST ',
        ]);

        $this->assertDatabaseHas('contacts', ['email' => 'ana@comercializadora.test']);
    }

    public function test_a_contact_belonging_to_another_client_cannot_be_created_here(): void
    {
        $client = Client::factory()->create();
        $other = Client::factory()->create();

        // client_id no va en el formulario y el cliente sale de la URL: un
        // payload manipulado no mueve el contacto de cliente.
        $this->actingAs($this->manager())->post("/clients/{$client->id}/contacts", [
            'first_name' => 'Ana',
            'client_id' => $other->id,
        ]);

        $this->assertDatabaseHas('contacts', ['client_id' => $client->id, 'first_name' => 'Ana']);
        $this->assertDatabaseMissing('contacts', ['client_id' => $other->id]);
    }

    public function test_a_contact_is_never_the_primary_one_on_creation_unless_asked(): void
    {
        $client = Client::factory()->create();
        $client->contacts()->create(['first_name' => 'Ana', 'is_primary' => true]);

        $this->actingAs($this->manager())->post("/clients/{$client->id}/contacts", [
            'first_name' => 'Luis',
        ]);

        $this->assertSame(
            ['Ana', 'Luis'],
            $client->contacts()->orderBy('id')->pluck('first_name')->all(),
        );
        $this->assertSame(1, $client->contacts()->where('is_primary', true)->count());
        $this->assertSame('Ana', $client->contacts()->where('is_primary', true)->first()->first_name);
    }

    public function test_a_contact_can_be_created_as_the_primary_one(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($this->manager())
            ->post("/clients/{$client->id}/contacts", [
                'first_name' => 'Ana',
                'is_primary' => true,
            ])
            ->assertRedirect();

        $this->assertSame(1, $client->contacts()->where('is_primary', true)->count());
    }

    /**
     * Regresión: blank() devuelve false para cualquier booleano, así que
     * filled(false) es true. Un chequeo truthy leería también "0" como true,
     * que es lo que manda un checkbox sin marcar en HTML.
     */
    public function test_an_unchecked_primary_box_never_promotes_the_contact(): void
    {
        $payloads = [
            'absent' => [],
            'json false' => ['is_primary' => false],
            'string zero' => ['is_primary' => '0'],
        ];

        foreach ($payloads as $case => $payload) {
            $client = Client::factory()->create();
            $client->contacts()->create(['first_name' => 'Ana', 'is_primary' => true]);

            $this->actingAs($this->manager())
                ->post("/clients/{$client->id}/contacts", ['first_name' => 'Luis', ...$payload])
                ->assertRedirect("/clients/{$client->id}");

            $this->assertSame(
                1,
                $client->contacts()->where('is_primary', true)->count(),
                "The contact was promoted with the box sent as: {$case}",
            );
            $this->assertSame(
                'Ana',
                $client->contacts()->where('is_primary', true)->first()->first_name,
                "The wrong contact ended up primary with the box sent as: {$case}",
            );
            $this->assertFalse(
                $client->contacts()->where('first_name', 'Luis')->first()->is_primary,
                "The new contact became primary with the box sent as: {$case}",
            );
        }
    }

    public function test_promoting_a_contact_demotes_the_previous_primary(): void
    {
        $client = Client::factory()->create();
        $first = $client->contacts()->create(['first_name' => 'Ana', 'is_primary' => true]);
        $second = $client->contacts()->create(['first_name' => 'Luis']);

        $this->actingAs($this->manager())
            ->patch("/contacts/{$second->id}/primary")
            ->assertRedirect("/clients/{$client->id}");

        $this->assertFalse((bool) $first->refresh()->is_primary);
        $this->assertTrue((bool) $second->refresh()->is_primary);

        // La invariante, comprobada directamente: nunca más de uno.
        $this->assertSame(1, $client->contacts()->where('is_primary', true)->count());
    }

    public function test_promoting_the_same_contact_twice_is_harmless(): void
    {
        $client = Client::factory()->create();
        $contact = $client->contacts()->create(['first_name' => 'Ana', 'is_primary' => true]);

        $this->actingAs($this->manager())->patch("/contacts/{$contact->id}/primary")->assertRedirect();

        $this->assertTrue((bool) $contact->refresh()->is_primary);
        $this->assertSame(1, $client->contacts()->where('is_primary', true)->count());
    }

    public function test_a_seller_cannot_promote_a_contact(): void
    {
        $client = Client::factory()->create();
        $contact = $client->contacts()->create(['first_name' => 'Ana', 'is_primary' => true]);

        $this->actingAs($this->seller())
            ->patch("/contacts/{$contact->id}/primary")
            ->assertForbidden();

        $this->assertTrue((bool) $contact->refresh()->is_primary);
    }

    public function test_a_seller_cannot_send_the_primary_flag_on_the_edit_form(): void
    {
        // El checkbox está en el formulario de edición: la petición tiene que
        // rechazarlo ahí, no solo en la ruta dedicada.
        $client = Client::factory()->create();
        $contact = $client->contacts()->create(['first_name' => 'Ana', 'is_primary' => true]);

        $this->actingAs($this->seller())
            ->patch("/contacts/{$contact->id}", [
                'first_name' => 'Ana',
                'is_primary' => false,
            ])
            ->assertForbidden();

        $this->assertTrue((bool) $contact->refresh()->is_primary);
    }

    public function test_unchecking_the_box_leaves_the_client_without_a_primary_contact(): void
    {
        $client = Client::factory()->create();
        $contact = $client->contacts()->create(['first_name' => 'Ana', 'is_primary' => true]);

        $this->actingAs($this->manager())
            ->patch("/contacts/{$contact->id}", ['first_name' => 'Ana', 'is_primary' => false])
            ->assertRedirect();

        $this->assertFalse((bool) $contact->refresh()->is_primary);
        $this->assertSame(0, $client->contacts()->where('is_primary', true)->count());
    }

    public function test_editing_the_primary_contact_keeps_it_primary(): void
    {
        $client = Client::factory()->create();
        $contact = $client->contacts()->create(['first_name' => 'Ana', 'is_primary' => true]);

        $this->actingAs($this->manager())->patch("/contacts/{$contact->id}", [
            'first_name' => 'Ana Maria',
        ]);

        $this->assertSame('Ana Maria', $contact->refresh()->first_name);
        $this->assertTrue((bool) $contact->refresh()->is_primary);
    }

    public function test_a_contact_can_be_deleted(): void
    {
        $client = Client::factory()->create();
        $contact = $client->contacts()->create(['first_name' => 'Ana', 'is_primary' => true]);

        $this->actingAs($this->admin())
            ->delete("/contacts/{$contact->id}")
            ->assertRedirect("/clients/{$client->id}");

        $this->assertModelMissing($contact);
    }

    public function test_deleting_the_primary_contact_does_not_promote_another_one(): void
    {
        // La regla es "como mucho uno", no "exactamente uno": nombrar a otro
        // principal sin más sería decidir por el usuario.
        $client = Client::factory()->create();
        $primary = $client->contacts()->create(['first_name' => 'Ana', 'is_primary' => true]);
        $client->contacts()->create(['first_name' => 'Luis']);

        $this->actingAs($this->admin())->delete("/contacts/{$primary->id}");

        $this->assertSame(0, $client->contacts()->where('is_primary', true)->count());
    }

    public function test_a_manager_and_a_seller_cannot_delete_a_contact(): void
    {
        $client = Client::factory()->create();

        foreach ([$this->manager(), $this->seller()] as $user) {
            $contact = $client->contacts()->create(['first_name' => 'Ana']);

            $this->actingAs($user)->delete("/contacts/{$contact->id}")->assertForbidden();

            $this->assertModelExists($contact);
        }
    }

    public function test_a_seller_can_create_and_edit_a_contact(): void
    {
        $seller = $this->seller();
        $client = Client::factory()->create();

        $this->actingAs($seller)
            ->post("/clients/{$client->id}/contacts", ['first_name' => 'Ana'])
            ->assertRedirect();

        $contact = Contact::firstOrFail();

        $this->actingAs($seller)
            ->patch("/contacts/{$contact->id}", ['first_name' => 'Ana Maria'])
            ->assertRedirect();

        $this->assertSame('Ana Maria', $contact->refresh()->first_name);
    }

    public function test_an_account_without_the_permission_cannot_open_the_module(): void
    {
        // Solo con roles.view: basta para probar que el 403 viene de falta de
        // contacts.view y no del rol que le tocó.
        $user = $this->userWithoutCrmPermissions();

        $this->assertTrue($user->can('roles.view'));
        $this->assertFalse($user->can('contacts.view'));

        $this->actingAs($user)->get('/contacts')->assertForbidden();
    }

    public function test_the_creation_screen_requires_contact_permission(): void
    {
        $client = Client::factory()->create();

        // La pantalla está anidada bajo el cliente: el Gate se pregunta por
        // crear un contacto de ese cliente, no por el cliente.
        $this->actingAs($this->manager())
            ->get("/clients/{$client->id}/contacts/create")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Contacts/Create')
                ->where('client.id', $client->id)
            );

        // Quien ve el cliente pero no tiene contacts.create sigue sin entrar:
        // la URL anidada es accesible, la capacidad no.
        $user = $this->userWithoutCrmPermissions();
        $user->givePermissionTo('clients.view');

        $this->assertTrue($user->can('view', $client));
        $this->assertFalse($user->can('contacts.create'));

        $this->actingAs($user->fresh())
            ->get("/clients/{$client->id}/contacts/create")
            ->assertForbidden();
    }

    /**
     * Cuenta activa y verificada sin roles, con un solo permiso ajeno. Los
     * roles sembrados no sirven: los tres llegan a los módulos del CRM.
     */
    private function userWithoutCrmPermissions(): User
    {
        $user = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $user->givePermissionTo('roles.view');

        return $user->fresh();
    }

    public function test_the_global_listing_can_be_searched_by_name_email_and_client(): void
    {
        $client = Client::factory()->create(['name' => 'Comercializadora del Sur']);
        $client->contacts()->create(['first_name' => 'Ana', 'email' => 'ana@sur.test']);
        $other = Client::factory()->create(['name' => 'Transportes del Norte']);
        $other->contacts()->create(['first_name' => 'Luis', 'email' => 'luis@norte.test']);

        $this->actingAs($this->admin())
            ->get('/contacts?search=Ana')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Contacts/Index')
                ->has('contacts.data', 1)
                ->where('contacts.data.0.first_name', 'Ana')
            );

        $this->actingAs($this->admin())
            ->get('/contacts?search=luis@norte.test')
            ->assertInertia(fn ($page) => $page->has('contacts.data', 1));

        $this->actingAs($this->admin())
            ->get('/contacts?search=Transportes')
            ->assertInertia(fn ($page) => $page
                ->has('contacts.data', 1)
                ->where('contacts.data.0.client.name', 'Transportes del Norte')
            );
    }

    public function test_the_display_name_joins_the_first_and_last_name(): void
    {
        $contact = Contact::factory()->create(['first_name' => 'Ana', 'last_name' => 'Lopez']);

        $this->assertSame('Ana Lopez', $contact->display_name);
        $this->assertSame('Ana Lopez', $contact->toArray()['display_name']);

        $contact->update(['last_name' => null]);

        $this->assertSame('Ana', $contact->display_name);
    }
}
