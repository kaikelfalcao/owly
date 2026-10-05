---
paths:
    - 'app/**/*.php'
    - 'routes/**/*.php'
    - 'database/**/*.php'
    - 'config/**/*.php'
    - 'bootstrap/**/*.php'
---

# Back-end (Laravel)

- **Domínios separados** (`docs/arquitetura.md`): cada domínio vive em `app/Domains/<Nome>` e só lê e grava as próprias tabelas. Para usar outro domínio, chame o contrato público dele (`Contracts/`) ou ouça um evento dele. Nunca importe um model de outro domínio.
- **Provedores são adaptadores.** IA, formatos de importação e, depois, WhatsApp entram por uma interface do domínio; a implementação concreta fica em `Adapters/<Provedor>` e é escolhida por configuração. Regra de negócio não sabe qual provedor está ligado.
- **Uma regra, uma classe.** Cada regra de leitura (cliente sem resposta, tempo até responder…) é uma classe em `app/Domains/Insights/Rules`, com teste próprio.
- **Empresa sempre.** Toda tabela de negócio tem `organization_id`; toda consulta filtra por ela. Rota com `{id}` busca dentro da empresa e responde 404 fora dela.
- **Controllers finos**, regra em serviços do domínio. Jobs com `tries`/`backoff` e falha que avisa a pessoa.
- **Respostas Inertia:** sucesso com `Inertia::flash('toast', ['type' => 'success', 'message' => '...'])` e `back()`; validação com mensagens em português.
- **Auditoria e logs** (`docs/observabilidade.md`): ação que muda dado ou acesso grava auditoria; log e rastro levam só ids, códigos e números.
- **Migrations:** `down()` funcionando, índices para colunas filtradas. Nunca edite migration já publicada na `main`; crie outra.
- **Datas:** guarde em UTC; mostre no fuso da empresa.
- Formate com `vendor/bin/pint --dirty` (o hook já faz ao editar).
