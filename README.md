# Mercado Admin

Marketplace digital com painel de comprador, vendedor e administrador, pagamentos PIX (BlackCat), carteira interna, escrow e sistema completo de afiliados.

## Stack

- PHP 8.2
- PostgreSQL (Railway) com camada de compatibilidade em `src/db.php`
- Tailwind CSS (CDN)
- Alpine.js / Chart.js / Quill.js

## Principais funcionalidades

- Loja pública com URLs amigáveis (`/p/{slug}`, `/c/{slug}`, `/loja/{slug}`)
- Storefront estilo GGMax (announcement bar, categories bar, hero banner, category cards, "Como funciona")
- Carrinho + checkout com split Wallet + PIX
- Webhook idempotente para confirmação de pagamento
- Escrow com liberação manual e automática
- **Código de entrega estilo iFood** — comprador recebe código de 6 dígitos, vendedor insere para liberar escrow
- **Entrega automática** — produtos digitais com pool de itens, consumo automático ao pagar
- **Sistema de temas** — Green e Blue com modos Dark e Light, configurável pelo admin
- Painéis completos de comprador, vendedor e admin
- Gestão de produtos, categorias, usuários, vendas, depósitos e saques
- Chat comprador/vendedor com monitoramento admin
- Sistema de afiliados com rastreio, comissões e saques
- **Google OAuth** — login com Google (exige conta existente) e registro com seleção de role (comprador/vendedor)
- **Avaliações (Reviews)** — estrelas 1–5 com validação de compra, resposta do vendedor, moderação
- **Perguntas e Respostas (Q&A)** — marketplace-style com máscara anti-fraude
- **Favoritos / Wishlist** — toggle AJAX com coração em cards, admin analytics
- **Notificações in-app** — 4 tipos (anuncio/venda/chat/ticket), polling, badge na nav
- **Tickets de suporte** — 8 categorias, fluxo de status, reply admin, notificação automática
- **Denúncias** — prevenção de duplicatas, fluxo de moderação admin
- **Páginas legais** — Termos de Uso, Privacidade, Reembolso com UI premium
- **Central de Ajuda / FAQ** — hub de suporte, FAQ categorizado com acordeão

## Atualizações recentes

- **Storefront reestruturado (estilo GGMax)**: announcement bar com trust badges, barra horizontal de categorias, hero banner com side cards (segurança + carteira + PIX), grid visual de categorias com ícones coloridos, seção "Como funciona" em 4 passos, trust bar com cards, layout responsivo 2 colunas mobile
- **Sistema de código de entrega (iFood)**: código de 6 caracteres alfanumérico gerado ao pagar pedido, comprador visualiza em caixas individuais com botão copiar, vendedor insere código para liberar escrow — substitui o antigo botão "Confirmar entrega"
- **Engine de temas**: 2 temas (Green e Blue) com 2 modos cada (Dark e Light), 12 tokens de cor por variação, CSS custom properties (`--t-*`), Tailwind dinâmico via PHP, light mode via classe `.light-mode`
- **Página admin de temas**: seleção de tema e modo, preview visual com swatches, página de detalhes com paleta completa de cores e CSS classes associadas
- **Toggle dark/light** em todas as áreas: storefront nav, dashboard do comprador, vendedor e admin
- Sistema de afiliados implementado end-to-end (cadastro, rastreio por código, conversões, payouts e páginas por role)
- Máscaras de chave PIX para afiliados (CPF, telefone, email e aleatória)
- Paginação e hardening de queries no admin de afiliados
- **Google OAuth dual-mode**: login (exige conta existente), registro com modal de seleção comprador/vendedor, intermediário `google_redirect.php`, env-var fallback
- **Tickets de suporte**: 8 categorias, fluxo de status (aberto→em_andamento→resolvido/fechado), reply admin, notificações automáticas, 6 páginas (público, comprador, vendedor, admin)
- **Entrega automática**: toggle por produto, pool de itens em JSONB, consumo automático ao confirmar pagamento, reposição via painel vendedor
- **Denúncias de produtos**: comprador denuncia, admin modera (pendente→analisando→resolvida/descartada), prevenção de duplicatas
- **Páginas legais + Central de Ajuda**: Termos de Uso, Política de Privacidade, Política de Reembolso, Central de Ajuda com FAQ categorizado e acordeão
- **Avaliações (Reviews)**: 1–5 estrelas, foto upload, validação de compra, resposta do vendedor, moderação admin
- **Q&A (Perguntas e Respostas)**: marketplace-style, máscara anti-fraude, resposta do vendedor
- **Favoritos / Wishlist**: toggle AJAX em cards e página de produto, analytics no admin
- **Notificações in-app**: 4 tipos (anuncio/venda/chat/ticket), polling AJAX, badge na navbar, leitura em lote
- **Auth guard fix**: `exigirUsuario` → `exigirLogin` em 9 páginas do painel comprador

## Estrutura relevante

- `public/` páginas web e endpoints
- `src/` regras de negócio e serviços
- `views/partials/` layouts compartilhados
- `sql/` schemas e migrações
- `docs/` documentação funcional e operacional

## Como rodar localmente (XAMPP)

1. Configure as credenciais e variáveis de ambiente em `src/config.php`.
2. Aplique o schema de banco (`sql/schema.postgres.sql` ou fluxo de migração adotado no ambiente).
3. Inicie o Apache/PHP local.
4. Acesse: `http://localhost/mercado_admin/public/`.

## Documentação complementar

- Funcionalidades completas: `docs/funcionalidades.md`
- Wallet + escrow: `docs/wallet-escrow-blackcat.md`
- Teste de webhook: `docs/postman-webhook-blackcat.md`
- Recovery de banco: `docs/database-recovery.md`