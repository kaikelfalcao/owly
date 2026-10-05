import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    CalendarClock,
    CalendarX2,
    ChevronRight,
    Eye,
    FileText,
    HandCoins,
    MessageCircleWarning,
    MoonStar,
    Timer,
} from 'lucide-react';
import type { ReactNode } from 'react';
import OwlyMascot from '@/components/owly-mascot';
import { Button } from '@/components/ui/button';
import { date, duration } from '@/lib/time';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { edit as editHours } from '@/routes/business-hours';
import { index as importIndex } from '@/routes/imports';
import { show as insight } from '@/routes/insights';

type Period = { key: string; since: string | null; until: string };

type Props = {
    period: Period;
    hasData: boolean;
    totals: { conversations: number; turns: number };
    unanswered: number;
    response: {
        medianSeconds: number | null;
        withinHourShare: number | null;
        answered: number;
    };
    outOfHours: { turns: number; share: number | null };
    quotes: { sent: number; stalled: number };
    sales: number;
    topics: { key: string; label: string; count: number }[];
    sellers: {
        name: string;
        conversations: number;
        messages: number;
        medianSeconds: number | null;
    }[];
    health: {
        finishedAt: string;
        gaps: { from: string; to: string; days: number }[];
        problems: number;
    } | null;
    hoursConfigured: boolean;
};

const PERIODS = [
    { key: '7', label: '7 dias' },
    { key: '30', label: '30 dias' },
    { key: 'tudo', label: 'Tudo' },
];

const percent = (share: number | null) =>
    share === null ? '—' : `${Math.round(share * 100)}%`;

const n = (value: number) => value.toLocaleString('pt-BR');

/** "2026-09-15" -> "15/09" */
const day = (iso: string) => iso.split('-').reverse().slice(0, 2).join('/');

