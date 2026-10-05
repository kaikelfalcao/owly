import { usePage } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';

/** Avatar, nome e empresa no topo; abre o menu da conta. */
export function HeaderUser() {
    const { auth } = usePage().props;
    const initials = useInitials();

    if (!auth.user) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    className="flex items-center gap-2 rounded-full py-1 pr-2 pl-1 transition-[background-color,transform] duration-150 hover:bg-accent active:scale-[0.97] data-[state=open]:bg-accent"
                    data-test="header-user-button"
                >
                    <Avatar className="size-8 overflow-hidden rounded-full">
                        <AvatarImage
                            src={auth.user.avatar}
                            alt={auth.user.name}
                        />
                        <AvatarFallback className="rounded-full bg-primary/10 text-xs font-medium text-primary">
                            {initials(auth.user.name)}
                        </AvatarFallback>
                    </Avatar>
                    <span className="hidden text-left leading-tight sm:block">
                        <span className="block max-w-36 truncate text-sm font-medium">
                            {auth.user.name}
                        </span>
                        {auth.organization && (
                            <span className="block max-w-36 truncate text-xs text-muted-foreground">
                                {auth.organization.name}
                            </span>
                        )}
                    </span>
                    <ChevronDown className="size-4 text-muted-foreground" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="min-w-56 rounded-lg">
                <UserMenuContent user={auth.user} />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
