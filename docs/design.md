# Identidade visual

Base: o tema **Minimal Neutral** do tweakcn, sem mudanças (cinzas puros, DM Sans, cantos de 1rem, sombras leves, claro e escuro). Por cima entram só três cores da coruja, cada uma com um trabalho. Os valores estão em `resources/css/app.css`.

## Cores

| Nome     | Variável / classe              | Para quê                                                                |
| -------- | ------------------------------ | ----------------------------------------------------------------------- |
| Violeta  | `--primary` / `bg-primary`     | botão principal, item ativo do menu, links, foco                        |
| Âmbar    | `--highlight` / `bg-highlight` | selo "Owly viu": o que precisa da atenção do dono                       |
| Laranja  | `--spotlight`, `--chart-1`     | o dado em destaque num gráfico; as outras barras em cinza (`--chart-2`) |
| Vermelho | `--destructive`                | erro e perda, nunca decoração                                           |

- Âmbar e laranja são sempre fundo com texto escuro por cima (`text-highlight-foreground`); texto âmbar sobre branco não tem contraste.
- `--accent` continua cinza, como no tweakcn: é o fundo de hover do shadcn.
- Nunca hex solto no TSX. A exceção é a coruja (`components/app-logo-icon.tsx`), que tem as cores fixas da marca.

## Tipografia

- **DM Sans** em toda a interface.
- **Geist Mono** em números alinhados, horários e ids (`font-mono`, `tabular-nums`).
- **Fredoka** só no nome "owly" (`font-brand`).

Fontes vêm do npm (`@fontsource-variable/*`), sem depender de serviço de fontes.

## Marca

- A coruja simplificada em SVG está em `components/app-logo-icon.tsx` e em `public/favicon.svg`. A ilustração original (gerada no ChatGPT) é referência; a versão final em vetor ainda vai ser feita.
- O nome é texto (`font-brand`), não imagem, para trocar de cor no modo escuro.

## Ícones e imagens

- Ícones do `lucide-react`, traço padrão.
- A coruja é a única ilustração: tela de entrar, telas vazias, espera da importação. Nada de foto de banco nem avatar inventado.

## Telas de referência

- **Entrar:** formulário estreito à esquerda, painel com a coruja e frases do que a Owly faz à direita; erro embaixo do campo.
- **Barra do topo:** trilha da página à esquerda; sino e usuário clicável com menu à direita.
- **Painel:** cinza primeiro, laranja no dado que importa, selo "Owly viu" no que pede ação.
- **Cliente:** ficha à esquerda (ícone, rótulo, valor), linha do tempo agrupada por mês à direita. A mesma linha do tempo serve para a auditoria.
