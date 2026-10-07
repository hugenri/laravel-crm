<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Opportunity;
use App\Models\Prospect;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

/**
 * Los reportes agregan en lectura las mismas tablas que los listados: aquí
 * se comprueba que las cifras coincidan con las del listado y que ningún
 * permiso deje escapar el módulo que oculta.
 *
 * Nada depende de la fecha actual: cada registro lleva fecha explícita,
 * porque un reporte cuyas aserciones se mueven con el calendario deja de
 * servir al día siguiente de escrito.
 */
class ReportsTest extends TestCase
{
    use InteractsWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
    }

    private function admin(): User
    {
        return $this->userWithRoles(['Administrador']);
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
            [$without],
        )));

        return $user->fresh();
    }

    /**
     * Carga el reporte y devuelve su aserción Inertia.
     */
    private function reportFor(User $user, array $query = []): AssertableInertia
    {
        $page = null;

        $this->actingAs($user)
            ->get('/reports'.($query === [] ? '' : '?'.http_build_query($query)))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $inertia) use (&$page) {
                $page = $inertia;
            });

        return $page;
    }

    /**
     * Lee una prop, o una clave dentro de ella: AssertableInertia no tiene
     * getter y hay tests que necesitan el valor para ramificar.
     */
    private function prop(AssertableInertia $page, string $key, ?string $inside = null): mixed
    {
        $props = $page->toArray()['props'];

        if ($inside === null) {
            return $props[$key] ?? null;
        }

        return $props[$key][$inside] ?? null;
    }

    // -------------------------------------------------------------------
    // Acceso
    // -------------------------------------------------------------------

    public function test_a_guest_is_redirected_to_the_login_screen(): void
    {
        $this->get('/reports')->assertRedirect('/login');
    }

    public function test_an_inactive_account_is_turned_away(): void
    {
        $inactive = User::factory()->create([
            'is_active' => false,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($inactive)->get('/reports')->assertRedirect('/login');
    }

    public function test_an_unverified_account_is_sent_to_the_email_verification(): void
    {
        $unverified = User::factory()->unverified()->create(['is_active' => true]);

        $this->actingAs($unverified)->get('/reports')->assertRedirect('/email/verify');
    }

    public function test_an_account_without_the_report_permission_is_refused(): void
    {
        // Puede consultar los cuatro módulos y aun así no entrar: el permiso
        // se decide en la ruta, antes de que corra el controlador.
        $user = $this->userWithAllPermissionsExcept('reports.view');

        $this->actingAs($user)->get('/reports')->assertForbidden();
    }

    public function test_an_account_without_any_role_cannot_see_the_reports(): void
    {
        $withoutRoles = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($withoutRoles)->get('/reports')->assertForbidden();
    }

    public function test_the_three_system_roles_reach_the_reports(): void
    {
        foreach ([$this->admin(), $this->manager(), $this->seller()] as $user) {
            $this->actingAs($user)
                ->get('/reports')
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page->component('Reports/Index'));
        }
    }

    public function test_the_report_permission_is_in_the_catalog_and_in_the_matrix(): void
    {
        $this->assertArrayHasKey('reports.view', config('roles.permissions'));

        // El administrador entra por la matriz comodin, no por el permiso literal.
        $this->assertSame(['*'], config('roles.matrix')['Administrador']);

        foreach (['Gerente', 'Vendedor'] as $role) {
            $this->assertContains('reports.view', config('roles.matrix')[$role], $role);
        }
    }

    public function test_the_screen_is_read_only(): void
    {
        // Ni otro verbo que GET ni tabla propia: el reporte agrega filas que
        // ya existen, no es un registro suyo.
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route): bool => $route->uri() === 'reports');

        $this->assertCount(1, $routes);

        $this->assertSame(['GET', 'HEAD'], $routes->first()->methods());

        foreach ([
            'reports', 'report_items', 'report_snapshots',
            'analytics', 'metrics', 'dashboards',
        ] as $table) {
            $this->assertFalse(Schema::hasTable($table), "la tabla {$table} no debe existir");
        }
    }

    // -------------------------------------------------------------------
    // Oportunidades
    // -------------------------------------------------------------------

    public function test_the_opportunity_block_counts_the_period_by_status(): void
    {
        Opportunity::factory()->count(3)->create(['amount' => '1000.00']);
        Opportunity::factory()->count(2)->won()->create(['amount' => '5000.00']);
        Opportunity::factory()->count(1)->lost()->create(['amount' => '7000.00']);

        $this->reportFor($this->admin())
            ->where('opportunities.total', 6)
            ->where('opportunities.open', 3)
            ->where('opportunities.won', 2)
            ->where('opportunities.lost', 1)
            ->where('opportunities.openAmount', '3000.00')
            ->where('opportunities.wonAmount', '10000.00');
    }

    public function test_the_open_and_won_amounts_leave_out_the_lost_ones(): void
    {
        Opportunity::factory()->won()->create(['amount' => '125000.50']);
        Opportunity::factory()->lost()->create(['amount' => '999999.99']);
        Opportunity::factory()->create(['amount' => '25000.25']);

        $this->reportFor($this->admin())
            ->where('opportunities.openAmount', '25000.25')
            ->where('opportunities.wonAmount', '125000.50');
    }

    public function test_an_opportunity_without_amount_does_not_break_the_sum(): void
    {
        // "amount" es nullable y una oportunidad se puede capturar sin monto.
        Opportunity::factory()->create(['amount' => null]);
        Opportunity::factory()->create(['amount' => '1500.00']);

        $this->reportFor($this->admin())
            ->where('opportunities.total', 2)
            ->where('opportunities.openAmount', '1500.00');
    }

    public function test_the_stage_breakdown_walks_the_whole_catalog(): void
    {
        Opportunity::factory()->count(2)->stage('nueva')->create();
        Opportunity::factory()->count(3)->stage('negociacion')->create();
        Opportunity::factory()->won()->create();
        Opportunity::factory()->lost()->create();

        $counts = array_fill_keys(array_keys(config('opportunities.stages')), 0);
        $counts['nueva'] = 2;
        $counts['negociacion'] = 3;
        $counts['ganada'] = 1;
        $counts['perdida'] = 1;

        $expected = array_map(
            fn (string $stage): array => [
                'stage' => $stage,
                'label' => config('opportunities.stages')[$stage],
                'count' => $counts[$stage],
            ],
            array_keys(config('opportunities.stages')),
        );

        // El recorrido es sobre el catalogo completo, no solo sobre las etapas
        // abiertas: una etapa sin operaciones aparece con cero.
        $this->reportFor($this->admin())->where('byStage', $expected);

        $this->assertSame(7, count($expected));
        $this->assertSame(0, $counts['propuesta']);
    }

    public function test_the_responsible_breakdown_repeats_the_measures_per_user(): void
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $luis = User::factory()->create(['name' => 'Luis']);

        Opportunity::factory()->responsible($ana)->count(3)->create(['amount' => '1000.00']);
        Opportunity::factory()->responsible($ana)->won()->create(['amount' => '4000.00']);
        Opportunity::factory()->responsible($ana)->lost()->create(['amount' => '6000.00']);
        Opportunity::factory()->responsible($luis)->count(2)->create(['amount' => '2000.00']);
        Opportunity::factory()->responsible($luis)->won()->create(['amount' => '8000.00']);

        $rows = $this->prop($this->reportFor($this->admin()), 'byResponsible', 'opportunities');

        $this->assertCount(2, $rows);

        $this->assertSame([
            'user_id' => $ana->id,
            'name' => 'Ana',
            'total' => 5,
            'open' => 3,
            'won' => 1,
            'lost' => 1,
            'openAmount' => '3000.00',
            'wonAmount' => '4000.00',
        ], $rows[0]);

        $this->assertSame([
            'user_id' => $luis->id,
            'name' => 'Luis',
            'total' => 3,
            'open' => 2,
            'won' => 1,
            'lost' => 0,
            'openAmount' => '4000.00',
            'wonAmount' => '8000.00',
        ], $rows[1]);
    }

    public function test_the_responsible_breakdown_keeps_the_work_without_a_responsible(): void
    {
        // Nullable para no perder el historial al borrar una cuenta: el
        // reporte nombra esa fila en vez de esconderla o descuadrar el total.
        $ana = User::factory()->create(['name' => 'Ana']);

        Opportunity::factory()->responsible($ana)->count(2)->create(['amount' => '1000.00']);
        Opportunity::factory()->create(['user_id' => null, 'amount' => '1000.00']);

        $rows = $this->prop($this->reportFor($this->admin()), 'byResponsible', 'opportunities');

        $this->assertCount(2, $rows);
        $this->assertSame($ana->id, $rows[0]['user_id']);
        $this->assertSame(2, $rows[0]['total']);

        $this->assertNull($rows[1]['user_id']);
        $this->assertSame(__('reports.unassigned'), $rows[1]['name']);
        $this->assertSame(1, $rows[1]['total']);
        $this->assertSame('1000.00', $rows[1]['openAmount']);

        // El encabezado cuenta lo mismo que la tabla.
        $this->assertSame(
            3,
            $this->prop($this->reportFor($this->admin()), 'opportunities')['total'],
        );
    }

    // -------------------------------------------------------------------
    // Cotizaciones
    // -------------------------------------------------------------------

    public function test_the_quote_block_counts_and_sums_every_status(): void
    {
        // Las cotizaciones sin partidas valen 0, asi que un reporte de montos
        // probado contra la factory vacia probaria el cero y no la suma.
        $byStatus = [
            'borrador' => Quote::factory()->withItems()->create(),
            'enviada' => Quote::factory()->sent()->withItems()->create(),
            'aceptada' => Quote::factory()->accepted()->withItems()->create(),
            'rechazada' => Quote::factory()->rejected()->withItems()->create(),
            'vencida' => Quote::factory()->status('vencida')->withItems()->create(),
        ];

        $this->assertNotSame(
            '0.00',
            $byStatus['aceptada']->total,
            'la factory debe producir un importe distinto de cero',
        );

        $expected = array_map(
            fn (string $status, Quote $quote): array => [
                'status' => $status,
                'label' => config('quotes.statuses')[$status],
                'count' => 1,
                'amount' => number_format((float) $quote->total, 2, '.', ''),
            ],
            array_keys(config('quotes.statuses')),
            array_values($byStatus),
        );

        $this->reportFor($this->admin())
            ->where('quotes.total', 5)
            ->where('quotes.totalAmount', number_format(
                array_sum(array_map(fn (Quote $q): float => (float) $q->total, array_values($byStatus))),
                2,
                '.',
                '',
            ))
            ->where('quotes.acceptedAmount', number_format((float) $byStatus['aceptada']->total, 2, '.', ''))
            ->where('byStatus.quotes', $expected);
    }

    public function test_a_status_with_no_quotes_appears_with_zero(): void
    {
        Quote::factory()->sent()->withItems()->create();

        $rows = $this->prop($this->reportFor($this->admin()), 'byStatus', 'quotes');

        $this->assertCount(count(config('quotes.statuses')), $rows);

        $byStatus = array_column($rows, null, 'status');

        $this->assertSame(1, $byStatus['enviada']['count']);
        $this->assertNotSame('0.00', $byStatus['enviada']['amount']);
        $this->assertSame(0, $byStatus['aceptada']['count']);
        $this->assertSame('0.00', $byStatus['aceptada']['amount']);
    }

    public function test_the_expired_quote_is_counted_on_the_column_and_not_on_its_validity(): void
    {
        // App\Models\Quote deriva "vencida" de la vigencia y el listado
        // filtra por la columna a propósito; el reporte cuenta lo mismo: una
        // enviada que caduca sola sigue esperando respuesta.
        $lapsed = Quote::factory()->sent()->expired()->withItems()->create();

        $this->assertSame('enviada', $lapsed->status);
        $this->assertTrue($lapsed->isExpired());

        $rows = $this->prop($this->reportFor($this->admin()), 'byStatus', 'quotes');
        $byStatus = array_column($rows, null, 'status');

        $this->assertSame(1, $byStatus['enviada']['count']);
        $this->assertSame(0, $byStatus['vencida']['count']);
    }

    public function test_the_quote_period_is_the_issue_date_and_not_the_row_creation(): void
    {
        $old = Quote::factory()->create(['issue_date' => '2026-05-04']);
        $old->forceFill(['created_at' => now()])->save();

        Quote::factory()->create(['issue_date' => '2026-06-15']);

        // Las dos existen desde hoy, asi que si el reporte leyera created_at
        // contaria las dos y el filtro de periodo no tendria sentido.
        $this->reportFor($this->admin(), ['date_from' => '2026-06-01', 'date_to' => '2026-06-30'])
            ->where('quotes.total', 1);
    }

    // -------------------------------------------------------------------
    // Prospectos
    // -------------------------------------------------------------------

    public function test_the_prospect_block_counts_every_status(): void
    {
        Prospect::factory()->count(2)->status('nuevo')->create();
        Prospect::factory()->count(3)->status('contactado')->create();
        Prospect::factory()->count(4)->status('calificado')->create();
        Prospect::factory()->count(5)->status('no_interesado')->create();
        Prospect::factory()->count(6)->converted()->create();

        $this->reportFor($this->admin())
            ->where('prospects.total', 20)
            ->where('prospects.converted', 6)
            ->where('prospects.open', 14)
            ->where('prospects.conversionRate', '30.00');
    }

    public function test_the_conversion_rate_is_zero_when_there_are_no_prospects(): void
    {
        $this->reportFor($this->admin())
            ->where('prospects.total', 0)
            ->where('prospects.converted', 0)
            ->where('prospects.open', 0)
            ->where('prospects.conversionRate', '0.00');
    }

    public function test_the_conversion_rate_is_rounded_to_two_decimals(): void
    {
        Prospect::factory()->converted()->create();
        Prospect::factory()->count(2)->create();

        // 1 de 3 es 33.3333... y el reporte entrega 33.33, no el entero mas
        // cercano ni una cadena con los decimales de la division.
        $this->reportFor($this->admin())
            ->where('prospects.conversionRate', '33.33');
    }

    public function test_the_prospect_status_breakdown_walks_the_catalog(): void
    {
        Prospect::factory()->converted()->create();

        $expected = array_map(
            fn (string $status): array => [
                'status' => $status,
                'label' => config('prospects.statuses')[$status],
                'count' => $status === 'convertido' ? 1 : 0,
            ],
            array_keys(config('prospects.statuses')),
        );

        $this->reportFor($this->admin())->where('byStatus.prospects', $expected);
    }

    public function test_the_prospects_are_also_grouped_by_responsible(): void
    {
        // Ultimo reporte de prospectos que pide el alcance: quien captura el
        // embudo y cuanto de lo suyo se convierte.
        $ana = User::factory()->create(['name' => 'Ana']);
        $luis = User::factory()->create(['name' => 'Luis']);

        Prospect::factory()->responsible($ana)->count(4)->create();
        Prospect::factory()->responsible($ana)->converted()->create();
        Prospect::factory()->responsible($luis)->count(2)->create();
        Prospect::factory()->responsible($luis)->create(['status' => 'no_interesado']);
        Prospect::factory()->create(['user_id' => null]);

        // "En el embudo" es el complemento de convertido, como el scope
        // open() del modelo, así que el descartado de Luis sigue contando;
        // se fija aquí para que reporte y listado no discrepen sobre "abierto".

        $rows = $this->prop($this->reportFor($this->admin()), 'byResponsible', 'prospects');

        $this->assertCount(3, $rows);

        $this->assertSame([
            'user_id' => $ana->id,
            'name' => 'Ana',
            'total' => 5,
            'open' => 4,
            'converted' => 1,
            'conversionRate' => '20.00',
        ], $rows[0]);

        $this->assertSame([
            'user_id' => $luis->id,
            'name' => 'Luis',
            'total' => 3,
            'open' => 3,
            'converted' => 0,
            'conversionRate' => '0.00',
        ], $rows[1]);

        $this->assertNull($rows[2]['user_id']);
        $this->assertSame(__('reports.unassigned'), $rows[2]['name']);
        $this->assertSame(1, $rows[2]['total']);
    }

    public function test_the_responsible_conversion_rate_uses_each_own_total(): void
    {
        // El denominador de cada fila es su propio total. Promediar las tasas de
        // un equipo con 1 de 1 y 0 de 9 daria 50% cuando ese equipo no capturo
        // nada, y por eso la tasa viaja calculada desde el backend.
        $ana = User::factory()->create(['name' => 'Ana']);
        $luis = User::factory()->create(['name' => 'Luis']);

        Prospect::factory()->responsible($ana)->converted()->create();
        Prospect::factory()->responsible($luis)->count(9)->create();

        $page = $this->reportFor($this->admin());

        // El resumen del equipo si promedia: 1 de 10 es 10%.
        $this->assertSame('10.00', $this->prop($page, 'prospects')['conversionRate']);

        $rates = array_column($this->prop($page, 'byResponsible', 'prospects'), 'conversionRate', 'name');

        $this->assertSame('100.00', $rates['Ana']);
        $this->assertSame('0.00', $rates['Luis']);
    }

    // -------------------------------------------------------------------
    // Actividades
    // -------------------------------------------------------------------

    public function test_the_activity_block_counts_only_the_pending_ones(): void
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $luis = User::factory()->create(['name' => 'Luis']);

        Activity::factory()->responsible($ana)->count(3)->status('pendiente')->create();
        Activity::factory()->responsible($luis)->count(2)->status('pendiente')->create();
        Activity::factory()->count(7)->status('completada')->create();
        Activity::factory()->count(4)->status('cancelada')->create();

        $page = $this->reportFor($this->admin())->where('activities.pending', 5);

        $this->assertSame([
            [
                'user_id' => $ana->id,
                'name' => 'Ana',
                'count' => 3,
            ],
            [
                'user_id' => $luis->id,
                'name' => 'Luis',
                'count' => 2,
            ],
        ], $this->prop($page, 'byResponsible', 'activities'));
    }

    public function test_the_pending_total_and_the_table_agree(): void
    {
        $ana = User::factory()->create(['name' => 'Ana']);

        Activity::factory()->responsible($ana)->count(4)->status('pendiente')->create();
        Activity::factory()->create(['user_id' => null, 'status' => 'pendiente']);

        $page = $this->reportFor($this->admin());

        $rows = $this->prop($page, 'byResponsible', 'activities');
        $sum = array_sum(array_column($rows, 'count'));

        // El encabezado y la tabla son la misma pregunta hecha de dos formas.
        $this->assertSame($this->prop($page, 'activities')['pending'], $sum);
        $this->assertCount(2, $rows);
    }

    // -------------------------------------------------------------------
    // Filtros
    // -------------------------------------------------------------------

    public function test_the_period_includes_both_whole_days(): void
    {
        // El error clasico de un reporte: un between de datetime deja fuera todo
        // lo que se creo el ultimo dia del periodo.
        $first = $this->opportunityOn('2026-10-01 00:00:00');
        $middle = $this->opportunityOn('2026-10-15 23:59:59');
        $last = $this->opportunityOn('2026-10-31 00:00:00');
        $before = $this->opportunityOn('2026-09-30 23:59:59');
        $after = $this->opportunityOn('2026-11-01 00:00:00');

        $page = $this->reportFor($this->admin(), [
            'date_from' => '2026-10-01',
            'date_to' => '2026-10-31',
        ]);

        $this->assertSame(3, $this->prop($page, 'opportunities')['total']);

        // Y el desglose por responsable, que es la otra lectura de los mismos
        // datos, tiene que contar exactamente lo mismo.
        $ids = array_column($this->prop($page, 'byResponsible', 'opportunities'), 'user_id');

        $this->assertContains($first->user_id, $ids);
        $this->assertContains($middle->user_id, $ids);
        $this->assertContains($last->user_id, $ids);
        $this->assertNotContains($before->user_id, $ids);
        $this->assertNotContains($after->user_id, $ids);
    }

    public function test_only_the_start_date_can_be_given(): void
    {
        $this->opportunityOn('2026-10-10 12:00:00');
        $this->opportunityOn('2026-09-10 12:00:00');

        $this->reportFor($this->admin(), ['date_from' => '2026-10-01'])
            ->where('opportunities.total', 1);
    }

    public function test_only_the_end_date_is_accepted(): void
    {
        // Un filtro con "hasta" y sin "desde" es valido, y la regla que compara
        // las dos fechas no debe inventarse un error cuando falta una de ellas.
        $this->opportunityOn('2026-10-10 12:00:00');
        $this->opportunityOn('2026-11-10 12:00:00');

        $this->reportFor($this->admin(), ['date_to' => '2026-10-31'])
            ->where('opportunities.total', 1)
            ->where('filters.date_to', '2026-10-31');
    }

    public function test_the_period_reaches_every_block(): void
    {
        $this->opportunityOn('2026-01-10 09:00:00');
        $this->opportunityOn('2026-10-10 09:00:00');

        Quote::factory()->create(['issue_date' => '2026-01-10']);
        Quote::factory()->create(['issue_date' => '2026-10-10']);

        $oldProspect = Prospect::factory()->create();
        $oldProspect->forceFill(['created_at' => '2026-01-10 09:00:00'])->save();
        $newProspect = Prospect::factory()->create();
        $newProspect->forceFill(['created_at' => '2026-10-10 09:00:00'])->save();

        Activity::factory()->on('2026-01-10 09:00:00')->status('pendiente')->create();
        Activity::factory()->on('2026-10-10 09:00:00')->status('pendiente')->create();

        $query = ['date_from' => '2026-10-01', 'date_to' => '2026-10-31'];
        $page = $this->reportFor($this->admin(), $query);

        // Un solo registro de cada bloque cae dentro del periodo, y el filtro no
        // se aplica a uno solo: los cuatro leen las mismas fechas.
        $this->assertSame(1, $this->prop($page, 'opportunities')['total']);
        $this->assertSame(1, $this->prop($page, 'quotes')['total']);
        $this->assertSame(1, $this->prop($page, 'prospects')['total']);
        $this->assertSame(1, $this->prop($page, 'activities')['pending']);
    }

    public function test_the_responsible_filter_applies_to_every_block(): void
    {
        $ana = User::factory()->create(['name' => 'Ana', 'is_active' => true]);
        $luis = User::factory()->create(['name' => 'Luis', 'is_active' => true]);

        Opportunity::factory()->responsible($ana)->create(['amount' => '1000.00']);
        Opportunity::factory()->responsible($luis)->won()->create(['amount' => '5000.00']);

        Quote::factory()->responsible($ana)->create();
        Quote::factory()->responsible($luis)->accepted()->create();

        Prospect::factory()->responsible($ana)->create();
        Prospect::factory()->responsible($luis)->converted()->create();

        Activity::factory()->responsible($ana)->status('pendiente')->create();
        Activity::factory()->responsible($luis)->status('pendiente')->create();

        $this->reportFor($this->admin(), ['user_id' => $ana->id])
            ->where('opportunities.total', 1)
            ->where('opportunities.openAmount', '1000.00')
            ->where('opportunities.won', 0)
            ->where('quotes.total', 1)
            ->where('prospects.total', 1)
            ->where('prospects.converted', 0)
            ->where('activities.pending', 1);

        // Y el desglose por responsable no inventa una fila para el otro.
        $this->assertSame(
            1,
            count($this->prop(
                $this->reportFor($this->admin(), ['user_id' => $ana->id]),
                'byResponsible',
                'opportunities',
            )),
        );
    }

    public function test_the_responsible_filter_is_echoed_back(): void
    {
        $ana = User::factory()->create(['name' => 'Ana', 'is_active' => true]);

        // El prop refleja lo que vino en la URL, y una URL siempre trae cadenas.
        $this->reportFor($this->admin(), ['user_id' => $ana->id])
            ->where('filters.user_id', (string) $ana->id);
    }

    public function test_a_date_in_the_wrong_format_is_refused(): void
    {
        $this->actingAs($this->admin())
            ->get('/reports?date_from=01-10-2026')
            ->assertSessionHasErrors('date_from');

        $this->actingAs($this->admin())
            ->get('/reports?date_to=octubre')
            ->assertSessionHasErrors('date_to');
    }

    public function test_a_period_that_ends_before_it_starts_is_refused(): void
    {
        $this->actingAs($this->admin())
            ->get('/reports?date_from=2026-10-31&date_to=2026-10-01')
            ->assertSessionHasErrors('date_to');
    }

    public function test_a_responsible_that_does_not_exist_is_refused(): void
    {
        $this->actingAs($this->admin())
            ->get('/reports?user_id=999999')
            ->assertSessionHasErrors('user_id');
    }

    public function test_the_filters_survive_in_the_url(): void
    {
        $this->reportFor($this->admin(), [
            'date_from' => '2026-10-01',
            'date_to' => '2026-10-31',
            'user_id' => 1,
        ])->where('filters', [
            'date_from' => '2026-10-01',
            'date_to' => '2026-10-31',
            'user_id' => '1',
        ]);
    }

    public function test_a_filter_that_was_not_given_is_absent_from_the_props(): void
    {
        // La vista inicializa sus campos desde el prop, asi que una clave
        // ausente se distingue de una cadena vacia.
        $this->reportFor($this->admin())
            ->where('filters', []);
    }

    public function test_the_responsible_dropdown_offers_the_active_accounts(): void
    {
        $ana = User::factory()->create(['name' => 'Ana', 'is_active' => true]);
        User::factory()->create(['name' => 'Inactivo', 'is_active' => false]);

        $users = $this->prop($this->reportFor($this->admin()), 'users');

        $this->assertContains($ana->id, array_column($users, 'id'));
        $this->assertNotContains('Inactivo', array_column($users, 'name'));
    }

    // -------------------------------------------------------------------
    // Base de datos vacía
    // -------------------------------------------------------------------

    public function test_the_reports_work_on_an_empty_database(): void
    {
        $this->reportFor($this->admin())
            ->where('opportunities.total', 0)
            ->where('opportunities.open', 0)
            ->where('opportunities.won', 0)
            ->where('opportunities.lost', 0)
            ->where('opportunities.openAmount', '0.00')
            ->where('opportunities.wonAmount', '0.00')
            ->where('quotes.total', 0)
            ->where('quotes.totalAmount', '0.00')
            ->where('quotes.acceptedAmount', '0.00')
            ->where('prospects.total', 0)
            ->where('prospects.converted', 0)
            ->where('prospects.open', 0)
            ->where('prospects.conversionRate', '0.00')
            ->where('activities.pending', 0)
            ->where('byResponsible.opportunities', [])
            ->where('byResponsible.prospects', [])
            ->where('byResponsible.activities', []);
    }

    // -------------------------------------------------------------------
    // Aislamiento entre módulos
    // -------------------------------------------------------------------

    public function test_a_module_the_account_cannot_consult_arrives_as_null(): void
    {
        Opportunity::factory()->won()->create(['amount' => '100000.00']);
        Quote::factory()->accepted()->create();
        Prospect::factory()->converted()->create();
        Activity::factory()->status('pendiente')->create();

        $page = $this->reportFor($this->userWithAllPermissionsExcept('quotes.view'));

        $this->assertNull($this->prop($page, 'quotes'));
        $this->assertNull($this->prop($page, 'byStatus', 'quotes'));
        $this->assertNotNull($this->prop($page, 'opportunities'));
        $this->assertNotNull($this->prop($page, 'prospects'));
        $this->assertNotNull($this->prop($page, 'activities'));
    }

    public function test_the_report_never_shows_more_than_the_listing_would(): void
    {
        Opportunity::factory()->won()->create(['amount' => '100000.00']);
        Prospect::factory()->create(['name' => 'Prospecto que no se debe ver']);

        $onlyProspects = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $onlyProspects->givePermissionTo('reports.view', 'prospects.view');

        $this->actingAs($onlyProspects)
            ->get('/reports')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('opportunities', null)
                ->where('quotes', null)
                ->where('activities', null)
                ->where('byStage', null)
            )
            ->assertDontSee('100000.00')
            ->assertDontSee('Prospecto que no se debe ver');
    }

    public function test_the_report_permission_alone_shows_an_empty_page(): void
    {
        Opportunity::factory()->count(2)->create();

        $onlyReports = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $onlyReports->givePermissionTo('reports.view');

        $page = $this->reportFor($onlyReports);

        $this->assertNull($this->prop($page, 'opportunities'));
        $this->assertNull($this->prop($page, 'quotes'));
        $this->assertNull($this->prop($page, 'prospects'));
        $this->assertNull($this->prop($page, 'activities'));
    }

    // -------------------------------------------------------------------
    // Auxiliares
    // -------------------------------------------------------------------

    /**
     * Oportunidad con fecha de alta fija. El responsable se crea propio para que
     * cada oportunidad sea una fila distinta de la tabla por responsable.
     */
    private function opportunityOn(string $createdAt): Opportunity
    {
        $opportunity = Opportunity::factory()->create();

        $opportunity->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        return $opportunity->fresh();
    }
}
