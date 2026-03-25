<?php
declare(strict_types=1);
/**
 * Product Reviews — CRUD + aggregate helpers
 */

/* ── AUTO-CREATE TABLE ────────────────────────────────────────────────── */

function reviewEnsureTable($conn): void
{
    static $done = false;
    if ($done) return;

    $exists = false;
    try {
        $result = $conn->query("SELECT 1 FROM product_reviews LIMIT 1");
        $exists = ($result !== false);
    } catch (\Throwable $e) {
        $exists = false;
    }

    if ($exists) {
        $done = true;
        return;
    }

    // Try creating via SQL file (with FK constraints)
    $sqlFile = __DIR__ . '/../sql/reviews.sql';
    if (file_exists($sqlFile)) {
        $sql = file_get_contents($sqlFile);
        foreach (explode(';', $sql) as $stmt) {
            $stmt = trim($stmt);
            if ($stmt !== '') {
                try { $conn->query($stmt); } catch (\Throwable $ee) {
                    error_log("[reviews] SQL statement failed: " . $ee->getMessage());
                }
            }
        }
    }

    // Verify table was created
    try {
        $result = $conn->query("SELECT 1 FROM product_reviews LIMIT 1");
        if ($result !== false) {
            $done = true;
            return;
        }
    } catch (\Throwable $e) {}

    // Fallback: create table without FK constraints
    error_log("[reviews] product_reviews table creation failed with FKs, trying without FKs...");
    $fallbackSql = "
        CREATE TABLE IF NOT EXISTS product_reviews (
            id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
            product_id BIGINT NOT NULL,
            user_id BIGINT NOT NULL,
            order_id BIGINT,
            rating SMALLINT NOT NULL CHECK (rating >= 1 AND rating <= 5),
            titulo VARCHAR(160),
            comentario TEXT,
            resposta_vendedor TEXT,
            respondido_em TIMESTAMP,
            status VARCHAR(20) NOT NULL DEFAULT 'ativo',
            criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ";
    $fallbackResult = $conn->query($fallbackSql);
    if ($fallbackResult === false) {
        error_log("[reviews] Fallback table creation also failed (query returned false).");
        return; // $done stays false so next request retries
    }

    // Verify the fallback actually created the table
    try {
        $verifyResult = $conn->query("SELECT 1 FROM product_reviews LIMIT 1");
        if ($verifyResult === false) {
            error_log("[reviews] Fallback CREATE TABLE ran but table still doesn't exist.");
            return; // $done stays false
        }
    } catch (\Throwable $e) {
        error_log("[reviews] Fallback verification failed: " . $e->getMessage());
        return; // $done stays false
    }

    // Create indexes
    $indexes = [
        "CREATE UNIQUE INDEX IF NOT EXISTS idx_reviews_user_product ON product_reviews(user_id, product_id)",
        "CREATE INDEX IF NOT EXISTS idx_reviews_product ON product_reviews(product_id)",
        "CREATE INDEX IF NOT EXISTS idx_reviews_status ON product_reviews(status)",
        "CREATE INDEX IF NOT EXISTS idx_reviews_rating ON product_reviews(rating)",
        "CREATE INDEX IF NOT EXISTS idx_reviews_criado ON product_reviews(criado_em DESC)",
    ];
    foreach ($indexes as $idx) {
        try { $conn->query($idx); } catch (\Throwable $e) {}
    }

    $done = true;
}

/* ── CREATE ───────────────────────────────────────────────────────────── */

function reviewCreate($conn, $userId, $productId, $rating, string $titulo = '', string $comentario = '', $orderId = null): int|false
{
    $userId = (int)$userId;
    $productId = (int)$productId;
    $rating = (int)$rating;
    $orderId = $orderId !== null ? (int)$orderId : null;
    reviewEnsureTable($conn);
    $rating = max(1, min(5, $rating));

    try {
        // Check if user already reviewed this product
        $check = $conn->prepare("SELECT id FROM product_reviews WHERE user_id = ? AND product_id = ? LIMIT 1");
        $check->bind_param('ii', $userId, $productId);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();
        $check->close();
        if ($existing) return false; // already reviewed

        $st = $conn->prepare(
            "INSERT INTO product_reviews (product_id, user_id, order_id, rating, titulo, comentario) 
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $st->bind_param('iiisss', $productId, $userId, $orderId, $rating, $titulo, $comentario);
        $st->execute();
        $conn->refreshInsertId();
        $id = $conn->insert_id;
        $st->close();
        return $id ?: false;
    } catch (\Throwable $e) {
        error_log("[reviews] reviewCreate error: " . $e->getMessage());
        return false;
    }
}

/* ── UPDATE (own review) ──────────────────────────────────────────────── */

function reviewUpdate($conn, $reviewId, $userId, $rating, string $titulo, string $comentario): bool
{
    $reviewId = (int)$reviewId;
    $userId = (int)$userId;
    $rating = (int)$rating;
    $rating = max(1, min(5, $rating));
    $st = $conn->prepare(
        "UPDATE product_reviews SET rating = ?, titulo = ?, comentario = ?, atualizado_em = CURRENT_TIMESTAMP 
         WHERE id = ? AND user_id = ?"
    );
    $st->bind_param('issii', $rating, $titulo, $comentario, $reviewId, $userId);
    $st->execute();
    $ok = $st->affected_rows > 0;
    $st->close();
    return $ok;
}

/* ── VENDOR REPLY ─────────────────────────────────────────────────────── */

function reviewVendorReply($conn, $reviewId, $vendorId, string $resposta): bool
{
    $reviewId = (int)$reviewId;
    $vendorId = (int)$vendorId;
    // Only allow vendor who owns the product
    $st = $conn->prepare(
        "UPDATE product_reviews SET resposta_vendedor = ?, respondido_em = CURRENT_TIMESTAMP, atualizado_em = CURRENT_TIMESTAMP
         WHERE id = ? AND product_id IN (SELECT id FROM products WHERE vendedor_id = ?)"
    );
    $st->bind_param('sii', $resposta, $reviewId, $vendorId);
    $st->execute();
    $ok = $st->affected_rows > 0;
    $st->close();
    return $ok;
}

/* ── DELETE / MODERATE ────────────────────────────────────────────────── */

function reviewDelete($conn, $reviewId, $userId = null): bool
{
    $reviewId = (int)$reviewId;
    $userId = $userId !== null ? (int)$userId : null;
    if ($userId !== null) {
        // User can only delete own
        $st = $conn->prepare("DELETE FROM product_reviews WHERE id = ? AND user_id = ?");
        $st->bind_param('ii', $reviewId, $userId);
    } else {
        // Admin delete
        $st = $conn->prepare("DELETE FROM product_reviews WHERE id = ?");
        $st->bind_param('i', $reviewId);
    }
    try {
        $st->execute();
    } catch (\Throwable) {
        return false;
    }
    $ok = $st->affected_rows > 0;
    $st->close();
    return $ok;
}

function reviewModerate($conn, $reviewId, string $status): bool
{
    $reviewId = (int)$reviewId;
    if (!in_array($status, ['ativo', 'oculto', 'removido'], true)) return false;
    $st = $conn->prepare("UPDATE product_reviews SET status = ?, atualizado_em = CURRENT_TIMESTAMP WHERE id = ?");
    $st->bind_param('si', $status, $reviewId);
    $st->execute();
    $ok = $st->affected_rows > 0;
    $st->close();
    return $ok;
}

/* ── LIST for a product ───────────────────────────────────────────────── */

function reviewListByProduct($conn, $productId, int $limit = 20, int $offset = 0, ?int $filterRating = null): array
{
    $productId = (int)$productId;

    $where = 'r.product_id = ? AND r.status = \'ativo\'';
    $params = [$productId];
    $types  = 'i';

    if ($filterRating !== null && $filterRating >= 1 && $filterRating <= 5) {
        $where .= ' AND r.rating = ?';
        $params[] = $filterRating;
        $types .= 'i';
    }

    // Count total for pagination
    $countSt = $conn->prepare("SELECT COUNT(*) AS total FROM product_reviews r WHERE $where");
    $countSt->bind_param($types, ...$params);
    $countSt->execute();
    $totalRow = $countSt->get_result()->fetch_assoc();
    $countSt->close();
    $total = (int)($totalRow['total'] ?? 0);

    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';

    $st = $conn->prepare(
        "SELECT r.*, u.nome AS user_nome, u.avatar AS user_avatar
         FROM product_reviews r
         JOIN users u ON u.id = r.user_id
         WHERE $where
         ORDER BY r.criado_em DESC
         LIMIT ? OFFSET ?"
    );
    $st->bind_param($types, ...$params);
    $st->execute();
    $rows = [];
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) $rows[] = $row;
    $st->close();
    return ['rows' => $rows, 'total' => $total];
}

/* ── AGGREGATE for a product ──────────────────────────────────────────── */

function reviewAggregate($conn, $productId): array
{
    $productId = (int)$productId;
    $st = $conn->prepare(
        "SELECT COUNT(*) AS total, COALESCE(AVG(rating), 0) AS avg_rating,
                SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) AS r5,
                SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) AS r4,
                SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) AS r3,
                SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) AS r2,
                SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) AS r1
         FROM product_reviews 
         WHERE product_id = ? AND status = 'ativo'"
    );
    $st->bind_param('i', $productId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return [
        'total'      => (int)($row['total'] ?? 0),
        'avg_rating' => round((float)($row['avg_rating'] ?? 0), 1),
        'breakdown'  => [
            5 => (int)($row['r5'] ?? 0),
            4 => (int)($row['r4'] ?? 0),
            3 => (int)($row['r3'] ?? 0),
            2 => (int)($row['r2'] ?? 0),
            1 => (int)($row['r1'] ?? 0),
        ],
    ];
}

