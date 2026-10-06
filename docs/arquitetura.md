# Arquitetura

Monólito Laravel com domínios separados. O objetivo é não repetir o que acontece em ERPs que crescem sem fronteira: regra espalhada, domínio mexendo na tabela do outro e provedor amarrado no código.

## Domínios

| Domínio    | Pasta                       | Cuida de                                                         | Não sabe de        |
| ---------- | --------------------------- | ---------------------------------------------------------------- | ------------------ |
| Conta      | `app/Domains/Accounts`      | empresa, dono, login, preferências                               | conversas          |
| Conversas  | `app/Domains/Conversations` | clientes, conversas, mensagens e de onde vieram (`zip` ou `api`) | como o zip é lido  |
| Importação | `app/Domains/Imports`       | ler cada formato por um adaptador, registrar cada importação     | regras de leitura  |
| Leitura    | `app/Domains/Insights`      | as regras que geram os insights, uma por classe                  | telas e provedores |
| IA         | `app/Domains/Ai`            | conexões com provedores, pedidos à IA, máscara de dados, custo   | regras de negócio  |
| Plataforma | `app/Platform`              | logs, rastros, métricas, auditoria, notificações                 | regras de negócio  |

As pastas nascem quando o primeiro código do domínio entra. Já existem:

- `app/Platform`: telemetria, logs, auditoria (`docs/observabilidade.md`) e notificações do sino (`Notice`).
- `app/Domains/Conversations`: clientes, vendedoras, conversas e mensagens. Quem grava usa o contrato `ConversationStore`, que recebe `IncomingConversation` sem saber de onde veio e ignora mensagem que já existe (`external_id` por cliente). Cada mensagem guarda o cliente (`contact_id`) e a importação que a trouxe primeiro (`import_id`), sem depender da conversa. A conversa é um atendimento (veja "Atendimentos" abaixo): o `ConversationStore` corta o histórico de cada cliente ao gravar, e o comando `owly:recut` refaz o corte de uma empresa. As telas leem por `ConversationQueries`.
- `app/Domains/Imports`: a tabela `imports`, o serviço `StartImport` (guarda o zip, recusa arquivo repetido e manda para a fila), o job `ProcessImport` (lê pelo `Importer`, grava pelo `ConversationStore`, apaga o zip, audita e avisa no sino) e a regra `DateGaps`. O formato ligado vem de `owly.imports.format`.
- `app/Domains/Ai`: conexões com provedores (`AiConnection`), o contrato `AiProvider` com o adaptador `Gemini`, a máscara (`Redactor`) e as perguntas sobre conversas (`docs/ia.md`). Lê a conversa pelo contrato `ConversationTranscript` e aparece na tela da conversa por `ConversationPanels`, o espaço que Conversas abre para outros domínios.
- `app/Domains/Insights`: as leituras do painel (`docs/painel.md`). Uma regra por classe em `Rules` (`ClientTurns`, `ClosingMessage`, `QuoteMessage`, `SaleSignal`, `Topics`); o serviço `Insights` passa uma vez pelas conversas e o `Dashboard` monta o painel e as listas. Lê as conversas pelo contrato `ConversationFacts`, a última importação por `ImportHealth` e o horário da empresa por `CurrentOrganization::calendar()`.
- `app/Console`: comandos de operação que juntam domínios. Hoje só o `owly:integrity`, que tira a foto dos dados de uma empresa antes de uma migração e confere depois (veja "Mensagens" abaixo). Usa os contratos de Conversas e o `Dashboard` de Leitura, como a tela faz.
- `app/Domains/Accounts`: a empresa (`Organization`, com o horário de atendimento e os feriados), o calendário dela (`WorkingCalendar`, com as regras `BusinessHours` e `Holidays`), o comando `owly:owner` e `CurrentOrganization`, que os outros domínios usam para saber de qual empresa é a requisição e, por `calendar()`, o horário dela. O `User` continua em `app/Models`, onde o Laravel e o Fortify esperam.

## Regras de fronteira

