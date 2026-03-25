<?php
/**
 * Seed script: Inserts sample blog posts and product reviews.
 * 
 * Usage (CLI):   php scripts/seed_blog_reviews.php
 * Usage (browser): http://localhost/mercado_admin/scripts/seed_blog_reviews.php
 * 
 * Safe to run multiple times — checks for existing data before inserting.
 */
declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';

$conn = (new Database())->connect();

$out = [];
$html = php_sapi_name() !== 'cli';

function msg(string $text, array &$out, bool $html): void {
    $out[] = $text;
    if ($html) {
        echo "<p>" . htmlspecialchars($text) . "</p>\n";
    } else {
        echo $text . "\n";
    }
}

// ─── Ensure tables exist ───
try {
    $conn->query("SELECT 1 FROM blog_posts LIMIT 1");
} catch (\Throwable $e) {
    msg("Creating blog_posts table...", $out, $html);
    $sql = file_get_contents(__DIR__ . '/../sql/blog.sql');
    if ($sql) {
        foreach (explode(';', $sql) as $stmt) {
            $stmt = trim($stmt);
            if ($stmt !== '') {
                try { $conn->query($stmt); } catch (\Throwable $ee) {}
            }
        }
    }
}

try {
    $conn->query("SELECT 1 FROM product_reviews LIMIT 1");
} catch (\Throwable $e) {
    msg("Creating product_reviews table...", $out, $html);
    $sql = file_get_contents(__DIR__ . '/../sql/reviews.sql');
    if ($sql) {
        foreach (explode(';', $sql) as $stmt) {
            $stmt = trim($stmt);
            if ($stmt !== '') {
                try { $conn->query($stmt); } catch (\Throwable $ee) {}
            }
        }
    }
}

// ─── Find an admin user for blog authorship ───
$adminId = 0;
try {
    $st = $conn->prepare("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1");
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    if ($row) $adminId = (int)$row['id'];
    $st->close();
} catch (\Throwable $e) {}

if ($adminId === 0) {
    // Fallback: use any user
    try {
        $st = $conn->prepare("SELECT id FROM users ORDER BY id LIMIT 1");
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        if ($row) $adminId = (int)$row['id'];
        $st->close();
    } catch (\Throwable $e) {}
}

if ($adminId === 0) {
    msg("ERRO: Nenhum usuário encontrado no banco. Crie pelo menos um usuário primeiro.", $out, $html);
    exit(1);
}

msg("Usando author_id = $adminId para blog posts.", $out, $html);

