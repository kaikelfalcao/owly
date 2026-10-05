import { Head, Link, router, usePage } from '@inertiajs/react';
import { AlertCircle, Download, History, SearchX, X } from 'lucide-react';
import { useState } from 'react';
import { ActivityDetails } from '@/components/activity/activity-details';
import {
    ActivityFilters,
    ClearFilters,
    hasFilters,
} from '@/components/activity/activity-filters';
import {
    ResultLabel,
    SeverityBadge,
    resourceText,
    userText,
} from '@/components/activity/activity-labels';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dateTime } from '@/lib/time';
import { cn } from '@/lib/utils';
import { exportMethod, index } from '@/routes/activity';
import type {
    ActivityEntry,
    ActivityFilters as Filters,
    ActivityOptions,
    Paginated,
} from '@/types';

type Props = {
    entries: Paginated<ActivityEntry>;
    filters: Filters;
    options: ActivityOptions;
    timezone: string;
};

const RELOAD = ['entries', 'filters'];

/**
 * Minha conta › Atividade: a auditoria da empresa para investigar. Tabela
 * com filtros no servidor, detalhes num painel lateral e exportação do que
 * foi filtrado.
 */
export default function Activity({
    entries,
    filters,
    options,
    timezone,
}: Props) {
    const errors = usePage().props.errors as Partial<Record<string, string>>;
    const [selected, setSelected] = useState<ActivityEntry | null>(null);
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);
    // Trocar a chave remonta os filtros e limpa o que estava digitado.
    const [resetKey, setResetKey] = useState(0);

    const visit = {
        preserveState: true,
        preserveScroll: true,
        only: RELOAD,
        onStart: () => {
            setLoading(true);
            setFailed(false);
        },
        onFinish: () => setLoading(false),
        onHttpException: () => {
            setFailed(true);

            return false;
        },
        onNetworkError: () => {
            setFailed(true);

            return false;
        },
    };

    // `reset`: depois da resposta, remonta os filtros com o que veio do
    // servidor (limpa a busca digitada, as datas e o período escolhido).
    const apply = (next: Filters, reset = false) => {
        const query = Object.fromEntries(
            Object.entries(next).filter(([, value]) => !!value),
        );

        router.get(index.url(), query, {
            ...visit,
            replace: true,
            onSuccess: () => reset && setResetKey((key) => key + 1),
        });
    };

    const clear = () => apply({}, true);

    const filtered = hasFilters(filters);
    const resourceEntry =
        filters.resource_id &&
        entries.data.find(
            (entry) => String(entry.resource?.id) === filters.resource_id,
        );

    return (
        <>
            <Head title="Atividade" />

            <h1 className="sr-only">Atividade</h1>

            <div className="space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading
                        variant="small"
                        title="Atividade"
                        description="Quem fez o quê, quando e de onde, na empresa toda"
                    />
                    {entries.total > 0 ? (
                        <Button variant="outline" size="sm" asChild>
                            <a
                                href={exportMethod.url({ query: filters })}
                                download
                            >
                                <Download />
                                Exportar planilha
                            </a>
                        </Button>
                    ) : (
                        <Button variant="outline" size="sm" disabled>
                            <Download />
                            Exportar planilha
                        </Button>
                    )}
                </div>

                <ActivityFilters
                    key={resetKey}
                    filters={filters}
                    options={options}
                    errors={errors}
                    onChange={apply}
                />

                {filters.resource && filters.resource_id && (
                    <div className="flex items-center gap-2 rounded-lg bg-muted px-3 py-2 text-sm">
                        <span className="min-w-0 flex-1">
                            Só a atividade de{' '}
                            {resourceEntry && resourceEntry.resource
                                ? resourceText(resourceEntry.resource)
                                : (options.resources.find(
                                      (r) => r.value === filters.resource,
                                  )?.label ?? 'um registro')}{' '}
                            <span className="font-mono text-xs">
                                #{filters.resource_id}
                            </span>
                        </span>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-7"
                            aria-label="Ver a atividade de todos os registros"
                            onClick={() =>
                                apply({
                                    ...filters,
                                    resource: undefined,
                                    resource_id: undefined,
                                })
                            }
                        >
                            <X />
                        </Button>
                    </div>
                )}

                {failed && (
                    <Alert variant="destructive">
                        <AlertCircle />
                        <AlertTitle>
                            Não deu para carregar a atividade
                        </AlertTitle>
                        <AlertDescription>
                            <p>
                                Confira a conexão e tente de novo. Se continuar,
                                avise quem cuida da Owly.
                            </p>
                            <Button
                                variant="outline"
                                size="sm"
                                className="mt-2"
                                onClick={() => apply(filters)}
                            >
                                Tentar de novo
                            </Button>
                        </AlertDescription>
                    </Alert>
                )}

                <div className="flex min-h-8 items-center justify-between gap-2 text-sm text-muted-foreground">
                    <span
                        className="flex items-center gap-2"
                        aria-live="polite"
                    >
                        {loading && <Spinner aria-label="Carregando" />}
                        {entries.total === 1
                            ? '1 registro'
                            : `${entries.total.toLocaleString('pt-BR')} registros`}
                        {filtered && ' com esses filtros'}
                    </span>
                    {filtered && <ClearFilters onClear={clear} />}
                </div>

                {entries.data.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed px-6 py-10 text-center text-sm text-muted-foreground">
                        {filtered ? (
                            <>
                                <SearchX className="size-5" />
                                Nenhuma atividade com esses filtros.
                                <ClearFilters onClear={clear} />
                            </>
                        ) : (
                            <>
                                <History className="size-5" />
                                Nada registrado ainda.
                            </>
                        )}
                    </div>
                ) : (
                    <div
                        className={cn(
                            'rounded-xl border transition-opacity',
                            loading && 'opacity-60',
                        )}
                        aria-busy={loading}
                    >
                        <Table>
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead className="pl-4">
                                        Quando
                                    </TableHead>
                                    <TableHead className="hidden lg:table-cell">
                                        Quem
                                    </TableHead>
                                    <TableHead>Ação</TableHead>
                                    <TableHead className="hidden xl:table-cell">
                                        Recurso
                                    </TableHead>
                                    <TableHead className="hidden pr-4 sm:table-cell">
                                        Resultado
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {entries.data.map((entry) => (
                                    <Row
                                        key={entry.id}
                                        entry={entry}
                                        timezone={timezone}
                                        onOpen={() => setSelected(entry)}
                                    />
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                {entries.last_page > 1 && (
                    <nav
                        className="flex items-center justify-between gap-2"
                        aria-label="Páginas da atividade"
                    >
                        <PageLink href={entries.prev_page_url} visit={visit}>
                            Mais recentes
                        </PageLink>
                        <span className="text-sm text-muted-foreground tabular-nums">
                            {entries.from}–{entries.to} de{' '}
                            {entries.total.toLocaleString('pt-BR')} · página{' '}
                            {entries.current_page} de {entries.last_page}
                        </span>
                        <PageLink href={entries.next_page_url} visit={visit}>
                            Mais antigas
                        </PageLink>
                    </nav>
                )}
            </div>

            <ActivityDetails
                entry={selected}
                timezone={timezone}
                onClose={() => setSelected(null)}
                onOnlyResource={(entry) => {
                    setSelected(null);
                    apply(
                        {
                            resource: entry.resource?.key ?? undefined,
                            resource_id: String(entry.resource?.id ?? ''),
                        },
                        true,
                    );
                }}
            />
        </>
    );
}

function Row({
    entry,
    timezone,
    onOpen,
}: {
    entry: ActivityEntry;
    timezone: string;
    onOpen: () => void;
}) {
    return (
        <TableRow className="cursor-pointer" onClick={onOpen}>
            <TableCell className="pl-4 align-top font-mono text-xs text-muted-foreground tabular-nums">
                {dateTime(entry.at, timezone)}
            </TableCell>
            <TableCell
                className={cn(
                    'hidden align-top lg:table-cell',
                    !entry.user && 'text-muted-foreground',
                )}
            >
                {userText(entry.user)}
            </TableCell>
            <TableCell className="w-full align-top whitespace-normal">
                <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <button
                        type="button"
                        className="text-left hover:underline focus-visible:underline focus-visible:outline-none"
                        onClick={(e) => {
                            e.stopPropagation();
                            onOpen();
                        }}
                    >
                        {entry.label}
                    </button>
                    <SeverityBadge severity={entry.severity} />
                </div>
                {/* No celular, quem e o resultado vão embaixo da ação. */}
                <div className="mt-0.5 text-xs text-muted-foreground lg:hidden">
                    {userText(entry.user)}
                    {entry.result === 'failure' && (
                        <span className="text-destructive sm:hidden">
                            {' '}
                            · Falhou
                        </span>
                    )}
                </div>
            </TableCell>
            <TableCell className="hidden align-top xl:table-cell">
                {entry.resource ? (
                    <>
                        {resourceText(entry.resource)}{' '}
                        <span className="font-mono text-xs text-muted-foreground">
                            #{entry.resource.id}
                        </span>
                    </>
                ) : (
                    <span className="text-muted-foreground">—</span>
                )}
            </TableCell>
            <TableCell className="hidden pr-4 align-top sm:table-cell">
                <ResultLabel result={entry.result} />
            </TableCell>
        </TableRow>
    );
}

function PageLink({
    href,
    visit,
    children,
}: {
    href: string | null;
    visit: {
        only: string[];
        preserveScroll: boolean;
        onStart: () => void;
        onFinish: () => void;
    };
    children: string;
}) {
    if (!href) {
        return (
            <Button variant="outline" size="sm" disabled>
                {children}
            </Button>
        );
    }

    return (
        <Button variant="outline" size="sm" asChild>
            <Link
                href={href}
                only={visit.only}
                preserveState
                preserveScroll={visit.preserveScroll}
                onStart={visit.onStart}
                onFinish={visit.onFinish}
            >
                {children}
            </Link>
        </Button>
    );
}

Activity.layout = {
    breadcrumbs: [
        { title: 'Minha conta', href: '' },
        { title: 'Atividade', href: index() },
    ],
    wide: true,
};
