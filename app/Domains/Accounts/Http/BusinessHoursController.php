<?php

namespace App\Domains\Accounts\Http;

use App\Domains\Accounts\CurrentOrganization;
use App\Domains\Accounts\Models\Organization;
use App\Http\Controllers\Controller;
use App\Platform\Audit\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Horário de atendimento da empresa: é por ele que o painel conta o tempo
 * até responder e o que chega fora do horário.
 */
class BusinessHoursController extends Controller
{
    public function edit(CurrentOrganization $organization): Response
    {
        $current = $organization->get();

        return Inertia::render('settings/business-hours', [
            'hours' => $current->hours(),
            'timezone' => $current->timezone,
            'nationalHolidays' => $current->national_holidays,
            'holidays' => $current->holidays ?? [],
        ]);
    }

    public function update(Request $request, CurrentOrganization $current, Audit $audit): RedirectResponse
    {
        $rules = [];

        foreach (Organization::DAYS as $day) {
            $rules["hours.{$day}"] = ['nullable', 'array', 'size:2'];
            $rules["hours.{$day}.*"] = ['required', 'date_format:H:i'];
        }

        $rules['national_holidays'] = ['required', 'boolean'];
        $rules['holidays'] = ['array', 'max:100'];
        $rules['holidays.*.date'] = ['required', 'date_format:Y-m-d', 'distinct'];
        $rules['holidays.*.name'] = ['required', 'string', 'max:60'];

        $data = $request->validate($rules, [
            'hours.*.*.date_format' => 'Use o formato 08:00.',
            'hours.*.*.required' => 'Preencha a abertura e o fechamento.',
            'holidays.*.date.required' => 'Escolha a data do feriado.',
            'holidays.*.date.date_format' => 'Data inválida.',
            'holidays.*.date.distinct' => 'Essa data já está na lista.',
            'holidays.*.name.required' => 'Dê um nome para o feriado.',
            'holidays.*.name.max' => 'Use um nome com até 60 letras.',
            'holidays.max' => 'Cadastre no máximo 100 feriados.',
        ]);

        $hours = [];

        foreach (Organization::DAYS as $day) {
            $range = $data['hours'][$day] ?? null;

            if ($range !== null && $range[0] >= $range[1]) {
                throw ValidationException::withMessages(["hours.{$day}" => 'O fechamento precisa ser depois da abertura.']);
            }

            $hours[$day] = $range === null ? null : [$range[0], $range[1]];
        }

        $holidays = collect($data['holidays'] ?? [])
            ->map(fn (array $h) => ['date' => $h['date'], 'name' => trim($h['name'])])
            ->sortBy('date')
            ->values()
            ->all();

        $organization = $current->get();
        $before = $organization->hours();
        $beforeNational = $organization->national_holidays;
        $beforeHolidays = $organization->holidays ?? [];

        $organization->update([
            'business_hours' => $hours,
            'national_holidays' => (bool) $data['national_holidays'],
            'holidays' => $holidays,
        ]);

        $changes = [];

        if ($beforeNational !== $organization->national_holidays) {
            $changes['national_holidays'] = [$beforeNational, $organization->national_holidays];
        }

        // O nome do feriado é texto livre: a auditoria guarda só quantos são.
        if ($beforeHolidays !== $holidays) {
            $changes['holidays'] = [count($beforeHolidays), count($holidays)];
        }

        foreach (Organization::DAYS as $day) {
            if ($before[$day] !== $hours[$day]) {
                $changes[$day] = [self::code($before[$day]), self::code($hours[$day])];
            }
        }

        if ($changes !== []) {
            $audit->record('accounts.business_hours_changed', $organization, changes: $changes);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Horário salvo. O painel já conta por ele.']);

        return back();
    }

    /** ["08:00", "18:00"] -> "08:00-18:00"; fechado -> "closed". */
    private static function code(?array $range): string
    {
        return $range === null ? 'closed' : "{$range[0]}-{$range[1]}";
    }
}
