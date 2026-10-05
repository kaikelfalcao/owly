import { router, usePage } from '@inertiajs/react';
import { AlertTriangle, Bell, BellOff, CheckCheck, FileUp } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { ago } from '@/lib/time';
import { cn } from '@/lib/utils';
import { open as openNotification, readAll } from '@/routes/notifications';
import type { AppNotification } from '@/types';

// Ícone e cor de cada tipo de aviso. Tipo novo entra aqui.
const KIND: Record<string, [LucideIcon, string]> = {
    import: [FileUp, 'bg-primary/10 text-primary'],
    failed: [AlertTriangle, 'bg-destructive/10 text-destructive'],
};

const open = (n: AppNotification) => router.visit(openNotification(n.id));

/**
 * Sino do topo. Confere a cada 30 s enquanto a aba está visível e mostra um
 * toast quando chega um aviso novo.
 */
export function NotificationBell() {
    const data = usePage().props.notifications;
    const seen = useRef<Set<string> | null>(null);

    useEffect(() => {
        const timer = setInterval(() => {
            if (document.visibilityState === 'visible') {
                router.reload({ only: ['notifications'], showProgress: false });
            }
        }, 30000);

        return () => clearInterval(timer);
    }, []);

    // Avisos que chegaram com a tela aberta viram toast.
    useEffect(() => {
        const items = data?.items ?? [];

        if (seen.current === null) {
            seen.current = new Set(items.map((n) => n.id));

            return;
        }

        for (const n of items) {
            if (!seen.current.has(n.id) && !n.read) {
                toast(n.title, {
                    description: n.body ?? undefined,
                    action: n.url
                        ? { label: 'Abrir', onClick: () => open(n) }
                        : undefined,
                });
            }

            seen.current.add(n.id);
        }
    }, [data?.items]);

    if (!data) {
        return null;
    }

    const unread = data.unread;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative rounded-full"
                    aria-label={
                        unread
                            ? `Notificações, ${unread} não lidas`
                            : 'Notificações'
                    }
                >
                    <Bell />
                    {unread > 0 && (
                        <span className="absolute top-1 right-1 flex min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] leading-4 font-semibold text-white ring-2 ring-background">
                            {unread > 9 ? '9+' : unread}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                className="w-[min(380px,calc(100vw-2rem))] p-0"
            >
                <div className="flex items-center justify-between border-b px-4 py-3">
                    <div>
                        <p className="text-sm font-semibold">Notificações</p>
                        <p className="text-xs text-muted-foreground">
                            {unread
                                ? `${unread} não ${unread === 1 ? 'lida' : 'lidas'}`
                                : 'Tudo em dia'}
                        </p>
                    </div>
                    {unread > 0 && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() =>
                                router.post(
                                    readAll(),
                                    {},
                                    {
                                        preserveScroll: true,
                                        preserveState: true,
                                        only: ['notifications'],
                                    },
                                )
                            }
                        >
                            <CheckCheck /> Marcar como lidas
                        </Button>
                    )}
                </div>
                {data.items.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 px-6 py-10 text-center text-sm text-muted-foreground">
                        <BellOff className="size-5" />
                        Nenhum aviso por enquanto. Quando uma importação
                        terminar, ela aparece aqui.
                    </div>
                ) : (
                    <ul className="max-h-[420px] overflow-y-auto py-1">
                        {data.items.map((n) => {
                            const [Icon, tone] = KIND[n.kind] ?? [
                                Bell,
                                'bg-muted text-muted-foreground',
                            ];

                            return (
                                <li key={n.id}>
                                    <button
                                        type="button"
                                        onClick={() => open(n)}
                                        className={cn(
                                            'flex w-full items-start gap-3 px-4 py-2.5 text-left transition-colors hover:bg-accent',
                                            !n.read && 'bg-accent/40',
                                        )}
                                    >
                                        <span
                                            className={cn(
                                                'mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full',
                                                tone,
                                            )}
                                        >
                                            <Icon className="size-4" />
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <span
                                                className={cn(
                                                    'block text-sm leading-snug',
                                                    !n.read && 'font-semibold',
                                                )}
                                            >
                                                {n.title}
                                            </span>
                                            {n.body && (
                                                <span className="block truncate text-xs text-muted-foreground">
                                                    {n.body}
                                                </span>
                                            )}
                                            <span className="mt-0.5 block text-[11px] text-muted-foreground">
                                                {ago(n.at)}
                                            </span>
                                        </span>
                                        {!n.read && (
                                            <span
                                                className="mt-2 size-2 shrink-0 rounded-full bg-primary"
                                                aria-label="Não lida"
                                            />
                                        )}
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
