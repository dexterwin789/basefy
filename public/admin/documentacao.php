<?php
// filepath: c:\xampp\htdocs\mercado_admin\public\admin\documentacao.php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/config.php';
require_once dirname(__DIR__, 2) . '/src/auth.php';
exigirAdmin();

$pageTitle = 'Documentação do Sistema';
$activeMenu = 'documentacao';

include __DIR__ . '/../../views/partials/header.php';
include __DIR__ . '/../../views/partials/admin_layout_start.php';

// ── Feature data ──
$sections = [
    [
        'id'    => 'storefront',
        'icon'  => 'shopping-bag',
        'color' => 'emerald',
        'title' => 'Storefront (Loja Pública)',
        'desc'  => 'Páginas visíveis para todos os visitantes — catálogo, produto, carrinho, checkout, pagamento PIX, blog e avaliações.',
        'items' => [
            ['icon' => 'home',           'title' => 'Página Inicial',          'desc' => 'Hero com busca, barra de confiança (PIX · Escrow · Wallet · Moderação), pills de categorias, grid com 8 produtos em destaque.', 'url' => '/'],
            ['icon' => 'package',        'title' => 'Página de Produto',       'desc' => 'Roteamento por slug (<code>/p/slug</code>), galeria de imagens, descrição expandível, seletor de quantidade, adicionar ao carrinho, produtos relacionados.', 'url' => '/p/{slug}'],
            ['icon' => 'grid-3x3',       'title' => 'Catálogo',               'desc' => 'Pills de categorias, busca por texto, URLs amigáveis (<code>/c/slug</code>), grid com badges, adicionar ao carrinho.', 'url' => '/categorias'],
            ['icon' => 'store',          'title' => 'Loja do Vendedor',        'desc' => 'Perfil público (avatar, nome, bio, vendas), grid de produtos com busca, botão "Iniciar chat".', 'url' => '/loja/{slug}'],
            ['icon' => 'shopping-cart',  'title' => 'Carrinho',                'desc' => 'Sessão, AJAX qty, remover/limpar, resumo na sidebar, recálculo em tempo real.', 'url' => '/carrinho'],
            ['icon' => 'credit-card',    'title' => 'Checkout',                'desc' => '3 etapas (Itens → Pagamento → Confirmação), wallet + PIX split, AJAX place_order.', 'url' => '/checkout'],
            ['icon' => 'qr-code',        'title' => 'Pagamento PIX',           'desc' => 'QR Code BlackCat, copiar código, polling 5s, estados sucesso/erro.', 'url' => '/checkout_pix'],
            ['icon' => 'user-plus',      'title' => 'Registro',               'desc' => 'Nome, email, senha, tipo (comprador/vendedor), auto-login, vendedor → aprovação. Google OAuth com modal de seleção de role.', 'url' => '/register'],
            ['icon' => 'log-in',         'title' => 'Login',                   'desc' => 'Unificado (3 roles), Google OAuth (exige conta existente), preserva carrinho, redirect por role, param return_to.', 'url' => '/login'],
            ['icon' => 'newspaper',      'title' => 'Blog',                    'desc' => 'Listagem de posts com imagem de capa, autor, data, trecho. Paginação, visibilidade controlada por settings.', 'url' => '/blog'],
            ['icon' => 'file-text',      'title' => 'Post do Blog',           'desc' => 'Detalhe do post por slug, incremento de views, posts relacionados na sidebar.', 'url' => '/blog/{slug}'],
            ['icon' => 'star',           'title' => 'Avaliações de Produto',  'desc' => 'Sistema de reviews com estrelas (1-5), foto upload, moderação. Exibidas na página de produto e na home como testimonials.', 'url' => ''],
            ['icon' => 'help-circle',    'title' => 'Como Funciona',          'desc' => 'Página explicativa do marketplace — passos para comprar e vender, garantias e escrow.', 'url' => '/como_funciona'],
            ['icon' => 'heart',          'title' => 'Favoritos',              'desc' => 'Listagem de produtos favoritos do usuário. Toggle AJAX via coração nos cards e na página de produto.', 'url' => '/favoritos'],
            ['icon' => 'headphones',     'title' => 'Central de Ajuda',       'desc' => 'Hub de suporte com links para FAQ, tickets, termos e contato.', 'url' => '/central_ajuda'],
            ['icon' => 'message-circle', 'title' => 'FAQ',                    'desc' => 'Perguntas frequentes com acordeão, categorias visuais e busca.', 'url' => '/faq'],
            ['icon' => 'ticket',         'title' => 'Tickets de Suporte',     'desc' => 'Listagem pública de tickets do usuário, criar ticket com 8 categorias, acompanhar respostas.', 'url' => '/tickets'],
            ['icon' => 'flag',           'title' => 'Denunciar Produto',      'desc' => 'Formulário de denúncia com motivo, descrição, prevenção de duplicatas.', 'url' => '/denunciar'],
            ['icon' => 'file-text',      'title' => 'Termos de Uso',          'desc' => 'Página legal com acordos de uso da plataforma, UI premium.', 'url' => '/termos'],
            ['icon' => 'shield',         'title' => 'Política de Privacidade','desc' => 'Página legal LGPD-friendly com políticas de dados e cookies.', 'url' => '/privacidade'],
            ['icon' => 'refresh-cw',     'title' => 'Política de Reembolso',  'desc' => 'Página legal com regras de devolução e escrow release.', 'url' => '/reembolso'],
            ['icon' => 'link',           'title' => 'URLs Amigáveis',          'desc' => 'Todas sem <code>.php</code>. Redirect 301 automático. Slugs: <code>/p/</code>, <code>/c/</code>, <code>/loja/</code>.', 'url' => ''],
        ],
    ],
    [
        'id'    => 'user',
        'icon'  => 'user',
        'color' => 'blue',
        'title' => 'Dashboard do Usuário',
        'desc'  => 'Painel do comprador — pedidos, carteira, saques, depósitos, chat e configurações.',
        'items' => [
            ['icon' => 'layout-dashboard', 'title' => 'Dashboard',            'desc' => 'Avatar, stats de pedidos, gráficos Chart.js (7 dias, status rosca, resumo barras), ações rápidas.', 'url' => '/dashboard'],
            ['icon' => 'wallet-cards',     'title' => 'Carteira',             'desc' => 'Saldo, recarga PIX (modal QR), saque com tipo de chave PIX (CPF/CNPJ/Email/Tel/Aleatória), Alpine.js masks.', 'url' => '/wallet'],
            ['icon' => 'package-check',    'title' => 'Meus Pedidos',         'desc' => 'Filtro status/datas, paginação 5/10/20, thumbnails, badge pagamento (PIX/Wallet/Wallet+PIX).', 'url' => '/meus_pedidos'],
            ['icon' => 'file-text',        'title' => 'Detalhes do Pedido',   'desc' => 'Breakdown wallet vs PIX, moderação por item, "Confirmar entrega" (escrow release), entrega digital.', 'url' => '/pedido_detalhes?id='],
            ['icon' => 'banknote',         'title' => 'Depósitos',            'desc' => 'Histórico de recargas, filtro status, busca, paginação.', 'url' => '/depositos'],
            ['icon' => 'arrow-down-up',    'title' => 'Saques',               'desc' => 'Lista filtrável (status, chave PIX, valor, ID), paginação, badges.', 'url' => '/saques'],
            ['icon' => 'message-circle',   'title' => 'Chat',                 'desc' => 'Split view, lista conversas, polling 3s, Enter-to-send, responsivo mobile, bolhas animadas.', 'url' => '/chat'],
            ['icon' => 'user-circle-2',    'title' => 'Minha Conta',          'desc' => 'Perfil (nome, email), avatar drag-and-drop, alterar senha com verificação. Google OAuth: senha opcional.', 'url' => '/minha_conta'],
            ['icon' => 'heart',            'title' => 'Favoritos',            'desc' => 'Lista de produtos favoritados, toggle AJAX, integrado com cards e página de produto.', 'url' => '/favoritos'],
            ['icon' => 'ticket',           'title' => 'Meus Tickets',         'desc' => 'Dashboard de tickets de suporte, criar ticket com 8 categorias, acompanhar respostas admin.', 'url' => '/tickets_dashboard'],
            ['icon' => 'flag',             'title' => 'Minhas Denúncias',     'desc' => 'Lista de denúncias de produtos enviadas, status de moderação.', 'url' => '/denuncias'],
            ['icon' => 'share-2',          'title' => 'Afiliados',            'desc' => 'Painel do afiliado: código, link, conversões, ganhos, saque de comissões.', 'url' => '/afiliados'],
        ],
    ],
    [
        'id'    => 'vendor',
        'icon'  => 'store',
        'color' => 'violet',
        'title' => 'Dashboard do Vendedor',
        'desc'  => 'Painel do vendedor — produtos, vendas, carteira, entrega digital e chat.',
        'items' => [
            ['icon' => 'layout-dashboard', 'title' => 'Dashboard',            'desc' => 'Stats (produtos, vendas aprovadas/pendentes, saques), Chart.js (7 dias barras, status rosca).', 'url' => '/vendedor/dashboard'],
            ['icon' => 'package',          'title' => 'Meus Produtos',        'desc' => 'Lista com busca/filtro, paginação, CSRF delete, toggle ativo/inativo.', 'url' => '/vendedor/produtos'],
            ['icon' => 'file-plus',        'title' => 'Formulário Produto',   'desc' => 'Tipo produto/serviço, Quill.js editor, upload capa + galeria, slug, estoque, prazo entrega. Entrega automática com pool de itens.', 'url' => '/vendedor/produtos_form'],
            ['icon' => 'badge-check',      'title' => 'Vendas Aprovadas',     'desc' => 'Por pedido, info comprador, busca, datas, total mínimo, paginação.', 'url' => '/vendedor/vendas_aprovadas'],
            ['icon' => 'hourglass',        'title' => 'Vendas em Análise',    'desc' => 'Mesmos filtros + status pagamento, entrega digital inline.', 'url' => '/vendedor/vendas_analise'],
            ['icon' => 'send',             'title' => 'Entrega Digital',      'desc' => 'Envio de link/texto para item, reseta timer auto-release.', 'url' => ''],
            ['icon' => 'wallet-cards',     'title' => 'Carteira',             'desc' => 'Saldo, recarga PIX, saque com tipos de chave.', 'url' => '/vendedor/wallet'],
            ['icon' => 'wallet',           'title' => 'Saques',               'desc' => 'Lista filtrável, solicitar novo saque, paginação, badges de status.', 'url' => '/vendedor/saques'],
            ['icon' => 'banknote',         'title' => 'Depósitos',            'desc' => 'Histórico recargas, filtro status, busca, paginação.', 'url' => '/vendedor/depositos'],
            ['icon' => 'clipboard-check',  'title' => 'Aprovação',            'desc' => 'Formulário onboarding: nome_loja, CPF/CNPJ, tel, chave_pix, bio (30+ chars).', 'url' => '/vendedor/aprovacao'],
            ['icon' => 'message-circle',   'title' => 'Chat',                 'desc' => 'Mesmo do comprador, com widget flutuante em todas as páginas vendor.', 'url' => '/vendedor/chat'],
            ['icon' => 'user-circle-2',    'title' => 'Minha Conta',          'desc' => 'Perfil, avatar, chat toggle, alterar senha. Google OAuth: senha opcional com banner informativo.', 'url' => '/vendedor/minha_conta'],
            ['icon' => 'message-square',   'title' => 'Perguntas (Q&A)',      'desc' => 'Gerenciamento de perguntas dos compradores nos produtos. Responder, máscara anti-fraude.', 'url' => '/vendedor/perguntas'],
            ['icon' => 'ticket',           'title' => 'Tickets',              'desc' => 'Tickets de suporte do vendedor, enviar mensagens, acompanhar resoluções.', 'url' => '/vendedor/tickets'],
            ['icon' => 'flag',             'title' => 'Denúncias',            'desc' => 'Denúncias recebidas sobre produtos do vendedor, status de moderação.', 'url' => '/vendedor/denuncias'],
            ['icon' => 'share-2',          'title' => 'Afiliados',            'desc' => 'Programa de afiliados do vendedor: links, conversões, comissões.', 'url' => '/vendedor/afiliados'],
        ],
    ],
    [
        'id'    => 'admin',
        'icon'  => 'shield',
        'color' => 'amber',
        'title' => 'Dashboard do Administrador',
        'desc'  => 'Painel completo de gestão — moderação, usuários, vendedores, categorias, escrow, chat monitor, blog e temas.',
        'items' => [
            ['icon' => 'layout-dashboard', 'title' => 'Dashboard',            'desc' => 'Stats (usuários, vendedores, vendas hoje, moderação), Chart.js (tendências, rosca, novos usuários).', 'url' => '/admin/dashboard'],
            ['icon' => 'badge-dollar-sign','title' => 'Moderação de Vendas',  'desc' => 'Aprovar (credita vendedor - taxa, deduz estoque) / rejeitar (reembolsa comprador). Modal detalhe, auto "entregue".', 'url' => '/admin/vendas'],
            ['icon' => 'users',            'title' => 'Gestão de Usuários',   'desc' => 'Lista, busca, paginação, toggle ativo/inativo AJAX, formulários edição/criação.', 'url' => '/admin/usuarios'],
            ['icon' => 'store',            'title' => 'Gestão de Vendedores', 'desc' => 'Lista, toggle ativo, status aprovação, formulários edição/criação.', 'url' => '/admin/vendedores'],
            ['icon' => 'shield-check',     'title' => 'Administradores',      'desc' => 'Lista, busca, formulários edição/criação.', 'url' => '/admin/admins'],
            ['icon' => 'tags',             'title' => 'Categorias',           'desc' => 'CRUD, filtro tipo/ativo, toggle/delete AJAX, auto-slug.', 'url' => '/admin/categorias'],
            ['icon' => 'package',          'title' => 'Produtos',             'desc' => 'CRUD, filtros completos, thumbnails, tipo badge, Quill.js editor.', 'url' => '/admin/produtos'],
            ['icon' => 'file-clock',       'title' => 'Solicitações Vendedor', 'desc' => 'Pipeline (pendente→aprovada/rejeitada), perfil completo, mascaramento de dados.', 'url' => '/admin/solicitacoes_vendedor'],
            ['icon' => 'banknote',         'title' => 'Depósitos',            'desc' => 'Lista recargas, filtros, detalhe com webhooks relacionados.', 'url' => '/admin/depositos'],
            ['icon' => 'arrow-down-up',    'title' => 'Saques',               'desc' => 'Stats, tabs (pendentes/aprovados/todos), saque instantâneo BlackCat, observações.', 'url' => '/admin/saques'],
            ['icon' => 'wallet-cards',     'title' => 'Saldo Admin',          'desc' => 'Saldo da carteira admin, transações, saques, modo (auto vs manual).', 'url' => '/admin/wallet_admin'],
            ['icon' => 'settings',         'title' => 'Config Escrow/Wallet', 'desc' => 'Auto-release (1-60 dias), taxa % (0-100), admin recebedor, toggle auto-release.', 'url' => '/admin/wallet_config'],
            ['icon' => 'message-circle',   'title' => 'Chat Monitor',         'desc' => 'Stats, lista paginada, viewer de threads, supervisão read-only.', 'url' => '/admin/chat'],
            ['icon' => 'newspaper',        'title' => 'Blog',                 'desc' => 'CRUD de posts, settings (ativar/desativar, visibilidade por role), editor com imagem de capa e slug.', 'url' => '/admin/blog'],
            ['icon' => 'palette',          'title' => 'Temas',                'desc' => 'Gerenciamento de temas da loja (cores, dark/light mode). Seleção e ativação de tema por admin.', 'url' => '/admin/temas'],
            ['icon' => 'ticket',           'title' => 'Tickets de Suporte',   'desc' => 'Gestão de todos os tickets: filtros, responder, alterar status (aberto→em_andamento→resolvido/fechado).', 'url' => '/admin/tickets'],
            ['icon' => 'flag',             'title' => 'Denúncias',            'desc' => 'Moderação de denúncias de produtos: visualizar, analisar, resolver ou descartar.', 'url' => '/admin/denuncias'],
            ['icon' => 'heart',            'title' => 'Favoritos Analytics',  'desc' => 'Produtos mais favoritados, ranking, dados para decisão de destaque.', 'url' => '/admin/favoritos'],
            ['icon' => 'key-round',        'title' => 'Google OAuth',         'desc' => 'Configuração de Client ID, Client Secret e Redirect URI do Google OAuth 2.0.', 'url' => '/admin/google_oauth'],
            ['icon' => 'user-circle-2',    'title' => 'Minha Conta',          'desc' => 'Perfil do admin, avatar, alterar senha. Google OAuth: senha opcional.', 'url' => '/admin/minha_conta'],
            ['icon' => 'book-open',        'title' => 'Documentação',         'desc' => 'Esta página — referência completa de todas as funcionalidades, módulos e rotas do sistema.', 'url' => '/admin/documentacao'],
        ],
    ],
    [
        'id'    => 'api',
        'icon'  => 'code-2',
        'color' => 'cyan',
        'title' => 'API & Webhooks',
        'desc'  => 'Endpoints internos AJAX e integrações externas.',
        'items' => [
            ['icon' => 'send',            'title' => 'place_order',           'desc' => 'POST — Criação de pedido, split wallet+PIX, geração PIX BlackCat, retorna QR JSON.', 'url' => '/api/place_order'],
            ['icon' => 'message-circle',  'title' => 'chat',                  'desc' => 'POST — API REST: start, send, messages, poll, conversations, read, unread_count, archive.', 'url' => '/api/chat'],
            ['icon' => 'star',            'title' => 'reviews',               'desc' => 'POST — Submit, update, delete avaliações de produto. Upload de foto, validação de compra.', 'url' => '/api/reviews'],
            ['icon' => 'image',           'title' => 'media',                 'desc' => 'GET — Serve mídia do BD (base64), MIME correto, cache 30 dias, ETag/304.', 'url' => '/api/media'],
            ['icon' => 'toggle-left',     'title' => 'theme_toggle',          'desc' => 'POST — Alterna dark/light mode, persiste preferência via cookie e sessão.', 'url' => '/api/theme_toggle'],
            ['icon' => 'bell',            'title' => 'notifications',         'desc' => 'POST — API REST: list, unread_count, mark_read, mark_all_read. Polling AJAX.', 'url' => '/api/notifications'],
            ['icon' => 'heart',           'title' => 'favorites',             'desc' => 'POST — Toggle favorito: add/remove, list, count. Retorna JSON com status.', 'url' => '/api/favorites'],
            ['icon' => 'message-square',  'title' => 'questions',             'desc' => 'POST — CRUD de perguntas e respostas nos produtos. Máscara anti-fraude.', 'url' => '/api/questions'],
            ['icon' => 'activity',        'title' => 'wallet_topup_status',   'desc' => 'GET — Polling para verificar status de recarga PIX (pago/pendente/expirado).', 'url' => '/api/wallet_topup_status'],
            ['icon' => 'check-circle',    'title' => 'check_slug',            'desc' => 'POST — Verifica disponibilidade de slug para produtos/lojas.', 'url' => '/api/check_slug'],
            ['icon' => 'webhook',         'title' => 'blackcat webhook',      'desc' => 'POST — Recebe transaction.paid, idempotente (webhook_events), credita wallet, inicia escrow.', 'url' => '/webhooks/blackcat'],
        ],
    ],
    [
        'id'    => 'affiliates',
        'icon'  => 'share-2',
        'color' => 'violet',
        'title' => 'Sistema de Afiliados',
        'desc'  => 'Programa de afiliados com links de referência, comissões e painel de gestão.',
        'items' => [
            ['icon' => 'link',            'title' => 'Links de Referência',   'desc' => 'URL única por afiliado (?ref=CÓDIGO). Rastreamento via cookie com expiração configurável.', 'url' => ''],
            ['icon' => 'badge-dollar-sign','title' => 'Comissões',            'desc' => 'Comissão % configurável por admin. Creditada automaticamente após aprovação da venda (escrow).', 'url' => ''],
            ['icon' => 'users',           'title' => 'Painel Admin',          'desc' => 'Lista afiliados, status (ativo/inativo), total de referências, ganhos, filtros e buscas.', 'url' => '/admin/afiliados'],
            ['icon' => 'settings-2',      'title' => 'Configurações',         'desc' => 'Ativar/desativar programa, taxa de comissão (%), expiração do cookie de rastreamento.', 'url' => '/admin/afiliados_config'],
            ['icon' => 'bar-chart-3',     'title' => 'Relatórios',            'desc' => 'Referências por afiliado, conversões, ganhos totais e pendentes.', 'url' => ''],
        ],
    ],
    [
        'id'    => 'auth',
        'icon'  => 'key-round',
        'color' => 'amber',
        'title' => 'Autenticação & OAuth',
        'desc'  => 'Sistema de login, registro e autenticação social.',
        'items' => [
            ['icon' => 'log-in',          'title' => 'Login Nativo',          'desc' => 'Email + senha, bcrypt, sessão PHP, redirect baseado em role (admin/vendedor/usuario).', 'url' => '/login'],
            ['icon' => 'user-plus',       'title' => 'Registro',              'desc' => 'Nome, email, senha, tipo de conta. Vendedor requer aprovação admin.', 'url' => '/register'],
            ['icon' => 'chrome',          'title' => 'Google OAuth',          'desc' => 'Login via Google (exige conta existente) e registro com modal de seleção de role (comprador/vendedor). Intermediário <code>/google_redirect</code> evita race condition. Callback <code>/google_callback</code>.', 'url' => '/admin/google_oauth'],
            ['icon' => 'shield',          'title' => 'Sessões & Roles',       'desc' => '3 roles (admin, vendedor, usuario). Middleware exigirAdmin/exigirVendedor/exigirLogin. Vendedor pendente bloqueado de todas as páginas. Sessão persistente.', 'url' => ''],
            ['icon' => 'info',            'title' => 'Conta Google — Senha',  'desc' => 'Usuários Google não precisam de senha. Tela "Minha Conta" adapta: esconde campo "senha atual", mostra banner informativo, senha é opcional.', 'url' => ''],
        ],
    ],
    [
        'id'    => 'reviews',
        'icon'  => 'star',
        'color' => 'amber',
        'title' => 'Sistema de Avaliações',
        'desc'  => 'Avaliações de produtos com estrelas, fotos e moderação.',
        'items' => [
            ['icon' => 'star',            'title' => 'Avaliação de Produto',  'desc' => 'Nota 1-5 estrelas, título, comentário. Exibida na página do produto com breakdown de notas.', 'url' => ''],
            ['icon' => 'shield-check',    'title' => 'Validação de Compra',   'desc' => 'Só pode avaliar quem comprou e recebeu o produto (pedido aprovado/entregue).', 'url' => ''],
            ['icon' => 'message-circle',  'title' => 'Resposta do Vendedor',  'desc' => 'Vendedor pode responder avaliações. Respostas exibidas abaixo do review.', 'url' => ''],
            ['icon' => 'bar-chart-3',     'title' => 'Agregados',             'desc' => 'Média do produto, breakdown por estrela, média do vendedor. Exibidos na home como testimonials.', 'url' => ''],
        ],
    ],
    [
        'id'    => 'tickets',
        'icon'  => 'ticket',
        'color' => 'cyan',
        'title' => 'Tickets de Suporte',
        'desc'  => 'Sistema completo de suporte com tickets, categorias, fluxo de status e respostas admin.',
        'items' => [
            ['icon' => 'ticket',          'title' => 'Criar Ticket',          'desc' => '8 categorias (Pedido, Pagamento, Produto, Conta, Vendedor, Técnico, Sugestão, Outro). Título + descrição.', 'url' => '/tickets_novo'],
            ['icon' => 'list',            'title' => 'Meus Tickets',          'desc' => 'Dashboard com lista de tickets por status, filtro, paginação, badge de novas respostas.', 'url' => '/tickets_dashboard'],
            ['icon' => 'file-text',       'title' => 'Detalhe do Ticket',     'desc' => 'Timeline de mensagens, reply do usuário, status atualizado em tempo real.', 'url' => '/ticket_detalhe?id='],
            ['icon' => 'headphones',      'title' => 'Admin — Gerenciar',     'desc' => 'Lista todos os tickets do sistema, filtros por status/categoria, responder e alterar status.', 'url' => '/admin/tickets'],
            ['icon' => 'store',           'title' => 'Vendedor — Tickets',    'desc' => 'Tickets enviados pelo vendedor, mensagens e acompanhamento.', 'url' => '/vendedor/tickets'],
            ['icon' => 'bell',            'title' => 'Notificações',          'desc' => 'Notificação automática quando admin responde ticket. Tipo "ticket" no badge.', 'url' => ''],
        ],
    ],
    [
        'id'    => 'denuncias',
        'icon'  => 'flag',
        'color' => 'rose',
        'title' => 'Sistema de Denúncias',
        'desc'  => 'Mecanismo de denúncia de produtos com moderação administrativa.',
        'items' => [
            ['icon' => 'flag',            'title' => 'Denunciar Produto',     'desc' => 'Formulário com motivo e descrição. Prevenção de duplicatas por usuário+produto.', 'url' => '/denunciar'],
            ['icon' => 'list',            'title' => 'Minhas Denúncias',      'desc' => 'Lista de denúncias enviadas pelo comprador com status de moderação.', 'url' => '/denuncias'],
            ['icon' => 'shield-check',    'title' => 'Admin — Moderar',       'desc' => 'Fluxo completo: pendente→analisando→resolvida/descartada. Visualizar produto e vendedor.', 'url' => '/admin/denuncias'],
            ['icon' => 'store',           'title' => 'Vendedor — Denúncias',  'desc' => 'Denúncias recebidas sobre produtos do vendedor.', 'url' => '/vendedor/denuncias'],
        ],
    ],
    [
        'id'    => 'favorites',
        'icon'  => 'heart',
        'color' => 'rose',
        'title' => 'Favoritos / Wishlist',
        'desc'  => 'Sistema de favoritos com toggle AJAX e analytics admin.',
        'items' => [
            ['icon' => 'heart',           'title' => 'Toggle Favorito',       'desc' => 'Coração em cards de produto e na página de produto. AJAX add/remove sem recarregar.', 'url' => ''],
            ['icon' => 'list',            'title' => 'Meus Favoritos',        'desc' => 'Página do comprador com grid de produtos favoritados, remover individual.', 'url' => '/favoritos'],
            ['icon' => 'bar-chart-3',     'title' => 'Admin Analytics',       'desc' => 'Produtos mais favoritados, ranking, dados para decidir destaques.', 'url' => '/admin/favoritos'],
            ['icon' => 'code-2',          'title' => 'API Favorites',         'desc' => 'POST — Toggle (add/remove), list, count. Retorna JSON com status atualizado.', 'url' => '/api/favorites'],
        ],
    ],
    [
        'id'    => 'questions',
        'icon'  => 'message-square',
        'color' => 'blue',
        'title' => 'Perguntas e Respostas (Q&A)',
        'desc'  => 'Sistema marketplace-style de perguntas nos produtos.',
        'items' => [
            ['icon' => 'help-circle',     'title' => 'Perguntar',             'desc' => 'Comprador pergunta na página do produto. Exibição com data e nome mascarado.', 'url' => ''],
            ['icon' => 'message-circle',  'title' => 'Responder',             'desc' => 'Vendedor responde no painel. Máscara anti-fraude em textos.', 'url' => '/vendedor/perguntas'],
            ['icon' => 'code-2',          'title' => 'API Questions',         'desc' => 'POST — CRUD de perguntas e respostas, validação de dono.', 'url' => '/api/questions'],
        ],
    ],
    [
        'id'    => 'notifications',
        'icon'  => 'bell',
        'color' => 'amber',
        'title' => 'Sistema de Notificações',
        'desc'  => 'Notificações in-app com polling, badge e categorias.',
        'items' => [
            ['icon' => 'bell',            'title' => 'Bell na Navbar',        'desc' => 'Badge vermelho com contagem de não-lidas. Dropdown com lista de notificações.', 'url' => ''],
            ['icon' => 'inbox',           'title' => '4 Categorias',          'desc' => 'Anúncio, Venda, Chat, Ticket. Tabs visuais no dropdown para filtrar.', 'url' => ''],
            ['icon' => 'activity',        'title' => 'Polling AJAX',          'desc' => 'Verificação periódica de novas notificações. Badge atualiza sem recarregar.', 'url' => ''],
            ['icon' => 'check-circle',    'title' => 'Marcar como Lido',      'desc' => 'Individual e em lote ("Marcar tudo lido"). API /api/notifications.', 'url' => '/api/notifications'],
            ['icon' => 'volume-2',        'title' => 'Som de Notificação',    'desc' => 'Toggle de som no dropdown. Alerta sonoro quando chega notificação nova.', 'url' => ''],
        ],
    ],
    [
        'id'    => 'auto_delivery',
        'icon'  => 'zap',
        'color' => 'emerald',
        'title' => 'Entrega Automática',
        'desc'  => 'Produtos digitais com pool de itens e consumo automático ao pagar.',
        'items' => [
            ['icon' => 'toggle-left',     'title' => 'Toggle por Produto',    'desc' => 'Ativar/desativar entrega automática no formulário do produto (vendedor).', 'url' => ''],
            ['icon' => 'database',        'title' => 'Pool de Itens',         'desc' => 'Campo JSONB auto_delivery_items. Vendedor adiciona códigos/links, sistema consome um por venda.', 'url' => ''],
            ['icon' => 'zap',             'title' => 'Consumo Automático',    'desc' => 'Ao confirmar pagamento (webhook), item é removido do pool e entregue ao comprador.', 'url' => ''],
            ['icon' => 'refresh-cw',      'title' => 'Reposição',             'desc' => 'Vendedor pode adicionar mais itens ao pool pelo painel de produtos.', 'url' => '/vendedor/produtos_form'],
        ],
    ],
    [
        'id'    => 'legal',
        'icon'  => 'scale',
        'color' => 'cyan',
        'title' => 'Páginas Legais & Central de Ajuda',
        'desc'  => 'Páginas institucionais e suporte ao usuário.',
        'items' => [
            ['icon' => 'file-text',       'title' => 'Termos de Uso',         'desc' => 'Página legal com acordos de uso da plataforma, seções expandíveis, UI premium.', 'url' => '/termos'],
            ['icon' => 'shield',          'title' => 'Política de Privacidade','desc' => 'Página LGPD-friendly com políticas de dados, cookies e compartilhamento.', 'url' => '/privacidade'],
            ['icon' => 'refresh-cw',      'title' => 'Política de Reembolso', 'desc' => 'Regras de devolução, escrow release, prazos e exceções.', 'url' => '/reembolso'],
            ['icon' => 'headphones',      'title' => 'Central de Ajuda',      'desc' => 'Hub de suporte com links para FAQ, tickets, termos e canais de contato.', 'url' => '/central_ajuda'],
            ['icon' => 'help-circle',     'title' => 'FAQ',                   'desc' => 'Perguntas frequentes com acordeão, categorias visuais e busca.', 'url' => '/faq'],
            ['icon' => 'info',            'title' => 'Como Funciona',         'desc' => 'Página explicativa do marketplace: passos para comprar/vender, garantias.', 'url' => '/como_funciona'],
        ],
    ],
    [
        'id'    => 'infra',
        'icon'  => 'server',
        'color' => 'rose',
        'title' => 'Infraestrutura',
        'desc'  => 'Stack técnica, deploy e configuração.',
        'items' => [
            ['icon' => 'container',   'title' => 'Docker',        'desc' => 'php:8.2-cli, pdo_pgsql, mbstring, curl. Servidor built-in na $PORT com router.php.', 'url' => ''],
            ['icon' => 'database',    'title' => 'PostgreSQL',    'desc' => 'Railway-hosted. Camada PgCompat traduz MySQL→PG (backticks, IFNULL, LIMIT/OFFSET, ON CONFLICT).', 'url' => ''],
            ['icon' => 'route',       'title' => 'Router',        'desc' => 'Clean URLs sem .php, redirect 301, slugs (/p/, /c/, /loja/), .htaccess para Apache.', 'url' => ''],
            ['icon' => 'palette',     'title' => 'Tailwind CSS',  'desc' => 'CDN, tema custom (Inter, blackx/greenx), dark/light mode via CSS custom properties e classe .light-mode.', 'url' => ''],
            ['icon' => 'component',   'title' => 'Alpine.js',     'desc' => 'CDN, formulários interativos, masks, dropzones.', 'url' => ''],
            ['icon' => 'bar-chart-3', 'title' => 'Chart.js 4',    'desc' => 'CDN, gráficos line/doughnut/bar nos dashboards.', 'url' => ''],
            ['icon' => 'type',        'title' => 'Quill.js 2',    'desc' => 'CDN, editor rich-text para descrições de produtos.', 'url' => ''],
        ],
    ],
];

