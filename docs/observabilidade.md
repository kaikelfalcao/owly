# Observabilidade e auditoria

Pensadas desde o primeiro dia e entregues no passo 2 do escopo (o código está em `app/Platform`). Tudo sai por OpenTelemetry, para que trocar onde os dados são vistos seja só configuração.

**Regra de ouro:** nenhum log, rastro, métrica ou registro de auditoria leva texto de mensagem nem nome, telefone ou e-mail de cliente. Só ids, códigos e números.

| O quê       | Como                                                                                                               | Quem olha                          |
| ----------- | ------------------------------------------------------------------------------------------------------------------ | ---------------------------------- |
| Logs        | JSON estruturado, cada linha com `request_id`, empresa, usuário e tarefa da fila                                   | quem investiga um erro             |
| Rastros     | OpenTelemetry em cada requisição, tarefa da fila e consulta; a importação vira um rastro com uma etapa por arquivo | quem procura onde demorou          |
| Métricas    | duração e volume de cada importação, linhas rejeitadas, atraso da fila, tempo de resposta; custo e tokens da IA    | painel do Grafana                  |
| Dev         | contêiner `grafana/otel-lgtm` no `docker compose` (Grafana, Loki, Tempo e Prometheus juntos)                       | quem desenvolve                    |
| Auditoria   | tabela só de inclusão: quem, o quê, quando, sobre qual registro, de onde (canal, IP, navegador) e o que mudou      | o dono, em Minha conta › Atividade |
| Importações | cada importação é um registro com estado, contagens, erros por arquivo e a assinatura do zip                       | o dono, na tela Importar           |

Primeiras ações auditadas: entrar, falha ao entrar, trocar senha, ligar ou desligar duas etapas, importação iniciada, concluída e com erro, conexão de IA criada ou removida, pergunta feita à IA.

## Como usar no código

- **Log:** `Log::info('importação concluída', ['import_id' => $import->id, 'files' => 12])`. O canal padrão (`json`) já junta `request_id`, `trace_id`, `user_id` e, na fila, `job`. Campos com cara de dado pessoal (`email`, `phone`, `name`, `body`, `token`…) saem como `[removido]` (`ScrubPersonalData`), mas a regra é não mandar.
- **Auditoria:** `app(Audit::class)->record('imports.finished', $import, ['files' => 12])`. A ação segue `area.acao`; `meta` aceita só números, booleanos e códigos curtos em minúsculas, e recusa o resto com erro. A tabela `audit_entries` não aceita alteração nem exclusão, nem um registro por vez nem em lote (`AppendOnlyBuilder`); a aplicação não tem rota que mexa nela.
- **O que mudou:** `changes: ['provider' => ['gemini', 'claude']]` guarda antes e depois, com a mesma regra de `meta`. Campo com dado pessoal (nome, e-mail) entra como `Audit::HIDDEN`: fica registrado que mudou, sem o valor.
- **Quem e sobre o quê:** `user_id` é quem fez (vazio numa tentativa de entrar sem login); o registro afetado vai como `$subject`. Canal (`web`, `console`, `queue`), IP e navegador são preenchidos sozinhos; fora do navegador não há IP nem navegador.
- **Catálogo:** toda ação nova entra em `AuditCatalog` com o texto da tela, a criticidade (Normal, Importante, Crítico) e, se for uma falha, o resultado. Criticidade e resultado saem do catálogo, não são gravados: reclassificar vale para o histórico todo.
- **Rastro de uma etapa:** `app(Telemetry::class)->tracer()->spanBuilder('ler arquivo')->startSpan()` e `->end()` no fim. Requisições, tarefas da fila e consultas ao banco já viram rastro sozinhas (a consulta vai com os `?`, sem os valores).
- **Métrica:** `app(Telemetry::class)->histogram('owly.imports.duration', 's', 'Duração das importações')->record($segundos, ['outcome' => 'ok'])`. Atributos são códigos de poucos valores; nunca ids, para não criar uma série por registro.

## Ligar em dev

