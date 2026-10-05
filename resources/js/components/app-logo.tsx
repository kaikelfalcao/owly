import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <AppLogoIcon className="size-8 shrink-0" />
            <span className="ml-1 truncate font-brand text-xl leading-none font-semibold tracking-tight text-[#281D7D] dark:text-foreground">
                owly
            </span>
        </>
    );
}
