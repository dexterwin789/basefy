<?php
declare(strict_types=1);
/**
 * Novo Ticket — storefront
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Catch fatal errors that bypass try/catch
register_shutdown_function(static function(): void {
    $err = error_get_last();
    if ($err !== null && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        error_log('[tickets_novo.php SHUTDOWN] ' . $err['message'] . ' in ' . $err['file'] . ':' . $err['line']);
        while (ob_get_level()) ob_end_clean();
        if (!headers_sent()) { http_response_code(500); header('Content-Type: text/html; charset=utf-8'); }
        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Erro</title>';
        echo '<script src="https://cdn.tailwindcss.com"></script></head>';
        echo '<body class="min-h-screen bg-[#121316] text-white flex items-center justify-center px-4">';
        echo '<div class="text-center"><h1 class="text-2xl font-bold mb-2">Erro Fatal</h1>';
        echo '<p class="text-red-400 text-xs mb-4">' . htmlspecialchars($err['message']) . '</p>';
        echo '<a href="/" class="inline-block px-6 py-3 rounded-xl bg-greenx text-white font-bold text-sm">Voltar</a>';
        echo '</div></body></html>';
    }
});

ob_start();
try {

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/storefront.php';
require_once __DIR__ . '/../src/tickets.php';

$userId     = (int)($_SESSION['user_id'] ?? 0);
$userRole   = (string)($_SESSION['user']['role'] ?? 'usuario');
$isLoggedIn = $userId > 0;
$conn       = (new Database())->connect();
$cartCount  = sfCartCount();

$currentPage = 'tickets';
$pageTitle   = 'Criar novo Ticket';

$categories = ticketCategories();
$errors     = [];
$success    = false;

// Load user's recent orders for the "Related order" dropdown
$userOrders = [];
if ($isLoggedIn) {
    try {
        $st = $conn->prepare("SELECT id, status, total, criado_em FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 30");
        $st->bind_param('i', $userId);
        $st->execute();
        $userOrders = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    } catch (\Throwable $e) { /* table may not exist yet */ }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isLoggedIn) {
    $categoria = trim((string)($_POST['categoria'] ?? ''));
    $titulo    = trim((string)($_POST['titulo'] ?? ''));
    $mensagem  = trim((string)($_POST['mensagem'] ?? ''));
    $orderId   = ((int)($_POST['order_id'] ?? 0)) ?: null;

    if (!$categoria) $errors[] = 'Selecione uma categoria.';
    if (strlen($titulo) < 3) $errors[] = 'O título deve ter ao menos 3 caracteres.';
    if (strlen($mensagem) < 10) $errors[] = 'A descrição deve ter ao menos 10 caracteres.';

    // Only attach order for purchase-related categories
    $orderCategories = ['problemas_reembolsos', 'financeiro_retiradas'];
    if (!in_array($categoria, $orderCategories, true)) {
        $orderId = null;
    }

    if (empty($errors)) {
        $result = ticketCreate($conn, (int)$userId, $categoria, $titulo, $mensagem, $orderId);
        if ($result['ok']) {
            $success = true;
            $newTicketId = (int)$result['id'];
        } else {
            $errors[] = $result['error'] ?? 'Erro ao criar ticket.';
        }
    }
}

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/storefront_nav.php';
?>

