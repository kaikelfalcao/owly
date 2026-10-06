<?php

namespace App\Platform\Audit;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Tudo o que a tela de Atividade sabe sobre cada ação: o texto que o dono
 * lê, a criticidade e se é uma falha. Ação nova entra aqui junto com o lugar
 * que a grava; o que não está aqui aparece pelo código, como Normal.
 */
class AuditCatalog
{
    /**
     * @var array<string, array{0: string, 1: Severity, 2?: Outcome}>
     */
    private const ACTIONS = [
        'auth.login' => ['Entrou na Owly', Severity::Normal],
        'auth.logout' => ['Saiu da Owly', Severity::Normal],
        'auth.failed' => ['Tentativa de entrar com a senha errada', Severity::Important, Outcome::Failure],
        'auth.password_reset' => ['Criou uma senha nova pelo "Esqueceu a senha?"', Severity::Important],
        'auth.password_changed' => ['Trocou a senha', Severity::Important],
        'auth.two_factor_enabled' => ['Ligou a verificação em duas etapas', Severity::Normal],
        'auth.two_factor_disabled' => ['Desligou a verificação em duas etapas', Severity::Critical],
        'auth.two_factor_failed' => ['Errou o código da verificação em duas etapas', Severity::Important, Outcome::Failure],
        'auth.recovery_codes_generated' => ['Gerou novos códigos de recuperação', Severity::Important],
        'accounts.owner_created' => ['Conta criada', Severity::Normal],
        'accounts.name_changed' => ['Trocou o nome', Severity::Normal],
        'accounts.email_changed' => ['Trocou o e-mail de entrada', Severity::Critical],
        'accounts.business_hours_changed' => ['Mudou o horário de atendimento', Severity::Normal],
        'audit.exported' => ['Exportou a atividade', Severity::Important],
        'imports.started' => ['Subiu um zip de conversas', Severity::Normal],
        'imports.finished' => ['Importação de conversas concluída', Severity::Normal],
        'imports.failed' => ['Importação de conversas não deu certo', Severity::Important, Outcome::Failure],
        'ai.connection_created' => ['Conectou uma IA', Severity::Important],
        'ai.connection_default' => ['Trocou a IA padrão', Severity::Normal],
        'ai.connection_removed' => ['Removeu uma conexão de IA', Severity::Important],
        'ai.question_asked' => ['Perguntou à IA sobre uma conversa', Severity::Normal],
        'insights.opportunity_won' => ['Marcou uma oportunidade como ganha', Severity::Normal],
        'insights.opportunity_lost' => ['Marcou uma oportunidade como perdida', Severity::Normal],
        'insights.opportunity_discarded' => ['Marcou que não era oportunidade', Severity::Normal],
        'insights.opportunity_reopened' => ['Abriu de novo uma oportunidade', Severity::Normal],
    ];

    /**
     * O registro sobre o qual a ação foi feita. A chave curta vai na URL do
     * filtro; a classe é o que `subject_type` guarda.
     *
     * @var array<string, array{0: class-string, 1: string}>
     */
    private const RESOURCES = [
        'user' => [User::class, 'Dono'],
        // Pelo nome, sem importar o model: a Plataforma não depende de domínio.
        'import' => ['App\\Domains\\Imports\\Models\\Import', 'Importação'],
        'ai_connection' => ['App\\Domains\\Ai\\Models\\AiConnection', 'Conexão de IA'],
        'ai_question' => ['App\\Domains\\Ai\\Models\\AiQuestion', 'Pergunta à IA'],
        'organization' => ['App\\Domains\\Accounts\\Models\\Organization', 'Empresa'],
        'opportunity' => ['App\\Domains\\Insights\\Models\\Opportunity', 'Oportunidade'],
    ];

