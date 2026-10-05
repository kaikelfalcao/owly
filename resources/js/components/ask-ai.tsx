import { Form, Link } from '@inertiajs/react';
import { Send, Sparkles, X } from 'lucide-react';
import { useRef } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { ago } from '@/lib/time';
import { cn } from '@/lib/utils';
import { create } from '@/routes/ai';
import { store } from '@/routes/ai/questions';

export type AiPanelProps = {
    connection: { providerName: string; model: string } | null;
    questions: {
        id: number;
        question: string;
        messageId: number | null;
        answer: string | null;
        error: string | null;
        providerName: string;
        model: string;
        createdAt: string;
    }[];
};

export type FocusedMessage = { id: number; label: string } | null;

/** A IA vê "[cliente]"; quem lê a resposta aqui pode ver o nome. */
const unmask = (text: string, contactName: string) =>
    text.replaceAll('[cliente]', contactName);

const SUGGESTIONS = [
    'Resuma a conversa',
    'O cliente comprou? O que faltou para fechar?',
    'O atendimento foi bom? O que dava para melhorar?',
];

/** Perguntar à IA sobre a conversa ou sobre uma mensagem dela. */
export default function AskAi({
    conversationId,
    contactName,
    panel,
    focused,
    onClearFocus,
}: {
    conversationId: number;
    contactName: string;
    panel: AiPanelProps;
    focused: FocusedMessage;
    onClearFocus: () => void;
}) {
    const textarea = useRef<HTMLTextAreaElement>(null);

    if (!panel.connection) {
        return (
            <div className="flex flex-col gap-2 rounded-2xl border p-4 text-sm">
                <p className="flex items-center gap-2 font-medium">
                    <Sparkles className="size-4 text-primary" />
                    Perguntar à IA
                </p>
                <p className="text-muted-foreground">
                    Conecte uma IA para perguntar sobre esta conversa.
                </p>
                <Button asChild size="sm" variant="outline">
                    <Link href={create()}>Conectar uma IA</Link>
                </Button>
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-3 rounded-2xl border p-4 text-sm">
            <p className="flex items-center gap-2 font-medium">
                <Sparkles className="size-4 text-primary" />
                Perguntar à IA
            </p>

            <Form
                {...store.form(conversationId)}
                options={{ preserveScroll: true }}
                resetOnSuccess={['question']}
                onSuccess={onClearFocus}
                className="flex flex-col gap-2"
            >
                {({ processing, errors }) => (
                    <>
                        {focused && (
                            <p className="flex items-start gap-1 rounded-lg bg-primary/10 px-2 py-1 text-xs text-primary">
                                <span className="line-clamp-2 flex-1">
                                    Sobre a mensagem: {focused.label}
                                </span>
                                <button
                                    type="button"
                                    onClick={onClearFocus}
                                    aria-label="Perguntar sobre a conversa toda"
                                >
                                    <X className="size-3.5" />
                                </button>
                                <input
                                    type="hidden"
                                    name="message_id"
                                    value={focused.id}
                                />
                            </p>
                        )}
                        <textarea
                            ref={textarea}
                            name="question"
                            rows={3}
                            maxLength={1000}
                            required
                            placeholder={
                                focused
                                    ? 'O que quer saber sobre essa mensagem?'
                                    : 'O que quer saber sobre esta conversa?'
                            }
                            className="w-full resize-y rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            data-test="ai-question"
                        />
                        <InputError
                            message={errors.question ?? errors.message_id}
                        />
                        {!focused && (
                            <div className="flex flex-wrap gap-1">
                                {SUGGESTIONS.map((s) => (
                                    <button
                                        key={s}
                                        type="button"
                                        className="rounded-full border px-2 py-0.5 text-xs text-muted-foreground hover:border-primary hover:text-primary"
                                        onClick={() => {
                                            if (textarea.current) {
                                                textarea.current.value = s;
                                                textarea.current.focus();
                                            }
                                        }}
                                    >
                                        {s}
                                    </button>
                                ))}
                            </div>
                        )}
                        <Button
                            type="submit"
                            size="sm"
                            disabled={processing}
                            data-test="ai-ask"
                        >
                            {processing ? <Spinner /> : <Send />}
                            {processing ? 'Lendo a conversa…' : 'Perguntar'}
                        </Button>
                        <p className="text-xs text-muted-foreground">
                            {panel.connection?.providerName} lê a conversa com
                            nome, telefone e documentos escondidos.
                        </p>
                    </>
                )}
            </Form>

            {panel.questions.length > 0 && (
                <ul className="flex flex-col gap-3 border-t pt-3">
                    {panel.questions.map((q) => (
                        <li key={q.id} className="flex flex-col gap-1">
                            <p className="font-medium">
                                {q.question}
                                {q.messageId && (
                                    <span className="ml-1 text-xs font-normal text-muted-foreground">
                                        (sobre uma mensagem)
                                    </span>
                                )}
                            </p>
                            <p
                                className={cn(
                                    'break-words whitespace-pre-line',
                                    q.error
                                        ? 'text-destructive'
                                        : 'text-foreground/90',
                                )}
                            >
                                {q.answer
                                    ? unmask(q.answer, contactName)
                                    : q.error}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {q.providerName} · {ago(q.createdAt)}
                            </p>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