/* ── AGGREGATE for a vendor (all products) ────────────────────────────── */

function reviewVendorAggregate($conn, $vendorId): array
{
    $vendorId = (int)$vendorId;
    $st = $conn->prepare(
        "SELECT COUNT(*) AS total, COALESCE(AVG(r.rating), 0) AS avg_rating
         FROM product_reviews r
         JOIN products p ON p.id = r.product_id
         WHERE p.vendedor_id = ? AND r.status = 'ativo'"
    );
    $st->bind_param('i', $vendorId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return [
        'total'      => (int)($row['total'] ?? 0),
        'avg_rating' => round((float)($row['avg_rating'] ?? 0), 1),
    ];
}

/* ── CAN USER REVIEW? (must have bought product) ─────────────────────── */

function reviewCanUserReview($conn, $userId, $productId): array
{
    $userId = (int)$userId;
    $productId = (int)$productId;
    try {
        // Ensure table exists first (inside try-catch so failures are handled)
        reviewEnsureTable($conn);

        // Check if user purchased this product (paid order)
        $st = $conn->prepare(
            "SELECT o.id AS order_id FROM orders o
             JOIN order_items oi ON oi.order_id = o.id
             WHERE o.user_id = ? AND oi.product_id = ? AND LOWER(o.status) IN ('entregue','concluido','delivered')
             LIMIT 1"
        );
        if (!$st) return ['can' => false, 'reason' => 'Erro interno ao verificar compra.'];
        $st->bind_param('ii', $userId, $productId);
        $st->execute();
        $order = $st->get_result()->fetch_assoc();
        $st->close();

        if (!$order) {
            // Check if user has a paid but not-yet-delivered order for this product
            $stPending = $conn->prepare(
                "SELECT o.id FROM orders o
                 JOIN order_items oi ON oi.order_id = o.id
                 WHERE o.user_id = ? AND oi.product_id = ? AND LOWER(o.status) IN ('pago','paid','pendente','pending','enviado','shipped','processing')
                 LIMIT 1"
            );
            if ($stPending) {
                $stPending->bind_param('ii', $userId, $productId);
                $stPending->execute();
                $pending = $stPending->get_result()->fetch_assoc();
                $stPending->close();
                if ($pending) {
                    return ['can' => false, 'reason' => 'Você só pode avaliar após o produto ser entregue.'];
                }
            }
            return ['can' => false, 'reason' => 'Você precisa comprar este produto para avaliá-lo.'];
        }

        // Check if already reviewed
        $st2 = $conn->prepare("SELECT id FROM product_reviews WHERE user_id = ? AND product_id = ? LIMIT 1");
        if (!$st2) return ['can' => false, 'reason' => 'Erro interno ao verificar avaliações.'];
        $st2->bind_param('ii', $userId, $productId);
        $st2->execute();
        $existing = $st2->get_result()->fetch_assoc();
        $st2->close();

        if ($existing) {
            return ['can' => false, 'reason' => 'Você já avaliou este produto.', 'review_id' => (int)$existing['id']];
        }

        return ['can' => true, 'order_id' => (int)$order['order_id']];
    } catch (\Throwable $e) {
        error_log("[reviews] reviewCanUserReview error: " . $e->getMessage());
        return ['can' => false, 'reason' => 'Erro ao verificar avaliação. Tente novamente mais tarde.'];
    }
}

/* ── STAR HTML HELPER ─────────────────────────────────────────────────── */

function reviewStarsHTML(float $rating, string $size = 'w-4 h-4'): string
{
    $html = '';
    $full = (int)floor($rating);
    $half = ($rating - $full) >= 0.5;
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $full) {
            $html .= '<svg class="' . $size . ' text-yellow-400 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>';
        } elseif ($i === $full + 1 && $half) {
            $html .= '<svg class="' . $size . ' text-yellow-400" viewBox="0 0 20 20"><defs><linearGradient id="half"><stop offset="50%" stop-color="currentColor"/><stop offset="50%" stop-color="transparent"/></linearGradient></defs><path fill="url(#half)" stroke="currentColor" stroke-width="0.5" d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>';
        } else {
            $html .= '<svg class="' . $size . ' text-zinc-600 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>';
        }
    }
    return $html;
}
