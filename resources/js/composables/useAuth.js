import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Punto único de acceso al usuario autenticado; layout y navegación leen de
 * aquí para describir las shared props en un solo lugar.
 *
 * Los permisos no se exponen aquí: se consultan por nombre con
 * useAuthorization, así que no hay que sumar un booleano por cada acción.
 */
export function useAuth() {
    const page = usePage();

    const user = computed(() => page.props.auth.user);
    const roles = computed(() => page.props.auth.roles ?? []);

    const initials = computed(() =>
        (user.value?.name ?? '')
            .split(' ')
            .filter(Boolean)
            .slice(0, 2)
            .map((part) => part[0].toUpperCase())
            .join(''),
    );

    return { user, roles, initials };
}