1. `docker compose up -d otel`
2. No `.env`: `OTEL_EXPORTER_OTLP_ENDPOINT=http://localhost:4318`
3. Grafana em http://localhost:3000 (admin / admin): rastros no Tempo, métricas no Prometheus, logs no Loki.

Sem `OTEL_EXPORTER_OTLP_ENDPOINT` a telemetria fica desligada e nada sai da aplicação; os testes rodam assim. Se o coletor cair, a Owly segue funcionando e só registra o erro de envio. Em contêiner, `LOG_JSON_PATH=php://stderr` manda os logs JSON para a saída padrão.

## Já auditado

| Ação                                                                                          | Criticidade | Observação                                                                                                           |
| --------------------------------------------------------------------------------------------- | ----------- | -------------------------------------------------------------------------------------------------------------------- |
| `auth.login`, `auth.logout`                                                                   | Normal      | `remember` no login                                                                                                  |
| `auth.failed`                                                                                 | Importante  | falha; `known_user`, nunca o e-mail digitado                                                                         |
| `auth.two_factor_failed`                                                                      | Importante  | falha                                                                                                                |
| `auth.password_reset`                                                                         | Importante  |                                                                                                                      |
| `auth.password_changed`                                                                       | Importante  |                                                                                                                      |
| `auth.recovery_codes_generated`                                                               | Importante  |                                                                                                                      |
| `auth.two_factor_enabled`                                                                     | Normal      |                                                                                                                      |
| `auth.two_factor_disabled`                                                                    | Crítico     | a conta fica mais fácil de invadir                                                                                   |
| `accounts.owner_created`                                                                      | Normal      | pelo comando `owly:owner`                                                                                            |
| `accounts.name_changed`                                                                       | Normal      | valor oculto                                                                                                         |
| `accounts.email_changed`                                                                      | Crítico     | troca o login; valor oculto                                                                                          |
| `accounts.business_hours_changed`                                                             | Normal      | antes e depois de cada dia (`08:00-18:00` ou `closed`), feriados nacionais e quantos feriados próprios; nunca o nome |
| `audit.exported`                                                                              | Importante  | `rows`, `period` e `filtered`; nunca o texto da busca                                                                |
| `imports.started`                                                                             | Normal      | `size`                                                                                                               |
| `imports.finished`                                                                            | Normal      | `files`, `conversations_new`, `messages_new`, `problems`                                                             |
| `imports.failed`                                                                              | Importante  | falha; `code`                                                                                                        |
| `ai.connection_created`                                                                       | Importante  | `provider`                                                                                                           |
| `ai.connection_default`                                                                       | Normal      | `provider`                                                                                                           |
| `ai.connection_removed`                                                                       | Importante  | `provider`                                                                                                           |
| `ai.question_asked`                                                                           | Normal      | `provider`, `ok`, `input_tokens`, `output_tokens`; nunca o texto                                                     |
| `insights.opportunity_won`, `insights.opportunity_discarded`, `insights.opportunity_reopened` | Normal      | situação antes e depois                                                                                              |
| `insights.opportunity_lost`                                                                   | Normal      | `reason` (código do motivo); situação antes e depois                                                                 |

Nome do arquivo nunca vai para a auditoria, nem o texto da pergunta ou da resposta à IA.

Métrica `owly.imports.duration` (segundos, atributo `outcome`: `done` ou `failed`) e rastro `import` por importação. Cada pergunta à IA vira o rastro `ai.generate` (provedor e tokens) e entra na métrica `owly.ai.duration` (atributos `provider` e `outcome`).

## Tela de Atividade

`/conta/atividade`: lista paginada (25 por página) da empresa de quem está logado, mais recente primeiro. Filtros por período (contado no fuso da empresa), quem, ação, recurso, resultado e criticidade, todos juntos e feitos no banco; a busca procura no nome, no texto da ação, no `#id` do registro, no IP e no request id. Cada linha abre um painel com tudo o que foi guardado. `?resource=user&resource_id=3` mostra só a atividade de um registro: é o link que as telas de cada recurso vão usar. "Exportar planilha" baixa um CSV com tudo o que os filtros acham e grava `audit.exported`.
