# Observabilidade e auditoria

Pensadas desde o primeiro dia e entregues no passo 2 do escopo. Tudo sai por OpenTelemetry, para que trocar onde os dados são vistos seja só configuração.

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