1. Um domínio só lê e grava as próprias tabelas e models.
2. Para usar outro domínio: o contrato público dele (`app/Domains/<Nome>/Contracts`) ou um evento que ele publica (`Events`). Nada de importar model alheio.
3. A direção é sempre esta: Importação grava em Conversas pelo contrato; Leitura lê Conversas pelo contrato; IA é chamada por quem precisa, pelo contrato.
4. `tests/Unit/ArchitectureTest.php` garante isso: de outro domínio, só `Contracts`, `Data`, `Events` e, de Conta, o que faz o isolamento por empresa (`CurrentOrganization`, o trait `BelongsToOrganization` e o middleware de job `ForOrganization`).

## Um calendário só

Dia útil e hora útil têm uma definição só: o `WorkingCalendar` de Conta (expediente por dia, feriados nacionais quando ligados e feriados da empresa). O corte dos atendimentos, o orçamento parado, o sem resposta e a demora perguntam a ele (`isOpen`, `isWorkingDay`, `secondsBetween`, `workingDaysBetween`). O `ArchitectureTest` reprova outra conta de feriado ou expediente fora de Conta.

## Provedores como adaptadores

Cada dependência de fora entra por uma interface do domínio, com a implementação em `Adapters/<Provedor>`:

- **Importação:** `Importer` com o adaptador `WhatsAppXlsxZip` (o zip de planilhas da ferramenta de exportação). A exportação nativa do WhatsApp e a API oficial viram outros adaptadores.
- **IA:** `AiProvider` com o adaptador `Gemini`. A conta pode conectar vários provedores; trocar ou somar um não mexe nas regras.
- Todo adaptador tem uma versão falsa para testes, e nenhum teste chama a rede.

## Empresa desde o primeiro dia

Toda tabela de negócio tem `organization_id`, e toda consulta filtra por ela (`CurrentOrganization::id()`), mesmo com uma empresa só usando. Logs e auditoria recebem a empresa pelo contexto do Laravel, preenchido no login. Rota com `{id}` busca dentro da empresa e responde 404 fora dela.

O filtro não depende de ninguém lembrar dele:

- **Modelos de negócio usam `BelongsToOrganization`** (Conta). O trait acrescenta `organization_id = empresa do contexto` em toda consulta e preenche a empresa nos registros novos. Sem empresa no contexto, a consulta lança exceção em vez de devolver dados de todas as empresas. Registro novo com outra empresa, dentro de um contexto, também é recusado. Só `Organization` fica sem o trait; `User`, auditoria e notificações não são modelos de domínio.
- **De onde vem a empresa:** na requisição, do usuário logado. Em job, do middleware `ForOrganization`, com o id que o job guarda (job guarda ids, nunca modelos: o modelo seria buscado antes do middleware, sem contexto). O `failed()` do job não passa pelo middleware e abre o contexto com `runAs`. Em comando e teste, `CurrentOrganization::runAs($id, fn)`; nos testes, `inOrganization()` e `acrossOrganizations()` do `TestCase`.
- **Serviços que recebem o id da empresa** (os contratos de Conversas) chamam `CurrentOrganization::ensure($id, fn)`: com outra empresa no contexto é erro de programação; sem contexto, abre um só para a chamada.
- **Ver todas as empresas** só com `across($motivo, fn)`, e só em comandos (`Console/`) ou na futura administração (`app/Platform/Admin`). O motivo vai para o log.
- **`Model::insert()` em lote** não passa pelo `creating`: quem usa monta as linhas com `organization_id`.
- **O `ArchitectureTest` reprova** modelo de domínio sem o trait e, em `app/`, consultas que passam por cima do escopo: `DB::table`, `DB::select` e parentes, `getQuery()`, `toBase()`, `withoutGlobalScope` e `join`. `DB::transaction` e `DB::raw` dentro de consulta Eloquent continuam liberados.

## Atendimentos

A tabela `conversations` guarda atendimentos: um cliente tem vários, cada um com começo e fim. A regra mora em `Conversations/Rules/ConversationCut`, com um teste por exemplo.

