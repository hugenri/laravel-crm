<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useAuthorization } from '../composables/useAuthorization';
import { routes } from '../lib/routes';

const page = usePage();
const { can } = useAuthorization();

/**
 * Cada enlace se controla por permiso, no por rol. El backend valida
 * los permisos en cada petición, por lo que el menú oculta lo que
 * el servidor rechazaría.
 */
const sections = computed(() => {
    const usersPath = routes.users.index();
    const rolesPath = routes.roles.index();
    const clientsPath = routes.clients.index();
    const contactsPath = routes.contacts.index();
    const opportunitiesPath = routes.opportunities.index();
    const activitiesPath = routes.activities.index();
    const calendarPath = routes.calendar.index();
    const reportsPath = routes.reports.index();
    const prospectsPath = routes.prospects.index();
    const quotesPath = routes.quotes.index();

    return [
        {
            items: [
                {
                    label: 'Panel',
                    href: routes.dashboard(),
                    active: page.url === routes.dashboard(),
                },
            ],
        },
        {
            label: 'CRM',
            items: [
                {
                    label: 'Clientes',
                    href: clientsPath,
                    active: isActive(page.url, clientsPath),
                    visible: can('clients.view'),
                },
                {
                    label: 'Contactos',
                    href: contactsPath,
                    active: isActive(page.url, contactsPath),
                    visible: can('contacts.view'),
                },
                {
                    label: 'Oportunidades',
                    href: opportunitiesPath,
                    active: isActive(page.url, opportunitiesPath),
                    visible: can('opportunities.view'),
                },
                {
                    // Prospectos antes que clientes.
                    label: 'Prospectos',
                    href: prospectsPath,
                    active: isActive(page.url, prospectsPath),
                    visible: can('prospects.view'),
                },
                {
                    // Cotizaciones tras las oportunidades.
                    label: 'Cotizaciones',
                    href: quotesPath,
                    active: isActive(page.url, quotesPath),
                    visible: can('quotes.view'),
                },
                {
                    label: 'Actividades',
                    href: activitiesPath,
                    active: isActive(page.url, activitiesPath),
                    visible: can('activities.view'),
                },
                {
                    // Usa el mismo permiso que actividades.
                    label: 'Calendario',
                    href: calendarPath,
                    active: isActive(page.url, calendarPath),
                    visible: can('activities.view'),
                },
            ],
        },
        {
            // Sección aparte porque resume varios módulos.
            label: 'Reportes',
            items: [
                {
                    label: 'Reportes',
                    href: reportsPath,
                    active: isActive(page.url, reportsPath),
                    visible: can('reports.view'),
                },
            ],
        },
        {
            label: 'Administración',
            // Solo se muestra si hay ítems visibles.
            items: [
                {
                    label: 'Usuarios',
                    href: usersPath,
                    active: isActive(page.url, usersPath),
                    visible: can('users.view'),
                },
                {
                    label: 'Roles y permisos',
                    href: rolesPath,
                    active: isActive(page.url, rolesPath),
                    visible: can('roles.view'),
                },
            ],
        },
    ]
        .map((section) => ({
            ...section,
            items: section.items.filter((item) => item.visible !== false),
        }))
        .filter((section) => section.items.length > 0);
});

/**
 * Ruta activa: coincide con la ruta exacta, rutas anidadas o con query.
 */
function isActive(current, path) {
    return (
        current === path
        || current.startsWith(`${path}/`)
        || current.startsWith(`${path}?`)
    );
}
</script>

<template>
    <nav class="flex flex-col gap-4 p-3">
        <div
            v-for="section in sections"
            :key="section.label ?? 'main'"
            class="flex flex-col gap-1"
        >
            <p
                v-if="section.label"
                class="px-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-slate-500"
            >
                {{ section.label }}
            </p>

            <Link
                v-for="item in section.items"
                :key="item.href"
                :href="item.href"
                class="rounded-lg px-3 py-2 text-sm font-medium text-slate-300 transition hover:bg-slate-800 hover:text-white"
                :class="item.active ? 'bg-brand-600 text-white hover:bg-brand-600' : ''"
            >
                {{ item.label }}
            </Link>
        </div>
    </nav>
</template>