    /** Nomes dos campos que aparecem em "Alterações". */
    private const FIELDS = [
        'name' => 'Nome',
        'email' => 'E-mail de entrada',
        'mon' => 'Segunda',
        'tue' => 'Terça',
        'wed' => 'Quarta',
        'thu' => 'Quinta',
        'fri' => 'Sexta',
        'sat' => 'Sábado',
        'sun' => 'Domingo',
        'national_holidays' => 'Feriados nacionais',
        'holidays' => 'Feriados da empresa (quantidade)',
        'status' => 'Situação',
    ];

    /** Nomes dos detalhes gravados em `meta`. */
    private const META = [
        'remember' => 'Continuar conectado',
        'known_user' => 'E-mail cadastrado na Owly',
        'rows' => 'Linhas exportadas',
        'period' => 'Período',
        'filtered' => 'Com filtros',
        'size' => 'Tamanho do zip (bytes)',
        'files' => 'Planilhas no zip',
        'contacts_new' => 'Clientes novos',
        'conversations_new' => 'Atendimentos novos',
        'messages_new' => 'Mensagens novas',
        'problems' => 'Planilhas que ficaram de fora',
        'code' => 'Motivo (código)',
        'provider' => 'Provedor de IA',
        'ok' => 'Respondeu',
        'input_tokens' => 'Tokens enviados',
        'output_tokens' => 'Tokens recebidos',
        'reason' => 'Motivo da perda (código)',
    ];

    public static function label(string $action): string
    {
        return self::ACTIONS[$action][0] ?? $action;
    }

    public static function severity(string $action): Severity
    {
        return self::ACTIONS[$action][1] ?? Severity::Normal;
    }

    public static function outcome(string $action): Outcome
    {
        return self::ACTIONS[$action][2] ?? Outcome::Success;
    }

    /**
     * @return array<string, string> código => texto
     */
    public static function actions(): array
    {
        return array_map(fn (array $entry) => $entry[0], self::ACTIONS);
    }

    /**
     * Ações com a criticidade pedida, para filtrar no banco.
     *
     * @return list<string>
     */
    public static function actionsWithSeverity(Severity $severity): array
    {
        return array_keys(array_filter(self::ACTIONS, fn (array $entry) => $entry[1] === $severity));
    }

    /**
     * @return list<string>
     */
    public static function failures(): array
    {
        return array_keys(array_filter(self::ACTIONS, fn (array $entry) => ($entry[2] ?? null) === Outcome::Failure));
    }

    /**
     * Ações cujo texto ou código contém a busca, sem ligar para acento nem
     * maiúscula ("senha" acha "Trocou a senha").
     *
     * @return list<string>
     */
    public static function actionsMatching(string $search): array
    {
        $needle = Str::lower(Str::ascii($search));

        return array_keys(array_filter(
            self::ACTIONS,
            fn (array $entry, string $code) => str_contains(Str::lower(Str::ascii($entry[0])), $needle)
                || str_contains($code, $needle),
            ARRAY_FILTER_USE_BOTH,
        ));
    }

    /**
     * @return array<string, string> chave => nome na tela
     */
    public static function resources(): array
    {
        return array_map(fn (array $entry) => $entry[1], self::RESOURCES);
    }

    /** A classe guardada em `subject_type` para a chave do filtro. */
    public static function resourceType(string $key): ?string
    {
        return self::RESOURCES[$key][0] ?? null;
    }

    /** A chave curta de uma classe guardada em `subject_type`. */
    public static function resourceKey(?string $type): ?string
    {
        foreach (self::RESOURCES as $key => [$class]) {
            if ($class === $type) {
                return $key;
            }
        }

        return null;
    }

    public static function resourceLabel(?string $type): ?string
    {
        $key = self::resourceKey($type);

        return $key === null ? ($type === null ? null : class_basename($type)) : self::RESOURCES[$key][1];
    }

    public static function field(string $field): string
    {
        return self::FIELDS[$field] ?? $field;
    }

    public static function meta(string $field): string
    {
        return self::META[$field] ?? $field;
    }
}
