<?php
declare(strict_types=1);
/**
 * Blog System — CRUD + role-based visibility
 * Uses platform_settings for visibility toggles.
 */

/* ── SETTINGS HELPERS ─────────────────────────────────────────────────── */

function blogSettingGet($conn, string $key, string $default = '1'): string
{
    $fullKey = 'blog.' . $key;
    $st = $conn->prepare("SELECT setting_value FROM platform_settings WHERE setting_key = ? LIMIT 1");
    if (!$st) return $default;
    $st->bind_param('s', $fullKey);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ? (string)$row['setting_value'] : $default;
}

function blogSettingSet($conn, string $key, string $value): void
{
    $fullKey = 'blog.' . $key;
    $st = $conn->prepare("INSERT INTO platform_settings (setting_key, setting_value) VALUES (?, ?)
                          ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value");
    if ($st) {
        $st->bind_param('ss', $fullKey, $value);
        $st->execute();
        $st->close();
    }
}

function blogIsEnabled($conn): bool
{
    return blogSettingGet($conn, 'enabled', '1') === '1';
}

function blogIsVisibleForRole($conn, string $role): bool
{
    if (!blogIsEnabled($conn)) return false;
    return blogSettingGet($conn, 'visible_' . $role, '1') === '1';
}

function blogGetAllSettings($conn): array
{
    return [
        'enabled'          => blogSettingGet($conn, 'enabled', '1'),
        'visible_usuario'  => blogSettingGet($conn, 'visible_usuario', '1'),
        'visible_vendedor' => blogSettingGet($conn, 'visible_vendedor', '1'),
        'visible_admin'    => blogSettingGet($conn, 'visible_admin', '1'),
        'visible_public'   => blogSettingGet($conn, 'visible_public', '1'),
    ];
}

function blogSaveSettings($conn, array $settings): void
{
    $keys = ['enabled', 'visible_usuario', 'visible_vendedor', 'visible_admin', 'visible_public'];
    foreach ($keys as $k) {
        if (isset($settings[$k])) {
            blogSettingSet($conn, $k, $settings[$k] === '1' ? '1' : '0');
        }
    }
}

/* ── AUTO-CREATE TABLE ────────────────────────────────────────────────── */

/**
 * Ensures the blog_posts table exists. Creates it if missing.
 * Returns true on success, false on failure.
 */
function blogEnsureTable($conn): bool
{
    try {
        $sqlPath = dirname(__DIR__) . '/sql/blog.sql';
        if (!is_file($sqlPath)) return false;
        $sql = file_get_contents($sqlPath);
        if (!$sql) return false;
        // Execute each statement separately (CREATE TABLE + indexes)
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $stmt) {
            if ($stmt !== '') {
                $conn->query($stmt);
            }
        }
        // Ensure categoria column exists (migration for existing tables)
        try {
            $conn->query("ALTER TABLE blog_posts ADD COLUMN IF NOT EXISTS categoria VARCHAR(100)");
        } catch (\Throwable $e) {}
        return true;
    } catch (\Throwable $e) {
        return false;
    }
}

/* ── BLOG CATEGORIES HELPER ───────────────────────────────────────────── */

function blogGetCategories($conn): array
{
    try {
        $st = $conn->prepare("SELECT DISTINCT categoria FROM blog_posts WHERE categoria IS NOT NULL AND categoria != '' ORDER BY categoria");
        $st->execute();
        $rows = [];
        $res = $st->get_result();
        while ($r = $res->fetch_assoc()) $rows[] = (string)$r['categoria'];
        $st->close();
        return $rows;
    } catch (\Throwable $e) {
        return [];
    }
}

/* ── SLUG HELPER ──────────────────────────────────────────────────────── */

function blogGenerateSlug(string $title): string
{
    $slug = mb_strtolower($title);
    $slug = preg_replace('/[àáâãäå]/u', 'a', $slug);
    $slug = preg_replace('/[èéêë]/u', 'e', $slug);
    $slug = preg_replace('/[ìíîï]/u', 'i', $slug);
    $slug = preg_replace('/[òóôõö]/u', 'o', $slug);
    $slug = preg_replace('/[ùúûü]/u', 'u', $slug);
    $slug = preg_replace('/[ç]/u', 'c', $slug);
    $slug = preg_replace('/[ñ]/u', 'n', $slug);
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    return trim($slug, '-');
}

