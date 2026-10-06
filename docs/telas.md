# Telas

Só o dono entra. Toda tela, exceto as de entrar, pede login.

| Tela                       | Endereço                                  | O que tem                                                                                                                                                                               |
| -------------------------- | ----------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Entrar                     | `/login`                                  | e-mail e senha, "Continuar conectado", coruja e o que a Owly faz                                                                                                                        |
| Esqueceu a senha           | `/forgot-password`, `/reset-password/...` | link por e-mail para criar senha nova                                                                                                                                                   |
| Verificação em duas etapas | `/two-factor-challenge`                   | código do aplicativo ou código de recuperação                                                                                                                                           |
| Painel                     | `/dashboard`                              | clientes esperando, orçamentos parados, tempo até responder, vendas, o que procuram e vendedoras (`docs/painel.md`)                                                                     |
| Leitura do painel          | `/dashboard/{leitura}`                    | as conversas por trás de um número do painel, no mesmo período                                                                                                                          |
| Importar                   | `/importar`                               | subir o zip; cada importação com o que trouxe, período, dias sem mensagem e planilhas que ficaram de fora                                                                               |
| Conversas                  | `/conversas`                              | um atendimento por linha, o mais recente primeiro: cliente, situação (aberto ou fechado), responsável, "Atendimento 2 de 3" e a última mensagem; busca por nome ou telefone             |
| Conversa                   | `/conversas/{id}`                         | um atendimento: "Atendimento 2 de 3" com o anterior e o seguinte, situação, "Começou pela empresa", responsável, quem atendeu, oportunidades, as mensagens dia a dia e "Perguntar à IA" |
| IA                         | `/ia`                                     | as conexões com provedores de IA: qual é a padrão, modelo, final da chave; trocar a padrão e remover                                                                                    |
| Conectar uma IA            | `/ia/conectar`                            | assistente em três passos: provedor, chave (testada antes de guardar) e modelo                                                                                                          |
| Minha conta › Perfil       | `/conta/perfil`                           | nome e e-mail de entrada                                                                                                                                                                |
| Minha conta › Segurança    | `/conta/seguranca`                        | trocar a senha, ligar ou desligar as duas etapas (pede a senha antes)                                                                                                                   |
| Minha conta › Horário      | `/conta/horario`                          | horário de atendimento de cada dia, feriados nacionais e feriados da empresa                                                                                                            |
| Minha conta › Aparência    | `/conta/aparencia`                        | tema claro, escuro ou do sistema                                                                                                                                                        |
| Minha conta › Atividade    | `/conta/atividade`                        | a auditoria da empresa: filtros, busca, detalhes e exportação em CSV                                                                                                                    |

## Em toda tela logada

- **Menu lateral** com a coruja, "Painel", "Conversas", "Importar" e "IA".
- **Barra do topo:** a trilha da página (o último item é o nome da tela), o sino de notificações e o usuário com o nome da empresa. O menu do usuário tem Minha conta, o tema e Sair.
- **Sino:** os últimos dez avisos, quantos não lidos e "Marcar como lidas". Abrir um aviso marca como lido e leva para a tela dele. Confere avisos novos a cada 30 s com a aba aberta e mostra um toast quando chega um.

## Importar e Conversas

- O zip é lido em segundo plano. A tela se atualiza sozinha enquanto a importação roda, e o sino avisa quando termina ou falha.
- Subir o mesmo arquivo de novo é recusado ("Este zip já foi importado em…"). Um zip diferente que cobre o mesmo período entra, e só as mensagens novas são gravadas.
- O zip é apagado assim que é lido; ficam só as conversas.
- Datas e horários aparecem no fuso da empresa, não no do navegador.
- A conversa mostra imagem, áudio e documento só pelo nome: o arquivo não vem no zip.
- Quem abre uma conversa pela lista ou por uma leitura do painel volta, pela trilha, para a mesma busca, página, leitura, período e filtro. O anterior e o seguinte mantêm esse caminho.
- **Oportunidades** no atendimento: as que abriram ou fecharam ali e as que vêm abertas de antes, com o orçamento e a venda ("ver mensagem" leva até ela). Aberta tem "Ganhou", "Perdeu" (pede o motivo) e "Não era oportunidade"; as outras têm "Desfazer". O que o dono marca vale mais que a regra e vai para a Atividade.
- "Perguntar à IA" só aparece com uma IA conectada; sem ela, o quadro convida a conectar. O brilho numa mensagem faz a pergunta ser sobre ela (`docs/ia.md`).

Não existe "excluir conta": o dono é a empresa, e encerrar a empresa vai ser um fluxo próprio do site de vendas.
