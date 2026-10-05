# Glossário

Uma palavra por conceito, igual na tela e no código. Conceito novo entra aqui antes de entrar na tela.

| Na tela                    | No código       | O que é                                                                |
| -------------------------- | --------------- | ---------------------------------------------------------------------- |
| Empresa                    | `Organization`  | quem usa a Owly; todo dado pertence a uma                              |
| Dono                       | `User` (owner)  | a pessoa que entra na Owly; na primeira entrega, a única               |
| Cliente                    | `Contact`       | quem escreve para a empresa pelo WhatsApp, identificado pelo telefone  |
| Conversa                   | `Conversation`  | o histórico de mensagens com um cliente                                |
| Mensagem                   | `Message`       | uma mensagem, do cliente ou da empresa                                 |
| Vendedora                  | `Seller`        | quem responde pela empresa; na exportação, o nome em `*Nome:*`         |
| Importação                 | `Import`        | um zip enviado e o que ele trouxe                                      |
| Formato                    | `Importer`      | o jeito de ler um tipo de arquivo; hoje, o zip de planilhas            |
| Planilha que ficou de fora | `problems`      | arquivo do zip que não deu para ler, com o motivo                      |
| Dias sem mensagem          | `DateGaps`      | dois dias ou mais seguidos sem mensagem no zip: exportação incompleta  |
| Resposta automática        | `bot`           | mensagem do robô da empresa (fora do horário, modelo), não de pessoa   |
| Equipe                     | seller sem nome | mensagem da empresa sem assinatura `*Nome:*`                           |
| Origem                     | `source`        | de onde veio a conversa: `zip` ou `api`                                |
| Owly viu                   | `Insight`       | algo que a Owly encontrou e que pede atenção do dono                   |
| Orçamento                  | `Quote`         | preço enviado ao cliente                                               |
| Venda                      | `Sale`          | orçamento que virou pedido (comprovante, autorização ou "está pronto") |
| Notificação                | `Notice`        | aviso no sino do topo (importação concluída, falha…)                   |
| Atividade                  | `AuditEntry`    | o que aconteceu na conta: entradas, trocas de senha e afins            |
| Minha conta                | `/conta`        | perfil, segurança, aparência e atividade do dono                       |
| Pergunta à IA              | `AiQuestion`    | pergunta do dono sobre uma conversa ou uma mensagem, com a resposta    |
| Padrão                     | `is_default`    | a conexão de IA que responde às perguntas                              |
| Modelo                     | `model`         | a versão da IA do provedor (ex.: Gemini 2.5 Flash)                     |
| Máscara                    | `Redactor`      | troca nome, telefone e documentos por marcadores antes de ir para a IA |
| Conexão de IA              | `AiConnection`  | a chave de um provedor de IA ligada à empresa                          |
| Provedor                   | `Provider`      | quem fornece a IA (Gemini primeiro)                                    |
