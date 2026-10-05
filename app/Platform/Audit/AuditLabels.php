<?php

namespace App\Platform\Audit;

/**
 * O texto que o dono lê para cada ação auditada. Ação nova entra aqui junto
 * com o lugar que a grava.
 */
class AuditLabels
{
    private const LABELS = [
        'auth.login' => 'Entrou na Owly',
        'auth.logout' => 'Saiu da Owly',
        'auth.failed' => 'Tentativa de entrar com a senha errada',
        'auth.password_reset' => 'Criou uma senha nova pelo "Esqueceu a senha?"',
        'auth.password_changed' => 'Trocou a senha',
        'auth.two_factor_enabled' => 'Ligou a verificação em duas etapas',
        'auth.two_factor_disabled' => 'Desligou a verificação em duas etapas',
        'auth.two_factor_failed' => 'Errou o código da verificação em duas etapas',
        'auth.recovery_codes_generated' => 'Gerou novos códigos de recuperação',
        'accounts.owner_created' => 'Conta criada',
        'accounts.email_changed' => 'Trocou o e-mail de entrada',
        'imports.started' => 'Subiu um zip de conversas',
        'imports.finished' => 'Importação de conversas concluída',
        'imports.failed' => 'Importação de conversas não deu certo',
        'ai.connection_created' => 'Conectou uma IA',
        'ai.connection_default' => 'Trocou a IA padrão',
        'ai.connection_removed' => 'Removeu uma conexão de IA',
        'ai.question_asked' => 'Perguntou à IA sobre uma conversa',
    ];

    /** Ações que merecem atenção na lista. */
    private const WARNINGS = [
        'auth.failed',
        'auth.two_factor_failed',
        'auth.two_factor_disabled',
        'imports.failed',
    ];

    public static function for(string $action): string
    {
        return self::LABELS[$action] ?? $action;
    }

    public static function tone(string $action): string
    {
        return in_array($action, self::WARNINGS, true) ? 'warning' : 'default';
    }
}