// ─── Seed Blog Posts ───
$blogPosts = [
    [
        'titulo' => 'Como vender suas contas de jogos com segurança',
        'slug' => 'como-vender-contas-jogos-seguranca',
        'resumo' => 'Dicas essenciais para anunciar suas contas no marketplace, garantir a segurança da transação e evitar problemas com compradores.',
        'conteudo' => '<h2>Guia Completo para Vendedores</h2>
<p>Vender contas de jogos online pode ser muito lucrativo, mas exige cuidados para garantir uma transação segura. Aqui estão as dicas essenciais:</p>
<h3>1. Mantenha suas credenciais seguras</h3>
<p>Nunca compartilhe sua senha antes do pagamento ser confirmado. Use o sistema de escrow da plataforma para garantir que ambos os lados fiquem protegidos.</p>
<h3>2. Descreva o produto com detalhes</h3>
<p>Inclua informações como nível da conta, skins/itens disponíveis, servidor, e qualquer detalhe relevante. Quanto mais detalhada a descrição, menos problema você terá.</p>
<h3>3. Use o código de entrega</h3>
<p>Nosso sistema de código de entrega (estilo iFood) garante que o vendedor só recebe o pagamento quando o comprador confirma que recebeu o produto.</p>
<h3>4. Responda rápido</h3>
<p>Compradores valorizam vendedores que respondem rapidamente pelo chat. Mantenha o chat ativo para construir reputação.</p>',
        'status' => 'publicado',
    ],
    [
        'titulo' => 'Gift Cards: Guia completo de compra e venda',
        'slug' => 'gift-cards-guia-completo',
        'resumo' => 'Tudo sobre o mercado de gift cards digitais — Steam, PlayStation, Xbox, Google Play e mais. Saiba como lucrar com revendas.',
        'conteudo' => '<h2>O Mercado de Gift Cards</h2>
<p>Gift cards são um dos produtos digitais mais procurados. Eles são fáceis de entregar, têm valor fixo e alta demanda.</p>
<h3>Plataformas populares</h3>
<ul>
<li><strong>Steam</strong> — O maior marketplace de jogos para PC</li>
<li><strong>PlayStation Store</strong> — Para jogadores de PS4/PS5</li>
<li><strong>Xbox/Microsoft</strong> — Game Pass e jogos digitais</li>
<li><strong>Google Play</strong> — Apps e jogos mobile</li>
<li><strong>Apple/iTunes</strong> — Ecossistema Apple</li>
</ul>
<h3>Dicas para revenda</h3>
<p>Compre gift cards em promoção e revenda com uma margem justa. Use a plataforma para alcançar mais compradores e aproveite o sistema de pagamento via PIX.</p>',
        'status' => 'publicado',
    ],
    [
        'titulo' => 'Novidades da plataforma: PIX instantâneo e escrow',
        'slug' => 'novidades-pix-instantaneo-escrow',
        'resumo' => 'Conheça o sistema de pagamento PIX com confirmação automática e o escrow que protege compradores e vendedores.',
        'conteudo' => '<h2>PIX Instantâneo</h2>
<p>Agora todos os pagamentos via PIX são confirmados automaticamente através do nosso webhook. Assim que o pagamento é detectado, o pedido muda para o status "pago" em segundos.</p>
<h3>Como funciona o Escrow</h3>
<p>O escrow (custódia) é um sistema que mantém o dinheiro da compra em uma "conta intermediária" até que o comprador confirme o recebimento do produto. Isso protege ambos os lados:</p>
<ul>
<li><strong>Comprador:</strong> Recebe o produto antes do vendedor receber o dinheiro</li>
<li><strong>Vendedor:</strong> Tem garantia de que o dinheiro existe e será liberado após a entrega</li>
</ul>
<h3>Código de entrega estilo iFood</h3>
<p>Cada pedido recebe um código de 6 caracteres. O comprador compartilha esse código com o vendedor após verificar o produto, liberando automaticamente o pagamento.</p>',
        'status' => 'publicado',
    ],
    [
        'titulo' => 'Como funciona o programa de afiliados',
        'slug' => 'como-funciona-programa-afiliados',
        'resumo' => 'Saiba como ganhar comissões indicando produtos e vendedores. Cadastro grátis e pagamento via PIX.',
        'conteudo' => '<h2>Programa de Afiliados MercadoAdmin</h2>
<p>Nosso programa de afiliados permite que você ganhe dinheiro compartilhando links de produtos. Funciona assim:</p>
<ol>
<li><strong>Cadastre-se</strong> — Crie sua conta e ative o módulo de afiliados</li>
<li><strong>Gere seus links</strong> — Cada produto tem um link exclusivo com seu ID de afiliado</li>
<li><strong>Compartilhe</strong> — Envie para amigos, redes sociais, grupos, etc</li>
<li><strong>Ganhe comissão</strong> — Quando alguém compra pelo seu link, você ganha uma porcentagem</li>
</ol>
<p>As comissões são creditadas diretamente na sua carteira e podem ser sacadas via PIX a qualquer momento.</p>',
        'status' => 'publicado',
    ],
    [
        'titulo' => '5 dicas para comprar com segurança no marketplace',
        'slug' => '5-dicas-comprar-seguranca-marketplace',
        'resumo' => 'Antes de fazer sua primeira compra, confira essas dicas essenciais para uma experiência segura e tranquila.',
        'conteudo' => '<h2>Comprando com Segurança</h2>
<h3>1. Verifique a reputação do vendedor</h3>
<p>Confira as avaliações e o histórico de vendas antes de comprar. Vendedores com mais avaliações positivas são mais confiáveis.</p>
<h3>2. Leia a descrição completa</h3>
<p>Certifique-se de que o produto é exatamente o que você precisa. Em caso de dúvida, use o chat para perguntar ao vendedor.</p>
<h3>3. Use o chat da plataforma</h3>
<p>Todas as comunicações pelo chat ficam registradas. Isso facilita a resolução de eventuais problemas.</p>
<h3>4. Confirme a entrega com cuidado</h3>
<p>Só compartilhe o código de entrega depois de verificar que o produto está correto e funcionando.</p>
<h3>5. Avalie o vendedor</h3>
<p>Após a compra, deixe uma avaliação. Isso ajuda outros compradores e melhora a plataforma.</p>',
        'status' => 'publicado',
    ],
];

