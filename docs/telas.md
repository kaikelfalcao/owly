# Telas

Só o dono entra. Toda tela, exceto as de entrar, pede login.

| Tela                       | Endereço                                  | O que tem                                                             |
| -------------------------- | ----------------------------------------- | --------------------------------------------------------------------- |
| Entrar                     | `/login`                                  | e-mail e senha, "Continuar conectado", coruja e o que a Owly faz      |
| Esqueceu a senha           | `/forgot-password`, `/reset-password/...` | link por e-mail para criar senha nova                                 |
| Verificação em duas etapas | `/two-factor-challenge`                   | código do aplicativo ou código de recuperação                         |
| Painel                     | `/dashboard`                              | por enquanto, boas-vindas; os insights chegam no passo 6 do escopo    |
| Minha conta › Perfil       | `/conta/perfil`                           | nome e e-mail de entrada                                              |
| Minha conta › Segurança    | `/conta/seguranca`                        | trocar a senha, ligar ou desligar as duas etapas (pede a senha antes) |
| Minha conta › Aparência    | `/conta/aparencia`                        | tema claro, escuro ou do sistema                                      |
| Minha conta › Atividade    | `/conta/atividade`                        | a auditoria da empresa: filtros, busca, detalhes e exportação em CSV  |

## Em toda tela logada

- **Menu lateral** com a coruja e "Painel".
- **Barra do topo:** a trilha da página (o último item é o nome da tela), o sino de notificações e o usuário com o nome da empresa. O menu do usuário tem Minha conta, o tema e Sair.
- **Sino:** os últimos dez avisos, quantos não lidos e "Marcar como lidas". Abrir um aviso marca como lido e leva para a tela dele. Confere avisos novos a cada 30 s com a aba aberta e mostra um toast quando chega um.

Não existe "excluir conta": o dono é a empresa, e encerrar a empresa vai ser um fluxo próprio do site de vendas.
