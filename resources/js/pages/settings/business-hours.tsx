import { Head, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit, update } from '@/routes/business-hours';

type Day = 'mon' | 'tue' | 'wed' | 'thu' | 'fri' | 'sat' | 'sun';
type Range = [string, string] | null;
type Holiday = { date: string; name: string };

type Props = {
    hours: Record<Day, Range>;
    timezone: string;
    nationalHolidays: boolean;
    holidays: Holiday[];
};

const DAYS: { key: Day; label: string }[] = [
    { key: 'mon', label: 'Segunda' },
    { key: 'tue', label: 'Terça' },
    { key: 'wed', label: 'Quarta' },
    { key: 'thu', label: 'Quinta' },
    { key: 'fri', label: 'Sexta' },
    { key: 'sat', label: 'Sábado' },
    { key: 'sun', label: 'Domingo' },
];

/**
 * Horário de atendimento: o painel só conta como espera o tempo em que a
 * empresa está aberta.
 */
export default function BusinessHours(props: Props) {
    const form = useForm({
        hours: props.hours,
        national_holidays: props.nationalHolidays,
        holidays: props.holidays,
    });
    const errors = form.errors as Record<string, string | undefined>;

    const setDay = (day: Day, range: Range) =>
        form.setData('hours', { ...form.data.hours, [day]: range });

    const setHoliday = (index: number, holiday: Holiday) =>
        form.setData(
            'holidays',
            form.data.holidays.map((h, i) => (i === index ? holiday : h)),
        );

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(update(), { preserveScroll: true });
    };

    return (
        <>
            <Head title="Horário" />

            <h1 className="sr-only">Horário</h1>

            <form onSubmit={submit} className="space-y-10">
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Horário de atendimento"
                        description="Uma mensagem que chega na sexta às 19h e é respondida na segunda às 8h05 conta 5 minutos de espera, não um fim de semana inteiro."
                    />

                    <div className="divide-y rounded-xl border">
                        {DAYS.map(({ key, label }) => {
                            const range = form.data.hours[key];
                            const error =
                                errors[`hours.${key}`] ??
                                errors[`hours.${key}.0`] ??
                                errors[`hours.${key}.1`];

                            return (
                                <div key={key} className="px-3 py-2">
                                    <div className="flex flex-wrap items-center gap-3">
                                        <label className="flex w-28 items-center gap-2 text-sm">
                                            <Checkbox
                                                checked={range !== null}
                                                onCheckedChange={(checked) =>
                                                    setDay(
                                                        key,
                                                        checked
                                                            ? ['08:00', '18:00']
                                                            : null,
                                                    )
                                                }
                                                aria-label={`Abre na ${label.toLowerCase()}`}
                                            />
                                            {label}
                                        </label>
                                        {range ? (
                                            <div className="flex items-center gap-2 text-sm">
                                                <Input
                                                    type="time"
                                                    value={range[0]}
                                                    onChange={(e) =>
                                                        setDay(key, [
                                                            e.target.value,
                                                            range[1],
                                                        ])
                                                    }
                                                    className="w-28 font-mono"
                                                    aria-label={`${label}: abre`}
                                                    required
                                                />
                                                às
                                                <Input
                                                    type="time"
                                                    value={range[1]}
                                                    onChange={(e) =>
                                                        setDay(key, [
                                                            range[0],
                                                            e.target.value,
                                                        ])
                                                    }
                                                    className="w-28 font-mono"
                                                    aria-label={`${label}: fecha`}
                                                    required
                                                />
                                            </div>
                                        ) : (
                                            <span className="text-sm text-muted-foreground">
                                                Fechado
                                            </span>
                                        )}
                                    </div>
                                    <InputError message={error} />
                                </div>
                            );
                        })}
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Horário de {props.timezone.replace('_', ' ')}.
                    </p>
                </div>

                <div className="space-y-4">
                    <Heading
                        variant="small"
                        title="Feriados"
                        description="Em feriado a empresa conta como fechada."
                    />

                    <label className="flex items-start gap-2 text-sm">
                        <Checkbox
                            checked={form.data.national_holidays}
                            onCheckedChange={(checked) =>
                                form.setData(
                                    'national_holidays',
                                    checked === true,
                                )
                            }
                            className="mt-0.5"
                        />
                        <span>
                            Feriados nacionais
                            <span className="block text-xs text-muted-foreground">
                                Ano-novo, Sexta-feira Santa, Tiradentes, Dia do
                                Trabalho, Independência, Aparecida, Finados,
                                Proclamação da República, Consciência Negra e
                                Natal. Carnaval e Corpus Christi não são
                                feriados nacionais: se a empresa fecha, cadastre
                                abaixo.
                            </span>
                        </span>
                    </label>

                    <div className="space-y-2">
                        <Label>Feriados da empresa e da cidade</Label>
                        {form.data.holidays.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                Nenhum cadastrado.
                            </p>
                        )}
                        {form.data.holidays.map((holiday, index) => (
                            <div key={index}>
                                <div className="flex items-center gap-2">
                                    <Input
                                        type="date"
                                        value={holiday.date}
                                        onChange={(e) =>
                                            setHoliday(index, {
                                                ...holiday,
                                                date: e.target.value,
                                            })
                                        }
                                        className="w-40 shrink-0 font-mono"
                                        aria-label="Data do feriado"
                                        required
                                    />
                                    <Input
                                        value={holiday.name}
                                        onChange={(e) =>
                                            setHoliday(index, {
                                                ...holiday,
                                                name: e.target.value,
                                            })
                                        }
                                        placeholder="Ex.: Aniversário da cidade"
                                        maxLength={60}
                                        aria-label="Nome do feriado"
                                        required
                                    />
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        onClick={() =>
                                            form.setData(
                                                'holidays',
                                                form.data.holidays.filter(
                                                    (_, i) => i !== index,
                                                ),
                                            )
                                        }
                                        aria-label="Tirar este feriado"
                                    >
                                        <Trash2 />
                                    </Button>
                                </div>
                                <InputError
                                    message={
                                        errors[`holidays.${index}.date`] ??
                                        errors[`holidays.${index}.name`]
                                    }
                                />
                            </div>
                        ))}
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                form.setData('holidays', [
                                    ...form.data.holidays,
                                    { date: '', name: '' },
                                ])
                            }
                        >
                            <Plus />
                            Adicionar feriado
                        </Button>
                        <InputError message={errors.holidays} />
                    </div>
                </div>

                <Button disabled={form.processing}>Salvar</Button>
            </form>
        </>
    );
}

BusinessHours.layout = {
    breadcrumbs: [
        { title: 'Minha conta', href: '' },
        { title: 'Horário', href: edit() },
    ],
};
