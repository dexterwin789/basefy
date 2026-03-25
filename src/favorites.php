<?php
declare(strict_types=1);
/**
 * Favorites / Wishlist — backend logic
 */

require_once __DIR__ . '/db.php';

function favoritesEnsureTable($conn): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $conn->query("
            CREATE TABLE IF NOT EXISTS user_favorites (
                id SERIAL PRIMARY KEY,
                user_id INTEGER NOT NULL,
                product_id INTEGER NOT NULL,
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(user_id, product_id)
            )
        ");
        try {
            $conn->query("CREATE INDEX IF NOT EXISTS idx_user_favorites_user ON user_favorites(user_id)");
            $conn->query("CREATE INDEX IF NOT EXISTS idx_user_favorites_product ON user_favorites(product_id)");
        } catch (\Throwable $e) {}
    } catch (\Throwable $e) {}
}

/**
 * Toggle favorite status. Returns [bool isFavorited, string message]
 */
function favoritesToggle($conn, int $userId, int $productId): array
{
    favoritesEnsureTable($conn);

    // Check if already favorited
    $stmt = $conn->prepare("SELECT id FROM user_favorites WHERE user_id = ? AND product_id = ? LIMIT 1");
    $stmt->bind_param('ii', $userId, $productId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        // Remove
        $del = $conn->prepare("DELETE FROM user_favorites WHERE id = ?");
        $id = (int)$row['id'];
        $del->bind_param('i', $id);
        $del->execute();
        $del->close();
        return [false, 'Removido dos favoritos.'];
    }

    // Add
    $ins = $conn->prepare("INSERT INTO user_favorites (user_id, product_id) VALUES (?, ?)");
    $ins->bind_param('ii', $userId, $productId);
    $ins->execute();
    $ins->close();
    return [true, 'Adicionado aos favoritos!'];
}

/**
 * Check if a product is favorited by user
 */
function favoritesCheck($conn, int $userId, int $productId): bool
{
    favoritesEnsureTable($conn);
    $stmt = $conn->prepare("SELECT 1 FROM user_favorites WHERE user_id = ? AND product_id = ? LIMIT 1");
    $stmt->bind_param('ii', $userId, $productId);
    $stmt->execute();
    $found = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $found;
}

/**
 * Check multiple product ids at once. Returns array of favorited product IDs.
 */
function favoritesCheckBulk($conn, int $userId, array $productIds): array
{
    if (!$productIds) return [];
    favoritesEnsureTable($conn);

    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $types = 'i' . str_repeat('i', count($productIds));
    $params = array_merge([$userId], $productIds);

    $stmt = $conn->prepare("SELECT product_id FROM user_favorites WHERE user_id = ? AND product_id IN ($placeholders)");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    $stmt->close();

    return array_column($rows, 'product_id');
}

/**
 * List user's favorite products with pagination
 */
function favoritesList($conn, int $userId, int $page = 1, int $pp = 12): array
{
    favoritesEnsureTable($conn);

    // Count (only favorites whose products still exist)
    $cStmt = $conn->prepare("SELECT COUNT(*) total FROM user_favorites f INNER JOIN products p ON p.id = f.product_id WHERE f.user_id = ?");
    $cStmt->bind_param('i', $userId);
    $cStmt->execute();
    $total = (int)($cStmt->get_result()->fetch_assoc()['total'] ?? 0);
    $cStmt->close();

    $totalPaginas = max(1, (int)ceil($total / $pp));
    $page = min($page, $totalPaginas);
    $offset = ($page - 1) * $pp;

    $stmt = $conn->prepare("
        SELECT f.id AS fav_id, f.criado_em AS fav_criado_em, p.*, u.nome AS vendedor_nome
        FROM user_favorites f
        LEFT JOIN products p ON p.id = f.product_id
        LEFT JOIN users u ON u.id = p.vendedor_id
        WHERE f.user_id = ?
        ORDER BY f.criado_em DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param('iii', $userId, $pp, $offset);
    $stmt->execute();
    $itens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    $stmt->close();

    return [
        'itens'         => $itens,
        'total'         => $total,
        'pagina'        => $page,
        'total_paginas' => $totalPaginas,
        'por_pagina'    => $pp,
    ];
}

/**
 * Count user's favorites
 */
function favoritesCount($conn, int $userId): int
{
    favoritesEnsureTable($conn);
    $stmt = $conn->prepare("SELECT COUNT(*) total FROM user_favorites f INNER JOIN products p ON p.id = f.product_id WHERE f.user_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $c = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();
    return $c;
}

/**
 * Admin: list all favorites with product/user info, paginated
 */
function favoritesAdminList($conn, array $filters = [], int $page = 1, int $pp = 20): array
{
    favoritesEnsureTable($conn);

    $where = '1=1';
    $params = [];
    $types = '';

    $q = trim((string)($filters['q'] ?? ''));
    if ($q !== '') {
        $where .= ' AND (p.nome ILIKE ? OR u.nome ILIKE ?)';
        $types .= 'ss';
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
    }

    // Count
    $cSql = "SELECT COUNT(*) total FROM user_favorites f LEFT JOIN products p ON p.id = f.product_id LEFT JOIN users u ON u.id = f.user_id WHERE $where";
    $cStmt = $conn->prepare($cSql);
    if ($types && $params) {
        $cStmt->bind_param($types, ...$params);
    }
    $cStmt->execute();
    $total = (int)($cStmt->get_result()->fetch_assoc()['total'] ?? 0);
    $cStmt->close();

    $totalPaginas = max(1, (int)ceil($total / $pp));
    $page = min($page, $totalPaginas);
    $offset = ($page - 1) * $pp;

    $sql = "SELECT f.id AS fav_id, f.criado_em AS fav_criado_em, f.user_id, f.product_id,
                   p.nome AS produto_nome, p.imagem AS produto_imagem, p.preco,
                   u.nome AS user_nome, u.email AS user_email
            FROM user_favorites f
            LEFT JOIN products p ON p.id = f.product_id
            LEFT JOIN users u ON u.id = f.user_id
            WHERE $where
            ORDER BY f.criado_em DESC
            LIMIT ? OFFSET ?";
    $types2 = $types . 'ii';
    $params2 = array_merge($params, [$pp, $offset]);

    $stmt = $conn->prepare($sql);
    if ($types2 && $params2) {
        $stmt->bind_param($types2, ...$params2);
    }
    $stmt->execute();
    $itens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    $stmt->close();

    return [
        'itens'         => $itens,
        'total'         => $total,
        'pagina'        => $page,
        'total_paginas' => $totalPaginas,
        'por_pagina'    => $pp,
    ];
}

/**
 * Admin: most favorited products ranking
 */
function favoritesTopProducts($conn, int $limit = 10): array
{
    favoritesEnsureTable($conn);
    $stmt = $conn->prepare("
        SELECT p.id, p.nome, p.imagem, p.preco, COUNT(f.id) AS total_favs
        FROM user_favorites f
        LEFT JOIN products p ON p.id = f.product_id
        GROUP BY p.id, p.nome, p.imagem, p.preco
        ORDER BY total_favs DESC
        LIMIT ?
    ");
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    $stmt->close();
    return $rows;
}

/**
 * Count how many users favorited a product
 */
function favoritesProductCount($conn, int $productId): int
{
    favoritesEnsureTable($conn);
    $stmt = $conn->prepare("SELECT COUNT(*) total FROM user_favorites WHERE product_id = ?");
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $c = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();
    return $c;
}
