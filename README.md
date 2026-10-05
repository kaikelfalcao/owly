# owly

A Owly lê as conversas de venda pelo WhatsApp e mostra ao dono da empresa onde a venda escapou.

```bash
composer setup     # instala, cria o .env, migra e faz o build
composer dev       # sobe servidor, fila e Vite
composer ci:check  # o mesmo que o CI
```

O zip aceito vai até 50 MB (`OWLY_IMPORT_MAX_KB`). O `php artisan serve` (e o `composer dev`) já sobe o PHP com esse limite; o padrão do PHP é 2 MB. Num servidor de verdade, ajuste `upload_max_filesize` e `post_max_size` do PHP e o limite de corpo do proxy (no nginx, `client_max_body_size`) para o mesmo tamanho.

Para entrar em dev, `php artisan db:seed` cria `dono@owly.test` com a senha `owly`. Em produção, o dono é criado com `php artisan owly:owner`: não existe cadastro aberto.

Guia para quem desenvolve (pessoas e agentes): [AGENTS.md](AGENTS.md). Escopo atual: [docs/escopo.md](docs/escopo.md).