$colorMap = [
    'emerald' => ['bg' => 'bg-greenx/10', 'border' => 'border-greenx/20', 'text' => 'text-greenx', 'badge' => 'bg-greenx/15 text-greenx border-greenx/30'],
    'blue'    => ['bg' => 'bg-greenx/10',    'border' => 'border-greenx/20',    'text' => 'text-purple-400',    'badge' => 'bg-greenx/15 text-purple-400 border-greenx/30'],
    'violet'  => ['bg' => 'bg-violet-500/10',  'border' => 'border-violet-500/20',  'text' => 'text-violet-400',  'badge' => 'bg-violet-500/15 text-violet-400 border-violet-500/30'],
    'amber'   => ['bg' => 'bg-amber-500/10',   'border' => 'border-amber-500/20',   'text' => 'text-amber-400',   'badge' => 'bg-amber-500/15 text-amber-400 border-amber-500/30'],
    'cyan'    => ['bg' => 'bg-cyan-500/10',    'border' => 'border-cyan-500/20',    'text' => 'text-cyan-400',    'badge' => 'bg-cyan-500/15 text-cyan-400 border-cyan-500/30'],
    'rose'    => ['bg' => 'bg-rose-500/10',    'border' => 'border-rose-500/20',    'text' => 'text-rose-400',    'badge' => 'bg-rose-500/15 text-rose-400 border-rose-500/30'],
];
?>