<div class="min-h-screen bg-blackx">
    <!-- Breadcrumb -->
    <div class="px-4 sm:px-6 pt-6">
        <nav class="flex items-center gap-2 text-sm text-zinc-500 animate-fade-in">
            <a href="/" class="hover:text-greenx transition-colors">Início</a>
            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
            <a href="/central_ajuda" class="hover:text-greenx transition-colors">Central de ajuda</a>
            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
            <a href="/tickets" class="hover:text-greenx transition-colors">Tickets</a>
            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
            <span class="text-zinc-300">Novo</span>
        </nav>
    </div>

    <?php if (!$isLoggedIn): ?>
    <div class="max-w-2xl mx-auto text-center py-16 px-4">
        <p class="text-zinc-400 mb-4">Você precisa estar logado para criar um ticket.</p>
        <a href="/login?return_to=<?= urlencode('/tickets_novo') ?>" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-greenx text-white font-bold text-sm hover:bg-greenx2 transition-all">
            <i data-lucide="log-in" class="w-4 h-4"></i> Fazer login
        </a>
    </div>
    <?php elseif ($success): ?>
    <!-- Success state -->
    <div class="max-w-lg mx-auto text-center py-16 px-4 animate-fade-in-up">
        <div class="w-20 h-20 mx-auto mb-6 rounded-full bg-greenx/10 border border-greenx/30 flex items-center justify-center">
            <i data-lucide="check-circle" class="w-10 h-10 text-greenx"></i>
        </div>
        <h2 class="text-2xl font-black mb-2">Ticket criado com sucesso!</h2>
        <p class="text-zinc-400 text-sm mb-6">
            Seu ticket <strong>#<?= $newTicketId ?></strong> foi criado. Você receberá uma resposta em breve.
        </p>
        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
            <a href="<?= BASE_PATH ?>/ticket_detalhe?id=<?= $newTicketId ?>"
               class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-greenx text-white font-bold text-sm hover:bg-greenx2 transition-all">
                <i data-lucide="eye" class="w-4 h-4"></i> Ver Ticket
            </a>
            <a href="<?= BASE_PATH ?>/tickets"
               class="inline-flex items-center gap-2 px-6 py-3 rounded-xl border border-white/[0.08] text-zinc-300 hover:border-greenx/30 font-semibold text-sm transition-all">
                <i data-lucide="list" class="w-4 h-4"></i> Meus Tickets
            </a>
        </div>
    </div>
    <?php else: ?>

    <section class="px-4 sm:px-6 pt-8 pb-16 animate-fade-in-up">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-black mb-2">Criar novo Ticket</h1>
            <p class="text-zinc-400 text-sm">
                Preencha os campos abaixo com o máximo de detalhes possível.
            </p>
        </div>

        <?php if ($errors): ?>
        <div class="bg-red-500/10 border border-red-400/30 rounded-xl p-4 mb-6">
            <?php foreach ($errors as $e): ?>
            <p class="text-red-400 text-sm flex items-center gap-2">
                <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i> <?= htmlspecialchars($e) ?>
            </p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php $catDescsJson = json_encode(array_map(fn($c) => $c['desc'], $categories), JSON_UNESCAPED_UNICODE); ?>
        <script>window.__ticketCatDescs = <?= $catDescsJson ?>;</script>

        <form method="POST" class="space-y-6" x-data="{
            cat: '<?= htmlspecialchars($categoria ?? '', ENT_QUOTES) ?>',
            catDescs: window.__ticketCatDescs
        }">
            <!-- Category -->
            <div>
                <label class="block text-sm font-semibold mb-2 text-zinc-300">Categoria do Ticket <span class="text-red-400">*</span></label>
                <select name="categoria" x-model="cat" required
                        class="w-full bg-blackx2 border border-white/[0.08] rounded-xl p-3 text-sm text-zinc-100 focus:border-greenx/60 focus:ring-1 focus:ring-greenx/30 outline-none transition">
                    <option value="">Selecione uma categoria...</option>
                    <?php foreach ($categories as $key => $cat): ?>
                    <option value="<?= $key ?>" <?= ($categoria ?? '') === $key ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['label']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <p x-show="cat && catDescs[cat]" x-text="catDescs[cat]" class="text-xs text-zinc-500 mt-1.5 pl-1" style="display:none"></p>
            </div>

            <!-- Title -->
            <div>
                <label class="block text-sm font-semibold mb-2 text-zinc-300">Título <span class="text-red-400">*</span></label>
                <input type="text" name="titulo" value="<?= htmlspecialchars($titulo ?? '') ?>" required minlength="3" maxlength="255"
                       placeholder="Escreva um título objetivo..."
                       class="w-full bg-blackx2 border border-white/[0.08] rounded-xl p-3 text-sm text-zinc-100 placeholder:text-zinc-600 focus:border-greenx/60 focus:ring-1 focus:ring-greenx/30 outline-none transition">
            </div>

            <!-- Related order (only for purchase-related categories) -->
            <div x-show="cat === 'problemas_reembolsos' || cat === 'financeiro_retiradas'" x-transition style="display:none">
                <label class="block text-sm font-semibold mb-2 text-zinc-300">Compra relacionada <span class="text-zinc-600">(opcional)</span></label>
                <select name="order_id"
                        class="w-full bg-blackx2 border border-white/[0.08] rounded-xl p-3 text-sm text-zinc-100 focus:border-greenx/60 focus:ring-1 focus:ring-greenx/30 outline-none transition">
                    <option value="">Nenhuma compra relacionada</option>
                    <?php foreach ($userOrders as $order): ?>
                    <option value="<?= (int)$order['id'] ?>" <?= ((int)($orderId ?? 0)) === (int)$order['id'] ? 'selected' : '' ?>>
                        Pedido #<?= (int)$order['id'] ?> — R$<?= number_format((float)$order['total'], 2, ',', '.') ?> (<?= ucfirst((string)$order['status']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Description -->
            <div>
                <label class="block text-sm font-semibold mb-2 text-zinc-300">Descrição <span class="text-red-400">*</span></label>
                <textarea name="mensagem" required minlength="10" rows="6"
                          placeholder="Descreva seu problema ou solicitação com o máximo de detalhes possível..."
                          class="w-full bg-blackx2 border border-white/[0.08] rounded-xl p-3 text-sm text-zinc-100 placeholder:text-zinc-600 focus:border-greenx/60 focus:ring-1 focus:ring-greenx/30 outline-none transition resize-y"><?= htmlspecialchars($mensagem ?? '') ?></textarea>
            </div>

            <!-- Submit -->
            <div class="flex items-center justify-between gap-4 pt-2">
                <a href="<?= BASE_PATH ?>/tickets" class="text-sm text-zinc-400 hover:text-zinc-200 transition">
                    <i data-lucide="arrow-left" class="w-4 h-4 inline -mt-0.5"></i> Voltar
                </a>
                <button type="submit"
                        class="inline-flex items-center gap-2 px-8 py-3 rounded-xl bg-greenx hover:bg-greenx2 text-white font-bold text-sm transition-all shadow-lg shadow-greenx/20">
                    <i data-lucide="send" class="w-4 h-4"></i> Criar Ticket
                </button>
            </div>
        </form>
    </section>
    <?php endif; ?>
