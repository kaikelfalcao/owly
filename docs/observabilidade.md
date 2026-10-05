# Observabilidade e auditoria

Pensadas desde o primeiro dia e entregues no passo 2 do escopo (o código está em `app/Platform`). Tudo sai por OpenTelemetry, para que trocar onde os dados são vistos seja só configuração.

**Regra de ouro:** nenhum log, rastro, métrica ou registro de auditoria leva texto de mensagem nem nome, telefone ou e-mail de cliente. Só ids, códigos e números.

| O quê       | Como                                                                                                               | Quem olha                          |
| ----------- | ------------------------------------------------------------------------------------------------------------------ | ---------------------------------- |
| Logs        | JSON estruturado, cada linha com `request_id`, empresa, usuário e tarefa da fila                                   | quem investiga um erro             |
| Rastros     | OpenTelemetry em cada requisição, tarefa da fila e consulta; a importação vira um rastro com uma etapa por arquivo | quem procura onde demorou          |
| Métricas    | duração e volume de cada importação, linhas rejeitadas, atraso da fila, tempo de resposta; custo e tokens da IA    | painel do Grafana                  |
| Dev         | contêiner `grafana/otel-lgtm` no `docker compose` (Grafana, Loki, Tempo e Prometheus juntos)                       | quem desenvolve                    |
| Auditoria   | tabela só de inclusão: quem, o quê, quando, IP e sobre qual registro                                               | o dono, em Minha conta › Atividade |
| Importações | cada importação é um registro com estado, contagens, erros por arquivo e a assinatura do zip                       | o dono, na tela Importar           |

Primeiras ações auditadas: entrar, falha ao entrar, trocar senha, ligar ou desligar duas etapas, importação iniciada, concluída e com erro, conexão de IA criada ou removida, pergunta feita à IA.

## Como usar no código

- **Log:** `Log::info('importação concluída', ['import_id' => $import->id, 'files' => 12])`. O canal padrão (`json`) já junta `request_id`, `trace_id`, `user_id` e, na fila, `job`. Campos com cara de dado pessoal (`email`, `phone`, `name`, `body`, `token`…) saem como `[removido]` (`ScrubPersonalData`), mas a regra é não mandar.
- **Auditoria:** `app(Audit::class)->record('imports.finished', $import, ['files' => 12])`. A ação segue `area.acao`; `meta` aceita só números, booleanos e códigos curtos em minúsculas, e recusa o resto com erro. A tabela `audit_entries` não aceita alteração nem exclusão.
- **Rastro de uma etapa:** `app(Telemetry::class)->tracer()->spanBuilder('ler arquivo')->startSpan()` e `->end()` no fim. Requisições, tarefas da fila e consultas ao banco já viram rastro sozinhas (a consulta vai com os `?`, sem os valores).
- **Métrica:** `app(Telemetry::class)->histogram('owly.imports.duration', 's', 'Duração das importações')->record($segundos, ['outcome' => 'ok'])`. Atributos são códigos de poucos valores; nunca ids, para não criar uma série por registro.

## Ligar em dev

1. `docker compose up -d otel`
2. No `.env`: `OTEL_EXPORTER_OTLP_ENDPOINT=http://localhost:4318`
3. Grafana em http://localhost:3000 (admin / admin): rastros no Tempo, métricas no Prometheus, logs no Loki.

Sem `OTEL_EXPORTER_OTLP_ENDPOINT` a telemetria fica desligada e nada sai da aplicação; os testes rodam assim. Se o coletor cair, a Owly segue funcionando e só registra o erro de envio. Em contêiner, `LOG_JSON_PATH=php://stderr` manda os logs JSON para a saída padrão.

## Já auditado

`auth.login`, `auth.logout`, `auth.failed` (com `known_user`, nunca o e-mail digitado), `auth.password_reset`, `auth.password_changed`, `auth.two_factor_enabled`, `auth.two_factor_disabled`, `auth.two_factor_failed`, `auth.recovery_codes_generated`. As ações de importação e IA entram com os passos delas.
