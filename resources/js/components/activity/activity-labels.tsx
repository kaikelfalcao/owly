import { CircleCheck, CircleX } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { ActivityEntry, ActivityResult, ActivitySeverity } from '@/types';

const SEVERITY: Record<ActivitySeverity, string> = {
    normal: 'Normal',
    important: 'Importante',
    critical: 'Crítico',
};

/**
 * Criticidade discreta: Normal não aparece na lista, Importante é só o
 * contorno e Crítico ganha o âmbar de "pede atenção". Nada de vermelho:
 * vermelho é erro.
 */
export function SeverityBadge({
    severity,
    showNormal = false,
}: {
    severity: ActivitySeverity;
    showNormal?: boolean;
}) {
    if (severity === 'normal' && !showNormal) {
        return null;
    }

    return (
        <Badge
            variant="outline"
            className={cn(
                severity === 'critical' &&
                    'border-transparent bg-highlight text-highlight-foreground',
                severity === 'normal' && 'text-muted-foreground',
            )}
        >
            {SEVERITY[severity]}
        </Badge>
    );
}

export function ResultLabel({ result }: { result: ActivityResult }) {
    return result === 'failure' ? (
        <span className="inline-flex items-center gap-1 text-sm text-destructive">
            <CircleX className="size-4" />
            Falhou
        </span>
    ) : (
        <span className="inline-flex items-center gap-1 text-sm text-muted-foreground">
            <CircleCheck className="size-4" />
            Concluída
        </span>
    );
}

/** "Dono: Ana Dona", ou só o tipo quando o registro não tem nome. */
export function resourceText(
    resource: NonNullable<ActivityEntry['resource']>,
): string {
    return resource.name
        ? `${resource.label}: ${resource.name}`
        : (resource.label ?? '');
}

/** Quem fez, como a lista mostra. */
export function userText(user: ActivityEntry['user']): string {
    if (!user) {
        return 'Sem login';
    }

    return user.name ?? `#${user.id}`;
}

/**
 * "Chrome no Windows": um resumo do navegador para ler rápido. O texto
 * completo continua nos detalhes.
 */
export function browser(userAgent: string): string {
    const name =
        [
            ['Edg/', 'Edge'],
            ['OPR/', 'Opera'],
            ['Firefox/', 'Firefox'],
            ['Chrome/', 'Chrome'],
            ['Safari/', 'Safari'],
        ].find(([token]) => userAgent.includes(token))?.[1] ?? 'Navegador';

    const system =
        [
            ['Android', 'Android'],
            ['iPhone', 'iPhone'],
            ['iPad', 'iPad'],
            ['Windows', 'Windows'],
            ['Mac OS', 'Mac'],
            ['Linux', 'Linux'],
        ].find(([token]) => userAgent.includes(token))?.[1] ?? null;

    return system ? `${name} no ${system}` : name;
}
