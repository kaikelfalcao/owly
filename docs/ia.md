# IA

A IA lê uma conversa quando o dono pergunta. Ela sugere; nunca manda mensagem nem muda nada sozinha.

## Conectar

Em **IA** (`/ia`), o dono conecta um ou vários provedores pelo assistente (`/ia/conectar`), em três passos:

1. **Provedor:** Gemini (Google) é o único com adaptador. Claude e ChatGPT aparecem como "em breve".
2. **Chave:** o assistente explica onde criar a chave (Google AI Studio) e testa antes de guardar. Chave recusada aparece no campo, com o motivo.
3. **Modelo:** a lista vem da própria chave (só modelos de texto). O assistente sugere o "flash" estável mais novo: rápido e barato para ler conversa.

A primeira conexão vira a **padrão**, a que responde às perguntas. Com mais de uma, "Usar esta" troca a padrão. Remover a padrão passa a vez para a mais antiga que sobrou.

## Perguntar

Na tela da conversa, o quadro "Perguntar à IA" aceita uma pergunta livre ou uma das sugestões (resumir, se comprou, se o atendimento foi bom). O ícone de brilho numa mensagem faz a pergunta ser sobre ela: a mensagem vai em destaque junto com a conversa.

A pergunta é síncrona: o botão mostra "Lendo a conversa…" até a resposta chegar. Resposta e falha ficam guardadas na conversa (`ai_questions`), com o modelo, os tokens e o tempo.

## Privacidade

- Antes de sair, a conversa e a pergunta passam pelo `Redactor`: nome do cliente (inteiro e cada parte), telefone, e-mail, CPF, CNPJ e CEP viram `[cliente]`, `[telefone]`, `[e-mail]`, `[CPF]`, `[CNPJ]`, `[CEP]`. Na primeira entrega a máscara está **sempre ligada**, sem opção de desligar.
- Nome da vendedora vai como está: é da equipe e é o que permite perguntar "a Ana respondeu bem?".
- As instruções pedem para não adivinhar o que estava atrás dos marcadores. Na tela, o `[cliente]` da resposta volta a ser o nome do cliente: a troca acontece só no navegador de quem já vê a conversa.
- Conversa muito longa vai só com a parte mais recente (até 60 mil caracteres), para segurar o custo.
- A chave fica cifrada (`encrypted`), vai no cabeçalho `x-goog-api-key` (nunca na URL) e só aparece na tela pelos últimos quatro caracteres.
- Auditoria e logs levam só provedor, tokens, ok/falha e ids. Nunca o texto da pergunta nem da resposta.

## Custo

Quem paga o provedor é a empresa, na conta dela no Google. A Owly guarda os tokens de entrada e saída de cada pergunta (`input_tokens`, `output_tokens`) para mostrar o consumo quando o painel e a cobrança chegarem.

## Código

- Contrato `App\Domains\Ai\Contracts\AiProvider` (`models`, `suggest`, `generate`) e o adaptador `Adapters/Gemini`.
- `ProviderCatalog` lista os provedores do assistente. Provedor novo é um adaptador mais uma linha ali.
- `Services/ConnectProvider` (testar, conectar, padrão, remover) e `Services/AskAboutConversation` (máscara, prompt, gravação, auditoria).
- A IA lê um atendimento por vez, não o histórico inteiro do cliente. Quando o corte refaz atendimentos, a pergunta vai junto com a mensagem em foco (`ConversationsRecut`).
- A conversa chega pelo contrato `ConversationTranscript` de Conversas. O quadro na tela da conversa entra por `ConversationPanels`, sem Conversas saber da IA.
- Erros do provedor viram códigos (`invalid_key`, `quota`, `unavailable`, `blocked`, `no_models`, `unexpected`) com mensagem em português em `AiFailed`.
