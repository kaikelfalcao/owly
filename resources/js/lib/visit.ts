import { router } from '@inertiajs/react';
import type { VisitOptions } from '@inertiajs/core';

type RouteDefinition = { url: string; method: string };

/** Ação sem formulário (botão que apaga, marca, troca) numa rota do Wayfinder. */
export function send(
    route: RouteDefinition,
    options: Omit<VisitOptions, 'method'> = {},
): void {
    router.visit(route.url, {
        preserveScroll: true,
        ...options,
        method: route.method as VisitOptions['method'],
    });
}
