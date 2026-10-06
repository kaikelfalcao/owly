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
- `app/Domains/Conversations`: clientes, vendedoras, conversas e mensagens. Quem grava usa o contrato `ConversationStore`, que recebe `IncomingConversation` sem saber de onde veio e ignora mensagem que já existe (`external_id` por conversa). As telas leem por `ConversationQueries`.
- `app/Domains/Imports`: a tabela `imports`, o serviço `StartImport` (guarda o zip, recusa arquivo repetido e manda para a fila), o job `ProcessImport` (lê pelo `Importer`, grava pelo `ConversationStore`, apaga o zip, audita e avisa no sino) e a regra `DateGaps`. O formato ligado vem de `owly.imports.format`.
- `app/Domains/Ai`: conexões com provedores (`AiConnection`), o contrato `AiProvider` com o adaptador `Gemini`, a máscara (`Redactor`) e as perguntas sobre conversas (`docs/ia.md`). Lê a conversa pelo contrato `ConversationTranscript` e aparece na tela da conversa por `ConversationPanels`, o espaço que Conversas abre para outros domínios.
- `app/Domains/Insights`: as leituras do painel (`docs/painel.md`). Uma regra por classe em `Rules` (`BusinessHours`, `Holidays`, `ClientTurns`, `ClosingMessage`, `QuoteMessage`, `SaleSignal`, `Topics`); o serviço `Insights` passa uma vez pelas conversas e o `Dashboard` monta o painel e as listas. Lê as conversas pelo contrato `ConversationFacts`, a última importação por `ImportHealth` e o horário da empresa por `CurrentOrganization::calendar()`.
- `app/Domains/Accounts`: a empresa (`Organization`, com o horário de atendimento e os feriados), o comando `owly:owner` e `CurrentOrganization`, que os outros domínios usam para saber de qual empresa é a requisição e, por `calendar()`, o horário dela. O `User` continua em `app/Models`, onde o Laravel e o Fortify esperam.

## Regras de fronteira

1. Um domínio só lê e grava as próprias tabelas e models.
2. Para usar outro domínio: o contrato público dele (`app/Domains/<Nome>/Contracts`) ou um evento que ele publica (`Events`). Nada de importar model alheio.
3. A direção é sempre esta: Importação grava em Conversas pelo contrato; Leitura lê Conversas pelo contrato; IA é chamada por quem precisa, pelo contrato.
4. `tests/Unit/ArchitectureTest.php` garante isso: de outro domínio, só `Contracts`, `Data`, `Events` e, de Conta, o que faz o isolamento por empresa (`CurrentOrganization`, o trait `BelongsToOrganization` e o middleware de job `ForOrganization`).

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