/* ── CREATE ───────────────────────────────────────────────────────────── */

function blogCreate($conn, int $authorId, string $titulo, string $conteudo, string $resumo = '', string $imagem = '', string $status = 'rascunho', string $categoria = ''): int|false
{
    $slug = blogGenerateSlug($titulo);
    // Ensure slug is unique
    $st = $conn->prepare("SELECT id FROM blog_posts WHERE slug = ? LIMIT 1");
    $st->bind_param('s', $slug);
    $st->execute();
    if ($st->get_result()->fetch_assoc()) {
        $slug .= '-' . time();
    }
    $st->close();

    if (!in_array($status, ['rascunho', 'publicado', 'arquivado'], true)) $status = 'rascunho';

    $st = $conn->prepare(
        "INSERT INTO blog_posts (author_id, titulo, slug, resumo, conteudo, imagem, categoria, status) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $st->bind_param('isssssss', $authorId, $titulo, $slug, $resumo, $conteudo, $imagem, $categoria, $status);
    $st->execute();
    $conn->refreshInsertId();
    $id = $conn->insert_id;
    $st->close();
    return $id ?: false;
}

/* ── UPDATE ───────────────────────────────────────────────────────────── */

function blogUpdate($conn, int $postId, string $titulo, string $conteudo, string $resumo = '', string $imagem = '', string $status = 'rascunho', string $categoria = '', string $slug = ''): bool
{
    if (!in_array($status, ['rascunho', 'publicado', 'arquivado'], true)) $status = 'rascunho';

    if ($slug !== '') {
        $st = $conn->prepare(
            "UPDATE blog_posts SET titulo = ?, slug = ?, conteudo = ?, resumo = ?, imagem = ?, categoria = ?, status = ?, atualizado_em = CURRENT_TIMESTAMP 
             WHERE id = ?"
        );
        $st->bind_param('sssssssi', $titulo, $slug, $conteudo, $resumo, $imagem, $categoria, $status, $postId);
    } else {
        $st = $conn->prepare(
            "UPDATE blog_posts SET titulo = ?, conteudo = ?, resumo = ?, imagem = ?, categoria = ?, status = ?, atualizado_em = CURRENT_TIMESTAMP 
             WHERE id = ?"
        );
        $st->bind_param('ssssssi', $titulo, $conteudo, $resumo, $imagem, $categoria, $status, $postId);
    }
    $st->execute();
    $ok = $st->affected_rows >= 0;
    $st->close();
    return $ok;
}

/* ── DELETE ───────────────────────────────────────────────────────────── */

function blogDelete($conn, int $postId): bool
{
    $st = $conn->prepare("DELETE FROM blog_posts WHERE id = ?");
    $st->bind_param('i', $postId);
    try {
        $st->execute();
    } catch (\Throwable) {
        return false;
    }
    $ok = $st->affected_rows > 0;
    $st->close();
    return $ok;
}

/* ── GET BY ID / SLUG ─────────────────────────────────────────────────── */

function blogGetById($conn, int $postId): ?array
{
    $st = $conn->prepare(
        "SELECT b.*, u.nome AS author_nome FROM blog_posts b JOIN users u ON u.id = b.author_id WHERE b.id = ? LIMIT 1"
    );
    $st->bind_param('i', $postId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ?: null;
}

function blogGetBySlug($conn, string $slug): ?array
{
    $st = $conn->prepare(
        "SELECT b.*, u.nome AS author_nome FROM blog_posts b JOIN users u ON u.id = b.author_id WHERE b.slug = ? AND b.status = 'publicado' LIMIT 1"
    );
    $st->bind_param('s', $slug);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ?: null;
}

/* ── LIST (with optional status filter) ───────────────────────────────── */

function blogList($conn, string $status = 'publicado', int $limit = 20, int $offset = 0): array
{
    if ($status === 'all') {
        $st = $conn->prepare(
            "SELECT b.*, u.nome AS author_nome FROM blog_posts b JOIN users u ON u.id = b.author_id
             ORDER BY b.criado_em DESC LIMIT ? OFFSET ?"
        );
        $st->bind_param('ii', $limit, $offset);
    } else {
        $st = $conn->prepare(
            "SELECT b.*, u.nome AS author_nome FROM blog_posts b JOIN users u ON u.id = b.author_id
             WHERE b.status = ? ORDER BY b.criado_em DESC LIMIT ? OFFSET ?"
        );
        $st->bind_param('sii', $status, $limit, $offset);
    }
    $st->execute();
    $rows = [];
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) $rows[] = $row;
    $st->close();
    return $rows;
}

function blogCount($conn, string $status = 'publicado'): int
{
    if ($status === 'all') {
        $st = $conn->prepare("SELECT COUNT(*) AS total FROM blog_posts");
    } else {
        $st = $conn->prepare("SELECT COUNT(*) AS total FROM blog_posts WHERE status = ?");
        $st->bind_param('s', $status);
    }
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return (int)($row['total'] ?? 0);
}

/* ── INCREMENT VIEW COUNT ─────────────────────────────────────────────── */

function blogIncrementViews($conn, int $postId): void
{
    $st = $conn->prepare("UPDATE blog_posts SET visualizacoes = visualizacoes + 1 WHERE id = ?");
    $st->bind_param('i', $postId);
    $st->execute();
    $st->close();
}

/* ── LATEST (for homepage widget) ─────────────────────────────────────── */

function blogLatest($conn, int $limit = 3): array
{
    return blogList($conn, 'publicado', $limit);
}

/* ── LIST BY AUTHOR ───────────────────────────────────────────────────── */

function blogListByAuthor($conn, int $authorId, int $limit = 20, int $offset = 0): array
{
    $st = $conn->prepare(
        "SELECT b.*, u.nome AS author_nome FROM blog_posts b JOIN users u ON u.id = b.author_id
         WHERE b.author_id = ? AND b.status = 'publicado' ORDER BY b.criado_em DESC LIMIT ? OFFSET ?"
    );
    $st->bind_param('iii', $authorId, $limit, $offset);
    $st->execute();
    $rows = [];
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) $rows[] = $row;
    $st->close();
    return $rows;
}

