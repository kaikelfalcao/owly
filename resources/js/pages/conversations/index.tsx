import { Head, Link, router, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import EpisodeStatus from '@/components/episode-status';
import Heading from '@/components/heading';
import OwlyMascot from '@/components/owly-mascot';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ago, dateTime } from '@/lib/time';
import { index, show } from '@/routes/conversations';
import { index as importIndex } from '@/routes/imports';

type Row = {
    id: number;
    contact: string;
    phone: string | null;
    messagesCount: number;
    lastMessageAt: string | null;
    lastMessage: { who: string; text: string } | null;
    status: 'open' | 'closed';
    openedBy: 'contact' | 'company';
    seller: string | null;
    position: number;
    episodes: number;
};

type Page<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Props = {
    conversations: Page<Row>;
    search: string | null;
};

/** Um atendimento por linha, o mais recente primeiro. */
export default function Conversations({ conversations, search }: Props) {
    const timeZone = usePage().props.auth.organization?.timezone;
    const [term, setTerm] = useState(search ?? '');
    const first = useRef(true);

    // Busca enquanto digita, com uma pequena espera para não pedir a cada tecla.
    useEffect(() => {
        if (first.current) {
            first.current = false;

            return;
        }

        const timer = setTimeout(
            () =>
                router.get(
                    index.url(),
                    term.trim() ? { busca: term.trim() } : {},
                    { preserveState: true, replace: true },
                ),
            300,
        );

        return () => clearTimeout(timer);
    }, [term]);

    const empty = conversations.total === 0;
    // A conversa aberta volta para esta mesma busca e página.
    const back = {
        ...(search ? { busca: search } : {}),
        ...(conversations.current_page > 1
            ? { page: String(conversations.current_page) }
            : {}),
    };

    return (
        <>
            <Head title="Conversas" />
            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
                <Heading
                    title="Conversas"
                    description="Um atendimento por linha. O cliente que volta a escrever depois de um dia útil inteiro ganha outro."
                />

                <div className="relative">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        type="search"
                        value={term}
                        onChange={(e) => setTerm(e.target.value)}
                        placeholder="Buscar por nome ou telefone"
                        className="pl-9"
                        aria-label="Buscar conversa"
                    />
                </div>

                {empty && !search && (
                    <div className="flex flex-col items-center gap-3 rounded-2xl border px-6 py-10 text-center text-sm text-muted-foreground">
                        <OwlyMascot className="h-28 w-auto" />
                        Nenhuma conversa ainda.
                        <Button asChild size="sm">
                            <Link href={importIndex()}>Importar o zip</Link>
                        </Button>
                    </div>
                )}

                {empty && search && (
                    <p className="rounded-2xl border px-6 py-10 text-center text-sm text-muted-foreground">
                        Nenhum cliente encontrado para “{search}”.
                    </p>
                )}

                {!empty && (
                    <ul className="divide-y rounded-2xl border">
                        {conversations.data.map((row) => (
                            <li key={row.id}>
                                <Link
                                    href={show(row.id, { query: back })}
                                    className="flex items-start gap-3 px-4 py-3 transition-colors hover:bg-muted/50"
                                >
                                    <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-medium text-primary">
                                        {initials(row.contact)}
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-baseline gap-2">
                                            <span className="truncate font-medium">
                                                {row.contact}
                                            </span>
                                            {row.phone &&
                                                row.phone !== row.contact && (
                                                    <span className="hidden truncate font-mono text-xs text-muted-foreground sm:inline">
                                                        {row.phone}
                                                    </span>
                                                )}
                                            <span
                                                className="ml-auto shrink-0 text-xs text-muted-foreground"
                                                title={
                                                    row.lastMessageAt
                                                        ? dateTime(
                                                              row.lastMessageAt,
                                                              timeZone,
                                                          )
                                                        : undefined
                                                }
                                            >
                                                {ago(row.lastMessageAt)}
                                            </span>
                                        </div>
                                        <p className="truncate text-sm text-muted-foreground">
                                            {row.lastMessage && (
                                                <>
                                                    {row.lastMessage.who && (
                                                        <span className="text-foreground/80">
                                                            {
                                                                row.lastMessage
                                                                    .who
                                                            }
                                                            :{' '}
                                                        </span>
                                                    )}
                                                    {row.lastMessage.text}
                                                </>
                                            )}
                                        </p>
                                        <div className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
                                            <EpisodeStatus
                                                status={row.status}
                                            />
                                            {row.episodes > 1 && (
                                                <span>
                                                    Atendimento {row.position}{' '}
                                                    de {row.episodes}
                                                </span>
                                            )}
                                            {row.openedBy === 'company' && (
                                                <span>
                                                    Começou pela empresa
                                                </span>
                                            )}
                                            <span className="truncate">
                                                {row.seller
                                                    ? `Responsável: ${row.seller}`
                                                    : 'Ninguém respondeu'}
                                            </span>
                                        </div>
                                    </div>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}

                {conversations.last_page > 1 && (
                    <nav className="flex items-center justify-between text-sm text-muted-foreground">
                        <span>
                            Página {conversations.current_page} de{' '}
                            {conversations.last_page} ·{' '}
                            {conversations.total.toLocaleString('pt-BR')}{' '}
                            atendimentos
                        </span>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!conversations.prev_page_url}
                                asChild={!!conversations.prev_page_url}
                            >
                                {conversations.prev_page_url ? (
                                    <Link
                                        href={conversations.prev_page_url}
                                        preserveScroll={false}
                                    >
                                        Anterior
                                    </Link>
                                ) : (
                                    <span>Anterior</span>
                                )}
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!conversations.next_page_url}
                                asChild={!!conversations.next_page_url}
                            >
                                {conversations.next_page_url ? (
                                    <Link href={conversations.next_page_url}>
                                        Próxima
                                    </Link>
                                ) : (
                                    <span>Próxima</span>
                                )}
                            </Button>
                        </div>
                    </nav>
                )}
            </div>
        </>
    );
}

function initials(name: string): string {
    const letters = name
        .split(/\s+/)
        .filter((part) => /\p{L}/u.test(part[0] ?? ''))
        .slice(0, 2)
        .map((part) => part[0].toUpperCase())
        .join('');

    return letters || '#';
}

Conversations.layout = {
    breadcrumbs: [{ title: 'Conversas', href: index() }],
};