</div>

<?php
include __DIR__ . '/../views/partials/storefront_footer.php';
include __DIR__ . '/../views/partials/footer.php';

ob_end_flush();
} catch (\Throwable $fatalEx) {
    while (ob_get_level()) ob_end_clean();
    error_log('[tickets_novo.php] Fatal: ' . $fatalEx->getMessage() . ' in ' . $fatalEx->getFile() . ':' . $fatalEx->getLine());
    if (!headers_sent()) { http_response_code(500); }
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Erro — Tickets</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="min-h-screen bg-[#121316] text-white font-[Inter,sans-serif] flex items-center justify-center px-4">
<div class="text-center max-w-md">
<div class="w-16 h-16 mx-auto mb-4 rounded-full bg-red-500/10 border border-red-400/30 flex items-center justify-center"><i data-lucide="alert-triangle" class="w-8 h-8 text-red-400"></i></div>
<h1 class="text-2xl font-bold mb-2">Ops! Algo deu errado</h1>
<p class="text-zinc-400 mb-2">Não foi possível carregar esta página.</p>
<p class="text-zinc-600 text-xs mb-6"><?= htmlspecialchars($fatalEx->getMessage()) ?></p>
<a href="/" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-greenx text-white font-bold text-sm hover:bg-greenx2 transition-all"><i data-lucide="home" class="w-4 h-4"></i> Voltar ao início</a>
</div>
<script>lucide.createIcons();</script>
</body>
</html>
<?php
}
?>
