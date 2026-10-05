@AGENTS.md

## Claude Code neste repositório

- **Rules** (`.claude/rules/`): carregadas conforme os arquivos abertos (frontmatter `paths`). `commits.md`, `ci.md` e `privacy.md` valem sempre.
- **Skills** (`.claude/skills/`): `ci-check` (rodar o CI e corrigir até passar) e `commit` (conferir o `ci:check` e commitar no padrão).
- **Hooks** (`.claude/settings.json`): antes de commit e push, `scripts/ci-guard.php` confere se o `composer ci:check` passou com exatamente o código que vai subir; depois de editar PHP, o Pint formata o arquivo.
- Saída de PHPUnit e Artisan sai compacta em JSON para agentes (`laravel/pao`): leia `result`, `failures[].message` e `failures[].file:line`.
