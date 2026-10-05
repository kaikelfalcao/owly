<?php

/**
 * Garante que nada sobe sem o `composer ci:check` verde.
 *
 *   php scripts/ci-guard.php mark              o ci:check passou: guarda o código que passou
 *   php scripts/ci-guard.php check-commit      o código do commit passou no ci:check?
 *   php scripts/ci-guard.php check-push        o que vai no push (HEAD) passou?
 *   php scripts/ci-guard.php commit-msg <arq>  mensagem no padrão Conventional Commits?
 *   php scripts/ci-guard.php claude-hook       PreToolUse do Claude Code (lê o JSON do stdin)
 *
 * "O código" é a árvore do git (hash do conteúdo de todos os arquivos versionáveis),
 * então editar qualquer arquivo depois do ci:check exige rodar de novo.
 * As passagens ficam em .git/owly-ci-pass, fora do repositório.
 */
const TYPES = 'feat|fix|docs|style|refactor|perf|test|build|ci|chore|revert';

function git(string $args, array $env = []): string
{
    $prefix = '';
    foreach ($env as $k => $v) {
        $prefix .= $k.'='.escapeshellarg($v).' ';
    }
    exec($prefix.'git '.$args.' 2>/dev/null', $out, $code);

    return $code === 0 ? trim(implode("\n", $out)) : '';
}

function store(): string
{
    return git('rev-parse --git-path owly-ci-pass') ?: '.git/venditor-ci-pass';
}

/** Hash da árvore com tudo que está no disco (como se fosse um `git add -A`). */
function worktreeTree(): string
{
    $index = git('rev-parse --git-path index');
    $tmp = tempnam(sys_get_temp_dir(), 'owly-ci-');
    if ($index && is_file($index)) {
        copy($index, $tmp);
    } else {
        // Repositório sem índice ainda (antes do primeiro commit): o git cria um novo.
        @unlink($tmp);
    }
    git('add -A', ['GIT_INDEX_FILE' => $tmp]);
    $tree = git('write-tree', ['GIT_INDEX_FILE' => $tmp]);
    @unlink($tmp);

    return $tree;
}

function passed(): array
{
    return is_file(store()) ? array_filter(array_map('trim', file(store()))) : [];
}

function fail(string $why): never
{
    fwrite(STDERR, $why."\n");
    exit(2);
}

$cmd = $argv[1] ?? '';

switch ($cmd) {
    case 'mark':
        $tree = worktreeTree();
        $all = array_slice(array_values(array_unique([...passed(), $tree])), -30);
        file_put_contents(store(), implode("\n", $all)."\n");
        echo "ci:check verde registrado ({$tree}).\n";
        exit(0);

    case 'check-commit':
        $ok = array_intersect([git('write-tree'), worktreeTree()], passed());
        $ok || fail('Bloqueado: o `composer ci:check` não passou com o código atual. Rode `composer ci:check`, corrija o que falhar e commite de novo.');
        exit(0);

    case 'check-push':
        in_array(git('rev-parse HEAD^{tree}'), passed(), true)
            || fail('Bloqueado: o último commit não passou no `composer ci:check`. Rode `composer ci:check` (e corrija o que falhar) antes do push.');
        exit(0);

    case 'commit-msg':
        $first = trim(strtok((string) @file_get_contents($argv[2] ?? ''), "\n"));
        if ($first === '' || preg_match('/^(Merge|Revert|fixup!|squash!) /', $first)) {
            exit(0);
        }
        if (! preg_match('/^('.TYPES.')(\([a-z0-9-]+\))?!?: \S.*$/u', $first)) {
            fail("Mensagem fora do padrão: \"{$first}\"\nUse `tipo(escopo): descrição`, por exemplo `feat(ai): sugerir respostas na caixa de entrada`.\nTipos: ".str_replace('|', ', ', TYPES).'. Veja .claude/rules/commits.md.');
        }
        if (mb_strlen($first) > 72) {
            fail('A primeira linha da mensagem tem '.mb_strlen($first).' caracteres; o limite é 72.');
        }
        exit(0);

    case 'claude-hook':
        $input = json_decode((string) stream_get_contents(STDIN), true) ?: [];
        $command = (string) ($input['tool_input']['command'] ?? '');
        if (preg_match('/--no-verify\b/', $command) && preg_match('/\bgit\b/', $command)) {
            fail('Bloqueado: não use --no-verify. Os hooks garantem o ci:check verde e o padrão de commit.');
        }
        if (preg_match('/\bgit\s+(?:-\S+\s+)*commit\b/', $command)) {
            // `git add -A && git commit` chega aqui antes do add: vale a árvore do disco.
            array_intersect([git('write-tree'), worktreeTree()], passed())
                || fail('Bloqueado: rode `composer ci:check` e veja passar antes de commitar (o código mudou desde a última passagem).');
        }
        if (preg_match('/\bgit\s+(?:-\S+\s+)*push\b/', $command)) {
            in_array(git('rev-parse HEAD^{tree}'), passed(), true)
                || fail('Bloqueado: o último commit não passou no `composer ci:check`. Rode e veja passar antes do push.');
        }
        exit(0);

    default:
        fwrite(STDERR, "Uso: php scripts/ci-guard.php mark|check-commit|check-push|commit-msg <arquivo>|claude-hook\n");
        exit(1);
}
