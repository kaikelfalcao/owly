import { Link } from '@inertiajs/react';
import { Clock, MessageCircleWarning, Sparkles } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import OwlyMascot from '@/components/owly-mascot';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

const POINTS = [
    {
        icon: MessageCircleWarning,
        text: 'Mostra o cliente que ficou sem resposta.',
    },
    {
        icon: Clock,
        text: 'Mede quanto tempo a equipe leva para responder.',
    },
    {
        icon: Sparkles,
        text: 'Pergunta à IA o que aconteceu numa conversa.',
    },
];

/**
 * Entrar: formulário à esquerda e, em telas largas, o painel lilás com a
 * coruja e o que a Owly faz.
 */
export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="grid min-h-dvh lg:grid-cols-2">
            <div className="flex flex-col px-6 py-8 sm:px-10">
                <Link
                    href={home()}
                    className="flex items-center gap-1 self-start"
                >
                    <AppLogo />
                </Link>
                <div className="flex flex-1 items-center justify-center py-10">
                    <div className="flex w-full max-w-sm flex-col gap-6">
                        <div className="flex flex-col gap-2">
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {title}
                            </h1>
                            <p className="text-sm text-balance text-muted-foreground">
                                {description}
                            </p>
                        </div>
                        {children}
                    </div>
                </div>
            </div>
            <div className="relative hidden overflow-hidden bg-primary/10 p-10 lg:flex lg:flex-col lg:justify-between dark:bg-primary/15">
                <div className="absolute -top-24 -right-24 size-96 rounded-full bg-primary/10" />
                <div className="absolute -bottom-32 -left-16 size-80 rounded-full bg-primary/5" />
                <p className="relative max-w-sm text-3xl leading-tight font-semibold">
                    Veja onde a venda escapou nas conversas do WhatsApp.
                </p>
                <OwlyMascot className="relative mx-auto h-72 w-auto drop-shadow-xl" />
                <ul className="relative flex flex-col gap-3 text-sm">
                    {POINTS.map(({ icon: Icon, text }) => (
                        <li key={text} className="flex items-center gap-3">
                            <span className="flex size-8 items-center justify-center rounded-full bg-primary text-primary-foreground">
                                <Icon className="size-4" />
                            </span>
                            {text}
                        </li>
                    ))}
                </ul>
            </div>
        </div>
    );
}
