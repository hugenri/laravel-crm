import { computed, unref } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Lee permisos del servidor (HandleInertiaRequests) y expone `can`/`canAny`.
 * Solo para UI; la autorización real está en servidor.
 */
export function useAuthorization() {
    const page = usePage();

    const granted = computed(() => page.props.auth?.permissions ?? []);

    /**
     * Cierto si el usuario tiene todos los permisos pedidos; un array se lee
     * como "todos".
     *
     * @param {string|string[]} permissions
     */
    function can(permissions) {
        const names = granted.value;

        return toList(permissions).every((permission) => names.includes(permission));
    }

    /**
     * Cierto si el usuario tiene al menos uno de los permisos pedidos.
     *
     * @param {string|string[]} permissions
     */
    function canAny(permissions) {
        const names = granted.value;

        return toList(permissions).some((permission) => names.includes(permission));
    }

    return {
        can,
        canAny,
        roles: computed(() => page.props.auth?.roles ?? []),
    };
}

function toList(permissions) {
    return (Array.isArray(permissions) ? permissions : [permissions]).filter(Boolean);
}
