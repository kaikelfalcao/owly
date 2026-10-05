import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { index as activity } from '@/routes/activity';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editHours } from '@/routes/business-hours';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Perfil',
        href: edit(),
        icon: null,
    },
    {
        title: 'Segurança',
        href: editSecurity(),
        icon: null,
    },
    {
        title: 'Horário',
        href: editHours(),
        icon: null,
    },
    {
        title: 'Aparência',
        href: editAppearance(),
        icon: null,
    },
    {
        title: 'Atividade',
        href: activity(),
        icon: null,
    },
];

/** `wide`: a tela usa a largura toda (tabela da Atividade), em vez da coluna estreita dos formulários. */
export default function SettingsLayout({
    children,
    wide = false,
}: PropsWithChildren<{ wide?: boolean }>) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <div className="px-4 py-6">
            <Heading
                title="Minha conta"
                description="Seus dados de entrada, o horário da empresa e o que aconteceu na conta"
            />

            <div className="flex flex-col lg:flex-row lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-48">
                    <nav
                        className="flex flex-col space-y-1 space-x-0"
                        aria-label="Minha conta"
                    >
                        {sidebarNavItems.map((item, index) => (
                            <Button
                                key={`${toUrl(item.href)}-${index}`}
                                size="sm"
                                variant="ghost"
                                asChild
                                className={cn('w-full justify-start', {
                                    'bg-muted': isCurrentOrParentUrl(item.href),
                                })}
                            >
                                <Link href={item.href}>
                                    {item.icon && (
                                        <item.icon className="h-4 w-4" />
                                    )}
                                    {item.title}
                                </Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="my-6 lg:hidden" />

                <div className={cn('min-w-0 flex-1', !wide && 'md:max-w-2xl')}>
                    <section className={cn('space-y-12', !wide && 'max-w-xl')}>
                        {children}
                    </section>
                </div>
            </div>
        </div>
    );
}
