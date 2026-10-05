# Commits

Padrão: **Conventional Commits**, com o tipo em inglês e a descrição em português.

```
<tipo>(<escopo>): <o que muda, no imperativo, minúsculo, sem ponto final>

<corpo opcional: por quê e o que muda para quem usa, em frases curtas ou tópicos>

<trailers, se houver>
```

- **Tipos:** `feat` (funcionalidade nova), `fix` (correção), `docs`, `refactor` (sem mudar comportamento), `perf`, `test`, `style` (formatação), `build` (dependências, Docker), `ci`, `chore` (manutenção), `revert`.
- **Escopo:** o domínio ou a área, em minúsculas: `accounts`, `conversations`, `imports`, `insights`, `ai`, `platform`, `ui`, `docs`, `deps`. Pode omitir se a mudança for transversal.
- **Mudança que quebra** (migração manual, API ou contrato entre domínios mudando): `!` depois do escopo e um rodapé `BREAKING CHANGE: ...`.
- Primeira linha com até 72 caracteres. Ex.: `feat(ai): perguntar à IA sobre uma conversa`, `fix(imports): não duplicar mensagem reimportada`, `docs: escopo da primeira entrega`.
- Um commit por assunto. Não misture refatoração com funcionalidade nova.
- O hook `.githooks/commit-msg` recusa mensagem fora do padrão (merge e revert gerados pelo git passam).

Antes de commitar:

1. `composer ci:check` passou com o código exato que vai no commit (`.claude/rules/ci.md`).
2. `git status` sem arquivos de dados (`data/`, `*.zip`, `*.xlsx`, `.env`).
3. Nada de credencial, token ou dado de cliente no diff.

Não reescreva histórico já enviado (`push --force` na `main` é proibido). Commite e faça push só quando a pessoa pedir ou quando a tarefa combinada for essa.
