<?php

namespace App\Platform\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Rede de segurança dos logs: campos que costumam trazer dado pessoal são
 * trocados por "[removido]" antes de a linha ser gravada. A regra continua
 * sendo não logar esses dados; isto pega o descuido.
 */
class ScrubPersonalData implements ProcessorInterface
{
    public const REMOVED = '[removido]';

    /**
     * Pedaços de nome de campo que indicam dado pessoal ou segredo.
     */
    private const SENSITIVE = [
        'email', 'phone', 'telefone', 'name', 'nome', 'cpf', 'cnpj',
        'body', 'text', 'texto', 'message', 'mensagem', 'content',
        'password', 'senha', 'token', 'secret', 'key', 'authorization', 'cookie',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            context: $this->scrub($record->context),
            extra: $this->scrub($record->extra),
        );
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function scrub(array $data): array
    {
        foreach ($data as $field => $value) {
            if (is_string($field) && $this->isSensitive($field)) {
                $data[$field] = self::REMOVED;
            } elseif (is_array($value)) {
                $data[$field] = $this->scrub($value);
            }
        }

        return $data;
    }

    private function isSensitive(string $field): bool
    {
        $field = strtolower($field);

        foreach (self::SENSITIVE as $piece) {
            if (str_contains($field, $piece)) {
                return true;
            }
        }

        return false;
    }
}
