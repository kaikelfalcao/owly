<?php

namespace App\Platform\Http;

use App\Platform\Audit\AuditCatalog;
use App\Platform\Audit\AuditEntry;
use App\Platform\Audit\Channel;
use Illuminate\Support\Collection;

/**
 * Uma linha da auditoria do jeito que a tela e a exportação mostram: textos
 * em português no lugar dos códigos e o nome de quem fez no lugar do id.
 */
class ActivityRow
{
    /**
     * @param  Collection<int, string>  $users  id => nome, só da empresa
     */
    public function __construct(private readonly Collection $users) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(AuditEntry $entry): array
    {
        $severity = AuditCatalog::severity($entry->action);
        $outcome = AuditCatalog::outcome($entry->action);
        $channel = Channel::tryFrom((string) $entry->channel);

        return [
            'id' => $entry->id,
            'action' => $entry->action,
            'label' => AuditCatalog::label($entry->action),
            'severity' => $severity->value,
            'result' => $outcome->value,
            'at' => $entry->created_at->toIso8601String(),
            'user' => $entry->user_id === null ? null : [
                'id' => $entry->user_id,
                'name' => $this->users->get($entry->user_id),
            ],
            'resource' => $entry->subject_type === null ? null : [
                'key' => AuditCatalog::resourceKey($entry->subject_type),
                'label' => AuditCatalog::resourceLabel($entry->subject_type),
                'id' => $entry->subject_id,
                'name' => $entry->subject_type === AuditCatalog::resourceType('user')
                    ? $this->users->get((int) $entry->subject_id)
                    : null,
            ],
            'channel' => $channel === null ? null : ['value' => $channel->value, 'label' => $channel->label()],
            'ip' => $entry->ip,
            'user_agent' => $entry->user_agent,
            'request_id' => $entry->request_id,
            'meta' => collect($entry->meta ?? [])
                ->map(fn ($value, string $field) => ['field' => $field, 'label' => AuditCatalog::meta($field), 'value' => $value])
                ->values()
                ->all(),
            'changes' => collect($entry->changes ?? [])
                ->map(fn (array $change, string $field) => [
                    'field' => $field,
                    'label' => AuditCatalog::field($field),
                    'hidden' => (bool) ($change['hidden'] ?? false),
                    'from' => $change['from'] ?? null,
                    'to' => $change['to'] ?? null,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Cabeçalho da planilha, na ordem de {@see self::toCsv()}.
     *
     * @return list<string>
     */
    public static function csvHeader(): array
    {
        return ['Registro', 'Data e hora', 'Quem', 'Ação', 'Código da ação', 'Recurso', 'Resultado', 'Criticidade', 'Canal', 'IP', 'Navegador', 'Request ID'];
    }

    /**
     * @return list<string|int|null>
     */
    public function toCsv(AuditEntry $entry, string $timezone): array
    {
        $resource = $entry->subject_type === null
            ? null
            : trim(AuditCatalog::resourceLabel($entry->subject_type).' #'.$entry->subject_id);

        return array_map($this->cell(...), [
            $entry->id,
            $entry->created_at->setTimezone($timezone)->format('d/m/Y H:i:s'),
            $entry->user_id === null ? 'Sem login' : ($this->users->get($entry->user_id) ?? "#{$entry->user_id}"),
            AuditCatalog::label($entry->action),
            $entry->action,
            $resource,
            AuditCatalog::outcome($entry->action)->label(),
            AuditCatalog::severity($entry->action)->label(),
            Channel::tryFrom((string) $entry->channel)?->label(),
            $entry->ip,
            $entry->user_agent,
            $entry->request_id,
        ]);
    }

    /**
     * Texto que começa com = + - @ vira fórmula no Excel; o apóstrofo na
     * frente faz a planilha mostrar como texto.
     */
    private function cell(string|int|null $value): string|int|null
    {
        return is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'{$value}" : $value;
    }
}
