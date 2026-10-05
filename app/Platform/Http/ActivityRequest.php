<?php

namespace App\Platform\Http;

use App\Platform\Audit\AuditCatalog;
use App\Platform\Audit\Outcome;
use App\Platform\Audit\Severity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Os filtros da tela de Atividade, que valem igual para a lista e para a
 * exportação. Filtro inválido volta com a mensagem, nunca vira consulta.
 */
class ActivityRequest extends FormRequest
{
    public const PERIODS = ['today', 'yesterday', '7d', '30d', 'month', 'custom'];

    /**
     * Filtro inválido volta para a tela limpa, nunca para o mesmo endereço:
     * aberto direto por um link, o "voltar" repetiria o erro em ciclo.
     */
    protected $redirectRoute = 'activity.index';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'period' => ['nullable', Rule::in(self::PERIODS)],
            'from' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d'],
            'to' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d', 'after_or_equal:from'],
            'user' => ['nullable', 'regex:/^(none|\d+)$/'],
            'action' => ['nullable', 'string', 'max:64'],
            'resource' => ['nullable', 'required_with:resource_id', Rule::in(array_keys(AuditCatalog::resources()))],
            'resource_id' => ['nullable', 'integer', 'min:1'],
            'result' => ['nullable', Rule::enum(Outcome::class)],
            'severity' => ['nullable', Rule::enum(Severity::class)],
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'from.required_if' => 'Escolha o primeiro dia do período.',
            'to.required_if' => 'Escolha o último dia do período.',
            'to.after_or_equal' => 'O último dia precisa ser igual ou depois do primeiro.',
            'from.date_format' => 'Data inválida.',
            'to.date_format' => 'Data inválida.',
            'q.max' => 'A busca aceita até 100 caracteres.',
        ];
    }

    /**
     * Só os filtros preenchidos, para a tela saber o que está ligado.
     *
     * @return array<string, string>
     */
    public function filters(): array
    {
        return array_filter(
            array_map(fn ($value) => is_string($value) ? trim($value) : (string) $value, $this->validated()),
            fn (string $value) => $value !== '',
        );
    }
}
