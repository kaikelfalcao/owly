import { Form, Head, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    CalendarX2,
    CheckCircle2,
    FileArchive,
    Loader2,
    Upload,
} from 'lucide-react';
import { useEffect } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import OwlyMascot from '@/components/owly-mascot';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { date, dateTime } from '@/lib/time';
import { cn } from '@/lib/utils';
import { index, store } from '@/routes/imports';

type Stats = {
    files: number;
    conversations_new: number;
    conversations_updated: number;
    messages_new: number;
    messages_known: number;
    first_at: string | null;
    last_at: string | null;
    gaps: { from: string; to: string; days: number }[];
};

type ImportRow = {
    id: number;
    status: 'pending' | 'running' | 'done' | 'failed';
    fileName: string;
    fileSize: number;
    stats: Stats | null;
    problems: { file: string; code: string }[];
    error: string | null;
    createdAt: string;
    finishedAt: string | null;
};

type Props = {
    imports: ImportRow[];
    maxSizeMb: number;
};

const PROBLEM: Record<string, string> = {
    unreadable_file: 'não deu para ler a planilha',
    empty_file: 'planilha vazia',
    no_messages: 'nenhuma mensagem com data',
};

const number = (n: number) => n.toLocaleString('pt-BR');

const fileSize = (bytes: number) =>
    bytes < 1024 * 1024
        ? `${Math.max(1, Math.round(bytes / 1024))} KB`
        : `${(bytes / 1024 / 1024).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} MB`;

