<?php

namespace App\Domains\Ai\Data;

use RuntimeException;

/**
 * O provedor recusou ou não respondeu. O código vai para a pergunta e para a
 * auditoria; a mensagem em português vai para o dono.
 */
class AiFailed extends RuntimeException
{
    public const MESSAGES = [
        'invalid_key' => 'O provedor recusou a chave. Confira se copiou a chave inteira e se ela está ativa.',
        'quota' => 'A chave chegou ao limite de uso do provedor. Espere um pouco ou aumente o limite na conta do provedor.',
        'unavailable' => 'O provedor não respondeu agora. Tente de novo em instantes.',
        'blocked' => 'O provedor se recusou a responder essa pergunta.',
        'no_models' => 'A chave funciona, mas não tem nenhum modelo de texto liberado.',
        'unexpected' => 'Algo deu errado ao falar com o provedor. Tente de novo.',
    ];

    public function __construct(public readonly string $reason)
    {
        parent::__construct(self::MESSAGES[$reason] ?? self::MESSAGES['unexpected']);
    }
}