// Enable blog settings
$blogSettings = [
    'blog.enabled' => '1',
    'blog.visible_usuario' => '1',
    'blog.visible_vendedor' => '1',
    'blog.visible_admin' => '1',
    'blog.visible_public' => '1',
];

foreach ($blogSettings as $k => $v) {
    try {
        $st = $conn->prepare("INSERT INTO platform_settings (chave, valor) VALUES (?, ?) ON CONFLICT (chave) DO UPDATE SET valor = EXCLUDED.valor");
        $st->bind_param('ss', $k, $v);
        $st->execute();
        $st->close();
    } catch (\Throwable $e) {
        // Try MySQL syntax
        try {
            $st = $conn->prepare("INSERT INTO platform_settings (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
            $st->bind_param('ss', $k, $v);
            $st->execute();
            $st->close();
        } catch (\Throwable $e2) {}
    }
}
msg("Blog settings enabled.", $out, $html);

$blogInserted = 0;
foreach ($blogPosts as $i => $post) {
    // Check if slug already exists
    try {
        $st = $conn->prepare("SELECT id FROM blog_posts WHERE slug = ? LIMIT 1");
        $st->bind_param('s', $post['slug']);
        $st->execute();
        $existing = $st->get_result()->fetch_assoc();
        $st->close();
        if ($existing) {
            msg("Blog post '{$post['slug']}' already exists, skipping.", $out, $html);
            continue;
        }
    } catch (\Throwable $e) {}

    try {
        $createdAt = date('Y-m-d H:i:s', strtotime("-" . ($i * 3 + 1) . " days"));
        $st = $conn->prepare("INSERT INTO blog_posts (author_id, titulo, slug, resumo, conteudo, status, criado_em, atualizado_em) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $st->bind_param('isssssss', $adminId, $post['titulo'], $post['slug'], $post['resumo'], $post['conteudo'], $post['status'], $createdAt, $createdAt);
        $st->execute();
        $st->close();
        $blogInserted++;
        msg("Inserted blog: {$post['titulo']}", $out, $html);
    } catch (\Throwable $e) {
        msg("Error inserting blog '{$post['slug']}': " . $e->getMessage(), $out, $html);
    }
}
msg("Blog posts inserted: $blogInserted", $out, $html);

// ─── Seed Product Reviews ───
// Get products that exist
$products = [];
try {
    $st = $conn->prepare("SELECT id, nome, vendedor_id FROM products WHERE ativo = 1 ORDER BY id LIMIT 20");
    $st->execute();
    $result = $st->get_result();
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    $st->close();
} catch (\Throwable $e) {
    // Try without ativo column
    try {
        $st = $conn->prepare("SELECT id, nome, vendedor_id FROM products ORDER BY id LIMIT 20");
        $st->execute();
        $result = $st->get_result();
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
        $st->close();
    } catch (\Throwable $e2) {}
}

if (empty($products)) {
    msg("Nenhum produto encontrado para inserir reviews.", $out, $html);
} else {
    msg("Found " . count($products) . " products for reviews.", $out, $html);
}

// Get users (non-vendor) who could be reviewers
$reviewers = [];
try {
    $st = $conn->prepare("SELECT id, nome FROM users WHERE role != 'admin' ORDER BY id LIMIT 10");
    $st->execute();
    $result = $st->get_result();
    while ($row = $result->fetch_assoc()) {
        $reviewers[] = $row;
    }
    $st->close();
} catch (\Throwable $e) {
    try {
        $st = $conn->prepare("SELECT id, nome FROM users ORDER BY id LIMIT 10");
        $st->execute();
        $result = $st->get_result();
        while ($row = $result->fetch_assoc()) {
            $reviewers[] = $row;
        }
        $st->close();
    } catch (\Throwable $e2) {}
}

// Sample review templates
$reviewTemplates = [
    ['rating' => 5, 'titulo' => 'Compra perfeita!', 'comentario' => 'Recebi em menos de 5 minutos. Produto exatamente como descrito. Vendedor super atencioso!'],
    ['rating' => 5, 'titulo' => 'Muito satisfeito', 'comentario' => 'Excelente produto e entrega super rápida. O sistema de escrow me deixou bem seguro durante toda a transação.'],
    ['rating' => 4, 'titulo' => 'Bom produto', 'comentario' => 'Tudo funcionou bem, entrega rápida. Só achei que poderia ter mais detalhes na descrição.'],
    ['rating' => 5, 'titulo' => 'Recomendo!', 'comentario' => 'Já é minha terceira compra e nunca tive problema. Pagamento PIX confirmou na hora!'],
    ['rating' => 5, 'titulo' => 'Vendedor top!', 'comentario' => 'Entrega antes do prazo, produto funcionando perfeitamente. Nota 10!'],
    ['rating' => 4, 'titulo' => 'Muito bom', 'comentario' => 'Produto bom, entregue no prazo. Vendedor respondeu rápido no chat quando tive dúvida.'],
    ['rating' => 5, 'titulo' => 'Experiência incrível', 'comentario' => 'Primeira compra na plataforma e já virei cliente. O processo de pagamento e entrega é muito bem feito.'],
    ['rating' => 3, 'titulo' => 'Razoável', 'comentario' => 'O produto é ok, mas demorou um pouco mais do que eu esperava. No geral deu tudo certo.'],
    ['rating' => 5, 'titulo' => 'Perfeito!', 'comentario' => 'Código verificado na hora, tudo certo. A plataforma realmente protege o comprador.'],
    ['rating' => 4, 'titulo' => 'Boa compra', 'comentario' => 'Produto conforme anunciado. O chat com o vendedor foi rápido e ele foi bem solícito.'],
    ['rating' => 5, 'titulo' => 'Rápido demais!', 'comentario' => 'Do pagamento à entrega foram menos de 3 minutos. Impressionante!'],
    ['rating' => 5, 'titulo' => 'Segurança em primeiro', 'comentario' => 'O sistema do código de entrega é genial. Meu dinheiro ficou protegido até eu confirmar tudo. Nota máxima!'],
];

$reviewsInserted = 0;
if (!empty($products) && !empty($reviewers)) {
    $templateIndex = 0;
    foreach ($products as $prod) {
        // Assign 1-3 reviews per product
        $numReviews = min(count($reviewers), rand(1, 3));
        $usedReviewers = array_slice($reviewers, 0, $numReviews);

        foreach ($usedReviewers as $reviewer) {
            // Skip if reviewer is the product vendor
            if ((int)$reviewer['id'] === (int)($prod['vendedor_id'] ?? 0)) continue;

            // Check if review already exists
            try {
                $userId = (int)$reviewer['id'];
                $productId = (int)$prod['id'];

                $st = $conn->prepare("SELECT id FROM product_reviews WHERE user_id = ? AND product_id = ? LIMIT 1");
                $st->bind_param('ii', $userId, $productId);
                $st->execute();
                $existing = $st->get_result()->fetch_assoc();
                $st->close();
                if ($existing) continue;
            } catch (\Throwable $e) { continue; }

            $template = $reviewTemplates[$templateIndex % count($reviewTemplates)];
            $templateIndex++;

            $daysAgo = rand(1, 30);
            $createdAt = date('Y-m-d H:i:s', strtotime("-{$daysAgo} days"));

            // Also add a vendor reply on some reviews
            $vendorReply = null;
            $repliedAt = null;
            if (rand(0, 2) === 0) {
                $vendorReply = 'Obrigado pela avaliação! Que bom que gostou. Volte sempre!';
                $repliedAt = date('Y-m-d H:i:s', strtotime("-" . max(0, $daysAgo - 1) . " days"));
            }

            try {
                $st = $conn->prepare("INSERT INTO product_reviews (product_id, user_id, rating, titulo, comentario, resposta_vendedor, respondido_em, status, criado_em, atualizado_em) VALUES (?, ?, ?, ?, ?, ?, ?, 'ativo', ?, ?)");
                $st->bind_param('iiissssss', $productId, $userId, $template['rating'], $template['titulo'], $template['comentario'], $vendorReply, $repliedAt, $createdAt, $createdAt);
                $st->execute();
                $st->close();
                $reviewsInserted++;
            } catch (\Throwable $e) {
                msg("Error inserting review for product {$prod['id']}: " . $e->getMessage(), $out, $html);
            }
        }
    }
}

msg("Product reviews inserted: $reviewsInserted", $out, $html);
msg("=== Seed complete! ===", $out, $html);
