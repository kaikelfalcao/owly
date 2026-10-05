# Checagem do CI: sempre verde

`composer ci:check` é exatamente o que o GitHub Actions roda (`.github/workflows/tests.yml`). Ele faz, em ordem:

1. `vp check`: formatação e lint do front;
2. `vp build`: build do front (gera as rotas do Wayfinder e o manifest que os testes de tela usam);
3. `tsc --noEmit`: tipos do front;
4. `pint --test`: estilo do PHP;
5. `php artisan test`: a suíte inteira, em SQLite na memória.

Regras:

- **Rode `composer ci:check` e veja passar antes de todo commit e de todo push.** O hook do Claude Code (`scripts/ci-guard.php`) bloqueia `git commit` e `git push` se a última passagem não foi com o código atual; o `pre-push` do git faz o mesmo para quem não usa agente.
- Passou é passou inteiro. "Só um teste falhou e não é meu" não é verde: descubra a causa. Se a falha já existia na `main`, diga isso, com o nome do teste e o erro, antes de seguir.
- Nunca pule, desligue, apague ou marque teste como ignorado para ficar verde. Nunca use `--no-verify` para fugir dos hooks.
- Para corrigir rápido, rode só o pedaço que falhou (`npm run check:fix`, `vendor/bin/pint --dirty`, `php artisan test --filter=...`) e depois o `ci:check` completo de novo.
- Depois do push, confira o resultado do workflow (GitHub Actions do repositório); se ficar vermelho, a correção é a próxima tarefa.
- Mudou o que o CI precisa (extensão PHP, variável, serviço)? Atualize o workflow e o `composer ci:check` juntos.
