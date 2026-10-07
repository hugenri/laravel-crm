<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import Badge from '../Components/ui/Badge.vue';
import Button from '../Components/ui/Button.vue';
import Card from '../Components/ui/Card.vue';
import StatCard from '../Components/ui/StatCard.vue';
import StatusBadge from '../Components/ui/StatusBadge.vue';
import { useAuthorization } from '../composables/useAuthorization';
import { formatCurrency, formatDate, formatDateTime } from '../lib/format';
import { routes } from '../lib/routes';

defineOptions({ layout: AppLayout });

/**
 * Cada bloque llega como null sin el permiso de consulta del módulo y el v-if
 * evita dibujarlo: no hay contadores globales que delaten lo oculto.
 */
const props = defineProps({
    account: { type: Object, required: true },
    clients: { type: Object, default: null },
    opportunities: { type: Object, default: null },
    activities: { type: Object, default: null },
    prospects: { type: Object, default: null },
    quotes: { type: Object, default: null },
});

const { can } = useAuthorization();

const firstName = computed(() => props.account.name.split(' ')[0]);

/**
 * Ancho de la barra de una etapa, en % del total de abiertas. Es la única
 * representación gráfica del panel y va con estilos, sin librería de gráficas.
 */
const stageRows = computed(() => {
    const rows = props.opportunities?.byStage ?? [];
    const highest = Math.max(1, ...rows.map((row) => row.count));

    return rows.map((row) => ({
        ...row,
        width: row.count === 0 ? 0 : Math.max(8, Math.round((row.count / highest) * 100)),
    }));
});

function activityTone(status) {
    if (status === 'completada') {
        return 'green';
    }

    if (status === 'cancelada') {
        return 'red';
    }

    return 'amber';
}
</script>

