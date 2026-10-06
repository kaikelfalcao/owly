# Glossário

Uma palavra por conceito, igual na tela e no código. Conceito novo entra aqui antes de entrar na tela.

| Na tela                    | No código        | O que é                                                                             |
| -------------------------- | ---------------- | ----------------------------------------------------------------------------------- |
| Empresa                    | `Organization`   | quem usa a Owly; todo dado pertence a uma                                           |
| Dono                       | `User` (owner)   | a pessoa que entra na Owly; na primeira entrega, a única                            |
| Cliente                    | `Contact`        | quem escreve para a empresa pelo WhatsApp, identificado pelo telefone               |
| Conversa                   | histórico        | todas as mensagens com um cliente, atravessando os atendimentos                     |
| Atendimento                | `Conversation`   | trecho da conversa com começo e fim: abre quando o cliente escreve                  |
| Começou pela empresa       | `opened_by`      | atendimento aberto por mensagem da empresa (só o primeiro do cliente)               |
| Aberto, Fechado            | `status`         | situação do atendimento: fecha depois de um dia útil inteiro sem mensagem de pessoa |
| Responsável                | `seller_id`      | a vendedora com mais mensagens no atendimento; no empate, quem respondeu primeiro   |
| Mensagem                   | `Message`        | uma mensagem, do cliente ou da empresa                                              |
| Vendedora                  | `Seller`         | quem responde pela empresa; na exportação, o nome em `*Nome:*`                      |
| Importação                 | `Import`         | um zip enviado e o que ele trouxe                                                   |
| Formato                    | `Importer`       | o jeito de ler um tipo de arquivo; hoje, o zip de planilhas                         |
| Planilha que ficou de fora | `problems`       | arquivo do zip que não deu para ler, com o motivo                                   |
| Dias sem mensagem          | `DateGaps`       | dois dias ou mais seguidos sem mensagem no zip: exportação incompleta               |
| Resposta automática        | `bot`            | mensagem do robô da empresa (fora do horário, modelo), não de pessoa                |
| Equipe                     | seller sem nome  | mensagem da empresa sem assinatura `*Nome:*`                                        |
| Origem                     | `source`         | de onde veio a conversa: `zip` ou `api`                                             |
| Owly viu                   | `Insight`        | algo que a Owly encontrou e que pede atenção do dono                                |
| Orçamento                  | `Quote`          | preço enviado ao cliente                                                            |
| Venda                      | `Sale`           | orçamento que virou pedido (comprovante, autorização ou "está pronto")              |
| Horário de atendimento     | `BusinessHours`  | os dias e horas em que a empresa está aberta; a espera só conta nele                |
| Hora útil                  | business seconds | tempo com a empresa aberta, fora feriados                                           |
| Feriado                    | `Holidays`       | dia fechado: os nacionais e os que a empresa cadastra                               |
| Vez do cliente             | `Turn`           | mensagens seguidas do cliente até uma vendedora responder                           |
| Tempo até responder        | response         | mediana, em horas úteis, de cada vez do cliente até a resposta                      |
| Período                    | `Period`         | 7 dias, 30 dias ou tudo, contados da mensagem mais recente                          |
| Notificação                | `Notice`         | aviso no sino do topo (importação concluída, falha…)                                |
| Atividade                  | `AuditEntry`     | o que aconteceu na conta: entradas, trocas de senha e afins                         |
| Criticidade                | `Severity`       | o quanto uma atividade pede atenção: Normal, Importante ou Crítico                  |
| Resultado                  | `Outcome`        | se a atividade deu certo (Concluída) ou não (Falhou)                                |
| Canal                      | `Channel`        | por onde a atividade chegou: navegador, comando ou tarefa da fila                   |
| Recurso                    | `subject`        | o registro sobre o qual a atividade foi feita (o login, a importação…)              |
| Minha conta                | `/conta`         | perfil, segurança, horário, aparência e atividade do dono                           |
| Pergunta à IA              | `AiQuestion`     | pergunta do dono sobre uma conversa ou uma mensagem, com a resposta                 |
| Padrão                     | `is_default`     | a conexão de IA que responde às perguntas                                           |
| Modelo                     | `model`          | a versão da IA do provedor (ex.: Gemini 2.5 Flash)                                  |
| Máscara                    | `Redactor`       | troca nome, telefone e documentos por marcadores antes de ir para a IA              |
| Conexão de IA              | `AiConnection`   | a chave de um provedor de IA ligada à empresa                                       |
| Provedor                   | `Provider`       | quem fornece a IA (Gemini primeiro)                                                 |
