import { Link } from '@inertiajs/react';
import { Clock, MessageCircleWarning, Sparkles } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import AppLogoIcon from '@/components/app-logo-icon';
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
 * Entrar: formulário à esquerda e, em telas largas, o painel violeta com a
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
            <div className="relative hidden overflow-hidden bg-primary p-10 text-primary-foreground lg:flex lg:flex-col lg:justify-between">
                <div className="absolute -top-24 -right-24 size-96 rounded-full bg-white/10" />
                <div className="absolute -bottom-32 -left-16 size-80 rounded-full bg-white/5" />
                <p className="relative max-w-sm text-3xl leading-tight font-semibold">
                    Veja onde a venda escapou nas conversas do WhatsApp.
                </p>
                <div className="relative mx-auto rounded-full bg-white/90 p-10 shadow-2xl">
                    <AppLogoIcon className="size-44" />
                </div>
                <ul className="relative flex flex-col gap-3 text-sm">
                    {POINTS.map(({ icon: Icon, text }) => (
                        <li key={text} className="flex items-center gap-3">
                            <span className="flex size-8 items-center justify-center rounded-full bg-white/15">
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