<template>
    <Head title="Panel" />

    <div class="mb-6">
        <h1 class="text-xl font-semibold tracking-tight text-slate-900">
            Hola, {{ firstName }}
        </h1>
        <p class="mt-1 text-sm text-slate-500">
            Resumen operativo del CRM a la fecha de hoy.
        </p>
    </div>

    <!--
        Los indicadores van por módulo y cada grupo aparece solo si hay permiso.
        Una venta es una oportunidad ganada, así que comparten grupo en vez de
        repetirse en un bloque propio.
    -->
    <div class="space-y-5">
        <section v-if="clients">
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500">Clientes</h2>

            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label="Activos" :value="clients.active" tone="green" />
                <StatCard label="Inactivos" :value="clients.inactive" />
            </div>
        </section>

        <section v-if="opportunities">
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500">
                Oportunidades y ventas
            </h2>

            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label="Abiertas" :value="opportunities.open" tone="brand" />
                <StatCard label="Ventas ganadas" :value="opportunities.won" tone="green" />
                <StatCard label="Perdidas" :value="opportunities.lost" tone="red" />
                <StatCard
                    label="Monto de ventas ganadas"
                    :value="formatCurrency(opportunities.wonAmount)"
                />
            </div>
        </section>

        <!--
            Las cotizaciones van junto a las oportunidades porque una propuesta
            nace de una oportunidad abierta, y es el único indicador que pide el
            alcance del módulo: cuántas siguen esperando respuesta.
        -->
        <section v-if="quotes">
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500">
                Cotizaciones
            </h2>

            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label="Pendientes" :value="quotes.pending" tone="amber" />
            </div>
        </section>

        <!--
            El bloque llega null sin activities.view, así que el enlace al
            calendario se esconde con él. Sin indicadores propios: el calendario
            ya muestra estas mismas actividades y un conteo repetiría el número.
        -->
        <section v-if="activities">
            <div class="mb-2 flex items-center justify-between gap-3">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Actividades</h2>

                <Link :href="routes.calendar.index()">
                    <Button type="button" variant="ghost" size="sm">Ver calendario</Button>
                </Link>
            </div>

            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label="Pendientes" :value="activities.pending" tone="amber" />
                <StatCard label="Completadas" :value="activities.completed" tone="green" />
                <StatCard label="Canceladas" :value="activities.cancelled" tone="red" />
            </div>
        </section>

        <!--
            El embudo va primero, antes que la cartera: los prospectos son lo que
            aún no es cliente. El bloque llega null sin prospects.view, como los
            demás.
        -->
        <section v-if="prospects">
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500">
                Prospectos
            </h2>

            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label="Nuevos" :value="prospects.new" tone="brand" />
                <StatCard label="En el embudo" :value="prospects.open" />
                <StatCard label="Calificados" :value="prospects.qualified" tone="green" />
            </div>
        </section>
    </div>

    <div class="mt-5 grid gap-5 lg:grid-cols-2">
        <Card
            v-if="opportunities"
            title="Oportunidades abiertas"
            description="Las que cierran primero."
        >
            <template #header>
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Oportunidades abiertas</h2>
                        <p class="mt-0.5 text-sm text-slate-500">
                            Las {{ opportunities.openList.length }} de cierre más próximo.
                        </p>
                    </div>

                    <Link v-if="can('opportunities.view')" :href="routes.opportunities.index()">
                        <Button type="button" variant="secondary" size="sm">Ver todas</Button>
                    </Link>
                </div>
            </template>

            <div v-if="opportunities.openList.length" class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Oportunidad</th>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Responsable</th>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Monto</th>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Etapa</th>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Cierre</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="opportunity in opportunities.openList" :key="opportunity.id">
                            <td class="px-3 py-3 text-sm">
                                <Link
                                    :href="routes.opportunities.show(opportunity.id)"
                                    class="font-medium text-slate-900 hover:text-brand-700"
                                >
                                    {{ opportunity.name }}
                                </Link>
                                <p class="text-xs text-slate-500">{{ opportunity.client?.name ?? '—' }}</p>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-sm text-slate-600">
                                {{ opportunity.user?.name ?? '—' }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-sm text-slate-600">
                                {{ formatCurrency(opportunity.amount) }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-sm text-slate-600">
                                {{ opportunity.stage_label ?? opportunity.stage }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-sm text-slate-600">
                                {{ formatDate(opportunity.expected_close_date) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p v-else class="py-6 text-center text-sm text-slate-500">
                No hay oportunidades abiertas.
            </p>
        </Card>

        <Card
            v-if="opportunities"
            title="Oportunidades por etapa"
            description="Solo oportunidades abiertas."
        >
            <ul v-if="stageRows.length" class="space-y-3">
                <li v-for="row in stageRows" :key="row.stage" class="flex items-center gap-3">
                    <span class="w-28 shrink-0 truncate text-sm text-slate-600">{{ row.label }}</span>
                    <span class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                        <span
                            class="block h-full rounded-full bg-brand-500"
                            :style="{ width: `${row.width}%` }"
                        />
                    </span>
                    <span class="w-8 shrink-0 text-right text-sm font-medium text-slate-900">
                        {{ row.count }}
                    </span>
                </li>
            </ul>

            <p v-else class="py-6 text-center text-sm text-slate-500">
                No hay etapas configuradas.
            </p>
        </Card>

        <Card
            v-if="activities"
            title="Actividades pendientes"
            description="Las que llevan más tiempo sin resolverse."
        >
            <template #header>
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Actividades pendientes</h2>
                        <p class="mt-0.5 text-sm text-slate-500">
                            Pendientes con la fecha más antigua primero.
                        </p>
                    </div>

                    <Link v-if="can('activities.view')" :href="routes.activities.index()">
                        <Button type="button" variant="secondary" size="sm">Ver todas</Button>
                    </Link>
                </div>
            </template>

            <div v-if="activities.pendingList.length" class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Fecha</th>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Tipo</th>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Cliente</th>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Seguimiento</th>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Responsable</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="activity in activities.pendingList" :key="activity.id">
                            <td class="whitespace-nowrap px-3 py-3 text-sm text-slate-600">
                                {{ formatDateTime(activity.activity_date) }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-sm text-slate-900">
                                {{ activity.type_label ?? activity.type }}
                            </td>
                            <td class="px-3 py-3 text-sm">
                                <Link
                                    :href="routes.clients.show(activity.client.id)"
                                    class="text-slate-900 hover:text-brand-700"
                                >
                                    {{ activity.client?.name ?? '—' }}
                                </Link>
                                <p v-if="activity.opportunity" class="text-xs text-slate-500">
                                    {{ activity.opportunity.name }}
                                </p>
                            </td>
                            <td class="px-3 py-3 text-sm text-slate-600">
                                <p class="line-clamp-2">{{ activity.description }}</p>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-sm text-slate-600">
                                {{ activity.user?.name ?? '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p v-else class="py-6 text-center text-sm text-slate-500">
                No hay actividades pendientes.
            </p>
        </Card>

        <Card
            v-if="activities"
            title="Actividad reciente"
            description="Últimos seguimientos registrados."
        >
            <template #header>
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Actividad reciente</h2>
                        <p class="mt-0.5 text-sm text-slate-500">
                            Seguimientos más recientes, en cualquier estado.
                        </p>
                    </div>

                    <Link v-if="can('activities.view')" :href="routes.activities.index()">
                        <Button type="button" variant="secondary" size="sm">Ver todas</Button>
                    </Link>
                </div>
            </template>

            <div v-if="activities.recent.length" class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Fecha</th>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Tipo</th>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Cliente</th>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Responsable</th>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Estado</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="activity in activities.recent" :key="activity.id">
                            <td class="whitespace-nowrap px-3 py-3 text-sm text-slate-600">
                                {{ formatDateTime(activity.activity_date) }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-sm text-slate-900">
                                {{ activity.type_label ?? activity.type }}
                            </td>
                            <td class="px-3 py-3 text-sm">
                                <Link
                                    :href="routes.clients.show(activity.client.id)"
                                    class="text-slate-900 hover:text-brand-700"
                                >
                                    {{ activity.client?.name ?? '—' }}
                                </Link>
                                <p class="line-clamp-1 text-xs text-slate-500">
                                    {{ activity.description }}
                                </p>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-sm text-slate-600">
                                {{ activity.user?.name ?? '—' }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-3">
                                <Badge :tone="activityTone(activity.status)">
                                    {{ activity.status_label ?? activity.status }}
                                </Badge>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p v-else class="py-6 text-center text-sm text-slate-500">
                Todavía no hay actividades registradas.
            </p>
        </Card>

        <Card
            v-if="clients"
            title="Clientes recientes"
            description="Últimos clientes registrados."
        >
            <template #header>
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Clientes recientes</h2>
                        <p class="mt-0.5 text-sm text-slate-500">
                            Los últimos clientes dados de alta.
                        </p>
                    </div>

                    <Link v-if="can('clients.view')" :href="routes.clients.index()">
                        <Button type="button" variant="secondary" size="sm">Ver todos</Button>
                    </Link>
                </div>
            </template>

            <div v-if="clients.recent.length" class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Cliente</th>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Tipo</th>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Estado</th>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Registro</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="client in clients.recent" :key="client.id">
                            <td class="px-3 py-3 text-sm">
                                <Link
                                    :href="routes.clients.show(client.id)"
                                    class="font-medium text-slate-900 hover:text-brand-700"
                                >
                                    {{ client.name }}
                                </Link>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-sm text-slate-600">
                                {{ client.type_label ?? client.type }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-3">
                                <StatusBadge :is-active="client.status === 'activo'" />
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-sm text-slate-600">
                                {{ formatDate(client.created_at) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p v-else class="py-6 text-center text-sm text-slate-500">
                Todavía no hay clientes registrados.
            </p>
        </Card>
    </div>

    <div class="mt-5 grid gap-5 lg:grid-cols-3">
        <Card class="lg:col-span-1" title="Estado de la cuenta">
            <dl class="space-y-4">
                <div class="flex items-center justify-between gap-4">
                    <dt class="text-sm text-slate-500">Situación</dt>
                    <dd><StatusBadge :is-active="account.is_active" /></dd>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <dt class="text-sm text-slate-500">Correo electrónico</dt>
                    <dd class="text-sm font-medium text-slate-900">{{ account.email }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <dt class="text-sm text-slate-500">Verificación</dt>
                    <dd>
                        <span
                            class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
                            :class="account.email_verified
                                ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                                : 'bg-amber-50 text-amber-800 ring-amber-200'"
                        >
                            {{ account.email_verified ? 'Verificado' : 'Sin verificar' }}
                        </span>
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <dt class="text-sm text-slate-500">Fecha de registro</dt>
                    <dd class="text-sm font-medium text-slate-900">{{ formatDate(account.registered_at) }}</dd>
                </div>
            </dl>
        </Card>

        <Card class="lg:col-span-2" title="Accesos rápidos">
            <div class="grid gap-4 sm:grid-cols-2">
                <Link
                    :href="routes.profile.edit()"
                    class="rounded-lg border border-slate-200 p-4 transition hover:border-brand-300 hover:bg-brand-50/40"
                >
                    <p class="text-sm font-semibold text-slate-900">Mi perfil</p>
                    <p class="mt-1 text-sm text-slate-500">Actualiza tus datos y tu contraseña.</p>
                </Link>

                <div
                    v-if="can('prospects.view')"
                    class="rounded-lg border border-slate-200 p-4"
                >
                    <p class="text-sm font-semibold text-slate-900">Prospectos nuevos</p>
                    <p class="mt-1 text-sm text-slate-500">
                        Embudo en estado inicial, pendientes de primer contacto.
                    </p>
                    <Link
                        :href="routes.prospects.index({ status: 'nuevo' })"
                        class="mt-3 inline-block text-sm font-medium text-brand-700 hover:text-brand-800"
                    >
                        Ver prospectos nuevos
                    </Link>
                </div>

                <div
                    v-if="can('quotes.view')"
                    class="rounded-lg border border-slate-200 p-4"
                >
                    <p class="text-sm font-semibold text-slate-900">Cotizaciones</p>
                    <p class="mt-1 text-sm text-slate-500">
                        Propuestas registradas y su estado frente al cliente.
                    </p>
                    <Link
                        :href="routes.quotes.index()"
                        class="mt-3 inline-block text-sm font-medium text-brand-700 hover:text-brand-800"
                    >
                        Ver cotizaciones
                    </Link>
                </div>

                <!--
                    El enlace a reportes es el único que se agrega al panel: sin
                    él habría que llegar por URL. No se agregan indicadores, ya
                    que los mismos números viven allá.
                -->
                <div
                    v-if="can('reports.view')"
                    class="rounded-lg border border-slate-200 p-4"
                >
                    <p class="text-sm font-semibold text-slate-900">Reportes</p>
                    <p class="mt-1 text-sm text-slate-500">
                        Oportunidades, cotizaciones, prospectos y actividades por periodo y responsable.
                    </p>
                    <Link
                        :href="routes.reports.index()"
                        class="mt-3 inline-block text-sm font-medium text-brand-700 hover:text-brand-800"
                    >
                        Ver reportes
                    </Link>
                </div>

                <div
                    v-if="can('users.view')"
                    class="rounded-lg border border-slate-200 p-4"
                >
                    <p class="text-sm font-semibold text-slate-900">Administración de usuarios</p>
                    <p class="mt-1 text-sm text-slate-500">
                        Gestiona las cuentas del sistema y sus permisos de acceso.
                    </p>
                    <Link
                        :href="routes.users.index()"
                        class="mt-3 inline-block text-sm font-medium text-brand-700 hover:text-brand-800"
                    >
                        Ver usuarios
                    </Link>
                </div>

                <div
                    v-if="can('roles.view')"
                    class="rounded-lg border border-slate-200 p-4"
                >
                    <p class="text-sm font-semibold text-slate-900">Roles y permisos</p>
                    <p class="mt-1 text-sm text-slate-500">
                        Define qué puede hacer cada rol dentro del CRM.
                    </p>
                    <Link
                        :href="routes.roles.index()"
                        class="mt-3 inline-block text-sm font-medium text-brand-700 hover:text-brand-800"
                    >
                        Ver roles
                    </Link>
                </div>
            </div>
        </Card>
    </div>
</template>