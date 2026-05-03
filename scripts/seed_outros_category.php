<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/storefront.php';

$conn = (new Database())->connect();

_sfEnsureCategorySlugColumn($conn);
_sfBackfillCategorySlugs($conn);

$name = 'Outros';
$slug = 'outros';
$type = 'produto';
$image = 'categories/outros-neon.svg';

$stmt = $conn->prepare("SELECT id FROM categories WHERE slug = ? OR LOWER(nome) = LOWER(?) ORDER BY id ASC LIMIT 1");
if (!$stmt) {
    fwrite(STDERR, "Could not prepare category lookup.\n");
    exit(1);
}
$stmt->bind_param('ss', $slug, $name);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($row) {
    $id = (int)$row['id'];
    $safeSlug = sfCreateUniqueCategorySlug($conn, $slug, $id);
    $update = $conn->prepare("UPDATE categories SET nome = ?, tipo = ?, ativo = TRUE, slug = ?, imagem = ? WHERE id = ?");
    if (!$update) {
        fwrite(STDERR, "Could not prepare category update.\n");
        exit(1);
    }
    $update->bind_param('ssssi', $name, $type, $safeSlug, $image, $id);
    $update->execute();
    $update->close();
    echo "Outros category updated: #{$id}\n";
    exit(0);
}

$insert = $conn->prepare("INSERT INTO categories (nome, tipo, ativo, slug, imagem, destaque) VALUES (?, ?, TRUE, ?, ?, FALSE)");
if (!$insert) {
    fwrite(STDERR, "Could not prepare category insert.\n");
    exit(1);
}
$insert->bind_param('ssss', $name, $type, $slug, $image);
$insert->execute();
$insert->close();

$id = (int)($conn->insert_id ?? 0);
echo $id > 0 ? "Outros category created: #{$id}\n" : "Outros category created.\n";