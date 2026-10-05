import { Head, usePage } from '@inertiajs/react';
import OwlyMascot from '@/components/owly-mascot';
import { dashboard } from '@/routes';

export default function Dashboard() {
    const { auth } = usePage().props;
    const firstName = auth.user.name.split(' ')[0];

    return (
        <>
            <Head title="Painel" />
            <div className="flex flex-1 items-center justify-center p-6">
                <div className="flex max-w-md flex-col items-center gap-4 text-center">
                    <OwlyMascot className="h-40 w-auto" />
                    <h1 className="text-2xl font-semibold">Olá, {firstName}</h1>
                    <p className="text-balance text-muted-foreground">
                        A Owly está pronta. Assim que a importação do zip das
                        conversas chegar, é aqui que você vai ver onde a venda
                        escapou.
                    </p>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Painel',
            href: dashboard(),
        },
    ],
};
