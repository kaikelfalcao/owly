import { Link, usePage } from '@inertiajs/react';
import { BadgeDollarSign } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { date, dateTime } from '@/lib/time';
import { send } from '@/lib/visit';
import { show } from '@/routes/conversations';
import { discard, lose, reopen, win } from '@/routes/opportunities';

export type Opportunity = {
    id: number;
    status: 'open' | 'won' | 'lost' | 'discarded';
    source: 'rule' | 'owner';
    decided: boolean;
    openedAt: string;
    closedAt: string | null;
    lossReason: string | null;
    anchorMessageId: number;
    anchorConversationId: number | null;
    closingMessageId: number | null;
    closingConversationId: number | null;
    bornWon: boolean;
};

export type OpportunitiesPanelProps = {
    opportunities: Opportunity[];
    reasons: { code: string; label: string }[];
};

const STATUS: Record<Opportunity['status'], string> = {
    open: 'Aberta',
    won: 'Ganha',
    lost: 'Perdida',
    discarded: 'Não era oportunidade',
};

/** As oportunidades que abriram ou fecharam neste atendimento, e as que vêm abertas de antes. */
export default function Opportunities({
    conversationId,
    panel,
}: {
    conversationId: number;
    panel: OpportunitiesPanelProps;
}) {
    const timeZone = usePage().props.auth.organization?.timezone;
    const [losing, setLosing] = useState<Opportunity | null>(null);

    return (
        <section className="flex flex-col gap-3 rounded-2xl border p-4">
            <h2 className="flex items-center gap-2 text-sm font-medium">
                <BadgeDollarSign className="size-4 text-primary" />
                Oportunidades
            </h2>

            {panel.opportunities.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Nenhum orçamento nem venda neste atendimento.
                </p>
            ) : (
                <ul className="flex flex-col gap-3">
                    {panel.opportunities.map((opportunity) => (
                        <li
                            key={opportunity.id}
                            className="flex flex-col gap-2 text-sm"
                        >
                            <div className="flex flex-wrap items-center gap-1.5">
                                <StatusBadge status={opportunity.status} />
                                {opportunity.decided && (
                                    <span
                                        className="text-xs text-muted-foreground"
                                        title="Você decidiu. A Owly não muda mais esta oportunidade."
                                    >
                                        Decisão sua
                                    </span>
                                )}
                            </div>
                            <p className="text-muted-foreground">
                                {story(opportunity, conversationId, timeZone)}
                            </p>
                            <Actions
                                opportunity={opportunity}
                                onLose={() => setLosing(opportunity)}
                            />
                        </li>
                    ))}
                </ul>
            )}

            <Dialog
                open={losing !== null}
                onOpenChange={(open) => !open && setLosing(null)}
            >
                <DialogContent>
                    <DialogTitle>Por que não virou venda?</DialogTitle>
                    <DialogDescription>
                        O motivo entra nas contas de perda do painel.
                    </DialogDescription>
                    <div className="grid gap-2 sm:grid-cols-2">
                        {panel.reasons.map((reason) => (
                            <Button
                                key={reason.code}
                                variant="outline"
                                onClick={() => {
                                    if (losing) {
                                        send(lose(losing.id), {
                                            data: { reason: reason.code },
                                            onFinish: () => setLosing(null),
                                        });
                                    }
                                }}
                            >
                                {reason.label}
                            </Button>
                        ))}
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="ghost">Cancelar</Button>
                        </DialogClose>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </section>
    );
}

function StatusBadge({ status }: { status: Opportunity['status'] }) {
    if (status === 'open') {
        return (
            <Badge className="bg-primary/10 text-primary">
                {STATUS[status]}
            </Badge>
        );
    }

    return (
        <Badge variant={status === 'won' ? 'default' : 'secondary'}>
            {STATUS[status]}
        </Badge>
    );
}

function Actions({
    opportunity,
    onLose,
}: {
    opportunity: Opportunity;
    onLose: () => void;
}) {
    if (opportunity.status !== 'open') {
        return (
            <div>
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => send(reopen(opportunity.id))}
                >
                    Desfazer
                </Button>
            </div>
        );
    }

    return (
        <div className="flex flex-wrap gap-2">
            <Button size="sm" onClick={() => send(win(opportunity.id))}>
                Ganhou
            </Button>
            <Button variant="outline" size="sm" onClick={onLose}>
                Perdeu
            </Button>
            <Button
                variant="ghost"
                size="sm"
                onClick={() => send(discard(opportunity.id))}
                title="Não era um orçamento de verdade (arte, boleto, tabela)"
            >
                Não era oportunidade
            </Button>
        </div>
    );
}

/** "Orçamento em 01/09 09:05 · fechou em 04/09 20:00, no atendimento seguinte." */
function story(
    opportunity: Opportunity,
    conversationId: number,
    timeZone?: string,
) {
    const here = (id: number | null) => id === conversationId;
    const place = (messageId: number, otherConversation: number | null) =>
        here(otherConversation) ? (
            <a href={`#m${messageId}`} className="underline underline-offset-2">
                ver mensagem
            </a>
        ) : otherConversation ? (
            <Link
                href={show(otherConversation)}
                className="underline underline-offset-2"
            >
                em outro atendimento
            </Link>
        ) : null;

    if (opportunity.bornWon) {
        return (
            <>
                Venda em{' '}
                {dateTime(
                    opportunity.closedAt ?? opportunity.openedAt,
                    timeZone,
                )}
                , sem orçamento antes ·{' '}
                {place(
                    opportunity.anchorMessageId,
                    opportunity.anchorConversationId,
                )}
            </>
        );
    }

    return (
        <>
            Orçamento em {dateTime(opportunity.openedAt, timeZone)} ·{' '}
            {place(
                opportunity.anchorMessageId,
                opportunity.anchorConversationId,
            )}
            {opportunity.status === 'won' && opportunity.closingMessageId && (
                <>
                    . Fechou em {dateTime(opportunity.closedAt ?? '', timeZone)}{' '}
                    ·{' '}
                    {place(
                        opportunity.closingMessageId,
                        opportunity.closingConversationId,
                    )}
                </>
            )}
            {opportunity.decided && opportunity.closedAt && (
                <>. Marcada em {date(opportunity.closedAt, timeZone)}</>
            )}
            {opportunity.lossReason && <>. Motivo: {opportunity.lossReason}</>}
        </>
    );
}
