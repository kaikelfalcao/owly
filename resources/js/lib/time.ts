const relative = new Intl.RelativeTimeFormat('pt-BR', { numeric: 'auto' });

const STEPS: [Intl.RelativeTimeFormatUnit, number][] = [
    ['second', 60],
    ['minute', 60],
    ['hour', 24],
    ['day', 30],
    ['month', 12],
    ['year', Infinity],
];

/** "há 5 minutos", "ontem", "há 3 meses". */
export function ago(iso: string | null): string {
    if (!iso) {
        return '';
    }

    let value = (new Date(iso).getTime() - Date.now()) / 1000;

    for (const [unit, size] of STEPS) {
        if (Math.abs(value) < size) {
            return relative.format(Math.round(value), unit);
        }

        value /= size;
    }

    return '';
}

/** "05/10/2026 14:32", no fuso pedido ou, sem ele, no de quem está vendo. */
export function dateTime(iso: string, timeZone?: string): string {
    return new Date(iso).toLocaleString('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
        timeZone,
    });
}

/** "5 de outubro de 2026, 14:32:07", com segundos, para investigar. */
export function fullDateTime(iso: string, timeZone?: string): string {
    return new Date(iso).toLocaleString('pt-BR', {
        dateStyle: 'long',
        timeStyle: 'medium',
        timeZone,
    });
}
