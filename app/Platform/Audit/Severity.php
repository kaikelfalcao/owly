<?php

namespace App\Platform\Audit;

/**
 * O quanto uma ação pede atenção de quem investiga. Vem do catálogo, não é
 * gravada: mudar a classificação vale também para o que já aconteceu.
 */
enum Severity: string
{
    case Normal = 'normal';
    case Important = 'important';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::Important => 'Importante',
            self::Critical => 'Crítico',
        };
    }
}
