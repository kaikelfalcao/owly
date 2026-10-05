# Owly: guia para agentes de código

Instruções para qualquer agente de IA (Claude Code, Codex, Cursor…) e para pessoas que mexem neste repositório. Regras por área em `.claude/rules/`; procedimentos em `.claude/skills/`.

## O que é

A Owly lê as conversas de venda pelo WhatsApp e mostra ao dono da empresa onde a venda escapou: cliente sem resposta, orçamento parado, demora para responder. A primeira fonte é o zip exportado do WhatsApp; a API oficial vem depois e convive com ele.

- Escopo da primeira entrega e o que fica de fora: `docs/escopo.md`
- Arquitetura (domínios, adaptadores, empresa): `docs/arquitetura.md`
- Identidade visual: `docs/design.md`
- Observabilidade e auditoria: `docs/observabilidade.md`
- Palavras da tela e do código: `docs/glossario.md`

## Stack

Laravel 13, PHP 8.3+, Inertia 3 + React 19 + TypeScript, Tailwind 4, shadcn/ui, Fortify (login e duas etapas), Wayfinder, Vite+ (`vp`), Pint, PHPUnit 12. SQLite em memória nos testes.

## Comandos

| O quê                           | Comando                                   |
| ------------------------------- | ----------------------------------------- |
| Instalar tudo                   | `composer setup`                          |
| Subir em dev                    | `composer dev`                            |
| **Checagem completa (a do CI)** | `composer ci:check`                       |
| Só um teste                     | `php artisan test --filter=nome_do_teste` |
| Formatar PHP                    | `vendor/bin/pint --dirty`                 |
| Formatar e lintar o front       | `npm run check:fix`                       |
| Ligar os hooks do git           | `composer hooks` (o `setup` já liga)      |

## Definição de pronto

1. `composer ci:check` passa **inteiro**, localmente, antes de todo commit e push (`.claude/rules/ci.md`).
2. Commit em Conventional Commits com descrição em português (`.claude/rules/commits.md`).
3. Fluxo novo tem teste de feature; regra nova tem teste unitário.
4. Tela, permissão ou conceito novo entra em `docs/`.

Não diga que algo está pronto, nem faça push, com o `ci:check` vermelho. Não pule, desligue nem marque teste como ignorado.

## Princípios

- **Uma coisa bem feita antes da próxima.** Nada entra no produto sem estar no escopo combinado; ideia nova vai para o fim de `docs/escopo.md`.
- **Domínios separados.** Um domínio não lê as tabelas do outro; conversa por contrato ou evento.
- **Provedor é adaptador.** IA, formato de importação e WhatsApp entram por interface; trocar de provedor não mexe em regra.
- **Uma regra, uma classe**, com teste.
- **Empresa sempre:** toda tabela de negócio tem `organization_id` e toda consulta filtra por ela.
- **A IA sugere, não age sozinha.**

## Convenções

- Interface, docs, comentários e commits em **português do Brasil**; identificadores de código em inglês.
- Texto para quem usa: frases curtas, sem jargão, com as palavras do glossário.
- Comentários poucos, explicando o porquê.

## Dados de clientes (inegociável)

- **Nunca** commite `data/`, `*.zip`, `*.xlsx`, exportações, prints com dados reais ou dumps.
- Não publique conteúdo de conversa fora do projeto.
- Testes e seeds usam dados inventados.
- Logs, rastros e auditoria levam só ids, códigos e números.
