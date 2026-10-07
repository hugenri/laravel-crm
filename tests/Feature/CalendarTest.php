<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Client;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

/**
 * El calendario no tiene datos propios: aquí se protege que no invente
 * datos, que no amplíe el acceso y que la aritmética de la rejilla sea
 * correcta. Cada actividad cae en su día, las fuera del rango no aparecen,
 * se abre con activities.view como el listado, y un mes que no cuadra en
 * semanas enteras coloca cada actividad en la columna equivocada.
 *
 * Siempre con ancla fija, octubre de 2026, nunca "hoy": sin ancla la prueba
 * pasa hoy y falla el mes siguiente sin que cambie ninguna línea.
 */
class CalendarTest extends TestCase
{
    use InteractsWithRoles;
    use RefreshDatabase;

    /** Ancla de la vista de mes con la que trabajan casi todas las pruebas. */
    private const OCTOBER = '2026-10-01';

    /** Lunes de la semana en la que caen los dias de referencia. */
    private const MONDAY = '2026-10-05';

    protected function setUp(): void
    {
        parent::setUp();

        // La cuenta bootstrap se salta la comprobación de permisos y las
        // pruebas de seguridad necesitan que se comprueben de verdad.
        config(['crm.admin_emails' => ['admin@crm.test']]);

        $this->seedRoles();
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
     * Permisos de todo el catálogo menos uno, para que un 403 solo pueda
     * explicarse por el permiso que falta.
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
     * @param  array<string, mixed>  $overrides
     */
    private function activity(array $overrides = []): Activity
    {
        return Activity::factory()->create(array_merge([
            'client_id' => Client::factory()->create()->id,
            'opportunity_id' => null,
            'user_id' => User::factory()->create(['is_active' => true])->id,
            'type' => 'llamada',
            'activity_date' => '2026-10-05 09:30:00',
            'status' => 'pendiente',
            'description' => 'Se confirmo la cita.',
        ], $overrides));
    }

    /**
     * La URL del calendario con el rango ya fijado, para que ninguna
     * aserción dependa del día en que corra la suite.
     *
     * @param  array<string, mixed>  $query
     */
    private function calendar(array $query = []): string
    {
        return '/calendar?'.http_build_query(array_merge([
            'view' => 'month',
            'anchor' => self::OCTOBER,
        ], $query));
    }

    // -------------------------------------------------------------------
    // Acceso
    // -------------------------------------------------------------------

    public function test_a_guest_cannot_reach_the_calendar(): void
    {
        $this->activity();

        $this->get('/calendar')->assertRedirect('/login');
    }

    public function test_an_account_without_the_view_permission_is_refused(): void
    {
        $this->activity();

        $withoutView = $this->userWithAllPermissionsExcept('activities.view');

        $this->actingAs($withoutView)->get('/calendar')->assertForbidden();
    }

    public function test_an_account_without_roles_is_refused(): void
    {
        $this->activity();

        $withoutRoles = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($withoutRoles)->get('/calendar')->assertForbidden();
    }

    public function test_the_calendar_opens_with_the_same_permission_as_the_listing(): void
    {
        $this->activity();

        $viewer = $this->userWithAllPermissionsExcept('activities.view');

        $this->actingAs($viewer)->get('/calendar')->assertForbidden();
        $this->actingAs($viewer)->get('/activities')->assertForbidden();

        $seller = $this->seller();

        $this->actingAs($seller)->get('/calendar')->assertOk();
        $this->actingAs($seller)->get('/activities')->assertOk();
    }

    public function test_the_manager_and_the_seller_see_the_same_calendar(): void
    {
        $this->activity();

        $this->actingAs($this->manager())->get($this->calendar())->assertOk();
        $this->actingAs($this->seller())->get($this->calendar())->assertOk();
    }

    // -------------------------------------------------------------------
    // Contenido
    // -------------------------------------------------------------------

    public function test_the_calendar_places_each_activity_on_its_own_day(): void
    {
        $client = Client::factory()->create(['name' => 'Grupo Cervantes']);
        $responsible = User::factory()->create(['is_active' => true, 'name' => 'Ana Ruiz']);

        $activity = $this->activity([
            'client_id' => $client->id,
            'user_id' => $responsible->id,
            'type' => 'reunion',
            'activity_date' => '2026-10-20 16:45:00',
            'status' => 'completada',
            'description' => 'Se reviso la propuesta con el area de compras.',
        ]);

        $this->actingAs($this->seller())->get($this->calendar())
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Calendar/Index')
                ->where('events', fn ($events) => count($events) === 1)
                ->where('events.0.id', $activity->id)
                ->where('events.0.date', '2026-10-20')
                ->where('events.0.time', '16:45')
                ->where('events.0.all_day', false)
                ->where('events.0.title', 'Se reviso la propuesta con el area de compras.')
                ->where('events.0.type', 'reunion')
                ->where('events.0.type_label', 'Reunión')
                ->where('events.0.status', 'completada')
                ->where('events.0.status_label', 'Completada')
                ->where('events.0.client.name', 'Grupo Cervantes')
                ->where('events.0.user.name', 'Ana Ruiz')
                ->where('events.0.url', route('activities.show', $activity))
            );
    }

