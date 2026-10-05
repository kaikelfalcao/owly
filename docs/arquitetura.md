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

As pastas nascem quando o primeiro código do domínio entra; este commit só traz a base.

## Regras de fronteira

1. Um domínio só lê e grava as próprias tabelas e models.
2. Para usar outro domínio: o contrato público dele (`app/Domains/<Nome>/Contracts`) ou um evento que ele publica (`Events`). Nada de importar model alheio.
3. A direção é sempre esta: Importação grava em Conversas pelo contrato; Leitura lê Conversas pelo contrato; IA é chamada por quem precisa, pelo contrato.
4. Um teste de arquitetura vai garantir essas regras assim que existirem dois domínios.

## Provedores como adaptadores

Cada dependência de fora entra por uma interface do domínio, com a implementação em `Adapters/<Provedor>`:

- **Importação:** `Importer` com o adaptador `WhatsAppXlsxZip` (o zip de planilhas da ferramenta de exportação). A exportação nativa do WhatsApp e a API oficial viram outros adaptadores.
- **IA:** `AiProvider` com o adaptador `Gemini`. A conta pode conectar vários provedores; trocar ou somar um não mexe nas regras.
- Todo adaptador tem uma versão falsa para testes, e nenhum teste chama a rede.

## Empresa desde o primeiro dia

Toda tabela de negócio tem `organization_id`, e toda consulta filtra por ela, mesmo com uma empresa só usando. Rota com `{id}` busca dentro da empresa e responde 404 fora dela.
