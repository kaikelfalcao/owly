<?php

namespace App\Domains\Imports\Data;

use RuntimeException;

/**
 * O arquivo inteiro não serve. O código vai para a importação e para a
 * auditoria; a mensagem em português vai para o dono.
 */
class ImportFailed extends RuntimeException
{
    public const MESSAGES = [
        'invalid_zip' => 'O arquivo não é um .zip válido.',
        'no_conversations' => 'O zip não tem nenhuma planilha de conversa (.xlsx).',
        'unexpected' => 'Algo deu errado ao ler o arquivo. Tente de novo; se continuar, fale com o suporte.',
    ];

    public function __construct(public readonly string $reason)
    {
        parent::__construct(self::MESSAGES[$reason] ?? self::MESSAGES['unexpected']);
    }
}
