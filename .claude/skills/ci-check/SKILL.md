---
name: ci-check
description: Roda a checagem do CI da Owly (composer ci:check), lê a falha e corrige até passar. Use antes de commit/push, quando o GitHub Actions ficar vermelho, ou quando pedirem para "ver se o CI passa".
---

# ci:check até ficar verde

1. Rode `composer ci:check` na raiz. Ele executa, em ordem: `npm run check` (vp: formatação + lint), `npm run build` (gera também as rotas do Wayfinder), `npm run types:check` (tsc), `pint --test`, `php artisan test` e, se tudo passar, registra a passagem (`scripts/ci-guard.php mark`).
2. Pare na primeira etapa que falhar e corrija só ela:
    - **vp check (formatação/lint):** `npm run check:fix`; o que sobrar é lint de verdade, corrija no código.
    - **tsc:** leia `arquivo:linha` do erro e ajuste o tipo. Não use `any` nem `@ts-ignore` para calar.
    - **build:** erro de import/sintaxe no front.
    - **pint --test:** `vendor/bin/pint --dirty`.
    - **testes:** a saída vem em JSON (laravel/pao). Leia `failures[].test`, `message` e `file:line`. Rode só o teste com `php artisan test --filter=nome_do_teste` enquanto corrige. "Not a valid Inertia response" quase sempre é tela nova sem `npm run build` ou erro 500 no controller (veja `$response->exception`).
3. Rode `composer ci:check` completo de novo. Só termine quando a última linha mostrar "ci:check verde registrado".
4. Se a falha já existia antes da sua mudança (confira com `git stash` e rodando de novo, ou no GitHub com `gh run list -L 5`), diga isso a quem pediu, com o teste e o erro. Não pule, não desligue, não marque teste como ignorado.

## CI no GitHub

- Workflow: `.github/workflows/tests.yml` roda `composer setup` e `composer ci:check` com SQLite.
- Ver o resultado: `gh run list -L 3` e `gh run view <id>`; falha no passo "Setup Application" é instalação/migração, não teste.
