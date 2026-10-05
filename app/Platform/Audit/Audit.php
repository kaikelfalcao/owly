<?php

namespace App\Platform\Audit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use InvalidArgumentException;

/**
 * Grava quem fez o quê, quando, de onde e sobre qual registro.
 *
 * A auditoria guarda só ids, códigos e números: um texto livre em `meta` ou
 * em `changes` (um e-mail, um nome, um trecho de mensagem) é recusado na
 * hora, para o descuido virar erro no teste e não dado pessoal guardado para
 * sempre. Campo com dado pessoal entra em `changes` como {@see self::HIDDEN}:
 * fica registrado que mudou, sem o valor.
 */
class Audit
{
    /** Valor de `changes` para campo que mudou mas não pode ser guardado. */
    public const HIDDEN = '[oculto]';

    /**
     * @param  string  $action  código no formato `area.acao`, ex.: `auth.login`
     * @param  array<string, scalar|null>  $meta  números e códigos curtos
     * @param  array<string, array{0: scalar|null, 1: scalar|null}|self::HIDDEN>  $changes  campo => [antes, depois]
     * @param  ?int  $userId  quem fez; sem ele, quem está logado (ninguém, numa tentativa de entrar)
     */
    public function record(
        string $action,
        ?Model $subject = null,
        array $meta = [],
        ?int $userId = null,
        ?int $organizationId = null,
        array $changes = [],
    ): AuditEntry {
        if (preg_match('/^[a-z]+(\.[a-z_]+)+$/', $action) !== 1) {
            throw new InvalidArgumentException("Ação de auditoria fora do padrão area.acao: {$action}");
        }

        foreach ($meta as $field => $value) {
            $this->assertSafe((string) $field, $value);
        }

        /** @var Request $request */
        $request = app('request');
        $channel = $this->channel($request);
        $web = $channel === Channel::Web;

        return AuditEntry::create([
            'organization_id' => $organizationId ?? Context::get('organization_id'),
            'user_id' => $userId ?? $request->user()?->getAuthIdentifier(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'channel' => $channel->value,
            // Fora do navegador o "IP" seria o do próprio servidor.
            'ip' => $web ? $request->ip() : null,
            'user_agent' => $web ? mb_substr((string) $request->userAgent(), 0, 255) ?: null : null,
            'request_id' => Context::get('request_id'),
            'meta' => $meta === [] ? null : $meta,
            'changes' => $changes === [] ? null : $this->changes($changes),
        ]);
    }

    /**
     * Requisição com rota é alguém no navegador; tarefa da fila deixa o nome
     * no contexto; o resto é comando no servidor.
     */
    private function channel(Request $request): Channel
    {
        return match (true) {
            $request->route() !== null => Channel::Web,
            Context::has('job') => Channel::Queue,
            default => Channel::Console,
        };
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, array{from: scalar|null, to: scalar|null}|array{hidden: true}>
     */
    private function changes(array $changes): array
    {
        $stored = [];

        foreach ($changes as $field => $change) {
            if (preg_match('/^[a-z_]{1,64}$/', (string) $field) !== 1) {
                throw new InvalidArgumentException("Campo de auditoria fora do padrão: {$field}");
            }

            if ($change === self::HIDDEN) {
                $stored[$field] = ['hidden' => true];

                continue;
            }

            if (! is_array($change) || count($change) !== 2) {
                throw new InvalidArgumentException("Alteração de \"{$field}\" precisa ser [antes, depois] ou Audit::HIDDEN.");
            }

            [$from, $to] = array_values($change);
            $this->assertSafe($field, $from);
            $this->assertSafe($field, $to);
            $stored[$field] = ['from' => $from, 'to' => $to];
        }

        return $stored;
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
