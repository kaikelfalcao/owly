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

/**
 * Datas sempre no fuso da empresa (o horário comercial é o dela), não no do
 * navegador de quem está vendo.
 */
const format = (
    iso: string,
    timeZone: string | undefined,
    options: Intl.DateTimeFormatOptions,
) => new Date(iso).toLocaleString('pt-BR', { timeZone, ...options });

/** "05/10/2026 14:32" */
export function dateTime(iso: string, timeZone?: string): string {
    return format(iso, timeZone, { dateStyle: 'short', timeStyle: 'short' });
}

/** "05/10/2026" */
export function date(iso: string, timeZone?: string): string {
    return format(iso, timeZone, { dateStyle: 'short' });
}

/** "14:32" */
export function time(iso: string, timeZone?: string): string {
    return format(iso, timeZone, { hour: '2-digit', minute: '2-digit' });
}

/** "segunda-feira, 5 de outubro" */
export function dayLabel(iso: string, timeZone?: string): string {
    return format(iso, timeZone, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    });
}

/** "outubro de 2026" */
export function monthLabel(iso: string, timeZone?: string): string {
    return format(iso, timeZone, { month: 'long', year: 'numeric' });
}

/** Chave para agrupar por dia no fuso dado: "2026-10-05". */
export function dayKey(iso: string, timeZone?: string): string {
    return new Date(iso).toLocaleDateString('en-CA', { timeZone });
}
