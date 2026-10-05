<?php

namespace App\Platform\Audit;

/**
 * Se a ação deu certo. Como a criticidade, sai do catálogo: a falha já está
 * no código da ação (`auth.failed`), então gravar de novo seria repetir.
 */
enum Outcome: string
{
    case Success = 'success';
    case Failure = 'failure';

    public function label(): string
    {
        return match ($this) {
            self::Success => 'Concluída',
            self::Failure => 'Falhou',
        };
    }
}
