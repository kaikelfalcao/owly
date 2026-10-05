import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { Fragment } from 'react';
import { HeaderUser } from '@/components/header-user';
import { NotificationBell } from '@/components/notification-bell';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { toUrl } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

/**
 * Barra do topo: a trilha da página à esquerda (o último item é o nome da
 * tela) e, à direita, o sino e o usuário com o menu da conta.
 */
export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItem[];
}) {
    const parents = breadcrumbs.slice(0, -1);
    const current = breadcrumbs.at(-1);

    return (
        <header className="flex h-16 shrink-0 items-center gap-3 border-b border-sidebar-border/50 px-4 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12">
            <SidebarTrigger className="-ml-1" />
            <nav
                aria-label="Você está em"
                className="flex min-w-0 flex-1 items-center gap-1.5 text-sm"
            >
                {parents.map((item) => (
                    <Fragment key={item.title}>
                        {toUrl(item.href) ? (
                            <Link
                                href={item.href}
                                className="hidden truncate text-muted-foreground transition-colors hover:text-foreground md:inline"
                            >
                                {item.title}
                            </Link>
                        ) : (
                            <span className="hidden truncate text-muted-foreground md:inline">
                                {item.title}
                            </span>
                        )}
                        <ChevronRight
                            className="hidden size-3.5 shrink-0 text-muted-foreground/60 md:inline"
                            aria-hidden
                        />
                    </Fragment>
                ))}
                {current && (
                    <h2
                        className="truncate text-base font-semibold"
                        aria-current="page"
                    >
                        {current.title}
                    </h2>
                )}
            </nav>
            <div className="flex shrink-0 items-center gap-1.5 sm:gap-2">
                <NotificationBell />
                <HeaderUser />
            </div>
        </header>
    );
}
