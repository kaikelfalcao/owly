import { Head, Link } from '@inertiajs/react';
import { KeyRound, Plus, Sparkles, Star, Trash2 } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import OwlyMascot from '@/components/owly-mascot';
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
import { send } from '@/lib/visit';
import { create, destroy, index, makeDefault } from '@/routes/ai';

type Connection = {
    id: number;
    provider: string;
    providerName: string;
    label: string;
    model: string;
    keyHint: string;
    isDefault: boolean;
    createdAt: string;
};

/** IA: as conexões da empresa com provedores de IA. */
export default function AiConnections({
    connections,
}: {
    connections: Connection[];
}) {
    const [removing, setRemoving] = useState<Connection | null>(null);

    return (
        <>
            <Head title="IA" />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="IA"
                        description="A IA lê uma conversa quando você pergunta. Ela sugere; quem decide é você."
                    />
                    {connections.length > 0 && (
                        <Button asChild>
                            <Link href={create()}>
                                <Plus />
                                Conectar outra
                            </Link>
                        </Button>
                    )}
                </div>

                {connections.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 rounded-2xl border px-6 py-10 text-center">
                        <OwlyMascot className="h-28 w-auto" />
                        <p className="font-medium">Nenhuma IA conectada</p>
                        <p className="max-w-md text-sm text-balance text-muted-foreground">
                            Conecte uma chave do Gemini para perguntar sobre
                            qualquer conversa: se o cliente comprou, como foi o
                            atendimento, o que ele pediu.
                        </p>
                        <Button asChild>
                            <Link href={create()} data-test="connect-ai">
                                <Sparkles />
                                Conectar uma IA
                            </Link>
                        </Button>
                    </div>
                ) : (
                    <ul className="flex flex-col gap-3">
                        {connections.map((connection) => (
                            <li
                                key={connection.id}
                                className="flex flex-wrap items-center gap-3 rounded-2xl border p-4"
                            >
                                <span className="flex size-10 items-center justify-center rounded-full bg-primary/10 text-primary">
                                    <Sparkles className="size-5" />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <p className="flex flex-wrap items-center gap-2 font-medium">
                                        {connection.label}
                                        {connection.isDefault && (
                                            <Badge>Padrão</Badge>
                                        )}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {connection.providerName} ·{' '}
                                        <span className="font-mono text-xs">
                                            {connection.model}
                                        </span>{' '}
                                        ·{' '}
                                        <span className="inline-flex items-center gap-1 font-mono text-xs">
                                            <KeyRound className="size-3" />
                                            {connection.keyHint}
                                        </span>
                                    </p>
                                </div>
                                <div className="flex gap-2">
                                    {!connection.isDefault && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                send(makeDefault(connection.id))
                                            }
                                        >
                                            <Star />
                                            Usar esta
                                        </Button>
                                    )}
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setRemoving(connection)}
                                        aria-label={`Remover ${connection.label}`}
                                    >
                                        <Trash2 />
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}

                <p className="text-sm text-muted-foreground">
                    Antes de sair para o provedor, a conversa passa por uma
                    máscara: nome do cliente, telefone, e-mail, CPF, CNPJ e CEP
                    viram marcadores. A chave fica cifrada e só aparece pelos
                    últimos caracteres.
                </p>
            </div>

            <Dialog
                open={removing !== null}
                onOpenChange={(open) => !open && setRemoving(null)}
            >
                <DialogContent>
                    <DialogTitle>Remover “{removing?.label}”?</DialogTitle>
                    <DialogDescription>
                        A chave é apagada da Owly. As respostas que essa conexão
                        já deu continuam nas conversas.
                    </DialogDescription>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">Cancelar</Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            onClick={() => {
                                if (removing) {
                                    send(destroy(removing.id), {
                                        onFinish: () => setRemoving(null),
                                    });
                                }
                            }}
                        >
                            Remover
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

AiConnections.layout = {
    breadcrumbs: [{ title: 'IA', href: index() }],
};
