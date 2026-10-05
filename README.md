# owly

A Owly lê as conversas de venda pelo WhatsApp e mostra ao dono da empresa onde a venda escapou.

```bash
composer setup     # instala, cria o .env, migra e faz o build
composer dev       # sobe servidor, fila e Vite
composer ci:check  # o mesmo que o CI
```

Para entrar em dev, `php artisan db:seed` cria `dono@owly.test` com a senha `owly`. Em produção, o dono é criado com `php artisan owly:owner`: não existe cadastro aberto.

Guia para quem desenvolve (pessoas e agentes): [AGENTS.md](AGENTS.md). Escopo atual: [docs/escopo.md](docs/escopo.md).
