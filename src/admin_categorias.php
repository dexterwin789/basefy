<?php
// filepath: c:\xampp\htdocs\mercado_admin\src\admin_categorias.php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function listarCategorias($conn, array $filtros = [], int $pagina = 1, int $porPagina = 15): array
{
    require_once __DIR__ . '/storefront.php';
    _sfEnsureCategorySlugColumn($conn);

    $pagina = max(1, $pagina);
    $offset = ($pagina - 1) * $porPagina;

    $q = '%' . trim((string)($filtros['q'] ?? '')) . '%';
    $tipo = (string)($filtros['tipo'] ?? '');
    $ativo = (string)($filtros['ativo'] ?? '');

    $where = ["nome LIKE ?"];
    $types = "s";
    $params = [$q];

    if (in_array($tipo, ['produto', 'servico'], true)) {
        $where[] = "tipo = ?";
        $types .= "s";
        $params[] = $tipo;
    } else {
        $where[] = "tipo != 'blog'";
    }
    if (in_array($ativo, ['0', '1'], true)) {
        $where[] = "ativo = ?";
        $types .= "i";
        $params[] = $ativo === '1';
    }

    $whereSql = implode(' AND ', $where);

    $sqlCount = "SELECT COUNT(*) total FROM categories WHERE $whereSql";
    $stmtCount = $conn->prepare($sqlCount);
    $stmtCount->bind_param($types, ...$params);
    $stmtCount->execute();
    $total = (int)($stmtCount->get_result()->fetch_assoc()['total'] ?? 0);

    $sqlList = "SELECT id, nome, tipo, ativo, criado_em, imagem, COALESCE(destaque, FALSE) AS destaque
                FROM categories
                WHERE $whereSql
                ORDER BY id DESC
                LIMIT ?, ?";
    $typesList = $types . "ii";
    $paramsList = [...$params, $offset, $porPagina];

    $stmtList = $conn->prepare($sqlList);
    $stmtList->bind_param($typesList, ...$paramsList);
    $stmtList->execute();
    $itens = $stmtList->get_result()->fetch_all(MYSQLI_ASSOC);

    return [
        'itens' => $itens,
        'total' => $total,
        'pagina' => $pagina,
        'por_pagina' => $porPagina,
        'total_paginas' => max(1, (int)ceil($total / $porPagina)),
    ];
}

function obterCategoriaPorId($conn, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }

    require_once __DIR__ . '/storefront.php';
    _sfEnsureCategorySlugColumn($conn);
    $stmt = $conn->prepare("SELECT id, nome, tipo, ativo, criado_em, slug, imagem, COALESCE(destaque, FALSE) AS destaque FROM categories WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
}

function salvarCategoria($conn, int $id, string $nome, string $tipo, string $customSlug = '', ?string $imagem = null, bool $destaque = false): array
{
    $nome = trim($nome);
    if ($nome === '' || !in_array($tipo, ['produto', 'servico'], true)) {
        return [false, 'Dados inválidos da categoria.'];
    }

    // Generate slug — use custom slug if provided, otherwise from name
    require_once __DIR__ . '/storefront.php';
    _sfEnsureCategorySlugColumn($conn);
    $slugBase = trim($customSlug) !== '' ? $customSlug : $nome;
    $slug = sfCreateUniqueCategorySlug($conn, $slugBase, $id);
    $destaqueInt = $destaque ? 1 : 0;

    if ($id > 0) {
        if ($imagem !== null) {
            $stmt = $conn->prepare("UPDATE categories SET nome = ?, tipo = ?, slug = ?, imagem = ?, destaque = ? WHERE id = ?");
            $stmt->bind_param('ssssii', $nome, $tipo, $slug, $imagem, $destaqueInt, $id);
        } else {
            $stmt = $conn->prepare("UPDATE categories SET nome = ?, tipo = ?, slug = ?, destaque = ? WHERE id = ?");
            $stmt->bind_param('sssii', $nome, $tipo, $slug, $destaqueInt, $id);
        }
        $stmt->execute();
        return [true, 'Categoria atualizada.'];
    }

    $stmt = $conn->prepare("INSERT INTO categories (nome, tipo, ativo, slug, imagem, destaque) VALUES (?, ?, TRUE, ?, ?, ?)");
    $imgVal = $imagem ?? '';
    $stmt->bind_param('ssssi', $nome, $tipo, $slug, $imgVal, $destaqueInt);
    $stmt->execute();
    return [true, 'Categoria criada.'];
}

function alterarAtivoCategoria($conn, int $id, int $ativo): array
{
    if ($id <= 0 || !in_array($ativo, [0, 1], true)) return [false, 'Parâmetros inválidos.'];
    $boolAtivo = $ativo === 1;
    $stmt = $conn->prepare("UPDATE categories SET ativo = ? WHERE id = ?");
    $stmt->bind_param('ii', $boolAtivo, $id);
    $stmt->execute();
    if ($stmt->affected_rows < 1) return [false, 'Categoria não encontrada.'];
    return [true, 'Status atualizado.'];
}

function excluirCategoria($conn, int $id): array
{
    if ($id <= 0) return [false, 'ID inválido.'];

    $check = $conn->prepare("SELECT COUNT(*) total FROM products WHERE categoria_id = ?");
    $check->bind_param('i', $id);
    $check->execute();
    $emUso = (int)($check->get_result()->fetch_assoc()['total'] ?? 0);

    if ($emUso > 0) return [false, 'Categoria em uso por produtos.'];

    $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->bind_param('i', $id);
    try {
        $stmt->execute();
    } catch (\Throwable $e) {
        $code = (string)$e->getCode();
        $msg = mb_strtolower($e->getMessage());
        if ($code === '23503' || str_contains($msg, 'foreign key') || str_contains($msg, 'violates foreign key constraint')) {
            return [false, 'Não é possível excluir esta categoria porque ela está vinculada a outros registros.'];
        }
        return [false, 'Erro ao excluir categoria.'];
    }

    if ($stmt->affected_rows < 1) return [false, 'Categoria não encontrada.'];
    return [true, 'Categoria excluída.'];
}