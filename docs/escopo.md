# Escopo da primeira entrega

Combinado com o Kaíke em 05/10/2026. A primeira entrega é pequena de propósito: o dono entra, sobe o zip das conversas e vê onde a venda escapou.

## Quem usa

Só o dono da empresa. O dono é criado por comando; não há cadastro aberto. Cadastro, planos e domínio próprio ficam num site de vendas separado, e o site de marketing é outro projeto.

## Entra

1. **Entrar:** e-mail e senha, esqueci a senha, verificação em duas etapas opcional.
2. **Layout:** menu lateral, trilha da página, sino de notificações e o usuário clicável com o menu da conta (tema, Minha conta, sair).
3. **Importar:** subir o zip, processar em segundo plano, avisar no sino quando terminar e mostrar o que a importação trouxe (conversas novas, mensagens, período, buracos de datas). Subir o mesmo zip de novo não duplica nada.
4. **Conversas e clientes:** lista e leitura de cada conversa; ficha do cliente com linha do tempo (conversas, orçamentos, vendas).
5. **Painel:** os insights escolhidos no estudo do zip (abaixo). Todo número abre as conversas que o formam.
6. **IA:** a conta conecta um ou vários provedores por um assistente passo a passo; o primeiro e único implementado é o Gemini. Primeiro uso: perguntar à IA sobre uma conversa ou uma resposta.
7. **Por baixo:** observabilidade e auditoria (`docs/observabilidade.md`) e testes desde o primeiro commit.

## Insights do painel

Escolhidos a partir do estudo do zip da Gráfica In Prime (499 conversas de setembro de 2026).

| Insight                                    | Como sai                                  | Entra? |
| ------------------------------------------ | ----------------------------------------- | ------ |
| Clientes sem resposta                      | Regra; a IA separa "obrigado" de pergunta | Sim    |
| Tempo até responder (horário comercial)    | Regra                                     | Sim    |
| Conversas que chegam fora do horário       | Regra                                     | Sim    |
| Orçamentos parados                         | Regra (o valor vem em PDF, fora do zip)   | Sim    |
| Vendas fechadas                            | Regra + pergunta "Fechou?" ou IA          | Sim    |
| O que os clientes procuram                 | Regra por palavras                        | Sim    |
| Carga por vendedora                        | Regra                                     | Sim    |
| Saúde da importação (buracos de datas)     | Regra                                     | Sim    |
| Perguntar à IA sobre uma conversa/resposta | IA, sob pedido                            | Sim    |
| Motivo da perda                            | IA                                        | Depois |
| Clientes que voltam                        | Regra                                     | Depois |
| Qualidade da resposta da vendedora         | IA                                        | Depois |
| Áudios (transcrição)                       | IA                                        | Depois |

## Fica de fora

- Cadastro público, planos, créditos, domínio próprio.
- Módulos, caixa de entrada e API oficial do WhatsApp (o modelo de dados já aceita a origem `api`; a tela não existe).
- Login de vendedora, convites, perfis.
- Webhooks, API pública, MCP, playbook.
- Editar ou juntar clientes.

## Ordem de entrega

Cada passo é um PR revisado antes do próximo, com o `ci:check` verde.

1. Projeto limpo com tema, coruja, CI e regras para agentes (este commit).
2. Plataforma: logs, rastros, métricas e auditoria.
3. Conta e layout.
4. Conversas e importação.
5. IA com o assistente de conexão.
6. Painel.

## Ideias para depois

Anote aqui o que surgir fora do escopo, em vez de construir.