    public function test_an_activity_at_midnight_is_reported_as_a_whole_day(): void
    {
        $allDay = $this->activity(['activity_date' => '2026-10-05 00:00:00']);
        $timed = $this->activity(['activity_date' => '2026-10-05 09:30:00']);

        $this->actingAs($this->seller())->get($this->calendar())
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('events.0.id', $allDay->id)
                ->where('events.0.time', '00:00')
                ->where('events.0.all_day', true)
                ->where('events.1.id', $timed->id)
                ->where('events.1.all_day', false)
            );
    }

    public function test_the_calendar_leaves_out_activities_outside_the_visible_range(): void
    {
        $this->activity(['activity_date' => '2026-10-05 09:00:00']);
        $this->activity(['activity_date' => '2026-10-31 23:00:00']);
        $this->activity(['activity_date' => '2026-12-15 10:00:00']);

        $this->actingAs($this->seller())->get($this->calendar())
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('events', fn ($events) => count($events) === 2)
            );
    }

    public function test_the_last_moment_of_the_month_still_belongs_to_it(): void
    {
        // El cierre de la rejilla es el domingo a las 23:59, no el dia siguiente
        // a medianoche: una actividad del ultimo minuto no debe quedar fuera.
        $this->activity(['activity_date' => '2026-10-31 23:59:00']);
        $this->activity(['activity_date' => '2026-11-01 00:00:00']);

        $this->actingAs($this->seller())->get($this->calendar())
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('events', fn ($events) => count($events) === 2)
            );
    }

    public function test_the_event_title_is_truncated_to_the_configured_length(): void
    {
        $this->activity(['description' => str_repeat('a', 400)]);

        $max = (int) config('calendar.title_max');

        $this->actingAs($this->seller())->get($this->calendar())
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('events.0.title', function (string $title) use ($max) {
                    $this->assertStringStartsWith(str_repeat('a', $max), $title);
                    $this->assertStringEndsWith('...', $title);
                    $this->assertSame($max + mb_strlen('...'), mb_strlen($title));

                    return true;
                })
            );
    }

    public function test_the_calendar_does_not_hide_activities_that_the_listing_shows(): void
    {
        // La policy no limita por propietario y el calendario tampoco: si lo
        // hiciera, el vendedor vería menos aquí que en su listado.
        $this->activity(['activity_date' => '2026-10-05 09:00:00']);
        $this->activity(['activity_date' => '2026-10-06 09:00:00']);

        $this->actingAs($this->seller())->get($this->calendar())
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('events', fn ($events) => count($events) === 2)
            );
    }

    public function test_an_activity_appears_once_in_the_week_that_owns_its_day(): void
    {
        // La semana del 28 de septiembre al 4 de octubre cae en dos meses: no
        // puede salir dos veces ni en la de septiembre ni en la de octubre.
        $this->activity(['activity_date' => '2026-10-01 11:00:00']);

        $this->actingAs($this->seller())->get($this->calendar(['view' => 'week', 'anchor' => '2026-09-28']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('range.start', '2026-09-28')
                ->where('range.end', '2026-10-04')
                ->where('events', fn ($events) => count($events) === 1)
            );
    }

    // -------------------------------------------------------------------
    // Las tres vistas
    // -------------------------------------------------------------------

    public function test_the_default_view_is_the_month_of_today(): void
    {
        $this->actingAs($this->seller())->get('/calendar')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('range.view', config('calendar.default_view'))
                ->where('range.anchor', now()->startOfMonth()->toDateString())
                ->where('range.today', now()->toDateString())
            );
    }

    public function test_the_month_view_covers_the_month_aligned_to_whole_weeks(): void
    {
        // Octubre de 2026 empieza en jueves: la rejilla arranca el lunes 28
        // de septiembre y cierra el domingo 1 de noviembre.
        $this->actingAs($this->seller())->get($this->calendar())
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('range.view', 'month')
                ->where('range.anchor', self::OCTOBER)
                ->where('range.start', '2026-09-28')
                ->where('range.end', '2026-11-01')
                ->where('range.day_count', 35)
                ->where('range.days.0', '2026-09-28')
                ->where('range.days.6', '2026-10-04')
                ->where('range.days.7', '2026-10-05')
                ->where('range.days.34', '2026-11-01')
                ->where('range.previous', '2026-09-01')
                ->where('range.next', '2026-11-01')
            );
    }

    public function test_a_month_that_needs_six_rows_still_ends_on_a_sunday(): void
    {
        // Agosto de 2026 necesita las seis filas, justo el tope que el backend
        // se impone para no pedir un rango ilimitado.
        $this->actingAs($this->seller())->get($this->calendar(['anchor' => '2026-08-01']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('range.anchor', '2026-08-01')
                ->where('range.start', '2026-07-27')
                ->where('range.end', '2026-09-06')
                ->where('range.day_count', 42)
            );
    }

    public function test_the_week_view_runs_from_monday_to_sunday(): void
    {
        $this->actingAs($this->seller())->get($this->calendar(['view' => 'week', 'anchor' => '2026-10-07']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('range.view', 'week')
                ->where('range.anchor', self::MONDAY)
                ->where('range.start', self::MONDAY)
                ->where('range.end', '2026-10-11')
                ->where('range.day_count', 7)
                ->where('range.previous', '2026-09-28')
                ->where('range.next', '2026-10-12')
            );
    }

    public function test_the_week_view_survives_the_turn_of_the_year(): void
    {
        $this->actingAs($this->seller())->get($this->calendar(['view' => 'week', 'anchor' => '2026-12-31']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('range.start', '2026-12-28')
                ->where('range.end', '2027-01-03')
                ->where('range.next', '2027-01-04')
            );
    }

    public function test_the_day_view_returns_a_single_day(): void
    {
        $this->activity(['activity_date' => '2026-10-05 09:30:00']);
        $this->activity(['activity_date' => '2026-10-06 09:30:00']);

        $this->actingAs($this->seller())->get($this->calendar(['view' => 'day', 'anchor' => '2026-10-06']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('range.view', 'day')
                ->where('range.anchor', '2026-10-06')
                ->where('range.start', '2026-10-06')
                ->where('range.end', '2026-10-06')
                ->where('range.day_count', 1)
                ->where('range.previous', '2026-10-05')
                ->where('range.next', '2026-10-07')
                ->where('events', fn ($events) => count($events) === 1)
            );
    }

    public function test_the_anchor_is_normalized_to_the_view_that_was_asked_for(): void
    {
        // Un ancla a mitad de mes vuelve como día 1 y una de domingo a su
        // lunes: así el mismo enlace produce siempre la misma rejilla.
        $this->actingAs($this->seller())->get($this->calendar(['anchor' => '2026-10-15']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.anchor', self::OCTOBER)
                ->where('range.anchor', self::OCTOBER)
            );

        $this->actingAs($this->seller())->get($this->calendar(['view' => 'week', 'anchor' => '2026-10-11']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.anchor', self::MONDAY)
            );
    }

    public function test_going_forward_and_back_returns_to_the_same_range(): void
    {
        $seller = $this->seller();

        $this->actingAs($seller)->get($this->calendar())
            ->assertInertia(fn (AssertableInertia $page) => $page->where('range.next', '2026-11-01'));

        $this->actingAs($seller)->get($this->calendar(['anchor' => '2026-11-01']))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('range.start', '2026-10-26'));

        $this->actingAs($seller)->get($this->calendar(['anchor' => '2026-11-01']))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('range.previous', '2026-10-01'));

        $this->actingAs($seller)->get($this->calendar())
            ->assertInertia(fn (AssertableInertia $page) => $page->where('range.start', '2026-09-28'));
    }

    // -------------------------------------------------------------------
    // Entradas invalidas
    // -------------------------------------------------------------------

    public function test_an_unknown_view_is_rejected_with_the_calendar_message(): void
    {
        $this->actingAs($this->seller())
            ->from('/calendar')
            ->get('/calendar?view=decada')
            ->assertRedirect('/calendar')
            ->assertSessionHasErrors('view');

        $this->assertStringStartsWith(
            Str::before(__('calendar.invalid_view'), ':'),
            session('errors')->first('view'),
        );
    }

    public function test_a_malformed_anchor_is_rejected(): void
    {
        $this->actingAs($this->seller())
            ->from('/calendar')
            ->get('/calendar?anchor=05-10-2026')
            ->assertRedirect('/calendar')
            ->assertSessionHasErrors('anchor');

        $this->assertSame(__('calendar.invalid_anchor'), session('errors')->first('anchor'));
    }

    public function test_an_unknown_type_or_status_is_rejected(): void
    {
        $this->actingAs($this->seller())->get('/calendar?type=teleporter')->assertSessionHasErrors('type');
        $this->actingAs($this->seller())->get('/calendar?status=archivada')->assertSessionHasErrors('status');
    }

    public function test_a_filter_pointing_to_a_record_that_does_not_exist_is_rejected(): void
    {
        $this->actingAs($this->seller())->get('/calendar?client_id=999999')->assertSessionHasErrors('client_id');
        $this->actingAs($this->seller())->get('/calendar?user_id=999999')->assertSessionHasErrors('user_id');
    }

    // -------------------------------------------------------------------
    // Filtros
    // -------------------------------------------------------------------

    public function test_the_calendar_filters_by_type_and_status(): void
    {
        $seller = $this->seller();

        $this->activity(['activity_date' => '2026-10-05 09:00:00', 'type' => 'llamada']);
        $this->activity(['activity_date' => '2026-10-06 09:00:00', 'type' => 'correo']);
        $this->activity(['activity_date' => '2026-10-07 09:00:00', 'type' => 'correo', 'status' => 'completada']);

        $this->actingAs($seller)->get($this->calendar(['type' => 'llamada']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.type', 'llamada')
                ->where('events', fn ($events) => count($events) === 1)
                ->where('events.0.type', 'llamada')
            );

        $this->actingAs($seller)->get($this->calendar(['status' => 'completada']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('events', fn ($events) => count($events) === 1)
                ->where('events.0.status', 'completada')
            );
    }

    public function test_the_calendar_filters_by_responsible_and_by_mine(): void
    {
        $seller = $this->seller();
        $other = User::factory()->create(['is_active' => true]);

        $mine = $this->activity(['activity_date' => '2026-10-05 09:00:00', 'user_id' => $seller->id]);
        $this->activity(['activity_date' => '2026-10-06 09:00:00', 'user_id' => $other->id]);

        $this->actingAs($seller)->get($this->calendar(['user_id' => $seller->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('events', fn ($events) => count($events) === 1)
                ->where('events.0.id', $mine->id)
            );

        $this->actingAs($seller)->get($this->calendar(['mine' => '1']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.mine', '1')
                ->where('events', fn ($events) => count($events) === 1)
                ->where('events.0.id', $mine->id)
            );
    }

    public function test_the_calendar_filters_by_client_and_opportunity(): void
    {
        $opportunity = Opportunity::factory()->create();

        $linked = $this->activity([
            'activity_date' => '2026-10-05 09:00:00',
            'client_id' => $opportunity->client_id,
            'opportunity_id' => $opportunity->id,
        ]);
        $this->activity(['activity_date' => '2026-10-06 09:00:00']);

        $this->actingAs($this->seller())->get($this->calendar(['client_id' => $opportunity->client_id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('events', fn ($events) => count($events) === 1)
                ->where('events.0.id', $linked->id)
            );

        $this->actingAs($this->seller())->get($this->calendar(['opportunity_id' => $opportunity->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('events', fn ($events) => count($events) === 1)
                ->where('events.0.opportunity.id', $opportunity->id)
            );
    }

    public function test_a_date_filter_narrows_the_visible_range(): void
    {
        $this->activity(['activity_date' => '2026-10-05 09:00:00']);
        $this->activity(['activity_date' => '2026-10-20 09:00:00']);

        $this->actingAs($this->seller())->get($this->calendar([
            'date_from' => '2026-10-10',
            'date_to' => '2026-10-25',
        ]))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filters.date_from', '2026-10-10')
            ->where('filters.date_to', '2026-10-25')
            ->where('events', fn ($events) => count($events) === 1)
            ->where('events.0.date', '2026-10-20')
        );
    }

    public function test_a_date_filter_outside_the_month_leaves_the_range_intact(): void
    {
        // El filtro recorta dentro de la vista, no la sustituye: un rango de
        // otro mes no trae actividades pero la rejilla no cambia.
        $this->activity(['activity_date' => '2026-10-05 09:00:00']);

        $this->actingAs($this->seller())->get($this->calendar([
            'date_from' => '2026-11-01',
            'date_to' => '2026-11-30',
        ]))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('range.start', '2026-09-28')
            ->where('events', fn ($events) => count($events) === 0)
        );
    }

    public function test_the_filters_survive_a_change_of_view(): void
    {
        // La vista y el ancla viajan junto a los filtros y no por separado: al
        // cambiar de mes a semana el filtro se conserva.
        $this->activity(['activity_date' => '2026-10-05 09:00:00', 'type' => 'correo']);

        $this->actingAs($this->seller())->get($this->calendar([
            'view' => 'week',
            'anchor' => '2026-10-05',
            'type' => 'correo',
        ]))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('range.view', 'week')
            ->where('filters.type', 'correo')
            ->where('events', fn ($events) => count($events) === 1)
        );
    }

    // -------------------------------------------------------------------
    // Lo que la pagina necesita para dibujarse
    // -------------------------------------------------------------------

    public function test_the_calendar_travels_with_the_catalogs_it_draws(): void
    {
        $this->actingAs($this->seller())->get($this->calendar())
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('views', config('calendar.views'))
                ->where('types', config('activities.types'))
                ->where('statuses', config('activities.statuses'))
                ->where('can.create', true)
            );
    }

    public function test_the_calendar_does_not_offer_to_create_without_the_permission(): void
    {
        $this->actingAs($this->userWithAllPermissionsExcept('activities.create'))
            ->get($this->calendar())
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('can.create', false));
    }

    public function test_the_create_link_carries_the_day_that_was_clicked(): void
    {
        $this->actingAs($this->seller())->get('/activities/create?activity_date=2026-10-05T00:00')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Activities/Create')
                ->where('initialActivityDate', '2026-10-05T00:00')
            );
    }

    public function test_a_malformed_preselected_date_is_refused_by_the_create_screen(): void
    {
        $this->actingAs($this->seller())
            ->from('/calendar')
            ->get('/activities/create?activity_date=2026-10-05')
            ->assertRedirect('/calendar')
            ->assertSessionHasErrors('activity_date');
    }

    public function test_the_create_screen_leaves_the_date_empty_without_a_preselection(): void
    {
        $this->actingAs($this->seller())->get('/activities/create')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('initialActivityDate', null));
    }
}