<div class="space-y-8">

  <!-- Hero -->
  <div class="relative overflow-hidden rounded-3xl border border-blackx3 bg-gradient-to-br from-blackx2 via-blackx to-blackx2 p-8 md:p-12">
    <div class="absolute top-0 right-0 w-96 h-96 bg-greenx/5 rounded-full blur-3xl -translate-y-1/2 translate-x-1/3"></div>
    <div class="relative">
      <div class="inline-flex items-center gap-2 rounded-full border border-greenx/30 bg-greenx/10 px-4 py-1.5 text-sm text-greenx mb-4">
        <i data-lucide="sparkles" class="w-4 h-4"></i>
        <span>Documentação Completa</span>
      </div>
      <h2 class="text-3xl md:text-4xl font-bold mb-3">MercadoAdmin</h2>
      <p class="text-zinc-400 text-lg max-w-2xl">Marketplace digital com pagamento PIX, carteira integrada, sistema de escrow e moderação de transações. Todas as funcionalidades documentadas abaixo.</p>

      <div class="mt-6 flex flex-wrap gap-3">
        <?php foreach ($sections as $s):
          $c = $colorMap[$s['color']]; ?>
          <a href="#<?= $s['id'] ?>" class="inline-flex items-center gap-2 rounded-xl border <?= $c['badge'] ?> px-3 py-1.5 text-sm transition hover:scale-105">
            <i data-lucide="<?= $s['icon'] ?>" class="w-3.5 h-3.5"></i>
            <?= $s['title'] ?>
            <span class="ml-1 text-white/40 text-xs"><?= count($s['items']) ?></span>
          </a>
        <?php endforeach; ?>
      </div>

      <div class="mt-6 grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="rounded-2xl border border-blackx3 bg-blackx/50 p-4 text-center">
          <p class="text-2xl font-bold text-greenx"><?= array_sum(array_map(fn($s) => count($s['items']), $sections)) ?></p>
          <p class="text-xs text-zinc-500 mt-1">Funcionalidades</p>
        </div>
        <div class="rounded-2xl border border-blackx3 bg-blackx/50 p-4 text-center">
          <p class="text-2xl font-bold text-greenx"><?= count($sections) ?></p>
          <p class="text-xs text-zinc-500 mt-1">Módulos</p>
        </div>
        <div class="rounded-2xl border border-blackx3 bg-blackx/50 p-4 text-center">
          <p class="text-2xl font-bold text-greenx">3</p>
          <p class="text-xs text-zinc-500 mt-1">Roles</p>
        </div>
        <div class="rounded-2xl border border-blackx3 bg-blackx/50 p-4 text-center">
          <p class="text-2xl font-bold text-greenx">100%</p>
          <p class="text-xs text-zinc-500 mt-1">Clean URLs</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Sections -->
  <?php foreach ($sections as $s):
    $c = $colorMap[$s['color']]; ?>
  <section id="<?= $s['id'] ?>" class="scroll-mt-24">
    <div class="rounded-3xl border border-blackx3 bg-blackx2 overflow-hidden">
      <!-- Section header -->
      <div class="p-6 md:p-8 border-b border-blackx3">
        <div class="flex items-start gap-4">
          <div class="w-12 h-12 rounded-2xl <?= $c['bg'] ?> border <?= $c['border'] ?> flex items-center justify-center flex-shrink-0">
            <i data-lucide="<?= $s['icon'] ?>" class="w-6 h-6 <?= $c['text'] ?>"></i>
          </div>
          <div>
            <h3 class="text-xl md:text-2xl font-bold"><?= $s['title'] ?></h3>
            <p class="text-zinc-400 mt-1"><?= $s['desc'] ?></p>
          </div>
          <span class="ml-auto flex-shrink-0 rounded-full border <?= $c['badge'] ?> px-3 py-1 text-xs font-semibold"><?= count($s['items']) ?> itens</span>
        </div>
      </div>

      <!-- Feature grid -->
      <div class="p-4 md:p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <?php foreach ($s['items'] as $i): ?>
          <div class="group rounded-2xl border border-blackx3 bg-blackx/40 hover:bg-blackx/70 hover:border-blackx3/80 p-4 transition-all duration-200">
            <div class="flex items-start gap-3">
              <div class="w-9 h-9 rounded-xl <?= $c['bg'] ?> border <?= $c['border'] ?> flex items-center justify-center flex-shrink-0 mt-0.5 group-hover:scale-110 transition-transform">
                <i data-lucide="<?= $i['icon'] ?>" class="w-4 h-4 <?= $c['text'] ?>"></i>
              </div>
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2 mb-1">
                  <h4 class="font-semibold text-sm"><?= $i['title'] ?></h4>
                  <?php if ($i['url']): ?>
                  <code class="hidden lg:inline-block text-[10px] px-1.5 py-0.5 rounded bg-blackx3/50 text-zinc-500 font-mono"><?= htmlspecialchars($i['url']) ?></code>
                  <?php endif; ?>
                </div>
                <p class="text-xs text-zinc-400 leading-relaxed"><?= $i['desc'] ?></p>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>
  <?php endforeach; ?>

  <!-- URL Structure Reference -->
  <section class="rounded-3xl border border-blackx3 bg-blackx2 overflow-hidden">
    <div class="p-6 md:p-8 border-b border-blackx3">
      <div class="flex items-center gap-4">
        <div class="w-12 h-12 rounded-2xl bg-greenx/10 border border-greenx/20 flex items-center justify-center flex-shrink-0">
          <i data-lucide="globe" class="w-6 h-6 text-greenx"></i>
        </div>
        <div>
          <h3 class="text-xl md:text-2xl font-bold">Mapa de URLs</h3>
          <p class="text-zinc-400 mt-1">Todas as rotas do sistema — 100% clean, sem <code>.php</code>.</p>
        </div>
      </div>
    </div>
    <div class="p-4 md:p-6">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-zinc-500 border-b border-blackx3">
              <th class="text-left py-3 px-3 font-semibold">Rota</th>
              <th class="text-left py-3 px-3 font-semibold">Descrição</th>
              <th class="text-left py-3 px-3 font-semibold">Role</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-blackx3/50">
            <?php
            $routes = [
                ['/', 'Página inicial', 'Público'],
                ['/categorias', 'Catálogo de produtos', 'Público'],
                ['/p/{slug}', 'Página de produto (slug)', 'Público'],
                ['/c/{slug}', 'Categoria por slug', 'Público'],
                ['/loja/{slug}', 'Loja do vendedor', 'Público'],
                ['/carrinho', 'Carrinho de compras', 'Público'],
                ['/como_funciona', 'Página Como Funciona', 'Público'],
                ['/central_ajuda', 'Central de Ajuda', 'Público'],
                ['/faq', 'Perguntas Frequentes', 'Público'],
                ['/termos', 'Termos de Uso', 'Público'],
                ['/privacidade', 'Política de Privacidade', 'Público'],
                ['/reembolso', 'Política de Reembolso', 'Público'],
                ['/checkout', 'Finalizar pedido', 'Usuário'],
                ['/checkout_pix', 'Pagamento PIX', 'Usuário'],
                ['/login', 'Login unificado (e-mail + Google)', 'Público'],
                ['/register', 'Registro (e-mail + Google)', 'Público'],
                ['/google_redirect', 'Redireciona para Google OAuth', 'Público'],
                ['/google_callback', 'Callback do Google OAuth', 'Público'],
                ['/blog', 'Blog — listagem de posts', 'Público'],
                ['/blog/{slug}', 'Detalhe do post', 'Público'],
                ['/dashboard', 'Painel do comprador', 'Usuário'],
                ['/meus_pedidos', 'Meus pedidos', 'Usuário'],
                ['/pedido_detalhes', 'Detalhe do pedido', 'Usuário'],
                ['/wallet', 'Carteira', 'Usuário'],
                ['/saques', 'Saques do usuário', 'Usuário'],
                ['/depositos', 'Depósitos do usuário', 'Usuário'],
                ['/chat', 'Chat comprador', 'Usuário'],
                ['/minha_conta', 'Configurações conta (Google-aware)', 'Usuário'],
                ['/favoritos', 'Meus favoritos / wishlist', 'Usuário'],
                ['/tickets_dashboard', 'Meus tickets de suporte', 'Usuário'],
                ['/tickets_novo', 'Criar novo ticket', 'Usuário'],
                ['/ticket_detalhe', 'Detalhe/responder ticket', 'Usuário'],
                ['/denuncias', 'Minhas denúncias', 'Usuário'],
                ['/denunciar', 'Formulário de denúncia de produto', 'Usuário'],
                ['/afiliados', 'Painel de afiliados (comprador)', 'Usuário'],
                ['/vendedor/dashboard', 'Painel vendedor', 'Vendedor'],
                ['/vendedor/produtos', 'Gestão de produtos', 'Vendedor'],
                ['/vendedor/produtos_form', 'Form produto (entrega automática)', 'Vendedor'],
                ['/vendedor/vendas_aprovadas', 'Vendas aprovadas', 'Vendedor'],
                ['/vendedor/vendas_analise', 'Vendas em análise', 'Vendedor'],
                ['/vendedor/wallet', 'Carteira vendedor', 'Vendedor'],
                ['/vendedor/saques', 'Saques vendedor', 'Vendedor'],
                ['/vendedor/depositos', 'Depósitos vendedor', 'Vendedor'],
                ['/vendedor/chat', 'Chat vendedor', 'Vendedor'],
                ['/vendedor/aprovacao', 'Formulário de aprovação', 'Vendedor'],
                ['/vendedor/minha_conta', 'Conta vendedor (Google-aware)', 'Vendedor'],
                ['/vendedor/perguntas', 'Perguntas Q&A dos produtos', 'Vendedor'],
                ['/vendedor/tickets', 'Tickets do vendedor', 'Vendedor'],
                ['/vendedor/denuncias', 'Denúncias do vendedor', 'Vendedor'],
                ['/vendedor/afiliados', 'Afiliados do vendedor', 'Vendedor'],
                ['/admin/dashboard', 'Painel admin', 'Admin'],
                ['/admin/vendas', 'Moderação de vendas', 'Admin'],
                ['/admin/pedidos', 'Gestão de pedidos', 'Admin'],
                ['/admin/usuarios', 'Gestão de usuários', 'Admin'],
                ['/admin/vendedores', 'Gestão de vendedores', 'Admin'],
                ['/admin/admins', 'Gestão de admins', 'Admin'],
                ['/admin/categorias', 'Gestão de categorias', 'Admin'],
                ['/admin/produtos', 'Gestão de produtos', 'Admin'],
                ['/admin/solicitacoes_vendedor', 'Solicitações de vendedor', 'Admin'],
                ['/admin/depositos', 'Gestão de depósitos', 'Admin'],
                ['/admin/saques', 'Gestão de saques', 'Admin'],
                ['/admin/wallet_admin', 'Saldo admin', 'Admin'],
                ['/admin/wallet_config', 'Config escrow', 'Admin'],
                ['/admin/chat', 'Monitor de chat', 'Admin'],
                ['/admin/blog', 'Gestão do blog', 'Admin'],
                ['/admin/temas', 'Temas da loja', 'Admin'],
                ['/admin/tickets', 'Gestão de tickets de suporte', 'Admin'],
                ['/admin/denuncias', 'Moderação de denúncias', 'Admin'],
                ['/admin/favoritos', 'Analytics de favoritos', 'Admin'],
                ['/admin/google_oauth', 'Config Google OAuth', 'Admin'],
                ['/admin/minha_conta', 'Conta admin (Google-aware)', 'Admin'],
                ['/admin/documentacao', 'Esta página', 'Admin'],
                ['/api/notifications', 'API Notificações (polling/read)', 'Usuário'],
                ['/api/favorites', 'API Favoritos (toggle/list)', 'Usuário'],
                ['/api/questions', 'API Perguntas Q&A', 'Usuário'],
                ['/api/wallet_topup_status', 'API Status recarga PIX', 'Usuário'],
                ['/api/check_slug', 'API Verificar slug disponível', 'Público'],
            ];
            $roleBadge = [
                'Público'  => 'bg-zinc-500/15 text-zinc-400 border-zinc-500/30',
                'Usuário'  => 'bg-greenx/15 text-purple-400 border-greenx/30',
                'Vendedor' => 'bg-violet-500/15 text-violet-400 border-violet-500/30',
                'Admin'    => 'bg-amber-500/15 text-amber-400 border-amber-500/30',
            ];
            foreach ($routes as [$path, $desc, $role]):
            ?>
            <tr class="hover:bg-blackx/40 transition">
              <td class="py-2.5 px-3"><code class="text-xs font-mono text-greenx bg-greenx/10 rounded px-1.5 py-0.5"><?= htmlspecialchars($path) ?></code></td>
              <td class="py-2.5 px-3 text-zinc-300"><?= htmlspecialchars($desc) ?></td>
              <td class="py-2.5 px-3"><span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-semibold <?= $roleBadge[$role] ?? '' ?>"><?= $role ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>

</div>

<?php
include __DIR__ . '/../../views/partials/admin_layout_end.php';
include __DIR__ . '/../../views/partials/footer.php';
?>
