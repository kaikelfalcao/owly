<?php

namespace App\Platform\Audit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use InvalidArgumentException;

/**
 * Grava quem fez o quê, quando, de onde e sobre qual registro.
 *
 * A auditoria guarda só ids, códigos e números: um texto livre em `meta`
 * (um e-mail, um nome, um trecho de mensagem) é recusado na hora, para o
 * descuido virar erro no teste e não dado pessoal guardado para sempre.
 */
class Audit
{
    /**
     * @param  string  $action  código no formato `area.acao`, ex.: `auth.login`
     * @param  array<string, scalar|null>  $meta  números e códigos curtos
     */
    public function record(
        string $action,
        ?Model $subject = null,
        array $meta = [],
        ?int $userId = null,
        ?int $organizationId = null,
    ): AuditEntry {
        if (preg_match('/^[a-z]+(\.[a-z_]+)+$/', $action) !== 1) {
            throw new InvalidArgumentException("Ação de auditoria fora do padrão area.acao: {$action}");
        }

        foreach ($meta as $field => $value) {
            $this->assertSafe((string) $field, $value);
        }

        /** @var Request $request */
        $request = app('request');

        return AuditEntry::create([
            'organization_id' => $organizationId ?? Context::get('organization_id'),
            'user_id' => $userId ?? $request->user()?->getAuthIdentifier(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'ip' => $request->ip(),
            'request_id' => Context::get('request_id'),
            'meta' => $meta === [] ? null : $meta,
        ]);
    }

    private function assertSafe(string $field, mixed $value): void
    {
        $safe = $value === null
            || is_int($value)
            || is_float($value)
            || is_bool($value)
            || (is_string($value) && preg_match('/^[a-z0-9_.:-]{1,64}$/', $value) === 1);

        if (! $safe) {
            throw new InvalidArgumentException(
                "Auditoria aceita só números e códigos curtos; o campo \"{$field}\" parece texto livre."
            );
        }
    }
}
