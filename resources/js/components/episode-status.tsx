import { Badge } from '@/components/ui/badge';

/** Aberto até passar um dia útil inteiro sem mensagem de pessoa. */
export default function EpisodeStatus({
    status,
}: {
    status: 'open' | 'closed';
}) {
    return status === 'open' ? (
        <Badge className="bg-primary/10 text-primary">Aberto</Badge>
    ) : (
        <Badge variant="secondary">Fechado</Badge>
    );
}
