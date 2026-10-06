# Painel

O que a Owly lê nas conversas e mostra ao dono. Cada número abre a lista das conversas que o formam (`/dashboard/{leitura}`). As regras ficam em `app/Domains/Insights/Rules`, uma por classe, com teste em `tests/Unit/Insights`.

## Período

7 dias, 30 dias (padrão) ou tudo. O "hoje" é a mensagem mais recente da empresa, não a data do computador: quem só tem zip olha para os dias do zip (`Period`).

## Horário de atendimento

Toda espera conta só o tempo com a empresa aberta (`WorkingCalendar`, de Conta). O cliente que escreve às 19h da sexta e é atendido às 8h05 da segunda esperou 5 minutos, não um fim de semana.

- O dono ajusta em **Minha conta › Horário** (`/conta/horario`): abertura e fechamento de cada dia, ou fechado.
- Padrão, enquanto ninguém mexe: segunda a sexta das 8h às 18h, sábado das 8h às 12h, domingo fechado. O painel avisa que está usando o padrão.
- **Feriados nacionais** vêm ligados (`Holidays`): Ano-novo, Sexta-feira Santa (pela Páscoa de cada ano), Tiradentes, Dia do Trabalho, Independência, Aparecida, Finados, Proclamação da República, Consciência Negra e Natal. Carnaval e Corpus Christi são ponto facultativo e ficam de fora.
- **Feriados da empresa e da cidade:** data e nome, cadastrados na mesma tela. Em feriado a empresa conta como fechada.
- Tudo no fuso da empresa. Sem nenhum dia aberto, conta o relógio corrido.

## Leituras

| No painel           | Leitura (`/dashboard/...`) | Regra                                                                                                                                                      |
| ------------------- | -------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Clientes esperando  | `sem-resposta`             | a última vez do cliente ficou sem resposta de pessoa. "Ok", "obrigado", 👍 encerram e não contam (`ClosingMessage`)                                        |
| Tempo até responder | `tempo-de-resposta`        | mediana, em horas úteis, de cada vez que o cliente escreveu até a primeira mensagem de uma vendedora (`ClientTurns`); e quanto ficou em até 1 hora útil    |
| Fora do horário     | `fora-do-horario`          | vezes em que o cliente começou a escrever com a empresa fechada                                                                                            |
| Orçamentos parados  | `orcamentos-parados`       | orçamento enviado (preço "R$ 90" ou documento, `QuoteMessage`), sem venda depois, e o cliente calado há `owly.insights.stalled_quote_days` dias (padrão 2) |
| Vendas              | `vendas`                   | o cliente autorizou ou pagou: "pode fazer", "fechado", "fiz o pix", comprovante (`SaleSignal`). Leitura provável, não nota fiscal                          |
| O que procuram      | `procuram?filtro=<tema>`   | temas por palavras, configurados em `owly.insights.topics` (`Topics`)                                                                                      |
| Vendedoras          | `vendedora?filtro=<nome>`  | conversas, mensagens e tempo até responder de cada uma                                                                                                     |

Mensagem de resposta automática (robô) e aviso do sistema não encerram a espera do cliente: só resposta de pessoa conta.

## De onde vêm os dados

O domínio Leitura não lê tabela de ninguém. Usa:

- `ConversationFacts` (Conversas): as conversas em ordem, como `Timeline` de `MessageFact`, e o resumo de cada uma para as listas;
- `ImportHealth` (Importação): a última importação e os dias sem mensagem, para avisar quando o zip está incompleto;
- `CurrentOrganization::calendar()` (Conta): o horário, o fuso e os feriados da empresa, como `WorkingCalendar`.

A leitura é feita na hora, numa passada só pelas conversas do período (`Insights`). Com o zip da Gráfica (cerca de 500 conversas em 30 dias) leva meio segundo.
