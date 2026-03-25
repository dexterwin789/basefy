# MercadoAdmin — Documentação de Funcionalidades

> Marketplace digital com pagamento PIX, carteira integrada, escrow e moderação.

---

## Sumário

1. [Storefront (Loja Pública)](#1-storefront-loja-pública)
2. [Dashboard do Usuário (Comprador)](#2-dashboard-do-usuário-comprador)
3. [Dashboard do Vendedor](#3-dashboard-do-vendedor)
4. [Dashboard do Administrador](#4-dashboard-do-administrador)
5. [Blog](#5-blog)
6. [Avaliações e Perguntas (Q&A)](#6-avaliações-e-perguntas-qa)
7. [Favoritos / Wishlist](#7-favoritos--wishlist)
8. [Notificações](#8-notificações)
9. [Denúncias / Reports](#9-denúncias--reports)
10. [Tickets de Suporte](#10-tickets-de-suporte)
11. [Entrega Automática (Auto-Delivery)](#11-entrega-automática-auto-delivery)
12. [Páginas Legais e Central de Ajuda](#12-páginas-legais-e-central-de-ajuda)
13. [Google OAuth (Login/Registro)](#13-google-oauth-loginregistro)
14. [API Endpoints](#14-api-endpoints)
15. [Webhooks](#15-webhooks)
16. [Serviços Internos (Backend)](#16-serviços-internos-backend)
17. [Banco de Dados](#17-banco-de-dados)
18. [Infraestrutura](#18-infraestrutura)
19. [Atualizações Recentes](#19-atualizações-recentes)

---

## 1. Storefront (Loja Pública)

| Funcionalidade | Descrição | Arquivo(s) |
|---|---|---|
| **Página inicial** | Announcement bar (trust badges), barra horizontal de categorias (scroll), hero banner com side cards (segurança + carteira + PIX), grid visual de categorias com ícones coloridos e imagens via `media_files`, produtos em destaque (2 cols mobile / 4 cols desktop), trust bar com cards, affiliate CTA, final CTA, botão "COMO FUNCIONA?" redireciona para página dedicada `/como_funciona` | `public/index.php` |
| **Como Funciona** | Página premium standalone com hero gradiente, abas Alpine.js (Comprador / Vendedor), fluxo de 4-5 passos com ícones animados e descrição detalhada do escrow, FAQ com acordeão, CTA de cadastro | `public/como_funciona.php` |
| **Seção de afiliados (homepage)** | Bloco premium entre vitrine e rodapé com CTA para programa de afiliados, passos de uso e dados dinâmicos de regras (`cookie_days`, programa ativo) | `public/index.php`, `src/affiliates.php` |
| **Página de produto** | Roteamento por slug e por ID, galeria de imagens (capa + galeria via `media_files`), links de categoria/vendedor, descrição expandível, seletor de quantidade, adicionar ao carrinho, sinais de confiança, produtos relacionados, **avaliações com estrelas** (1–5, média, barras de distribuição, paginação), **perguntas e respostas** (Q&A marketplace-style com paginação e máscaras de contato), botão de denúncia, chat inline com vendedor, login com `return_to` para redirecionar de volta à página do produto | `public/produto.php` |
| **Catálogo / Navegação** | Pills de categorias + filtro dropdown, busca por texto, URLs amigáveis por slug de categoria, grid de produtos com badges, adicionar ao carrinho | `public/categorias.php` |
| **Loja do Vendedor** | Perfil público (avatar, nome da loja, bio, membro desde, contagem de produtos e vendas), grid de produtos com busca + filtro de categoria, botão "Iniciar chat" | `public/loja.php` |
| **Carrinho de compras** | Carrinho baseado em sessão, atualização de quantidade via AJAX, remover/limpar, resumo do pedido na sidebar, link para checkout, recálculo de preços em tempo real | `public/carrinho.php` |
| **Checkout** | Indicador de progresso em 3 etapas (Itens → Pagamento → Confirmação), saldo da carteira exibido, checkbox "usar carteira" com recálculo live do PIX restante, criação de pedido via AJAX (`api/place_order.php`) | `public/checkout.php` |
| **Pagamento PIX** | QR Code PIX via BlackCat, código copia-e-cola, polling automático do status de pagamento (intervalo de 5s), estados de sucesso/erro, resumo do pedido | `public/checkout_pix.php` |
| **Registro** | Nome, email, senha, seletor de tipo (comprador/vendedor), auto-login após registro, vendedor → redireciona para formulário de aprovação, **botão Google com modal de seleção de role** (comprador/vendedor) | `public/register.php` |
| **Login** | Unificado para as 3 roles, preservação do carrinho entre login, redirecionamento baseado em role, parâmetro `return_to`, **botão Google (login-only, exige conta existente)** | `public/login.php` |
| **Central de Ajuda** | Hub com cards linkando FAQ, Tickets de Suporte, Termos de Uso, Política de Privacidade, Reembolso, links de contato e comunidade | `public/central_ajuda.php` |
| **FAQ** | Perguntas frequentes em formato acordeão categorizadas (Mercado Admin, Comprador, Vendedor, Tópicos adicionais), busca, scroll-spy de navegação lateral | `public/faq.php` |
| **Termos de Uso** | Página legal premium com sidebar de navegação, scroll-spy, seções animadas | `public/termos.php` |
| **Política de Privacidade** | Mesma UI premium legal com sidebar e scroll-spy | `public/privacidade.php` |
| **Política de Reembolso** | Política de reembolso com cards temáticos e sidebar de navegação | `public/reembolso.php` |
| **Tickets de Suporte (público)** | Listagem de tickets do usuário, filtros por status e busca, paginação | `public/tickets.php` |
| **Criar Ticket** | Formulário com 8 categorias pré-definidas, dropdown de pedido (para categorias relevantes), validação de duplicatas (mesmo título em 1h) | `public/tickets_novo.php` |
| **Detalhe do Ticket** | Thread de mensagens (estilo chat), envio de resposta, status badges | `public/ticket_detalhe.php` |
| **Denunciar Produto** | Formulário com preview do produto, 9 motivos pré-definidos, prevenção de duplicata (24h), confirmação de envio | `public/denunciar.php` |
| **URLs amigáveis** | `/p/{slug}` produtos, `/c/{slug}` categorias, `/loja/{slug}` lojas de vendedores (slug da `nome_loja`), **todas as URLs sem `.php`** (ex: `/dashboard`, `/carrinho`, `/checkout`), redirect 301 automático de `.php` → clean e de `?id=` → slug, fallback para `index.php` | `public/router.php`, `public/.htaccess` |
| **Slugs únicos** | Produtos, categorias e vendedores com slugs únicos (UNIQUE INDEX). Validação em tempo real via API `/api/check_slug` nos formulários (indicador verde/vermelho + sugestão disponível). Slug de vendedor gerado do `nome_loja` (seller_profiles). | `src/storefront.php`, `public/api/check_slug.php`, `public/assets/js/slug-checker.js` |
| **Navegação responsiva** | Header fixo (sticky), toggle de busca, badge do carrinho com contador (hover/active vermelho em dark/light mode), ícone de favoritos com badge de contagem, ícone de notificações com badge de não-lidos, **botão toggle dark/light**, hamburger mobile com drawer e busca, link de painel baseado na role | `views/partials/storefront_nav.php` |

---

## 2. Dashboard do Usuário (Comprador)

| Funcionalidade | Descrição | Arquivo(s) |
|---|---|---|
| **Visão geral** | Card de perfil com avatar, estatísticas de pedidos (total, pagos, em andamento), gráficos Chart.js (pedidos últimos 7 dias – linha, status – rosca, resumo – barras), links de ação rápida | `public/dashboard.php` |
| **Carteira** | Exibição de saldo, recarga PIX via modal QR BlackCat, solicitação de saque com seletor de tipo de chave PIX (CPF/CNPJ/Email/Telefone/Aleatória), máscaras de input via Alpine.js, polling automático de status PIX | `public/wallet.php` |
| **Meus pedidos** | Filtro por status/intervalo de datas, paginação (5/10/20 por página), miniaturas de produtos, exibição do método de pagamento (PIX/Wallet/Wallet+PIX), badges de status | `public/meus_pedidos.php` |
| **Detalhes do pedido** | Detalhamento de pagamento (wallet vs PIX), lista de itens com badges de status de moderação, **código de entrega estilo iFood** (6 dígitos em caixas individuais com botão copiar), exibição de conteúdo de entrega digital | `public/pedido_detalhes.php` |
| **Configurações da conta** | Atualização de perfil (nome, email), upload de avatar com drag-and-drop (Alpine.js), alteração de senha com verificação da senha atual | `public/minha_conta.php` |
| **Depósitos** | Histórico de recargas da carteira via `payment_transactions`, filtro de status, busca, paginação | `public/depositos.php` |
| **Saques** | Lista filtrável (status, busca por chave PIX/valor/ID), paginação, badges de status | `public/saques.php` |
| **Chat (comprador)** | Vista dividida em tela cheia (sidebar + mensagens), lista de conversas com avatar/preview/badges de não-lido, polling em tempo real (3s), envio com Enter, responsivo mobile (toggle sidebar/main), animação de bolhas de mensagem | `public/chat.php` |
| **Chat widget (comprador)** | FAB flutuante com badge de não-lidos e polling (15s), link direto para `/chat` | `views/partials/chat_widget_user.php` |
| **Favoritos** | Página "Meus Favoritos" com grid responsivo (2/3/4/6 cols), paginação, botão de coração AJAX em cards de produto, tratamento de produtos excluídos | `public/favoritos.php`, `public/api/favorites.php` |
| **Programa de afiliados (comprador)** | Cadastro no programa, gestão de chave PIX e bio, dashboard de desempenho (cliques/conversões/comissões), solicitação de saque, listagem de conversões e payouts | `public/afiliados.php`, `src/affiliates.php` |
| **Meus Tickets** | Dashboard de tickets do comprador no layout de usuário, filtros por status e busca, contagem por status | `public/tickets_dashboard.php` |
| **Minhas Denúncias** | Lista de denúncias enviadas pelo comprador com filtros (status, busca), paginação, modal de detalhe AJAX | `public/denuncias.php` |

---

## 3. Dashboard do Vendedor

| Funcionalidade | Descrição | Arquivo(s) |
|---|---|---|
| **Visão geral** | Cards de estatísticas (produtos, vendas aprovadas, vendas pendentes, saques), gráficos Chart.js (vendas 7 dias – barras, status – rosca, resumo – barras), mensagem de estado vazio | `public/vendedor/dashboard.php` |
| **Gestão de produtos** | Lista com busca/filtro (categoria, status), paginação, exclusão protegida por CSRF, toggle ativo/inativo | `public/vendedor/produtos.php` |
| **Formulário de produto** | Seletor de tipo produto vs serviço (Alpine.js), editor de descrição rich-text via Quill.js, upload de imagem de capa (para `media_files`), upload de galeria (múltiplas imagens), exclusão de galeria com drag-and-drop, máscara de preço, override de slug, estoque (apenas produtos), campos de prazo de entrega (apenas serviços), proteção dupla contra CSRF | `public/vendedor/produtos_form.php` |
| **Vendas aprovadas** | Agrupadas por pedido, info do comprador, busca, filtro de intervalo de datas + total mínimo, paginação | `public/vendedor/vendas_aprovadas.php` |
| **Vendas em análise** | Mesmos filtros das aprovadas + filtro de status de pagamento | `public/vendedor/vendas_analise.php` |
| **Detalhe da venda** | Resumo do pedido, lista de itens com badges de moderação, **verificação de código de entrega** (6 inputs com auto-focus Alpine.js), libera escrow ao inserir código correto do comprador | `public/vendedor/venda_detalhe.php` |
| **Entrega digital** | Vendedor envia conteúdo de entrega (link/texto) para itens do pedido, reseta timer de auto-release, validação de propriedade | `public/vendedor/api_deliver_digital.php` |
| **Carteira do vendedor** | Idêntica à do comprador: saldo, recarga PIX, saque com tipos de chave PIX | `public/vendedor/wallet.php` |
| **Histórico de saques** | Lista filtrável (status, busca por chave PIX/valor/ID), paginação, badges de status | `public/vendedor/saques.php` |
| **Histórico de depósitos** | Histórico de recargas via `payment_transactions`, filtro de status, busca, paginação | `public/vendedor/depositos.php` |
| **Aprovação / Onboarding** | Formulário com nome_loja, CPF/CNPJ, telefone, chave_pix, bio (mín. 30 caracteres), upsert em `seller_profiles` + cria `seller_requests`, exibição de status | `public/vendedor/aprovacao.php` |
| **Configurações da conta** | Perfil (nome, email), upload de avatar para `media_files` (drag-and-drop), alteração de senha | `public/vendedor/minha_conta.php` |
| **Chat (vendedor)** | UI de chat completa (mesma do comprador), sidebar de conversas, polling em tempo real, envio/leitura de mensagens, responsivo mobile | `public/vendedor/chat.php` |
| **Chat widget (vendedor)** | FAB flutuante Shopee-style com badge de não-lidos e animação de pulso, painel de chat inline (380×520px) com lista de conversas multi-comprador, thread de mensagens, polling automático | `views/partials/chat_widget_vendor.php` |
| **Perguntas (Q&A)** | Gestão de perguntas recebidas em todos os produtos do vendedor, cards com produto/avatar/pergunta, filtros (busca, respondidas/não-respondidas), contagem (total, aguardando, respondidas), resposta inline via AJAX, polling automático para novas perguntas, máscara automática de informações de contato | `public/vendedor/perguntas.php` |
| **Denúncias (vendedor)** | Visualização de denúncias enviadas pelo vendedor (somente leitura), stats cards, filtros, modal de detalhe | `public/vendedor/denuncias.php` |
| **Programa de afiliados (vendedor)** | Página de afiliados com cadastro/atualização de perfil PIX, visão de comissão gerada por vendas atribuídas ao vendedor (join por `order_items.vendedor_id`) e métricas consolidadas | `public/vendedor/afiliados.php`, `src/affiliates.php` |
| **Tickets (vendedor)** | Visualização de tickets do vendedor, mesma UI do comprador com filtros e paginação | `public/vendedor/tickets.php` |

---

## 4. Dashboard do Administrador

| Funcionalidade | Descrição | Arquivo(s) |
|---|---|---|
| **Visão geral** | Cards de estatísticas (usuários, vendedores, vendas hoje, moderação pendente, aprovados), gráficos Chart.js (vendas 7 dias, moderação rosca, novos usuários 7 dias) | `public/admin/dashboard.php` |
| **Moderação de vendas** | Lista com filtros (busca, status do pedido, status de moderação, intervalo de datas), paginação, botões de **aprovar/rejeitar** via AJAX (aprovar: credita carteira do vendedor com valor líquido após taxa da plataforma, deduz estoque; rejeitar: reembolsa carteira do comprador), modal de detalhe, todos itens aprovados → status do pedido muda para "entregue" | `public/admin/vendas.php`, `src/admin_vendas.php` |
| **Gestão de usuários** | Lista de compradores com busca, paginação, toggle ativo/inativo (AJAX), formulários de edição/criação | `public/admin/usuarios.php`, `src/admin_users.php` |
| **Gestão de vendedores** | Lista com busca, paginação, toggle ativo/inativo, exibição de status de aprovação, formulários de edição/criação | `public/admin/vendedores.php` |
| **Gestão de admins** | Lista de contas admin, busca, formulários de edição/criação | `public/admin/admins.php` |
| **Gestão de categorias** | CRUD + lista com filtro de tipo (produto/serviço) e filtro ativo, toggle/exclusão via AJAX (impede exclusão se houver produtos vinculados), geração automática de slug | `public/admin/categorias.php`, `src/admin_categorias.php` |
| **Gestão de produtos** | CRUD + lista com filtros (busca, categoria, vendedor, ativo), miniaturas de imagem, badge de tipo, exibição de quantidade, toggle/exclusão via AJAX, editor rich description Quill.js | `public/admin/produtos.php`, `src/admin_produtos.php` |
| **Revisão de solicitações de vendedor** | Lista com filtros (status: pendente/aberto/aprovada/rejeitada, busca, intervalo de datas), paginação, página de detalhe com perfil completo (nome_loja, CPF/CNPJ mascarado, telefone mascarado, chave_pix, bio), aprovar/rejeitar com motivo de rejeição | `public/admin/solicitacoes_vendedor.php`, `public/admin/solicitacao_vendedor_detalhe.php`, `src/admin_solicitacoes.php` |
| **Gestão de depósitos** | Lista de todas as recargas de carteira com filtros (busca, status, intervalo de datas), página de detalhe com lookup de webhooks relacionados | `public/admin/depositos.php`, `src/admin_depositos.php` |
| **Gestão de saques** | Estatísticas gerais (total solicitado, aprovados/pagos, pendentes), pills de tab (pendentes/aprovados/todos), botão de aprovação, modal de observação (adicionar notas), **saque instantâneo** (payout PIX via BlackCat sem aprovação, com seleção de tipo de chave), paginação | `public/admin/saques.php` |
| **Saldo admin** | Exibição do saldo da carteira do admin, histórico de transações, histórico de saques, modo de saque (auto vs manual) | `public/admin/wallet_admin.php` |
| **Configuração de escrow / wallet** | Configurável: dias de auto-release (1–60), taxa da plataforma % (0–100), seletor de admin recebedor de taxa, toggle de auto-release ativo/inativo | `public/admin/wallet_config.php` |
| **Monitor de chat** | Estatísticas (total de conversas, total de mensagens, ativas hoje), lista paginada de conversas com busca, modal de visualização de thread de mensagens, supervisão admin somente leitura | `public/admin/chat.php` |
| **Configurações da conta admin** | Atualização de perfil (nome, email, drop-zone de avatar), alteração de senha | `public/admin/minha_conta.php` |
| **Gestão de afiliados** | Aprovação/suspensão/rejeição de afiliados, revisão de conversões e pedidos de saque com paginação e filtros por status | `public/admin/afiliados.php`, `src/affiliates.php` |
| **Configurações de afiliados** | Regras globais do programa: taxa de comissão, dias de cookie, mínimo de saque, auto-approve, habilitar/desabilitar programa e regras de autoindicação | `public/admin/afiliados_config.php`, `src/affiliates.php` |
| **Gestão de temas** | Seleção de tema (Green/Blue) e modo (Dark/Light), preview visual com swatches e mini cards, badges de tema ativo | `public/admin/temas.php` |
| **Detalhes do tema** | Paleta completa de cores para dark e light mode, cada token com label, descrição, hexadecimal e classes CSS que o utilizam | `public/admin/tema_detalhes.php` |
| **Blog (admin)** | Gestão de posts (lista com filtros, status, paginação), create/edit com editor HTML + upload de capa via `media_files`, gerenciamento de categorias de blog (CRUD com contagem de posts), configurações de visibilidade (habilitar/desabilitar blog, visibilidade por role: público/usuario/vendedor/admin) | `public/admin/blog.php`, `public/admin/blog_form.php`, `public/admin/blog_categorias.php` |
| **Denúncias (admin)** | Stats cards (total, pendentes, analisando, resolvidos), filtros (busca, status), tabela com produto thumbnail + reporter + motivo + status, atualização de status inline via AJAX (pendente/analisando/resolvido/rejeitado), modal de detalhe, prevenção de duplicatas (24h) | `public/admin/denuncias.php` |
| **Favoritos (admin)** | Ranking dos 5 produtos mais favoritados com imagem e contagem, lista completa com busca e paginação (produto/usuário/data) | `public/admin/favoritos.php` |
| **Google OAuth (admin)** | Configuração de login Google (Client ID, Client Secret, Redirect URI), instruções passo-a-passo para Google Cloud Console, settings em `platform_settings` | `public/admin/google_oauth.php` |
| **Documentação (admin)** | Página interativa in-app com todas as funcionalidades do sistema categorizadas e linkadas | `public/admin/documentacao.php` |
| **Tickets (admin)** | Gestão de tickets de suporte: stats cards (total, abertos, respondidos, fechados), filtros (busca, status, categoria), tabela com info do usuário/categoria/status, botão de resposta inline (via AJAX), atualização de status (aberto→em_andamento→respondido→fechado) | `public/admin/tickets.php`, `src/tickets.php` |
| **Pedidos (admin)** | Redirect (302) para `/admin/vendas`, backend `listarPedidos()` com filtros | `public/admin/pedidos.php`, `src/admin_pedidos.php` |

---

## 5. Blog

| Funcionalidade | Descrição | Arquivo(s) |
|---|---|---|
| **Listing público** | Hero header com barra de busca e pills de categorias, grid responsivo (1/2/3 colunas) de cards (capa, título, resumo, autor, data), post em destaque na primeira página, paginação | `public/blog.php` |
| **Post público** | Breadcrumb, imagem de capa, conteúdo HTML renderizado, tempo de leitura estimado, contador de visualizações, botões de compartilhar (Twitter/X, WhatsApp, copiar link), posts relacionados | `public/blog_post.php` |
| **Categoria de blog** | Filtro por categoria (clean URL `/blog/categoria/{slug}`), grid de posts filtrado, imagem de categoria via `media_files` | `public/blog_categoria.php` |
| **Autor** | Posts de um autor específico (`/blog/autor/{id}`) | `public/blog_author.php` |
| **Visibilidade por role** | Blog habilitável/desabilitável, visibilidade configurável per-role (público, usuario, vendedor, admin) em `platform_settings` | `src/blog.php` |
| **Backend** | Auto-migração de tabela (`blogEnsureTable()`), CRUD, slug accent-safe, listagem com filtros (busca + categoria), incremento de views, categorias de blog via tabela `categories` com `tipo='blog'` | `src/blog.php` |

---

## 6. Avaliações e Perguntas (Q&A)

| Funcionalidade | Descrição | Arquivo(s) |
|---|---|---|
| **Avaliações de produto** | Estrelas 1–5, título e comentário, validação de compra (pedido pago/entregue), prevenção de duplicatas, resposta do vendedor, moderação (ativo/oculto/removido), agregados (média, total, distribuição por estrela), paginação na página do produto, SVG de estrelas (cheias/metade/vazias) | `public/api/reviews.php`, `src/reviews.php` |
| **Perguntas e respostas** | Sistema Q&A marketplace-style na página do produto, paginação (5 por página), máscara automática de contato (emails, telefones, URLs, @handles, WhatsApp/Telegram), avatar do comprador em tempo real (buscado do DB), login com redirect `return_to` para voltar à página do produto | `public/api/questions.php`, `src/questions.php` |
| **Gestão vendedor** | Dashboard de perguntas com stats (total, aguardando, respondidas), filtros e busca, resposta inline AJAX, polling automático para novas perguntas | `public/vendedor/perguntas.php` |

---

## 7. Favoritos / Wishlist

| Funcionalidade | Descrição | Arquivo(s) |
|---|---|---|
| **Página do usuário** | Grid responsivo de produtos favoritados (2/3/4/6 cols), paginação, remoção inline, tratamento de produtos excluídos, ícones com borda temática | `public/favoritos.php` |
| **Toggle AJAX** | Botão de coração em cards de produto, toggle via `action=toggle`, verificação via `action=check` e `action=check_bulk` (para grids), contagem via `action=count` | `public/api/favorites.php` |
| **Admin analytics** | Ranking top 5 produtos mais favoritados (com imagem e contagem), lista completa com busca e paginação | `public/admin/favoritos.php` |
| **Backend** | Auto-migração (`favoritesEnsureTable()`), toggle on/off, check single/bulk, lista paginada, admin list, top products | `src/favorites.php` |

---

## 8. Notificações

| Funcionalidade | Descrição | Arquivo(s) |
|---|---|---|
| **API** | Contagem de não-lidos (total + por tipo), listagem paginada, marcar como lida (individual ou todas), tipos: `anuncio`, `venda`, `chat`, `ticket` | `public/api/notifications.php` |
| **Broadcasts** | Notificações com `user_id=0` visíveis para todos os usuários | `src/notifications.php` |
| **Badge na nav** | Badge de contagem integrado ao header da storefront, polling periódico | `views/partials/storefront_nav.php` |
| **Backend** | Auto-migração (`notificationsEnsureTable()`), criar para usuário ou broadcast, listar com broadcasts inclusos, contagem por tipo, marcar como lida | `src/notifications.php` |

---

## 9. Denúncias / Reports

| Funcionalidade | Descrição | Arquivo(s) |
|---|---|---|
| **Submissão** | Denúncia de produto com motivo e mensagem, prevenção de duplicatas (mesmo user + produto em 24h) | `src/reports.php` |
| **Admin** | Stats cards (total, pendentes, analisando, resolvidos), filtros (busca, status), tabela com thumbnail + reporter + motivo + badge de status, atualização inline de status via AJAX, modal de detalhe | `public/admin/denuncias.php` |
| **Vendedor** | Visualização de denúncias enviadas pelo vendedor (somente leitura), mesma UI do admin sem ações de status | `public/vendedor/denuncias.php` |
| **Fluxo de status** | `pendente` → `analisando` → `resolvido` / `rejeitado` | `src/reports.php` |
| **Backend** | Auto-migração (`reportsEnsureTable()`), submit com check 24h, listagem com filtros, contagem agrupada por status, atualização de status | `src/reports.php` |

---

## 10. Tickets de Suporte

| Funcionalidade | Descrição | Arquivo(s) |
|---|---|---|
| **Backend** | Auto-migração de 3 tabelas (`ticketsEnsureTable()`), CRUD de tickets com mensagens e anexos, 8 categorias pré-definidas (Alteração Cadastral, Anúncios, Denúncias/Banimentos, Dúvidas Gerais, Financeiro/Retiradas, Outros, Problemas/Reembolsos, Problemas Técnicos), prevenção de duplicatas (mesmo título em 1h), fluxo de status: `aberto` → `em_andamento` → `respondido` → `fechado` | `src/tickets.php` |
| **Listagem (storefront)** | Listagem de tickets do usuário no layout da storefront, filtros por status e busca, paginação | `public/tickets.php` |
| **Criar ticket** | Formulário com seletor de categoria, dropdown de pedidos (para contexto), título e mensagem, validação de duplicata | `public/tickets_novo.php` |
| **Detalhe do ticket** | Thread de mensagens estilo chat (comprador + admin), envio de resposta, badges de status, timestamps | `public/ticket_detalhe.php` |
| **Dashboard comprador** | "Meus Tickets" no layout de usuário com stats cards (total, abertos, respondidos, fechados), filtros e busca | `public/tickets_dashboard.php` |
| **Dashboard vendedor** | Tickets do vendedor com mesma UI | `public/vendedor/tickets.php` |
| **Admin** | Gestão completa: stats cards, filtros (busca, status, categoria), resposta inline via AJAX, atualização de status, notificação automática ao dono do ticket | `public/admin/tickets.php` |

---

## 11. Entrega Automática (Auto-Delivery)

| Funcionalidade | Descrição | Arquivo(s) |
|---|---|---|
| **Backend** | Processamento automático de entregas para produtos digitais. Produtos com `auto_delivery_enabled=true` possuem pool JSON em `auto_delivery_items`. Ao confirmar pagamento, um item é consumido e gravado em `order_items.delivery_content`. Pool vazio → desativa auto-delivery do produto | `src/auto_delivery.php` |
| **Trigger** | Chamado automaticamente por `escrowInitializeOrderItems()` após confirmação de pagamento via webhook | `src/wallet_escrow.php` |
| **Notificação** | Comprador recebe notificação "Entrega automática realizada!" com link para o pedido | `src/notifications.php` |
| **Gestão do vendedor** | Vendedor configura auto-delivery no formulário de produto (toggle + textarea para itens JSON) | `public/vendedor/produtos_form.php` |
| **Colunas no BD** | `products.auto_delivery_enabled` (BOOLEAN DEFAULT FALSE), `products.auto_delivery_items` (TEXT JSON) | — |

---

## 12. Páginas Legais e Central de Ajuda

| Funcionalidade | Descrição | Arquivo(s) |
|---|---|---|
| **Central de Ajuda** | Hub de suporte com cards linkando FAQ, Tickets de Suporte, Termos de Uso, Privacidade, Reembolso, links de contato e comunidade | `public/central_ajuda.php` |
| **FAQ** | Perguntas frequentes em formato acordeão com Alpine.js, categorizadas (Mercado Admin, Comprador, Vendedor, Tópicos adicionais), scroll-spy de navegação lateral, busca | `public/faq.php` |
| **Termos de Uso** | Página legal premium com sidebar de navegação fixa, scroll-spy (seção ativa destacada), seções animadas, 11 cláusulas, tema dark/light | `public/termos.php` |
| **Política de Privacidade** | Mesma UI premium legal com sidebar e scroll-spy, dados tratados, LGPD, cookies | `public/privacidade.php` |
| **Política de Reembolso** | Política de reembolso com refund-cards temáticos, prazos, exceções, contato | `public/reembolso.php` |
| **Links no footer** | Footer com links para todas as páginas legais e central de ajuda | `views/partials/footer.php` |

---

## 13. Google OAuth (Login/Registro)

| Funcionalidade | Descrição | Arquivo(s) |
|---|---|---|
| **Backend** | OAuth 2.0 em PHP puro (sem Composer). Settings em `platform_settings` (`google.*`) com fallback para env vars (`GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`). Geração de URL com CSRF state, troca de token via cURL, fetch de perfil `openid email profile` | `src/google_auth.php` |
| **Fluxo de Login** | Botão "Entrar com Google" → `google_redirect.php?mode=login` → Google → callback. **Apenas usuários já cadastrados** — se o e-mail não existe no BD, retorna erro "Nenhuma conta encontrada" | `public/login.php`, `public/google_redirect.php` |
| **Fluxo de Registro** | Botão "Criar conta com Google" → **modal de seleção de role** (Comprador ou Vendedor) → `google_redirect.php?mode=register&role=X` → Google → callback. Cria conta com senha aleatória e role escolhida. Se e-mail já existe, faz login direto | `public/register.php`, `public/google_redirect.php` |
| **Callback** | Valida CSRF state, troca code → token → user info. Modo login: `googleLogin()`. Modo register: `googleRegister()`. Erro → redireciona para a página de origem com `google_error=...` | `public/google_callback.php` |
| **Intermediário** | `google_redirect.php` recebe `mode`, `role` e `return_to`, armazena na sessão, gera URL OAuth e redireciona para Google | `public/google_redirect.php` |
| **Config admin** | Painel admin para configurar Client ID, Client Secret e Redirect URI | `public/admin/google_oauth.php` |

---

## 14. API Endpoints

| Endpoint | Método | Descrição |
|---|---|---|
| `/api/place_order` | POST | Criação de pedido via AJAX a partir do carrinho, split wallet+PIX, geração de PIX BlackCat, retorna QR em JSON |
| `/api/chat` | POST | API REST do chat — ações: `start`, `send`, `messages`, `poll`, `conversations`, `read`, `unread_count`, `archive`, `vendor_status`, `toggle_chat`, `admin_conversations`, `admin_messages`; atualiza `last_seen_at` |
| `/api/media` | GET | Serve mídia do banco (decodifica base64), header MIME correto, cache de 30 dias no browser, suporte a ETag / 304 |
| `/api/check_slug` | GET | Verifica disponibilidade de slug (type=product\|category\|vendor, slug, exclude_id). Retorna `{available, slug, suggestion}` |
| `/admin/api_venda_action` | POST | AJAX admin: aprovar/rejeitar venda, buscar detalhes da venda em JSON |
| `/admin/api_venda_detalhe` | POST | AJAX admin: obter detalhes de escrow de item de venda |
| `/admin/api_toggle_status` | POST | AJAX admin: toggle de status ativo/inativo de usuário/vendedor |
| `/admin/api_categoria_action` | POST | AJAX admin: toggle/exclusão de categoria |
| `/admin/api_produto_action` | POST | AJAX admin: toggle/exclusão de produto |
| `/admin/api_usuario_action` | POST | AJAX admin: ações de usuário |
| `/admin/api_vendedor_action` | POST | AJAX admin: ações de vendedor |
| `/admin/api_solicitacao_action` | POST | AJAX admin: aprovar/rejeitar solicitações de vendedor |
| `/vendedor/api_deliver_digital` | POST | AJAX vendedor: enviar conteúdo de entrega digital |
| `/vendedor/api_produto_action` | POST | AJAX vendedor: toggle/exclusão de produto próprio |
| `/api/theme_toggle` | POST | Toggle de tema/modo: aceita `{mode: 'dark'\|'light', theme: 'green'\|'blue'}`, retorna `{ok, active}` |
| `/ref/{referral_code}` (rota pública) | GET | Captura clique de afiliado, registra tracking e define cookie de referência para atribuição futura de conversão |
| `/api/favorites` | POST/GET | Toggle, check, check_bulk, count — gestão de favoritos do usuário |
| `/api/notifications` | GET/POST | List, count, read, read_all — notificações in-app |
| `/api/questions` | POST/GET | Ask, answer, list — perguntas e respostas em produtos |
| `/api/reviews` | POST | Submit, update, delete — avaliações de produtos |

---

## 15. Webhooks

| Endpoint | Provedor | Comportamento |
|---|---|---|
| `webhooks/blackcat` | BlackCat Pagamentos | Recebe eventos `transaction.paid`; **idempotente** via tabela `webhook_events` (de-dup por `idempotency_key`); ao pagar: credita carteira para recargas, marca pedidos como `pago`, debita valores diferidos da carteira, inicializa escrow (`escrowInitializeOrderItems`); registra payload JSON |

---

## 16. Serviços Internos (Backend)

| Serviço | Funções Principais | Arquivo |
|---|---|---|
| **Autenticação** | `iniciarSessao()`, `cadastrarContaPublica()` (auto-slug), `autenticarConta()` (password_verify + fallback plain-text), normalização de role (admin/vendedor/usuario com tolerância a typos), guards `exigirAdmin/Vendedor/Usuario()`, `redirecionarPorPerfil()`, verificações de status de aprovação de vendedor | `src/auth.php` |
| **Abstração de BD** | `PgCompatConnection` encapsula PDO com **tradução MySQL → PostgreSQL** (backticks → aspas duplas, `IFNULL` → `COALESCE`, `NOW()` → `CURRENT_TIMESTAMP`, `LIMIT ?,?` → `OFFSET ? LIMIT ?`, `ON DUPLICATE KEY` → `ON CONFLICT`, tratamento de booleanos, `SHOW COLUMNS FROM` → `information_schema`) | `src/db.php` |
| **Configuração** | BD via variáveis de ambiente (compatível Railway), `APP_NAME`, `BASE_PATH`, URL/chave API BlackCat, flag `WALLET_ESCROW_ENABLED`, helper `fmtDate()` (America/Sao_Paulo) | `src/config.php` |
| **Lógica do Storefront** | Geração/backfill de slugs (produtos, categorias, vendedores), construtores de URL de produto/categoria/vendedor, listagem de produtos com filtros (busca, categoria, limite), **carrinho baseado em sessão** (add, set qty, remove, clear, summary), criação de pedido a partir do carrinho com pagamento split wallet+PIX, geração de PIX via BlackCat, perfis de vendedores, resolução de URL de imagem via sistema de mídia | `src/storefront.php` |
| **Escrow de Carteira** | Configurações de plataforma (`auto_release_days`, `platform_fee_percent`, `auto_release_enabled`, `platform_admin_user_id`, `withdraw_auto_enabled`) em tabela `platform_settings`; `escrowInitializeOrderItems()` define `auto_release_at` + gera código de entrega; `escrowReleaseOrderItem()` calcula taxa + credita vendedor + credita taxa admin + atualiza status; **`escrowConfirmDeliveryByCode()`** (vendedor insere código iFood-style → libera escrow); `escrowGenerateDeliveryCode()` (6 chars alfanuméricos, sem 0/O/1/I); `escrowProcessAutoReleases()` para cron | `src/wallet_escrow.php` |
| **Engine de Temas** | 2 temas (green/blue) × 2 modos (dark/light) = 4 variações com 12 tokens cada (`bg_body`, `bg_card`, `bg_border`, `accent`, `accent_hover`, `accent_soft`, `accent_rgb`, `gradient_from/to`, `text_on_accent`, `scrollbar_hover`, `pulse_rgb`); settings em `platform_settings` com prefixo `theme.*`; `themeRenderCSSVars()` gera `:root{}` CSS; `themeTailwindColors()` mapeia para Tailwind config dinâmico; light mode via classe `.light-mode` no `<html>` | `src/theme.php`, `public/assets/css/themes.css` |
| **Portal de Carteira** | `walletSaldo()`, `walletCriarRecargaPix()` (PIX BlackCat), `walletAplicarCreditoRecargaSeNecessario()` (crédito idempotente), `walletAtualizarStatusRecarga()` (polls BlackCat), `walletHandleTransactionPaidWebhook()`, `walletSolicitarSaque()` (validação de tipo de chave PIX: CPF/CNPJ/Email/Telefone/Aleatória), `walletAprovarSaqueAdmin()` (aprovação manual com verificação de saldo), `walletSaqueImediatoAdmin()` (instantâneo via API BlackCat), `walletHistoricoTransacoes/Saques()`, `walletAdminAdicionarObservacao()` | `src/wallet_portal.php` |
| **Sistema de Chat** | Auto-migração de tabelas de chat, `chatEnsureTables()`, toggle de chat do vendedor, CRUD de conversas com link de produto, envio com validação de propriedade, paginação, marcar como lido, listagem de conversas (roles comprador e vendedor), contagem de não-lidos, polling para novas mensagens, arquivamento, monitoramento admin (listar todos, visualizar qualquer, buscar) | `src/chat.php` |
| **Sistema de Mídia** | Imagens armazenadas no BD (base64 em `media_files` TEXT), `mediaSaveFromUpload()`, resolução de URL (trata `media:ID`, filesystem, URLs completas), listar/obter/excluir por entidade, definir imagem de capa, limite de 5 MB | `src/media.php` |
| **API BlackCat** | `blackcatRequest()` (HTTP genérico para `api.blackcatpagamentos.online`), `blackcatCreatePixSale()`, `blackcatGetSaleStatus()`, `blackcatCreateWithdrawal()`, auth por X-API-Key | `src/blackcat_api.php` |
| **Caminhos de Upload** | `uploadsBaseDiskPath()`, `uploadsBaseUrl()`, `uploadsPublicUrl()` — resolve referências `media:`, caminhos de filesystem, URLs completas | `src/upload_paths.php` |
| **Portal do Vendedor** | Detecção dinâmica de colunas (`vendedor_id`/`user_id`), `listarMeusProdutos()` (com paginação), `salvarMeuProduto()` (criar/atualizar com slug), `excluirMeuProduto()`, `toggleMeuProdutoAtivo()`, `listarMinhasVendasPorStatus()`, `detalheMinhaVenda()`, `solicitarSaque()`, `resumoDashboardVendedor()` | `src/vendor_portal.php` |
| **Serviços Admin** | `listarVendas()` / `decidirVenda()` (aprovar: crédito vendedor + cálculo de taxa + dedução de estoque; rejeitar: reembolso comprador), `listarUsuariosPorRole()` / `criarUsuarioPainel()` / `atualizarUsuarioPainel()`, `listarSolicitacoesVendedor()` / `decidirSolicitacaoVendedor()`, `listarCategorias()` / `salvarCategoria()` / `excluirCategoria()`, `listarProdutos()` / `salvarProduto()` / `excluirProduto()`, `listarDepositos()` / `obterDepositoPorId()` / `listarWebhooksRelacionadosAoDeposito()` | `src/admin_*.php` |
| **Sistema de Afiliados** | Auto-migração (`affEnsureTables()`), configuração de regras em `platform_settings` (`affiliate.*`), cadastro e aprovação, tracking de clique/cookie, atribuição de conversão em pedido pago, cálculo de saldo disponível, solicitação/aprovação/rejeição de payout, dashboards e listagens administrativas | `src/affiliates.php`, `sql/affiliates.sql` |
| **Sistema de Blog** | Auto-migração (`blogEnsureTable()`), CRUD de posts, slug accent-safe, listagem com filtros (busca + categoria), incremento de views, settings de visibilidade por role, categorias via `categories` com `tipo='blog'` | `src/blog.php` |
| **Sistema de Favoritos** | Auto-migração (`favoritesEnsureTable()`), toggle on/off, check single/bulk, listagem paginada, admin analytics (top products, lista com busca) | `src/favorites.php` |
| **Sistema de Notificações** | Auto-migração (`notificationsEnsureTable()`), criar por usuário ou broadcast (`user_id=0`), listar com broadcasts inclusos, contagem por tipo, marcar como lida (individual/todas) | `src/notifications.php` |
| **Sistema de Perguntas (Q&A)** | Auto-migração (`questionsEnsureTable()`), perguntar/responder, listagem por produto (paginada, com avatar), listagem por vendedor (com filtros), contagem de não-respondidas, máscara de contato via `hasContactInfo()` | `src/questions.php` |
| **Sistema de Denúncias** | Auto-migração (`reportsEnsureTable()`), submissão com check de duplicata 24h, listagem com filtros (status, busca, user_id, vendedor_id), contagem por status, atualização de status | `src/reports.php` |
| **Sistema de Avaliações** | Auto-migração (`reviewEnsureTable()`), CRUD com validação de compra, resposta do vendedor, moderação (ativo/oculto/removido), agregados (média, total, distribuição 1-5), verificação de permissão, renderização HTML de estrelas SVG | `src/reviews.php` |
| **Google OAuth** | Fluxo OAuth 2.0 em PHP puro (sem Composer), settings em `platform_settings` (`google.*`) com fallback para env vars (`GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`), **dois modos**: login-only (`googleLogin()` — exige conta existente) e registro com role (`googleRegister()` — cria conta como comprador ou vendedor), CSRF state token, intermediário `google_redirect.php` | `src/google_auth.php` |
| **Tickets de Suporte** | Auto-migração (`ticketsEnsureTable()`) de 3 tabelas (`support_tickets`, `support_ticket_messages`, `support_ticket_attachments`), CRUD com 8 categorias pré-definidas, fluxo de status (`aberto`→`em_andamento`→`respondido`→`fechado`), prevenção de duplicatas (1h), resposta admin com notificação automática | `src/tickets.php` |
| **Entrega Automática** | `autoDeliveryProcessOrder()` — consome item do pool JSON `auto_delivery_items` de produtos com `auto_delivery_enabled=true`, grava em `delivery_content`, desativa quando pool vazio, notifica comprador | `src/auto_delivery.php` |
| **Helpers / Anti-fraude** | `maskContactInfo()` — substitui emails, telefones, @handles, URLs, menções WhatsApp/Telegram/Instagram, obfuscações (`[at]`/`[dot]`); `hasContactInfo()` — boolean check | `src/helpers.php` |

---

## 17. Banco de Dados

**Engine:** PostgreSQL (via camada de compatibilidade `PgCompatConnection` MySQL → PG)

| Tabela | Descrição |
|---|---|
| `users` | Tabela unificada de usuários para as 3 roles (admin/vendedor/usuario). Campos: id, nome, email, senha, avatar, role, ativo, is_vendedor, status_vendedor, wallet_saldo, slug, last_seen_at, criado_em |
| `categories` | Categorias de produtos/serviços. Campos: id, nome, slug, tipo (produto/servico), ativo |
| `products` | Listagens de produtos/serviços. Campos: id, vendedor_id, categoria_id, nome, descricao, preco, imagem, ativo, slug, tipo, quantidade, prazo_entrega_dias, data_entrega, **auto_delivery_enabled** (BOOLEAN DEFAULT FALSE), **auto_delivery_items** (TEXT JSON — pool de itens para entrega automática) |
| `orders` | Cabeçalho de pedidos. Campos: id, user_id, status (pendente/pago/enviado/entregue/cancelado), total, gross_total, wallet_used, metodo_pagamento, **delivery_code** (VARCHAR 6, código iFood), criado_em |
| `order_items` | Itens do pedido com escrow e moderação. Campos: id, order_id, product_id, vendedor_id, quantidade, preco_unit, subtotal, moderation_status, moderation_motivo, moderation_at, moderation_by, auto_release_at, released_at, release_trigger, escrow_fee_percent/amount/net_amount, delivery_content, delivered_at |
| `platform_settings` | Configuração chave-valor (regras de escrow). Campos: setting_key, setting_value |
| `payment_transactions` | Registros de pagamento (PIX BlackCat). Campos: id, provider, order_id, user_id, external_ref, provider_transaction_id, status, payment_method, amount_centavos, net_centavos, fees_centavos, invoice_url, raw_response, created_at, paid_at |
| `webhook_events` | De-dup idempotente de webhooks. Campos: id, provider, event_name, idempotency_key (UNIQUE), status, payload, received_at, processed_at |
| `seller_requests` | Pipeline de solicitações de vendedor. Campos: id, user_id, status (pendente/aberto/aprovada/rejeitada), motivo_recusa, criado_em, atualizado_em |
| `seller_profiles` | Perfil/loja do vendedor. Campos: id, user_id (UNIQUE), nome_loja, documento, telefone, bio, chave_pix, chat_enabled |
| `wallet_withdrawals` | Solicitações de saque. Campos: id, user_id, valor, status (pendente/pago/processando), chave_pix, tipo_chave, observacao, transaction_id, criado_em |
| `wallet_transactions` | Livro-razão da carteira. Campos: id, user_id, tipo (credito/debito), origem, referencia_tipo, referencia_id, valor, descricao, criado_em |
| `media_files` | Imagens armazenadas no BD. Campos: id, entity_type, entity_id, file_data (TEXT/base64), mime_type, is_cover, sort_order, criado_em |
| `chat_conversations` | Threads de chat. Campos: id, buyer_id, vendor_id, product_id, buyer_archived, vendor_archived, criado_em, atualizado_em |
| `chat_messages` | Mensagens de chat. Campos: id, conversation_id, sender_id, message, is_read, criado_em |
| `affiliates` | Perfil de afiliado por usuário. Campos: user_id, referral_code, status, custom_rate, totais, dados PIX e bio |
| `affiliate_clicks` | Eventos de clique de referência (IP, user-agent, origem e landing) para tracking de funil |
| `affiliate_conversions` | Conversões atribuídas por pedido (order_id, buyer_id, total do pedido, taxa e comissão, status) |
| `affiliate_payouts` | Solicitações de saque de comissão do afiliado (valor, chave PIX, status, notas admin, processamento) |
| VIEW `saques` | Alias de compatibilidade para `wallet_withdrawals` |
| `blog_posts` | Posts do blog. Campos: id, author_id (FK→users), titulo, slug (UNIQUE), resumo, conteudo (HTML TEXT), imagem (suporta `media:ID`), categoria, status (rascunho/publicado/arquivado), visualizacoes, criado_em, atualizado_em. Auto-criada por `blogEnsureTable()` |
| `user_favorites` | Favoritos/wishlist do usuário. Campos: id, user_id (FK→users), product_id (FK→products), criado_em. UNIQUE(user_id, product_id). Auto-criada por `favoritesEnsureTable()` |
| `notifications` | Notificações in-app. Campos: id, user_id (0=broadcast), tipo (anuncio/venda/chat/ticket), titulo, mensagem (TEXT), link, lida (BOOLEAN), criado_em. Índice em (user_id, lida, criado_em DESC). Auto-criada por `notificationsEnsureTable()` |
| `product_questions` | Perguntas e respostas em produtos. Campos: id, product_id, user_id, user_nome, pergunta (TEXT), resposta (TEXT, NULL=não respondida), respondido_por, status (ativo/inativo), criado_em, respondido_em. Auto-criada por `questionsEnsureTable()` |
| `product_reports` | Denúncias de produtos. Campos: id, product_id, user_id, motivo, mensagem (TEXT), status (pendente/analisando/resolvido/rejeitado), criado_em. Auto-criada por `reportsEnsureTable()` |
| `product_reviews` | Avaliações de produtos. Campos: id, product_id (FK CASCADE), user_id (FK CASCADE), order_id (FK SET NULL), rating (1–5 CHECK), titulo, comentario (TEXT), resposta_vendedor (TEXT), respondido_em, status (ativo/oculto/removido), criado_em, atualizado_em. UNIQUE(user_id, product_id). Auto-criada por `reviewEnsureTable()` |
| `support_tickets` | Tickets de suporte. Campos: id, user_id, categoria (8 tipos), titulo, mensagem (TEXT), order_id (opcional), status (aberto/em_andamento/respondido/fechado), admin_resposta (TEXT), admin_id, respondido_em, criado_em, atualizado_em. Auto-criada por `ticketsEnsureTable()` |
| `support_ticket_messages` | Mensagens em tickets (thread). Campos: id, ticket_id (FK), user_id, is_admin (BOOLEAN), mensagem (TEXT), criado_em. Auto-criada por `ticketsEnsureTable()` |
| `support_ticket_attachments` | Anexos de tickets. Campos: id, ticket_id (FK), user_id, filename, filepath, criado_em. Auto-criada por `ticketsEnsureTable()` |

---

## 18. Infraestrutura

| Componente | Detalhes |
|---|---|
| **Docker** | Imagem `php:8.2-cli`, extensões: `pdo_pgsql`, `mbstring`, `curl`; servidor PHP built-in na porta `$PORT` (padrão 8080), `router.php` como front controller |
| **Apache** | Startup com `mpm_prefork`, bind dinâmico de `$PORT`, VirtualHost com DocRoot `/var/www/html/public`, `AllowOverride All` |
| **Front controller** | `router.php` — roteamento por slug (`/p/`, `/c/`, `/loja/`), **clean URLs sem `.php`** (redirect 301 automático), passthrough de arquivos estáticos, resolução `URI → .php`, redirecionamento de prefixo legado |
| **`.htaccess`** | RewriteEngine para Apache/XAMPP — redirect `.php` → clean, resolução de clean URL → `.php`, fallback `index.php` |
| **Tailwind CSS** | Via CDN com config dinâmico via PHP (fonte Inter, cores blackx/greenx mapeadas para tema ativo), suporte a dark e light mode |
| **Alpine.js** | Via CDN, usado em formulários interativos (wallet, formulário de produto, upload de avatar, chat) |
| **Chart.js 4** | Via CDN por página de dashboard, gráficos de linha/rosca/barras |
| **Quill.js 2** | Via CDN nos formulários de produto, editor rich-text para descrição |
| **Lucide Icons** | Biblioteca de ícones SVG via CDN |
| **Sistema de layouts** | Partials compartilhadas: `header.php` (carrega tema ativo + CSS vars + Tailwind dinâmico), `footer.php`, `storefront_nav.php` (com toggle dark/light, badges de carrinho/favoritos/notificações), wrappers de layout por role (`admin_layout_start/end`, `vendor_layout_start/end`, `user_layout_start/end` — todos com toggle), `pagination.php`, **chat widgets flutuantes**: `chat_widget.php` (Shopee-style FAB para storefront com painel inline 380×520px), `chat_widget_vendor.php` (FAB para vendedor com lista de conversas multi-comprador), `chat_widget_user.php` (FAB simples com link para `/chat` e badge de não-lidos) |
| **Ambiente** | Compatível Railway (vars de ambiente para BD), timezone `America/Sao_Paulo`, interface em Português Brasileiro |
| **Scripts de backup** | Scripts PowerShell para backup/restore/health-check/migração do BD |
| **BASE_PATH** | Constante dinâmica derivada de `APP_URL` — permite deploy em qualquer prefixo de URL sem alterações de código |
| **guard-submit.js** | Script global de prevenção de duplo-clique/duplo-submit — incluído via `footer.php` em todas as páginas. Intercepta `submit` em forms POST (desabilita botão + spinner "Processando…" + re-habilita após 8s safety), protege botões `[data-action]` AJAX (lock 3s), e forms `add_cart` específicos (1.5s cooldown). Forms com `data-no-guard` e forms GET são ignorados |

---

## 19. Atualizações Recentes

- **Sistema de tickets de suporte completo**: 3 tabelas (tickets, mensagens, anexos), 8 categorias pré-definidas, fluxo de status (aberto→em_andamento→respondido→fechado), prevenção de duplicatas (1h), páginas para comprador, vendedor e admin com reply inline, notificação automática ao dono do ticket.
- **Entrega automática (auto-delivery)**: Produtos digitais com pool JSON de itens, consumo automático ao confirmar pagamento, desativa quando pool vazio, notificação ao comprador.
- **Páginas legais premium**: Termos de Uso, Política de Privacidade, Política de Reembolso — todas com sidebar de navegação fixa, scroll-spy (seção ativa destacada), seções animadas, tema dark/light.
- **Central de Ajuda**: Hub de suporte com cards linkando FAQ, Tickets, Termos, Privacidade, Reembolso.
- **FAQ (Perguntas Frequentes)**: Acordeão categorizado com Alpine.js, scroll-spy de navegação lateral.
- **Google OAuth dois modos**: Login-only (exige conta cadastrada) e registro com modal de seleção de role (Comprador/Vendedor). Intermediário `google_redirect.php`, suporte a env vars (`GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`).
- **Robustez de notificações**: `notificationsEnsureTable()` verifica criação da tabela antes de marcar como concluído, null guards em todas as funções, log de diagnóstico.
- **Robustez de avaliações**: `reviewEnsureTable()` corrigido para verificar resultado em vez de exceções, null guards em `reviewCanUserReview()`.
- **Correção de auth guards**: `exigirUsuario()` substituído por `exigirLogin()` em 9 páginas de comprador — vendedores e admins agora podem acessar minha_conta, meus_pedidos, depositos, saques, dashboard, tickets_dashboard, denuncias, afiliados, pedido_detalhes.
- **Denunciar produto (página pública)**: Formulário com preview do produto, 9 motivos pré-definidos, prevenção de duplicata (24h).
- **Minhas Denúncias (comprador)**: Dashboard com filtros de status e busca, modal de detalhe AJAX.

- **Sistema de blog completo**: CMS com posts, categorias, autores, visibilidade per-role, contador de visualizações, compartilhamento social, posts relacionados, admin CRUD com editor HTML + upload de capa via `media_files`.
- **Sistema de avaliações (reviews)**: Estrelas 1–5 com validação de compra, resposta do vendedor, moderação, agregados com distribuição, SVG de estrelas.
- **Sistema de perguntas e respostas (Q&A)**: Perguntas marketplace-style na página de produto, respostas inline do vendedor, máscara automática de contato (anti-fraude), dashboard de perguntas do vendedor com polling.
- **Sistema de favoritos / wishlist**: Toggle AJAX com coração em cards, página "Meus Favoritos", check bulk para grids, admin analytics com ranking top 5.
- **Sistema de notificações in-app**: Tipos (anuncio/venda/chat/ticket), broadcasts para todos, badge na nav com polling, APIs de listagem/contagem/leitura.
- **Sistema de denúncias (reports)**: Submissão com prevenção de duplicatas 24h, fluxo de status admin (pendente→analisando→resolvido/rejeitado), visualização do vendedor.
- **Chat widgets flutuantes**: FAB Shopee-style para storefront (painel inline 380×520px), widget vendedor com lista multi-comprador, widget comprador com badge.
- **Google OAuth**: Login com Google configurável via admin, fluxo OAuth em PHP puro, settings em `platform_settings`.
- **Documentação in-app**: Página interativa no admin com todas as funcionalidades categorizadas e linkadas.
- **Página "Como Funciona" standalone**: Migrada de seção na homepage para página dedicada premium (`/como_funciona`) com abas Comprador/Vendedor, FAQ acordeão, animações.
- **Imagens de categorias**: Grid visual de categorias na homepage com ícones coloridos e imagens via `media_files`.
- **Navegação aprimorada**: Badge de favoritos + notificações na nav, hover/active vermelho no ícone do carrinho (dark/light mode), ícones de favoritos com borda temática.
- **Login com redirect**: Links de login na página de produto (Q&A e avaliações) incluem `return_to` para redirecionar de volta após login.
- **Avatar em tempo real**: Foto do comprador em novas perguntas e avaliações buscada do DB (não da sessão) para exibição imediata.
- **Anti-fraude**: `maskContactInfo()` e `hasContactInfo()` em `src/helpers.php` — filtra emails, telefones, @handles, URLs, menções WhatsApp/Telegram em perguntas e respostas.
- Sistema de afiliados publicado end-to-end (comprador, vendedor, admin e regras globais).
- Correção de compatibilidade de tipos para PostgreSQL BIGINT nas funções de afiliados (`int|string` com cast interno).
- Correção da visão de comissões do vendedor: substituição de coluna inexistente (`orders.seller_id`) por join correto via `order_items.vendedor_id`.
- Correção de agregação em resumo de vendas de afiliados (remoção do risco de subcontagem por `SUM(DISTINCT valor)`).
- Máscara de chave PIX adicionada nos formulários de afiliado (`CPF`, `telefone`, `email`, `aleatória`) via `public/assets/js/pix-mask.js`.
- Página admin de afiliados refinada com paginação em conversões e payouts, e consultas preparadas para filtros.
- **Storefront reestruturado (estilo GGMax)**: announcement bar, barra de categorias, hero banner com side cards, grid visual de categorias, trust bar com cards, layout responsivo.
- **Código de entrega estilo iFood**: 6 caracteres alfanuméricos gerado ao pagar, comprador visualiza em caixas individuais, vendedor insere para liberar escrow.
- **Engine de temas**: 2 temas (Green/Blue) × 2 modos (Dark/Light), 12 tokens CSS custom properties, Tailwind dinâmico, toggle em todas as áreas.
- **Admin de temas**: seleção de tema/modo com preview, página de detalhes com paleta de cores completa.
- **Toggle dark/light** em storefront nav, dashboard comprador, vendedor e admin.
- **Migração SQL**: `delivery_code VARCHAR(6)` na tabela `orders`.
