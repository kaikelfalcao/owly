---
name: commit
description: Faz commit no padrão da Owly (Conventional Commits com descrição em português) depois de garantir o composer ci:check verde. Use sempre que for commitar ou quando pedirem "commita", "sobe", "faz o push".
---

# Commit no padrão

1. `git status` e `git diff --stat`: confira o que entra. Nada de `data/`, `*.zip`, `*.xlsx`, `.env`, prints com dados de cliente ou chaves.
2. Rode `composer ci:check` e veja passar (skill `ci-check`). Os hooks bloqueiam commit e push se o código mudou desde a última passagem.
3. Separe por assunto: se o diff mistura uma correção com uma funcionalidade, faça dois commits (`git add -p` ou por arquivo).
4. Monte a mensagem (regras completas em `.claude/rules/commits.md`):
    ```
    tipo(escopo): o que muda, no imperativo e em minúsculas

    - por quê / o que muda para quem usa
    - migração, configuração ou passo manual necessário, se houver
    ```
    Tipos: feat, fix, docs, refactor, perf, test, style, build, ci, chore, revert. Escopo: o domínio (`accounts`, `conversations`, `imports`, `insights`, `ai`) ou a área (`platform`, `ui`, `docs`, `deps`). Primeira linha até 72 caracteres.
5. Commite com a mensagem num heredoc (`git commit -F - <<'EOF' ... EOF`) e mantenha os trailers que a sua ferramenta exige.
6. Push só quando pedirem: `git push -u origin <branch>`. Depois, `gh run list -L 1` para ver o CI; vermelho vira a próxima tarefa.

Nunca use `--no-verify`, `--amend` em commit já enviado ou `push --force` na `main`.
