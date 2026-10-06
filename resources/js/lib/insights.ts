import { dashboard } from '@/routes';
import { show as insight } from '@/routes/insights';
import type { BreadcrumbItem } from '@/types';

/**
 * Nome de cada leitura do painel, igual ao de Dashboard::INSIGHTS (um teste
 * confere). Serve à trilha da conversa aberta a partir de uma leitura.
 */
export const INSIGHT_TITLES: Record<string, string> = {
    'sem-resposta': 'Clientes sem resposta',
    'tempo-de-resposta': 'Tempo até responder',
    'fora-do-horario': 'Chegaram fora do horário',
    'orcamentos-parados': 'Orçamentos parados',
    vendas: 'Vendas prováveis',
    procuram: 'O que os clientes procuram',
    vendedora: 'Conversas da vendedora',
};

/** Onde a pessoa estava no painel: a leitura, o período e o filtro. */
export type InsightOrigin = {
    painel: string;
    periodo?: string;
    filtro?: string;
};

/** Painel › leitura, com o mesmo período e filtro. */
export function insightCrumbs(origin: InsightOrigin): BreadcrumbItem[] {
    const periodo = origin.periodo ? { periodo: origin.periodo } : {};
    const title =
        origin.painel === 'vendedora' && origin.filtro
            ? `Conversas de ${origin.filtro}`
            : (INSIGHT_TITLES[origin.painel] ?? 'Leitura');

    return [
        { title: 'Painel', href: dashboard({ query: periodo }) },
        {
            title,
            href: insight(origin.painel, {
                query: {
                    ...periodo,
                    ...(origin.filtro ? { filtro: origin.filtro } : {}),
                },
            }),
        },
    ];
}