/** Importar: subir o zip do WhatsApp e ver o que cada importação trouxe. */
export default function Imports({ imports, maxSizeMb }: Props) {
    const timeZone = usePage().props.auth.organization?.timezone;
    const active = imports.some(
        (i) => i.status === 'pending' || i.status === 'running',
    );

    // Enquanto alguma importação roda, a lista se atualiza sozinha.
    useEffect(() => {
        if (!active) {
            return;
        }

        const timer = setInterval(
            () =>
                router.reload({
                    only: ['imports', 'notifications'],
                    showProgress: false,
                }),
            3000,
        );

        return () => clearInterval(timer);
    }, [active]);

    return (
        <>
            <Head title="Importar" />
            <div className="mx-auto flex w-full max-w-4xl flex-col gap-8 p-4 md:p-6">
                <Heading
                    title="Importar conversas"
                    description="Suba o .zip exportado do WhatsApp. Subir um zip que cobre o mesmo período de outro não duplica nada."
                />

                <Form
                    {...store.form()}
                    resetOnSuccess
                    className="flex flex-col gap-3 rounded-2xl border border-dashed p-6"
                >
                    {({ processing, errors, progress }) => (
                        <>
                            <div className="flex items-center gap-3">
                                <span className="flex size-10 items-center justify-center rounded-full bg-primary/10 text-primary">
                                    <FileArchive className="size-5" />
                                </span>
                                <div>
                                    <p className="font-medium">
                                        Arquivo .zip das conversas
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        Uma planilha por cliente, como sai da
                                        ferramenta de exportação. Até{' '}
                                        {maxSizeMb} MB.
                                    </p>
                                </div>
                            </div>
                            <div className="flex flex-col gap-3 sm:flex-row">
                                <Input
                                    type="file"
                                    name="file"
                                    accept=".zip,application/zip"
                                    required
                                    className="cursor-pointer"
                                />
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="import-button"
                                >
                                    {processing ? <Spinner /> : <Upload />}
                                    Importar
                                </Button>
                            </div>
                            {progress && (
                                <p className="text-sm text-muted-foreground">
                                    Enviando… {progress.percentage}%
                                </p>
                            )}
                            <InputError message={errors.file} />
                        </>
                    )}
                </Form>

                <section className="flex flex-col gap-3">
                    <h3 className="text-base font-medium">Importações</h3>
                    {imports.length === 0 ? (
                        <div className="flex flex-col items-center gap-3 rounded-2xl border px-6 py-10 text-center text-sm text-muted-foreground">
                            <OwlyMascot className="h-28 w-auto" />
                            Nenhuma importação ainda. O primeiro zip aparece
                            aqui, com o que ele trouxe.
                        </div>
                    ) : (
                        <ul className="flex flex-col gap-3">
                            {imports.map((item) => (
                                <ImportCard
                                    key={item.id}
                                    item={item}
                                    timeZone={timeZone}
                                />
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}

function ImportCard({
    item,
    timeZone,
}: {
    item: ImportRow;
    timeZone?: string;
}) {
    const stats = item.stats;

    return (
        <li className="rounded-2xl border p-4">
            <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                <StatusIcon status={item.status} />
                <span className="min-w-0 flex-1 truncate font-medium">
                    {item.fileName}
                </span>
                <span className="font-mono text-xs text-muted-foreground tabular-nums">
                    {dateTime(item.createdAt, timeZone)} ·{' '}
                    {fileSize(item.fileSize)}
                </span>
            </div>

            {item.status === 'pending' && (
                <p className="mt-2 text-sm text-muted-foreground">
                    Na fila. Começa em instantes.
                </p>
            )}
            {item.status === 'running' && (
                <p className="mt-2 text-sm text-muted-foreground">
                    Lendo as conversas…
                </p>
            )}
            {item.status === 'failed' && (
                <p className="mt-2 text-sm text-destructive">{item.error}</p>
            )}

            {stats && (
                <>
                    <dl className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <Stat
                            label="Conversas novas"
                            value={number(stats.conversations_new)}
                        />
                        <Stat
                            label="Conversas atualizadas"
                            value={number(stats.conversations_updated)}
                        />
                        <Stat
                            label="Mensagens novas"
                            value={number(stats.messages_new)}
                        />
                        <Stat
                            label="Já estavam na Owly"
                            value={number(stats.messages_known)}
                        />
                    </dl>
                    {stats.first_at && stats.last_at && (
                        <p className="mt-3 text-sm text-muted-foreground">
                            Período: {date(stats.first_at, timeZone)} a{' '}
                            {date(stats.last_at, timeZone)} ·{' '}
                            {number(stats.files)} planilhas
                        </p>
                    )}
                    {stats.gaps.length > 0 && (
                        <div className="mt-3 flex gap-3 rounded-xl bg-highlight p-3 text-sm text-highlight-foreground">
                            <CalendarX2 className="mt-0.5 size-4 shrink-0" />
                            <div>
                                <p className="font-medium">
                                    Dias sem nenhuma mensagem no zip
                                </p>
                                <p>
                                    {stats.gaps
                                        .map((gap) =>
                                            gap.from === gap.to
                                                ? formatDay(gap.from)
                                                : `${formatDay(gap.from)} a ${formatDay(gap.to)} (${gap.days} dias)`,
                                        )
                                        .join('; ')}
                                    . A exportação pode ter ficado incompleta.
                                </p>
                            </div>
                        </div>
                    )}
                </>
            )}

            {item.problems.length > 0 && (
                <details className="mt-3 text-sm">
                    <summary className="cursor-pointer text-muted-foreground">
                        {item.problems.length}{' '}
                        {item.problems.length === 1
                            ? 'planilha ficou de fora'
                            : 'planilhas ficaram de fora'}
                    </summary>
                    <ul className="mt-2 flex flex-col gap-1 pl-4">
                        {item.problems.map((problem) => (
                            <li key={problem.file}>
                                <span className="font-mono text-xs">
                                    {problem.file}
                                </span>
                                : {PROBLEM[problem.code] ?? problem.code}
                            </li>
                        ))}
                    </ul>
                </details>
            )}
        </li>
    );
}

function Stat({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-xl bg-muted/60 p-3">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="font-mono text-lg font-semibold tabular-nums">
                {value}
            </dd>
        </div>
    );
}

function StatusIcon({ status }: { status: ImportRow['status'] }) {
    const base = 'size-5 shrink-0';

    if (status === 'done') {
        return <CheckCircle2 className={cn(base, 'text-primary')} />;
    }

    if (status === 'failed') {
        return <AlertTriangle className={cn(base, 'text-destructive')} />;
    }

    return (
        <Loader2 className={cn(base, 'animate-spin text-muted-foreground')} />
    );
}

/** "2026-09-15" -> "15/09" */
const formatDay = (day: string) =>
    day.split('-').reverse().slice(0, 2).join('/');

Imports.layout = {
    breadcrumbs: [{ title: 'Importar', href: index() }],
};
