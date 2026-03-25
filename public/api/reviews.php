<?php
declare(strict_types=1);
/**
 * API: Product Reviews — submit, update, delete, list
 * POST { action: 'submit'|'update'|'delete', product_id, rating, titulo?, comentario?, review_id? }
 * GET  ?action=list&product_id=X&page=1&filter_rating=5
 */
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/reviews.php';
require_once __DIR__ . '/../../src/media.php';

header('Content-Type: application/json; charset=UTF-8');

$conn = (new Database())->connect();

// GET requests: list action
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = (string)($_GET['action'] ?? '');
    if ($action === 'list') {
        $productId    = (int)($_GET['product_id'] ?? 0);
        $page         = max(1, (int)($_GET['page'] ?? 1));
        $filterRating = isset($_GET['filter_rating']) && $_GET['filter_rating'] !== '' ? (int)$_GET['filter_rating'] : null;
        $perPage      = 5;

        if ($productId < 1) {
            echo json_encode(['ok' => false, 'error' => 'product_id inválido.']);
            exit;
        }

        try {
            reviewEnsureTable($conn);
            $data = reviewListByProduct($conn, $productId, $perPage, ($page - 1) * $perPage, $filterRating);
            $totalPages = max(1, (int)ceil($data['total'] / $perPage));

            $reviews = [];
            foreach ($data['rows'] as $rev) {
                $avatarRaw = trim((string)($rev['user_avatar'] ?? ''));
                $avatarUrl = $avatarRaw !== '' ? mediaResolveUrl($avatarRaw) : '';
                $reviews[] = [
                    'id'                => (int)$rev['id'],
                    'user_nome'         => (string)($rev['user_nome'] ?? 'Usuário'),
                    'user_avatar_url'   => $avatarUrl,
                    'rating'            => (int)$rev['rating'],
                    'titulo'            => (string)($rev['titulo'] ?? ''),
                    'comentario'        => (string)($rev['comentario'] ?? ''),
                    'resposta_vendedor' => (string)($rev['resposta_vendedor'] ?? ''),
                    'criado_em'         => date('d/m/Y', strtotime((string)$rev['criado_em'])),
                ];
            }

            echo json_encode([
                'ok'      => true,
                'reviews' => $reviews,
                'total'   => $data['total'],
                'page'    => $page,
                'pages'   => $totalPages,
            ]);
        } catch (\Throwable $e) {
            error_log('[api/reviews] list error: ' . $e->getMessage());
            echo json_encode(['ok' => false, 'error' => 'Erro ao listar avaliações.']);
        }
        exit;
    }
    echo json_encode(['ok' => false, 'error' => 'Ação inválida.']);
    exit;
}

// POST requests: submit, update, delete
$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId === 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Faça login para avaliar.']);
    exit;
}

$input  = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = (string)($input['action'] ?? '');

try {
    switch ($action) {
        case 'submit':
            $productId = (int)($input['product_id'] ?? 0);
            $rating    = (int)($input['rating'] ?? 0);
            $titulo    = trim((string)($input['titulo'] ?? ''));
            $comentario = trim((string)($input['comentario'] ?? ''));

            if ($productId < 1 || $rating < 1 || $rating > 5) {
                echo json_encode(['ok' => false, 'error' => 'Dados inválidos.']);
                exit;
            }

            // Check eligibility
            $can = reviewCanUserReview($conn, $userId, $productId);
            if (!$can['can']) {
                echo json_encode(['ok' => false, 'error' => $can['reason']]);
                exit;
            }

            $id = reviewCreate($conn, $userId, $productId, $rating, $titulo, $comentario, $can['order_id'] ?? null);
            if ($id === false) {
                echo json_encode(['ok' => false, 'error' => 'Você já avaliou este produto.']);
                exit;
            }

            echo json_encode(['ok' => true, 'review_id' => $id, 'aggregate' => reviewAggregate($conn, $productId)]);
            break;

        case 'update':
            $reviewId   = (int)($input['review_id'] ?? 0);
            $rating     = (int)($input['rating'] ?? 0);
            $titulo     = trim((string)($input['titulo'] ?? ''));
            $comentario = trim((string)($input['comentario'] ?? ''));

            if ($reviewId < 1 || $rating < 1 || $rating > 5) {
                echo json_encode(['ok' => false, 'error' => 'Dados inválidos.']);
                exit;
            }

            $ok = reviewUpdate($conn, $reviewId, $userId, $rating, $titulo, $comentario);
            echo json_encode(['ok' => $ok]);
            break;

        case 'delete':
            $reviewId = (int)($input['review_id'] ?? 0);
            $ok = reviewDelete($conn, $reviewId, $userId);
            echo json_encode(['ok' => $ok]);
            break;

        default:
            echo json_encode(['ok' => false, 'error' => 'Ação inválida.']);
    }
} catch (\Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'Sistema de avaliações indisponível. Tabela product_reviews pode não existir.']);
}
