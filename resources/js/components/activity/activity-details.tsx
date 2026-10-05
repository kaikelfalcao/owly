import { Check, Copy, Filter } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    ResultLabel,
    SeverityBadge,
    resourceText,
    browser,
} from '@/components/activity/activity-labels';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useClipboard } from '@/hooks/use-clipboard';
import { ago, fullDateTime } from '@/lib/time';
import type { ActivityEntry } from '@/types';

type Props = {
    entry: ActivityEntry | null;
    timezone: string;
    onClose: () => void;
    onOnlyResource: (entry: ActivityEntry) => void;
};

/** Painel lateral com tudo o que a auditoria guardou de um registro. */
export function ActivityDetails({
    entry,
    timezone,
    onClose,
    onOnlyResource,
}: Props) {
    return (
        <Sheet
            open={entry !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <SheetContent className="w-full gap-0 overflow-y-auto sm:max-w-md">
                {entry && (
                    <>
                        <SheetHeader className="border-b pr-10">
                            <SheetTitle>{entry.label}</SheetTitle>
                            <SheetDescription>
                                {fullDateTime(entry.at, timezone)} ·{' '}
                                {ago(entry.at)}
                            </SheetDescription>
                            <div className="flex flex-wrap items-center gap-2 pt-1">
                                <ResultLabel result={entry.result} />
                                <SeverityBadge
                                    severity={entry.severity}
                                    showNormal
                                />
                            </div>
                        </SheetHeader>

                        <div className="space-y-6 p-4">
                            <Section title="O que aconteceu">
                                <Field label="Ação">
                                    {entry.label}
                                    <Mono>{entry.action}</Mono>
                                </Field>
                                <Field label="Quem fez">
                                    {entry.user ? (
                                        (entry.user.name ?? (
                                            <Mono>#{entry.user.id}</Mono>
                                        ))
                                    ) : (
                                        <span className="text-muted-foreground">
                                            Sem login (alguém que não entrou)
                                        </span>
                                    )}
                                </Field>
                                <Field label="Recurso">
                                    {entry.resource ? (
                                        <div className="space-y-1">
                                            <div>
                                                {resourceText(entry.resource)}{' '}
                                                <Mono inline>
                                                    #{entry.resource.id}
                                                </Mono>
                                            </div>
                                            {entry.resource.key && (
                                                <Button
                                                    variant="link"
                                                    size="sm"
                                                    className="h-auto p-0"
                                                    onClick={() =>
                                                        onOnlyResource(entry)
                                                    }
                                                >
                                                    <Filter />
                                                    Ver só a atividade deste
                                                    registro
                                                </Button>
                                            )}
                                        </div>
                                    ) : (
                                        <Empty />
                                    )}
                                </Field>
                                <Field label="Quando">
                                    <Mono inline>
                                        {fullDateTime(entry.at, timezone)}
                                    </Mono>
                                    <span className="block text-xs text-muted-foreground">
                                        Fuso da empresa: {timezone}
                                    </span>
                                </Field>
                            </Section>

                            {entry.changes.length > 0 && (
                                <Section title="Alterações">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Campo</TableHead>
                                                <TableHead>Antes</TableHead>
                                                <TableHead>Depois</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {entry.changes.map((change) => (
                                                <TableRow key={change.field}>
                                                    <TableCell className="whitespace-normal">
                                                        {change.label}
                                                    </TableCell>
                                                    {change.hidden ? (
                                                        <TableCell
                                                            colSpan={2}
                                                            className="whitespace-normal text-muted-foreground"
                                                        >
                                                            Mudou. O valor não
                                                            fica na auditoria
                                                            por ser dado
                                                            pessoal.
                                                        </TableCell>
                                                    ) : (
                                                        <>
                                                            <TableCell>
                                                                <Value
                                                                    value={
                                                                        change.from
                                                                    }
                                                                />
                                                            </TableCell>
                                                            <TableCell>
                                                                <Value
                                                                    value={
                                                                        change.to
                                                                    }
                                                                />
                                                            </TableCell>
                                                        </>
                                                    )}
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </Section>
                            )}

                            {entry.meta.length > 0 && (
                                <Section title="Detalhes">
                                    {entry.meta.map((item) => (
                                        <Field
                                            key={item.field}
                                            label={item.label}
                                        >
                                            <Value value={item.value} />
                                        </Field>
                                    ))}
                                </Section>
                            )}

                            <Section title="De onde veio">
                                <Field label="Canal">
                                    {entry.channel?.label ?? <Empty />}
                                </Field>
                                <Field label="IP">
                                    {entry.ip ? (
                                        <Mono inline>{entry.ip}</Mono>
                                    ) : (
                                        <Empty />
                                    )}
                                </Field>
                                <Field label="Navegador">
                                    {entry.user_agent ? (
                                        <>
                                            {browser(entry.user_agent)}
                                            <Mono>{entry.user_agent}</Mono>
                                        </>
                                    ) : (
                                        <Empty />
                                    )}
                                </Field>
                                <Field label="Request ID">
                                    {entry.request_id ? (
                                        <CopyValue value={entry.request_id} />
                                    ) : (
                                        <Empty />
                                    )}
                                </Field>
                                <Field label="Registro">
                                    <Mono inline>#{entry.id}</Mono>
                                </Field>
                            </Section>
                        </div>
                    </>
                )}
            </SheetContent>
        </Sheet>
    );
}

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="space-y-3">
            <h3 className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {title}
            </h3>
            <dl className="space-y-3">{children}</dl>
        </section>
    );
}

function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="grid grid-cols-[7rem_1fr] gap-3 text-sm">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="min-w-0 break-words">{children}</dd>
        </div>
    );
}

function Mono({
    children,
    inline = false,
}: {
    children: ReactNode;
    inline?: boolean;
}) {
    return (
        <span
            className={
                inline
                    ? 'font-mono text-xs tabular-nums'
                    : 'block font-mono text-xs break-all text-muted-foreground'
            }
        >
            {children}
        </span>
    );
}

function Empty() {
    return <span className="text-muted-foreground">Não registrado</span>;
}

function Value({ value }: { value: string | number | boolean | null }) {
    if (value === null) {
        return <span className="text-muted-foreground">vazio</span>;
    }

    if (typeof value === 'boolean') {
        return <>{value ? 'Sim' : 'Não'}</>;
    }

    return <Mono inline>{String(value)}</Mono>;
}

function CopyValue({ value }: { value: string }) {
    const [copied, copy] = useClipboard();

    return (
        <span className="flex items-center gap-1">
            <Mono inline>{value}</Mono>
            <Button
                variant="ghost"
                size="icon"
                className="size-7"
                onClick={() => copy(value)}
                aria-label="Copiar request id"
            >
                {copied === value ? <Check /> : <Copy />}
            </Button>
        </span>
    );
}
