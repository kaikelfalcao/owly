<?php

namespace App\Platform\Audit;

/**
 * Por onde a ação chegou: alguém no navegador, um comando no servidor
 * (`owly:owner`) ou uma tarefa da fila (a importação, depois).
 */
enum Channel: string
{
    case Web = 'web';
    case Console = 'console';
    case Queue = 'queue';

    public function label(): string
    {
        return match ($this) {
            self::Web => 'Navegador',
            self::Console => 'Comando no servidor',
            self::Queue => 'Tarefa em segundo plano',
        };
    }
}
