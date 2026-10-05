import { Head, Link, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { date, dateTime, duration } from '@/lib/time';
import { dashboard } from '@/routes';
import { show as conversation } from '@/routes/conversations';
import { show } from '@/routes/insights';

type Row = {
    id: number;
    contact: string;
    phone: string | null;
    lastMessageAt: string | null;
    at?: string;
    seconds?: number;
    seller?: string | null;
    silentSince?: string;
};

type Props = {
    insight: string;
    title: string;
    filter: string | null;
    period: { key: string; since: string | null; until: string };
    conversations: Row[];
    total: number;
};

/** O que cada leitura explica no topo da lista. */
const EXPLAIN: Record<string, string> = {
    'sem-resposta':
        'A última mensagem é do cliente e ninguém respondeu. O tempo conta só as horas em que a empresa está aberta.',
    'tempo-de-resposta':
        'A maior espera de cada conversa no período, contando só as horas em que a empresa está aberta.',
    'fora-do-horario':
        'Conversas em que o cliente escreveu com a empresa fechada.',
    'orcamentos-parados':
        'O cliente recebeu o preço e não respondeu mais. Vale um retorno.',
    vendas: 'O cliente disse que fechou: “pode fazer”, “fiz o pix”, mandou o comprovante.',
    procuram: 'Conversas em que o cliente falou desse produto.',
    vendedora: 'Conversas em que ela mandou mensagem no período.',
};

const PERIOD_LABEL: Record<string, string> = {
    '7': 'últimos 7 dias',
    '30': 'últimos 30 dias',
    tudo: 'todo o histórico',
};

/** A lista de conversas por trás de um número do painel. */
export default function InsightShow(props: Props) {
    const timeZone = usePage().props.auth.organization?.timezone;
    const { conversations, total, period } = props;

    return (
        <>
            <Head title={props.title} />
            <div className="mx-auto flex w-full max-w-4xl flex-col gap-4 p-4 md:p-6">
                <div>
                    <h1 className="text-xl font-semibold">{props.title}</h1>
                    <p className="text-sm text-muted-foreground">
                        {EXPLAIN[props.insight]}{' '}
                        <span className="whitespace-nowrap">
                            {total.toLocaleString('pt-BR')}{' '}
                            {total === 1 ? 'conversa' : 'conversas'},{' '}
                            {PERIOD_LABEL[period.key] ?? period.key}.
                        </span>
                    </p>
                </div>

                {conversations.length === 0 ? (
                    <p className="rounded-2xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                        Nenhuma conversa neste período.
                    </p>
                ) : (
                    <ul className="divide-y rounded-2xl border">
                        {conversations.map((row) => (
                            <li key={row.id}>
                                <Link
                                    href={conversation(row.id)}
                                    className="flex items-center gap-3 px-4 py-3 transition-colors hover:bg-muted/50"
                                >
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-medium">
                                            {row.contact}
                                        </p>
                                        <p className="truncate text-xs text-muted-foreground">
                                            {detail(
                                                props.insight,
                                                row,
                                                timeZone,
                                            )}
                                        </p>
                                    </div>
                                    {row.seconds !== undefined && (
                                        <span
                                            className="shrink-0 font-mono text-sm tabular-nums"
                                            title="Só horas com a empresa aberta"
                                        >
                                            {duration(row.seconds)}
                                        </span>
                                    )}
                                    <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}

                {total > conversations.length && (
                    <p className="text-xs text-muted-foreground">
                        Mostrando as {conversations.length} primeiras de{' '}
                        {total.toLocaleString('pt-BR')}.
                    </p>
                )}
            </div>
        </>
    );
}

function detail(insight: string, row: Row, timeZone?: string): string {
    const at = row.at ? dateTime(row.at, timeZone) : null;

    switch (insight) {
        case 'sem-resposta':
            return `Escreveu em ${at}`;
        case 'tempo-de-resposta':
            return `Escreveu em ${at}${row.seller ? ` · respondida por ${row.seller}` : ''}`;
        case 'fora-do-horario':
            return `Escreveu em ${at}`;
        case 'orcamentos-parados':
            return `Orçamento em ${at}${row.silentSince ? ` · calado desde ${date(row.silentSince, timeZone)}` : ''}`;
        case 'vendas':
            return `Fechou em ${at}`;
        default:
            return row.lastMessageAt
                ? `Última mensagem em ${dateTime(row.lastMessageAt, timeZone)}`
                : (row.phone ?? '');
    }
}

InsightShow.layout = (props: Props) => ({
    breadcrumbs: [
        {
            title: 'Painel',
            href: dashboard({ query: { periodo: props.period.key } }),
        },
        {
            title: props.title,
            href: show(props.insight, {
                query: {
                    periodo: props.period.key,
                    ...(props.filter ? { filtro: props.filter } : {}),
                },
            }),
        },
    ],
});
