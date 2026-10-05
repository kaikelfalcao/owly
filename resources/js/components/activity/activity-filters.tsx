import { Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import type {
    ActivityFilters as Filters,
    ActivityOption,
    ActivityOptions,
} from '@/types';

const ALL = 'all';

const PERIODS: ActivityOption[] = [
    { value: ALL, label: 'Todo o período' },
    { value: 'today', label: 'Hoje' },
    { value: 'yesterday', label: 'Ontem' },
    { value: '7d', label: 'Últimos 7 dias' },
    { value: '30d', label: 'Últimos 30 dias' },
    { value: 'month', label: 'Este mês' },
    { value: 'custom', label: 'Escolher datas' },
];

type Props = {
    filters: Filters;
    options: ActivityOptions;
    errors: Partial<Record<string, string>>;
    onChange: (filters: Filters) => void;
};

/**
 * Busca, período e os outros filtros da Atividade. Tudo vai para o servidor
 * junto; a busca espera a pessoa parar de digitar. Quem usa troca a `key`
 * para limpar o que está digitado.
 */
export function ActivityFilters({ filters, options, errors, onChange }: Props) {
    const [q, setQ] = useState(filters.q ?? '');
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');
    const [custom, setCustom] = useState(filters.period === 'custom');

    const timer = useRef<ReturnType<typeof setTimeout>>(undefined);

    useEffect(() => () => clearTimeout(timer.current), []);

    const search = (value: string) => {
        setQ(value);
        clearTimeout(timer.current);
        timer.current = setTimeout(
            () => onChange({ ...filters, q: value.trim() || undefined }),
            350,
        );
    };

    const set = (key: keyof Filters, value: string) => {
        onChange({ ...filters, [key]: value === ALL ? undefined : value });
    };

    const setPeriod = (value: string) => {
        if (value === 'custom') {
            // Só busca quando as duas datas estiverem escolhidas.
            setCustom(true);

            return;
        }

        setCustom(false);
        onChange({
            ...filters,
            period: value === ALL ? undefined : value,
            from: undefined,
            to: undefined,
        });
    };

    const setRange = (nextFrom: string, nextTo: string) => {
        setFrom(nextFrom);
        setTo(nextTo);

        if (nextFrom && nextTo && nextFrom <= nextTo) {
            onChange({
                ...filters,
                period: 'custom',
                from: nextFrom,
                to: nextTo,
            });
        }
    };

    const rangeError =
        from && to && from > to
            ? 'O último dia precisa ser igual ou depois do primeiro.'
            : (errors.from ?? errors.to);

    return (
        <div className="space-y-3">
            <div className="relative">
                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    type="search"
                    value={q}
                    onChange={(e) => search(e.target.value)}
                    placeholder="Buscar por nome, ação, IP, request id ou #id do registro"
                    aria-label="Buscar na atividade"
                    className="pl-9"
                />
            </div>
            <InputError message={errors.q} />

            <div className="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                <Filter
                    label="Período"
                    value={custom ? 'custom' : (filters.period ?? ALL)}
                    options={PERIODS}
                    onChange={setPeriod}
                />
                <Filter
                    label="Quem"
                    value={filters.user ?? ALL}
                    options={[
                        { value: ALL, label: 'Todos' },
                        ...options.users,
                        { value: 'none', label: 'Sem login' },
                    ]}
                    onChange={(value) => set('user', value)}
                />
                <Filter
                    label="Ação"
                    value={filters.action ?? ALL}
                    options={[
                        { value: ALL, label: 'Todas' },
                        ...options.actions,
                    ]}
                    onChange={(value) => set('action', value)}
                />
                <Filter
                    label="Recurso"
                    value={filters.resource ?? ALL}
                    options={[
                        { value: ALL, label: 'Todos' },
                        ...options.resources,
                    ]}
                    onChange={(value) =>
                        onChange({
                            ...filters,
                            resource: value === ALL ? undefined : value,
                            resource_id: undefined,
                        })
                    }
                />
                <Filter
                    label="Resultado"
                    value={filters.result ?? ALL}
                    options={[
                        { value: ALL, label: 'Todos' },
                        ...options.results,
                    ]}
                    onChange={(value) => set('result', value)}
                />
                <Filter
                    label="Criticidade"
                    value={filters.severity ?? ALL}
                    options={[
                        { value: ALL, label: 'Todas' },
                        ...options.severities,
                    ]}
                    onChange={(value) => set('severity', value)}
                />
            </div>

            {custom && (
                <div className="space-y-1">
                    <div className="flex flex-wrap items-end gap-2">
                        <div className="grid gap-1">
                            <Label htmlFor="activity-from" className="text-xs">
                                De
                            </Label>
                            <Input
                                id="activity-from"
                                type="date"
                                value={from}
                                max={to || undefined}
                                onChange={(e) => setRange(e.target.value, to)}
                                className="w-40"
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="activity-to" className="text-xs">
                                Até
                            </Label>
                            <Input
                                id="activity-to"
                                type="date"
                                value={to}
                                min={from || undefined}
                                onChange={(e) => setRange(from, e.target.value)}
                                className="w-40"
                            />
                        </div>
                    </div>
                    <InputError message={rangeError} />
                </div>
            )}
        </div>
    );
}

function Filter({
    label,
    value,
    options,
    onChange,
}: {
    label: string;
    value: string;
    options: ActivityOption[];
    onChange: (value: string) => void;
}) {
    const active = value !== ALL;

    return (
        <Select value={value} onValueChange={onChange}>
            <SelectTrigger
                size="sm"
                aria-label={label}
                className={cn(
                    'w-full sm:w-auto sm:max-w-64',
                    active && 'border-primary/50',
                )}
            >
                <span className="truncate">
                    <span className="text-muted-foreground">{label}: </span>
                    <SelectValue />
                </span>
            </SelectTrigger>
            <SelectContent>
                {options.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

export function hasFilters(filters: Filters): boolean {
    return Object.values(filters).some((value) => !!value);
}

export function ClearFilters({ onClear }: { onClear: () => void }) {
    return (
        <Button variant="ghost" size="sm" onClick={onClear}>
            <X />
            Limpar filtros
        </Button>
    );
}
