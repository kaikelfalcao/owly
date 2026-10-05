import { Link, router } from '@inertiajs/react';
import { LogOut, Monitor, Moon, Sun, UserRound } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useAppearance } from '@/hooks/use-appearance';
import type { Appearance } from '@/hooks/use-appearance';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

const THEMES: [Appearance, string, LucideIcon][] = [
    ['light', 'Claro', Sun],
    ['dark', 'Escuro', Moon],
    ['system', 'Do sistema', Monitor],
];

type Props = {
    user: User;
};

export function UserMenuContent({ user }: Props) {
    const cleanup = useMobileNavigation();
    const { appearance, updateAppearance } = useAppearance();

    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={edit()}
                        prefetch
                        onClick={cleanup}
                    >
                        <UserRound className="mr-2" />
                        Minha conta
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuLabel className="text-xs font-normal text-muted-foreground">
                Tema
            </DropdownMenuLabel>
            <DropdownMenuRadioGroup
                value={appearance}
                onValueChange={(value) => updateAppearance(value as Appearance)}
            >
                {THEMES.map(([value, label, Icon]) => (
                    <DropdownMenuRadioItem
                        key={value}
                        value={value}
                        onSelect={(event) => event.preventDefault()}
                    >
                        <Icon className="mr-2" />
                        {label}
                    </DropdownMenuRadioItem>
                ))}
            </DropdownMenuRadioGroup>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
                <Link
                    className="block w-full cursor-pointer"
                    href={logout()}
                    as="button"
                    onClick={handleLogout}
                    data-test="logout-button"
                >
                    <LogOut className="mr-2" />
                    Sair
                </Link>
            </DropdownMenuItem>
        </>
    );
}
