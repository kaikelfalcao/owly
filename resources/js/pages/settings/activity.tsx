import { Head, Link } from '@inertiajs/react';
import { History, ShieldAlert } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { dateTime } from '@/lib/time';
import { cn } from '@/lib/utils';
import { index } from '@/routes/activity';

type Entry = {
    id: number;
    action: string;
    label: string;
    tone: 'default' | 'warning';
    ip: string | null;
    at: string;
};

type Props = {
    entries: {
        data: Entry[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
};

/** Minha conta › Atividade: o que aconteceu na conta, do mais recente. */
export default function Activity({ entries }: Props) {
    return (
        <>
            <Head title="Atividade" />

            <h1 className="sr-only">Atividade</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Atividade"
                    description="Entradas, trocas de senha e o que mais aconteceu na conta"
                />

                {entries.data.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed px-6 py-10 text-center text-sm text-muted-foreground">
                        <History className="size-5" />
                        Nada registrado ainda.
                    </div>
                ) : (
                    <ol className="divide-y rounded-xl border">
                        {entries.data.map((entry) => (
                            <li
                                key={entry.id}
                                className="flex items-start gap-3 px-4 py-3"
                            >
                                <span
                                    className={cn(
                                        'mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full',
                                        entry.tone === 'warning'
                                            ? 'bg-highlight text-highlight-foreground'
                                            : 'bg-muted text-muted-foreground',
                                    )}
                                >
                                    {entry.tone === 'warning' ? (
                                        <ShieldAlert className="size-4" />
                                    ) : (
                                        <History className="size-4" />
                                    )}
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="block text-sm">
                                        {entry.label}
                                    </span>
                                    <span className="block font-mono text-xs text-muted-foreground tabular-nums">
                                        {dateTime(entry.at)}
                                        {entry.ip && ` · IP ${entry.ip}`}
                                    </span>
                                </span>
                            </li>
                        ))}
                    </ol>
                )}

                {(entries.prev_page_url || entries.next_page_url) && (
                    <div className="flex justify-between">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!entries.prev_page_url}
                            asChild={!!entries.prev_page_url}
                        >
                            {entries.prev_page_url ? (
                                <Link href={entries.prev_page_url}>
                                    Mais recentes
                                </Link>
                            ) : (
                                <span>Mais recentes</span>
                            )}
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!entries.next_page_url}
                            asChild={!!entries.next_page_url}
                        >
                            {entries.next_page_url ? (
                                <Link href={entries.next_page_url}>
                                    Mais antigas
                                </Link>
                            ) : (
                                <span>Mais antigas</span>
                            )}
                        </Button>
                    </div>
                )}
            </div>
        </>
    );
}

Activity.layout = {
    breadcrumbs: [
        { title: 'Minha conta', href: '' },
        { title: 'Atividade', href: index() },
    ],
};