- **Só o cliente abre.** Mensagem do cliente (inclusive ligação perdida dele) depois de um dia útil inteiro sem mensagem de pessoa abre um atendimento novo. Vendedora, resposta automática e avisos entram sempre no atendimento mais recente; uma cobrança da vendedora reabre o que estava fechado.
- **O silêncio conta qualquer pessoa.** Depois de aberto, mensagem do cliente ou da vendedora segura o atendimento. Robô e avisos entram, mas não mexem no relógio; sem nenhuma mensagem de pessoa, o relógio parte do começo.
- **Dia útil inteiro** é uma data com expediente estritamente entre as duas mensagens, no fuso da empresa (`WorkingCalendar::workingDaysBetween`). Conta a data, não as horas: sábado de 8h às 12h vale um dia.
- **Fechado** (`status`) quando passou um dia útil inteiro entre a última mensagem de pessoa e a referência, que é a mensagem mais recente da empresa no Owly. A importação fecha os parados no fim (`ConversationStore::closeIdle`).
- **Começou pela empresa** (`opened_by = company`). A primeira mensagem do histórico abre o primeiro atendimento mesmo quando é da empresa, porque toda mensagem precisa estar num atendimento. Isso não equivale a um atendimento aberto pelo cliente: as contas de "clientes que procuraram" vão separar os dois, e um atendimento da empresa sem mensagem do cliente não gera espera nem resposta.
- **Responsável** (`seller_id`): a vendedora com mais mensagens no atendimento; no empate, quem respondeu primeiro.
- **Ao gravar**, por cliente e numa transação só: separa as mensagens novas; se todas são depois do começo do último atendimento, recalcula só a partir dele; se veio mensagem mais antiga, recalcula o histórico inteiro. O atendimento que começa na mesma mensagem fica com o mesmo id; quando dois se juntam, fica o mais antigo e o outro é apagado depois de as mensagens mudarem de lugar. Se algo falha no meio, nada muda.
- **`ConversationsRecut`** sai depois do commit quando atendimentos que já existiam perderam mensagens ou foram apagados. A IA escuta e leva cada pergunta para o atendimento da mensagem em foco.
- **Duas importações da mesma empresa** não rodam juntas: o `ProcessImport` tem a trava `WithoutOverlapping` pela empresa.
- **`owly:recut --empresa=ID [--dry-run]`** refaz todos os atendimentos com o calendário de hoje, um cliente por transação. Rodar de novo sem mudança não muda nada. Mudar o horário ou os feriados não refaz atendimentos fechados sozinho: o comando é rodado de propósito.

Para levar uma base que ainda tem uma conversa por cliente (antes da migração `2026_10_06_000002`):

1. `php artisan owly:integrity --empresa=ID --snapshot`
2. `php artisan migrate`
3. `php artisan owly:recut --empresa=ID --dry-run` e depois sem `--dry-run`
4. `php artisan owly:integrity --empresa=ID --compare`

Até o passo 3, um atendimento por cliente é um estado válido, só mais grosso; uma importação nova já corta os clientes que vierem nela.

## Mensagens

O id de uma mensagem é a âncora de tudo o que vem depois (oportunidades, achados, perguntas à IA). Por isso:

- **Nada apaga e reinsere mensagem.** Nenhuma função futura ("reprocessar importação", "limpar e importar de novo") pode fazer isso. Reimportar só acrescenta o que é novo.
- **Apagar uma conversa nunca apaga mensagens.** A chave `messages.conversation_id` é obrigatória e não tem cascata: o banco recusa apagar conversa que ainda tem mensagem. Quem refaz conversas move as mensagens antes. Apagar o cliente ou a empresa continua levando tudo, pelas cascatas de `contact_id` e `organization_id`.
- **`owly:integrity --empresa=ID`** confere isso numa migração: `--snapshot` guarda em `storage/app/private/integrity/` as contagens, o hash das mensagens (`cliente:id externo`, em ordem), o atendimento mais antigo de cada cliente e os números do painel; `--compare` confere contra a última foto. Sai com erro se o total ou o hash das mensagens mudou, se o total de clientes mudou, se o atendimento mais antigo de um cliente trocou de id, ou se aparece: mensagem com cliente ou empresa diferente do seu atendimento, atendimento vazio ou com totais errados, atendimentos do mesmo cliente que se sobrepõem, atendimento aberto pela empresa fora do começo do histórico, cliente cujo corte mudaria com um `owly:recut`, ou pergunta à IA fora do atendimento da sua mensagem. O painel aparece lado a lado, sem reprovar, porque uma migração pode mudá-lo de propósito. Só números, ids e hashes saem do comando.