function blogCountByAuthor($conn, int $authorId): int
{
    $st = $conn->prepare("SELECT COUNT(*) AS total FROM blog_posts WHERE author_id = ? AND status = 'publicado'");
    $st->bind_param('i', $authorId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return (int)($row['total'] ?? 0);
}

/* ── FILTERED LIST (search + category) ─────────────────────────────────── */

function blogListFiltered($conn, string $status = 'publicado', int $limit = 20, int $offset = 0, string $search = '', string $categoria = ''): array
{
    $where  = "b.status = ?";
    $types  = 's';
    $params = [$status];

    if ($search !== '') {
        $where .= " AND (b.titulo ILIKE ? OR b.resumo ILIKE ?)";
        $types .= 'ss';
        $like  = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
    }
    if ($categoria !== '') {
        $where .= " AND b.categoria = ?";
        $types .= 's';
        $params[] = $categoria;
    }

    $sql = "SELECT b.*, u.nome AS author_nome FROM blog_posts b JOIN users u ON u.id = b.author_id WHERE $where ORDER BY b.criado_em DESC LIMIT ? OFFSET ?";
    $types .= 'ii';
    $params[] = $limit;
    $params[] = $offset;

    $st = $conn->prepare($sql);
    $st->bind_param($types, ...$params);
    $st->execute();
    $rows = [];
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) $rows[] = $row;
    $st->close();
    return $rows;
}

function blogCountFiltered($conn, string $status = 'publicado', string $search = '', string $categoria = ''): int
{
    $where  = "status = ?";
    $types  = 's';
    $params = [$status];

    if ($search !== '') {
        $where .= " AND (titulo ILIKE ? OR resumo ILIKE ?)";
        $types .= 'ss';
        $like  = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
    }
    if ($categoria !== '') {
        $where .= " AND categoria = ?";
        $types .= 's';
        $params[] = $categoria;
    }

    $st = $conn->prepare("SELECT COUNT(*) AS total FROM blog_posts WHERE $where");
    $st->bind_param($types, ...$params);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return (int)($row['total'] ?? 0);
}

/* ── GET AUTHOR INFO ──────────────────────────────────────────────────── */

function blogGetAuthor($conn, int $authorId): ?array
{
    $st = $conn->prepare("SELECT id, nome, email, role, criado_em FROM users WHERE id = ? LIMIT 1");
    $st->bind_param('i', $authorId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ?: null;
}
