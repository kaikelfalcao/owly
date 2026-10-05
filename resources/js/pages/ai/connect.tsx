import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    Check,
    ExternalLink,
    KeyRound,
    ShieldCheck,
    Sparkles,
} from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { create, index, store, verify } from '@/routes/ai';

type Provider = {
    id: string;
    name: string;
    company: string;
    available: boolean;
    keyUrl: string | null;
};

type Verified = {
    models: { id: string; name: string }[];
    suggested: string | null;
};

const STEPS = ['Provedor', 'Chave', 'Modelo'];

/** Assistente de conexão: escolher o provedor, colar a chave, testar e escolher o modelo. */
export default function ConnectAi({ providers }: { providers: Provider[] }) {
    const flash = usePage().flash as { verified?: Verified };
    const [step, setStep] = useState(0);
    const [verified, setVerified] = useState<Verified | null>(null);
    const form = useForm({
        provider: '',
        api_key: '',
        model: '',
        label: '',
    });
    const provider = providers.find((p) => p.id === form.data.provider);

    const choose = (p: Provider) => {
        form.setData({ ...form.data, provider: p.id, label: p.name });
        setVerified(null);
        setStep(1);
    };

    const test = () => {
        form.clearErrors();
        form.post(verify.url(), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: (page) => {
                const result = (page.flash as { verified?: Verified }).verified;

                if (result) {
                    setVerified(result);
                    form.setData(
                        'model',
                        result.suggested ?? result.models[0]?.id ?? '',
                    );
                    setStep(2);
                }
            },
        });
    };

    // Se a página recarregar com o resultado do teste (sem o onSuccess).
    const result = verified ?? flash.verified ?? null;

    return (
        <>
            <Head title="Conectar uma IA" />
            <div className="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4 md:p-6">
                <Heading
                    title="Conectar uma IA"
                    description="Três passos. A chave é testada antes de ser guardada."
                />

                <ol className="flex items-center gap-2 text-sm">
                    {STEPS.map((label, i) => (
                        <li key={label} className="flex items-center gap-2">
                            <span
                                className={cn(
                                    'flex size-6 items-center justify-center rounded-full border font-mono text-xs',
                                    i < step &&
                                        'border-primary bg-primary text-primary-foreground',
                                    i === step && 'border-primary text-primary',
                                    i > step && 'text-muted-foreground',
                                )}
                            >
                                {i < step ? (
                                    <Check className="size-3.5" />
                                ) : (
                                    i + 1
                                )}
                            </span>
                            <span
                                className={cn(
                                    i === step
                                        ? 'font-medium'
                                        : 'text-muted-foreground',
                                )}
                            >
                                {label}
                            </span>
                            {i < STEPS.length - 1 && (
                                <span className="mx-1 h-px w-6 bg-border sm:w-10" />
                            )}
                        </li>
                    ))}
                </ol>

                {step === 0 && (
                    <section className="flex flex-col gap-3">
                        <p className="text-sm text-muted-foreground">
                            Escolha de quem é a IA. Dá para conectar mais de uma
                            e escolher qual responde.
                        </p>
                        <div className="grid gap-3 sm:grid-cols-3">
                            {providers.map((p) => (
                                <button
                                    key={p.id}
                                    type="button"
                                    disabled={!p.available}
                                    onClick={() => choose(p)}
                                    data-test={`provider-${p.id}`}
                                    className={cn(
                                        'flex flex-col items-start gap-1 rounded-2xl border p-4 text-left transition-colors',
                                        p.available
                                            ? 'hover:border-primary hover:bg-primary/5'
                                            : 'cursor-not-allowed opacity-60',
                                        form.data.provider === p.id &&
                                            'border-primary bg-primary/5',
                                    )}
                                >
                                    <Sparkles className="mb-1 size-5 text-primary" />
                                    <span className="font-medium">
                                        {p.name}
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {p.available ? p.company : 'Em breve'}
                                    </span>
                                </button>
                            ))}
                        </div>
                        <InputError message={form.errors.provider} />
                    </section>
                )}

                {step === 1 && provider && (
                    <section className="flex flex-col gap-4">
                        <div className="rounded-2xl bg-muted/60 p-4 text-sm">
                            <p className="mb-2 font-medium">
                                Como pegar a chave do {provider.name}
                            </p>
                            <ol className="list-decimal space-y-1 pl-5 text-muted-foreground">
                                <li>
                                    Abra o{' '}
                                    {provider.keyUrl ? (
                                        <a
                                            href={provider.keyUrl}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="inline-flex items-center gap-1 font-medium text-primary underline-offset-4 hover:underline"
                                        >
                                            Google AI Studio
                                            <ExternalLink className="size-3" />
                                        </a>
                                    ) : (
                                        'site do provedor'
                                    )}{' '}
                                    com a sua conta Google.
                                </li>
                                <li>
                                    Clique em “Create API key” (Criar chave de
                                    API).
                                </li>
                                <li>Copie a chave e cole aqui embaixo.</li>
                            </ol>
                        </div>
                        <form
                            className="flex flex-col gap-2"
                            onSubmit={(e) => {
                                e.preventDefault();
                                test();
                            }}
                        >
                            <Label htmlFor="api_key">Chave</Label>
                            <PasswordInput
                                id="api_key"
                                value={form.data.api_key}
                                onChange={(e) =>
                                    form.setData(
                                        'api_key',
                                        e.target.value.trim(),
                                    )
                                }
                                autoComplete="off"
                                placeholder="Cole a chave aqui"
                                autoFocus
                            />
                            <InputError message={form.errors.api_key} />
                            <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                <ShieldCheck className="size-3.5" />
                                Guardada cifrada. Depois, só os últimos
                                caracteres aparecem.
                            </p>
                            <div className="mt-2 flex justify-between gap-2">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => setStep(0)}
                                >
                                    Voltar
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={
                                        form.processing || !form.data.api_key
                                    }
                                    data-test="verify-key"
                                >
                                    {form.processing ? (
                                        <Spinner />
                                    ) : (
                                        <KeyRound />
                                    )}
                                    Testar a chave
                                </Button>
                            </div>
                        </form>
                    </section>
                )}

                {step === 2 && provider && result && (
                    <form
                        className="flex flex-col gap-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(store.url());
                        }}
                    >
                        <p className="flex items-center gap-2 rounded-2xl bg-primary/10 p-3 text-sm text-primary">
                            <Check className="size-4" />
                            A chave funciona. {result.models.length}{' '}
                            {result.models.length === 1
                                ? 'modelo liberado'
                                : 'modelos liberados'}
                            .
                        </p>
                        <div className="flex flex-col gap-2">
                            <Label>Modelo</Label>
                            <Select
                                value={form.data.model}
                                onValueChange={(value) =>
                                    form.setData('model', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Escolha um modelo" />
                                </SelectTrigger>
                                <SelectContent>
                                    {result.models.map((m) => (
                                        <SelectItem key={m.id} value={m.id}>
                                            {m.name}
                                            {m.id === result.suggested
                                                ? ' (sugerido)'
                                                : ''}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <p className="text-xs text-muted-foreground">
                                O sugerido é rápido e barato, bom para ler
                                conversas.
                            </p>
                            <InputError message={form.errors.model} />
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="label">Nome da conexão</Label>
                            <Input
                                id="label"
                                value={form.data.label}
                                onChange={(e) =>
                                    form.setData('label', e.target.value)
                                }
                                maxLength={80}
                            />
                            <InputError message={form.errors.label} />
                            <InputError message={form.errors.api_key} />
                        </div>
                        <div className="flex justify-between gap-2">
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => setStep(1)}
                            >
                                Voltar
                            </Button>
                            <Button
                                type="submit"
                                disabled={form.processing || !form.data.model}
                                data-test="save-connection"
                            >
                                {form.processing ? <Spinner /> : <Check />}
                                Conectar
                            </Button>
                        </div>
                    </form>
                )}

                <Link
                    href={index()}
                    className="text-sm text-muted-foreground underline-offset-4 hover:underline"
                >
                    Cancelar
                </Link>
            </div>
        </>
    );
}

ConnectAi.layout = {
    breadcrumbs: [
        { title: 'IA', href: index() },
        { title: 'Conectar', href: create() },
    ],
};
