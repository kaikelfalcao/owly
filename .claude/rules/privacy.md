# Dados de clientes

As conversas importadas são de clientes reais de quem usa a Owly.

- Nunca commite nem anexe `data/`, `*.zip`, `*.xlsx`, exportações do WhatsApp, dumps de banco ou prints de telas com dados reais. O `.gitignore` cobre os casos conhecidos; confira o `git status` mesmo assim.
- Não envie conteúdo de conversa para serviço de fora (pastebin, gist, issue, busca na web, artifact público) para "ajudar a depurar".
- Testes, seeds e exemplos em docs usam nomes e telefones inventados.
- Tudo que vai para um provedor de IA passa pela máscara de dados pessoais (telefone, e-mail, CPF/CNPJ, CEP e nome) quando ela está ligada, e ela vem ligada. Recurso novo que mande texto para fora precisa do mesmo cuidado e de entrada em `docs/ia.md`.
- Chaves e tokens de terceiros ficam cifrados no banco (`encrypted` cast) e nunca voltam inteiros para o front.
- Auditoria, logs e rastros guardam só ids, códigos e números: nada de texto de mensagem nem nome, telefone ou e-mail de cliente (o assunto de uma conversa é "Conversa #id").
