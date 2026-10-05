---
paths:
    - 'tests/**'
---

# Testes

- Feature tests em `tests/Feature/` (fluxo com banco, `RefreshDatabase`), unitários em `tests/Unit/` (regras puras). Uma pasta por domínio quando houver mais de um arquivo.
- Nomes de método em português descrevendo o comportamento: `test_visitante_vai_para_o_login`.
- Banco: SQLite em memória (ver `phpunit.xml`). Não dependa de Postgres.
- Telas: `->assertInertia(fn (AssertableInertia $p) => $p->component('...'))`. Tela nova precisa de `npm run build` antes.
- Serviços de fora sempre falsos (`Http::fake()`); nenhum teste chama a rede.
- Dados inventados: nada de nome, telefone ou conversa de cliente real em teste, seed ou fixture.
- Cubra quem pode e quem não pode (outra empresa, visitante) em todo fluxo novo.
