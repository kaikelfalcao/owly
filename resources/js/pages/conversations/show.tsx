import { Head, usePage } from '@inertiajs/react';
import {
    Bot,
    FileText,
    Image as ImageIcon,
    Mic,
    PhoneMissed,
    Reply,
    Sparkles,
    Trash2,
    Video,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import AskAi from '@/components/ask-ai';
import type { AiPanelProps, FocusedMessage } from '@/components/ask-ai';
import { date, dateTime, dayKey, dayLabel, monthLabel, time } from '@/lib/time';
import { cn } from '@/lib/utils';
import { index, show } from '@/routes/conversations';

type Message = {
    id: number;
    sentAt: string;
    direction: 'in' | 'out';
    author: 'contact' | 'seller' | 'bot' | 'system';
    seller: string | null;
    body: string | null;
    mediaType: string | null;
    mediaName: string | null;
    event: string | null;
    quoted: string | null;
};

type Props = {
    conversation: {
        id: number;
        contact: string;
        phone: string | null;
        firstMessageAt: string | null;
        lastMessageAt: string | null;
        messagesCount: number;
        fromContact: number;
        sellers: { name: string; messages: number }[];
    };
    messages: Message[];
    panels: { ai?: AiPanelProps };
};

/** Uma conversa: a ficha do cliente e todas as mensagens, dia a dia. */
export default function ConversationShow({
    conversation,
    messages,
    panels,
}: Props) {
    const timeZone = usePage().props.auth.organization?.timezone;
    const groups = groupByDay(messages, timeZone);
    const [focused, setFocused] = useState<FocusedMessage>(null);
    const ai = panels.ai;
    const askAbout = ai?.connection
        ? (message: Message) =>
              setFocused({
                  id: message.id,
                  label: `${time(message.sentAt, timeZone)} · ${message.body ?? message.mediaName ?? 'arquivo'}`,
              })
        : undefined;

    return (
        <>
            <Head title={conversation.contact} />
            <div className="mx-auto grid w-full max-w-6xl gap-6 p-4 md:p-6 lg:grid-cols-[18rem_1fr]">
                <div className="flex h-fit flex-col gap-4 lg:sticky lg:top-4 lg:max-h-[calc(100svh-6rem)] lg:overflow-y-auto">
                    <aside className="flex flex-col gap-4 rounded-2xl border p-4">
                        <div>
                            <h1 className="text-lg font-semibold break-words">
                                {conversation.contact}
                            </h1>
                            {conversation.phone &&
                                conversation.phone !== conversation.contact && (
                                    <p className="font-mono text-sm text-muted-foreground">
                                        {conversation.phone}
                                    </p>
                                )}
                        </div>
                        <dl className="grid grid-cols-2 gap-3 text-sm">
                            <Fact label="Mensagens">
                                {conversation.messagesCount.toLocaleString(
                                    'pt-BR',
                                )}
                            </Fact>
                            <Fact label="Do cliente">
                                {conversation.fromContact.toLocaleString(
                                    'pt-BR',
                                )}
                            </Fact>
                            {conversation.firstMessageAt && (
                                <Fact label="Primeira">
                                    {date(
                                        conversation.firstMessageAt,
                                        timeZone,
                                    )}
                                </Fact>
                            )}
                            {conversation.lastMessageAt && (
                                <Fact label="Última">
                                    {date(conversation.lastMessageAt, timeZone)}
                                </Fact>
                            )}
                        </dl>
                        {conversation.sellers.length > 0 && (
                            <div className="text-sm">
                                <p className="mb-1 text-xs text-muted-foreground">
                                    Quem atendeu
                                </p>
                                <ul className="flex flex-col gap-1">
                                    {conversation.sellers.map((seller) => (
                                        <li
                                            key={seller.name}
                                            className="flex justify-between gap-2"
                                        >
                                            <span className="truncate">
                                                {seller.name}
                                            </span>
                                            <span className="font-mono text-muted-foreground tabular-nums">
                                                {seller.messages}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </aside>
                    {ai && (
                        <AskAi
                            conversationId={conversation.id}
                            contactName={conversation.contact}
                            panel={ai}
                            focused={focused}
                            onClearFocus={() => setFocused(null)}
                        />
                    )}
                </div>

                <section className="flex min-w-0 flex-col gap-2">
                    {groups.map((group) => (
                        <div key={group.key} className="flex flex-col gap-2">
                            {group.month && (
                                <h2 className="mt-4 text-sm font-semibold first-letter:uppercase first:mt-0">
                                    {group.month}
                                </h2>
                            )}
                            <p className="sticky top-0 z-10 mx-auto my-2 rounded-full bg-muted px-3 py-1 text-xs text-muted-foreground first-letter:uppercase">
                                {group.label}
                            </p>
                            {group.messages.map((message) => (
                                <Bubble
                                    key={message.id}
                                    message={message}
                                    timeZone={timeZone}
                                    onAsk={askAbout}
                                />
                            ))}
                        </div>
                    ))}
                </section>
            </div>
        </>
    );
}

function Fact({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="font-mono tabular-nums">{children}</dd>
        </div>
    );
}

function Bubble({
    message,
    timeZone,
    onAsk,
}: {
    message: Message;
    timeZone?: string;
    onAsk?: (message: Message) => void;
}) {
    const at = time(message.sentAt, timeZone);

    if (message.author === 'system') {
        const missed = message.event === 'missed_call';
        const Icon = missed ? PhoneMissed : Trash2;

        return (
            <p
                className="mx-auto flex items-center gap-1.5 text-xs text-muted-foreground"
                title={dateTime(message.sentAt, timeZone)}
            >
                <Icon className="size-3.5" />
                {missed ? 'Ligação perdida' : 'Mensagem apagada'}
                {message.direction === 'out'
                    ? ' pela empresa'
                    : ' pelo cliente'}{' '}
                · {at}
            </p>
        );
    }

    const out = message.direction === 'out';
    const bot = message.author === 'bot';

    return (
        <div className={cn('flex', out ? 'justify-end' : 'justify-start')}>
            <div
                className={cn(
                    'group max-w-[85%] rounded-2xl px-3 py-2 text-sm sm:max-w-[70%]',
                    out
                        ? 'rounded-br-sm bg-primary/10'
                        : 'rounded-bl-sm border bg-card',
                    bot && 'border border-dashed bg-muted/60',
                )}
            >
                {out && (
                    <p className="mb-0.5 flex items-center gap-1 text-xs font-medium text-primary">
                        {bot ? (
                            <>
                                <Bot className="size-3.5" />
                                Resposta automática
                            </>
                        ) : (
                            (message.seller ?? 'Equipe')
                        )}
                    </p>
                )}
                {message.quoted && (
                    <p className="mb-1 flex gap-1 border-l-2 border-primary/40 pl-2 text-xs text-muted-foreground">
                        <Reply className="mt-0.5 size-3 shrink-0" />
                        <span className="line-clamp-2 whitespace-pre-line">
                            {message.quoted}
                        </span>
                    </p>
                )}
                {message.mediaType && <Media message={message} />}
                {message.body && (
                    <p className="break-words whitespace-pre-line">
                        {message.body}
                    </p>
                )}
                <div className="mt-0.5 flex items-center justify-end gap-2">
                    {onAsk && (
                        <button
                            type="button"
                            onClick={() => onAsk(message)}
                            className="text-muted-foreground opacity-50 transition-opacity group-hover:opacity-100 hover:text-primary focus-visible:opacity-100 sm:opacity-0"
                            aria-label="Perguntar à IA sobre esta mensagem"
                            title="Perguntar à IA sobre esta mensagem"
                        >
                            <Sparkles className="size-3.5" />
                        </button>
                    )}
                    <span
                        className="font-mono text-[11px] text-muted-foreground"
                        title={dateTime(message.sentAt, timeZone)}
                    >
                        {at}
                    </span>
                </div>
            </div>
        </div>
    );
}

const MEDIA: Record<string, { icon: typeof FileText; label: string }> = {
    image: { icon: ImageIcon, label: 'Imagem' },
    sticker: { icon: ImageIcon, label: 'Figurinha' },
    video: { icon: Video, label: 'Vídeo' },
    audio: { icon: Mic, label: 'Áudio' },
    ptt: { icon: Mic, label: 'Áudio' },
};

/** O zip traz só o nome do arquivo, não o arquivo. */
function Media({ message }: { message: Message }) {
    const kind = MEDIA[message.mediaType ?? ''] ?? {
        icon: FileText,
        label: 'Arquivo',
    };
    const Icon = kind.icon;

    return (
        <p
            className="mb-1 flex items-center gap-2 rounded-lg bg-background/60 px-2 py-1.5 text-xs text-muted-foreground"
            title="O arquivo não vem no zip, só o nome dele."
        >
            <Icon className="size-4 shrink-0" />
            <span className="truncate">
                {kind.label}
                {message.mediaName ? `: ${message.mediaName}` : ''}
            </span>
        </p>
    );
}

type DayGroup = {
    key: string;
    label: string;
    month: string | null;
    messages: Message[];
};

function groupByDay(messages: Message[], timeZone?: string): DayGroup[] {
    const groups: DayGroup[] = [];
    let lastMonth = '';

    for (const message of messages) {
        const key = dayKey(message.sentAt, timeZone);
        let group = groups.at(-1);

        if (!group || group.key !== key) {
            const month = key.slice(0, 7);
            group = {
                key,
                label: dayLabel(message.sentAt, timeZone),
                month:
                    month !== lastMonth
                        ? monthLabel(message.sentAt, timeZone)
                        : null,
                messages: [],
            };
            lastMonth = month;
            groups.push(group);
        }

        group.messages.push(message);
    }

    return groups;
}

ConversationShow.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Conversas', href: index() },
        {
            title: props.conversation.contact,
            href: show(props.conversation.id),
        },
    ],
});
