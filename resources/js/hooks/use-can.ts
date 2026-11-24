import { usePage } from '@inertiajs/react';

/**
 * Indica si el usuario actual tiene un permiso. Solo sirve para mostrar u
 * ocultar elementos: la autorización real se valida en el servidor.
 */
export function useCan(): (permission: string) => boolean {
    const { auth } = usePage().props;
    const permissions = new Set(auth?.permissions ?? []);

    return (permission: string) => permissions.has(permission);
}
