---
paths:
    - 'resources/js/**'
    - 'resources/css/**'
---

# Front-end (Inertia + React + TS)

- **Identidade:** `docs/design.md`. Base é o tema Minimal Neutral do tweakcn; as únicas cores são o violeta (`primary`: botão principal, item ativo), o âmbar (`highlight`: selo "Owly viu", o que pede atenção) e o laranja (`spotlight`/`chart-1`: o dado em destaque num gráfico). Vermelho só para erro. Nunca hex solto no TSX; use as classes do Tailwind ligadas às variáveis de `resources/css/app.css`.
- Fontes: DM Sans na interface, Geist Mono em números e ids, Fredoka só no nome "owly" (`font-brand`). Ícones: `lucide-react`.
- shadcn/ui em `resources/js/components/ui/`. Toast com `sonner`. Nada de `window.confirm`.
- Telas em `resources/js/pages/<area>/<tela>.tsx`, com `<Head title>`.
- Todo número do painel abre as conversas que o formam.
- Texto da tela em português simples, com as palavras de `docs/glossario.md`.
- Modo claro, escuro e celular (390 px) precisam funcionar.
- Gráfico: cinza primeiro e uma cor no que importa; uma série = sem legenda; nunca dois eixos Y.
- Depois de criar tela: `npm run build` (os testes Inertia dependem do manifest) e `npm run check:fix`.