export default function Dashboard(props: Props) {
    const { auth } = usePage().props;
    const timeZone = auth.organization?.timezone;
    const firstName = auth.user.name.split(' ')[0];
    const { period } = props;
    const link = (key: string, filtro?: string) =>
        insight(key, {
            query: { periodo: period.key, ...(filtro ? { filtro } : {}) },
        });

    if (!props.hasData) {
        return (
            <>
                <Head title="Painel" />
                <div className="flex flex-1 items-center justify-center p-6">
                    <div className="flex max-w-md flex-col items-center gap-4 text-center">
                        <OwlyMascot className="h-40 w-auto" />
                        <h1 className="text-2xl font-semibold">
                            Olá, {firstName}
                        </h1>
                        <p className="text-balance text-muted-foreground">
                            Suba o zip das conversas do WhatsApp e a Owly mostra
                            aqui quem ficou sem resposta, quanto a equipe demora
                            e onde a venda escapou.
                        </p>
                        <Button asChild>
                            <Link href={importIndex()}>Importar o zip</Link>
                        </Button>
                    </div>
                </div>
            </>
        );
    }

    const maxTopic = Math.max(1, ...props.topics.map((t) => t.count));

    return (
        <>
            <Head title="Painel" />
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Olá, {firstName}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {n(props.totals.conversations)} conversas{' '}
                            {period.since
                                ? `de ${date(period.since, timeZone)} a ${date(period.until, timeZone)}`
                                : `até ${date(period.until, timeZone)}`}
                            , a data da mensagem mais recente.
                        </p>
                    </div>
                    <div
                        className="inline-flex rounded-lg border p-0.5"
                        role="group"
                        aria-label="Período"
                    >
                        {PERIODS.map((p) => (
                            <button
                                key={p.key}
                                type="button"
                                onClick={() =>
                                    router.get(
                                        dashboard.url(),
                                        { periodo: p.key },
                                        { preserveScroll: true },
                                    )
                                }
                                aria-pressed={period.key === p.key}
                                className={cn(
                                    'rounded-md px-3 py-1 text-sm',
                                    period.key === p.key
                                        ? 'bg-primary text-primary-foreground'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {p.label}
                            </button>
                        ))}
                    </div>
                </div>

                {!props.hoursConfigured && (
                    <p className="flex flex-wrap items-center gap-2 rounded-xl border border-dashed p-3 text-sm text-muted-foreground">
                        <CalendarClock className="size-4" />
                        Os tempos usam o horário padrão (seg. a sex. das 8h às
                        18h, sáb. das 8h às 12h, sem feriados da cidade).
                        <Link
                            href={editHours()}
                            className="font-medium text-primary underline-offset-4 hover:underline"
                        >
                            Ajustar o horário da empresa
                        </Link>
                    </p>
                )}

                {(props.unanswered > 0 || props.quotes.stalled > 0) && (
                    <section className="flex flex-col gap-2">
                        <h2 className="flex items-center gap-2 text-sm font-medium">
                            <span className="inline-flex items-center gap-1 rounded-full bg-highlight px-2 py-0.5 text-xs text-highlight-foreground">
                                <Eye className="size-3" />
                                Owly viu
                            </span>
                            Pede a sua atenção
                        </h2>
                        <div className="grid gap-3 sm:grid-cols-2">
                            {props.unanswered > 0 && (
                                <Attention
                                    href={link('sem-resposta')}
                                    icon={<MessageCircleWarning />}
                                    title={`${n(props.unanswered)} ${props.unanswered === 1 ? 'cliente esperando' : 'clientes esperando'} resposta`}
                                    text="A última mensagem é do cliente e ninguém respondeu. “Obrigado” e “ok” não entram."
                                />
                            )}
                            {props.quotes.stalled > 0 && (
                                <Attention
                                    href={link('orcamentos-parados')}
                                    icon={<FileText />}
                                    title={`${n(props.quotes.stalled)} ${props.quotes.stalled === 1 ? 'orçamento parado' : 'orçamentos parados'}`}
                                    text="O cliente recebeu o preço e está calado há dois dias ou mais. Vale um retorno."
                                />
                            )}
                        </div>
                    </section>
                )}

                <section className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Stat
                        href={link('tempo-de-resposta')}
                        icon={<Timer />}
                        label="Tempo até responder"
                        value={duration(props.response.medianSeconds)}
                        hint={`metade das respostas · ${percent(props.response.withinHourShare)} em até 1 h útil`}
                    />
                    <Stat
                        href={link('fora-do-horario')}
                        icon={<MoonStar />}
                        label="Chegaram fora do horário"
                        value={percent(props.outOfHours.share)}
                        hint={`${n(props.outOfHours.turns)} de ${n(props.totals.turns)} vezes que o cliente escreveu`}
                    />
                    <Stat
                        href={link('orcamentos-parados')}
                        icon={<FileText />}
                        label="Orçamentos parados"
                        value={n(props.quotes.stalled)}
                        hint={`de ${n(props.quotes.sent)} conversas com orçamento`}
                    />
                    <Stat
                        href={link('vendas')}
                        icon={<HandCoins />}
                        label="Vendas prováveis"
                        value={n(props.sales)}
                        hint="cliente autorizou ou mandou comprovante"
                    />
                </section>

                <div className="grid gap-6 lg:grid-cols-2">
                    <section className="flex flex-col gap-3 rounded-2xl border p-4">
                        <div>
                            <h2 className="font-medium">
                                O que os clientes procuram
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                Conversas que citam cada tema
                            </p>
                        </div>
                        {props.topics.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Nenhum tema conhecido nas mensagens do período.
                            </p>
                        ) : (
                            <ul className="flex flex-col gap-1">
                                {props.topics.map((topic, i) => (
                                    <li key={topic.key}>
                                        <Link
                                            href={link('procuram', topic.key)}
                                            className="group grid grid-cols-[8rem_1fr_auto] items-center gap-3 rounded-md px-1 py-1 text-sm hover:bg-muted/60 sm:grid-cols-[10rem_1fr_auto]"
                                            title={`${topic.label}: ${n(topic.count)} conversas`}
                                        >
                                            <span className="truncate">
                                                {topic.label}
                                            </span>
                                            <span className="h-3 overflow-hidden rounded-r bg-transparent">
                                                <span
                                                    className={cn(
                                                        'block h-full rounded-r-[4px]',
                                                        i === 0
                                                            ? 'bg-spotlight'
                                                            : 'bg-muted-foreground/35 group-hover:bg-muted-foreground/55',
                                                    )}
                                                    style={{
                                                        width: `${(topic.count / maxTopic) * 100}%`,
                                                    }}
                                                />
                                            </span>
                                            <span className="font-mono text-xs text-muted-foreground tabular-nums">
                                                {n(topic.count)}
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>

                    <section className="flex flex-col gap-3 rounded-2xl border p-4">
                        <div>
                            <h2 className="font-medium">Por vendedora</h2>
                            <p className="text-xs text-muted-foreground">
                                Tempo em horas úteis; metade das respostas sai
                                até esse tempo
                            </p>
                        </div>
                        {props.sellers.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Nenhuma mensagem assinada no período.
                            </p>
                        ) : (
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="text-left text-xs text-muted-foreground">
                                        <th className="pb-1 font-normal">
                                            Vendedora
                                        </th>
                                        <th className="pb-1 text-right font-normal">
                                            Conversas
                                        </th>
                                        <th className="hidden pb-1 text-right font-normal sm:table-cell">
                                            Mensagens
                                        </th>
                                        <th className="pb-1 text-right font-normal">
                                            Responde em
                                        </th>
                                        <th className="w-4" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {props.sellers.map((seller) => (
                                        <tr
                                            key={seller.name}
                                            className="cursor-pointer border-t hover:bg-muted/60"
                                            onClick={() =>
                                                router.visit(
                                                    link(
                                                        'vendedora',
                                                        seller.name,
                                                    ),
                                                )
                                            }
                                        >
                                            <td className="py-1.5">
                                                <Link
                                                    href={link(
                                                        'vendedora',
                                                        seller.name,
                                                    )}
                                                    onClick={(e) =>
                                                        e.stopPropagation()
                                                    }
                                                >
                                                    {seller.name}
                                                </Link>
                                            </td>
                                            <td className="text-right font-mono tabular-nums">
                                                {n(seller.conversations)}
                                            </td>
                                            <td className="hidden text-right font-mono tabular-nums sm:table-cell">
                                                {n(seller.messages)}
                                            </td>
                                            <td className="text-right font-mono tabular-nums">
                                                {duration(seller.medianSeconds)}
                                            </td>
                                            <td>
                                                <ChevronRight className="size-4 text-muted-foreground" />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </section>
                </div>

                {props.health && (
                    <section className="flex flex-col gap-1 text-sm text-muted-foreground">
                        <p>
                            Último zip importado em{' '}
                            {date(props.health.finishedAt, timeZone)}
                            {props.health.problems > 0 &&
                                ` · ${props.health.problems} planilhas ficaram de fora`}
                            .
                        </p>
                        {props.health.gaps.length > 0 && (
                            <p className="flex items-center gap-2">
                                <CalendarX2 className="size-4 text-highlight-foreground" />
                                Dias sem nenhuma mensagem no zip:{' '}
                                {props.health.gaps
                                    .map((g) =>
                                        g.from === g.to
                                            ? day(g.from)
                                            : `${day(g.from)} a ${day(g.to)}`,
                                    )
                                    .join(', ')}
                                . Os números desses dias podem estar baixos.
                            </p>
                        )}
                    </section>
                )}
            </div>
        </>
    );
}

function Attention({
    href,
    icon,
    title,
    text,
}: {
    href: ReturnType<typeof insight>;
    icon: ReactNode;
    title: string;
    text: string;
}) {
    return (
        <Link
            href={href}
            className="flex items-start gap-3 rounded-2xl border border-highlight bg-highlight/15 p-4 transition-colors hover:bg-highlight/25 [&_svg]:size-5"
        >
            <span className="mt-0.5 text-highlight-foreground">{icon}</span>
            <span className="flex-1">
                <span className="block font-medium">{title}</span>
                <span className="block text-sm text-muted-foreground">
                    {text}
                </span>
            </span>
            <ChevronRight className="mt-0.5 text-muted-foreground" />
        </Link>
    );
}

function Stat({
    href,
    icon,
    label,
    value,
    hint,
}: {
    href: ReturnType<typeof insight>;
    icon: ReactNode;
    label: string;
    value: string;
    hint: string;
}) {
    return (
        <Link
            href={href}
            className="flex flex-col gap-1 rounded-2xl border p-4 transition-colors hover:bg-muted/50 [&_svg]:size-4"
        >
            <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
                {icon}
                {label}
            </span>
            <span className="font-mono text-2xl font-semibold tabular-nums">
                {value}
            </span>
            <span className="text-xs text-muted-foreground">{hint}</span>
        </Link>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Painel', href: dashboard() }],
};
