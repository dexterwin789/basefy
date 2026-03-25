<?php
// filepath: c:\xampp\htdocs\mercado_admin\public\meus_pedidos.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/db.php';
require_once $ROOT . '/src/auth.php';
require_once $ROOT . '/src/media.php';

exigirLogin();

$conn = (new Database())->connect();
$uid  = (int)($_SESSION['user_id'] ?? 0);

$pageTitle  = 'Meus pedidos';
$activeMenu = 'pedidos';

$status = trim((string)($_GET['status'] ?? ''));
$de     = trim((string)($_GET['de'] ?? ''));
$ate    = trim((string)($_GET['ate'] ?? ''));

$pp = in_array(($_pp = (int)($_GET['pp'] ?? 10)), [5,10,20]) ? $_pp : 10;
$pagina = max(1, (int)($_GET['p'] ?? 1));
$offset = ($pagina - 1) * $pp;

$allowedStatus = ['pendente', 'pago', 'cancelado', 'enviado', 'entregue'];
if ($status !== '' && !in_array($status, $allowedStatus, true)) {
    $status = '';
}

$statusBadge = static function (string $status): string {
  $s = strtolower(trim($status));
  if (in_array($s, ['pago', 'paid', 'entregue'], true)) {
    return 'bg-greenx/15 border border-greenx/40 text-greenx';
  }
  if (in_array($s, ['pendente', 'pending', 'enviado', 'aguardando_pagamento'], true)) {
    return 'bg-orange-500/15 border border-orange-400/40 text-orange-300';
  }
  return 'bg-blackx border border-blackx3 text-zinc-300';
};

$sql = "
    SELECT
        o.id,
        o.status,
        o.total,
        o.gross_total,
        o.wallet_used,
        o.criado_em,
        COALESCE(SUM(oi.quantidade), 0) AS itens
    FROM orders o
    LEFT JOIN order_items oi ON oi.order_id = o.id
    WHERE o.user_id = ?
";
$types  = 'i';
$params = [$uid];

if ($status !== '') {
    $sql .= " AND o.status = ? ";
    $types .= 's';
    $params[] = $status;
}
if ($de !== '') {
    $sql .= " AND DATE(o.criado_em) >= ? ";
    $types .= 's';
    $params[] = $de;
}
if ($ate !== '') {
    $sql .= " AND DATE(o.criado_em) <= ? ";
    $types .= 's';
    $params[] = $ate;
}

// Count total
$countSql = "SELECT COUNT(DISTINCT o.id) FROM orders o LEFT JOIN order_items oi ON oi.order_id = o.id WHERE o.user_id = ?";
$cTypes = 'i';
$cParams = [$uid];
if ($status !== '') { $countSql .= " AND o.status = ?"; $cTypes .= 's'; $cParams[] = $status; }
if ($de !== '') { $countSql .= " AND DATE(o.criado_em) >= ?"; $cTypes .= 's'; $cParams[] = $de; }
if ($ate !== '') { $countSql .= " AND DATE(o.criado_em) <= ?"; $cTypes .= 's'; $cParams[] = $ate; }
$stC = $conn->prepare($countSql);
$cBind = array_merge([$cTypes], $cParams);
$cRefs = [];
foreach ($cBind as $k => $v) $cRefs[$k] = &$cBind[$k];
call_user_func_array([$stC, 'bind_param'], $cRefs);
$stC->execute();
$totalRegistros = (int)$stC->get_result()->fetch_row()[0];
$stC->close();
$totalPaginas = max(1, (int)ceil($totalRegistros / $pp));

$sql .= " GROUP BY o.id, o.status, o.total, o.gross_total, o.wallet_used, o.criado_em ORDER BY o.id DESC LIMIT ? OFFSET ?";
$types .= 'ii';
$params[] = $pp;
$params[] = $offset;

$pedidos = [];
$st = $conn->prepare($sql);
if ($st) {
    $bind = array_merge([$types], $params);
    $refs = [];
    foreach ($bind as $k => $v) $refs[$k] = &$bind[$k];
    call_user_func_array([$st, 'bind_param'], $refs);

    $st->execute();
    $pedidos = $st->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    $st->close();
}

/* ---------- product thumbs per order ---------- */
$orderProducts = [];
if ($pedidos) {
    $ids = array_map(fn($p) => (int)$p['id'], $pedidos);
    $inList = implode(',', $ids);
    $stProd = $conn->query("
        SELECT oi.order_id, p.id AS pid, p.nome, p.imagem
        FROM order_items oi
        JOIN products p ON p.id = oi.product_id
        WHERE oi.order_id IN ($inList)
        ORDER BY oi.order_id, oi.id ASC
    ");
    if ($stProd) {
        $allRows = (is_object($stProd) && method_exists($stProd, 'fetch_all')) ? $stProd->fetch_all(MYSQLI_ASSOC) : [];
        foreach ($allRows as $r) {
            $oid = (int)$r['order_id'];
            if (!isset($orderProducts[$oid])) {
                $orderProducts[$oid] = [];
            }
            $orderProducts[$oid][] = $r;
        }
    }
}

include $ROOT . '/views/partials/header.php';
include $ROOT . '/views/partials/user_layout_start.php';
?>

<div class="bg-blackx2 border border-blackx3 rounded-2xl p-4 md:p-5">
  <form method="get" class="mb-4 rounded-2xl border border-blackx3 bg-blackx/50 p-3 md:p-4">
    <div class="flex flex-col md:flex-row md:items-end md:flex-nowrap gap-3">
      <div class="md:w-48">
        <label class="block text-xs text-zinc-500 mb-1">Status</label>
        <select name="status" class="w-full bg-blackx border border-blackx3 rounded-xl px-3 py-2.5 text-sm outline-none focus:border-greenx">
          <option value="">Todos</option>
          <?php foreach ($allowedStatus as $s): ?>
            <option value="<?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?>" <?= $status === $s ? 'selected' : '' ?>>
              <?= ucfirst($s) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="md:w-40">
        <label class="block text-xs text-zinc-500 mb-1">De</label>
        <input type="date" name="de" value="<?= htmlspecialchars($de, ENT_QUOTES, 'UTF-8') ?>"
               class="w-full bg-blackx border border-blackx3 rounded-xl px-3 py-2.5 text-sm outline-none focus:border-greenx">
      </div>
      <div class="md:w-40">
        <label class="block text-xs text-zinc-500 mb-1">Até</label>
        <input type="date" name="ate" value="<?= htmlspecialchars($ate, ENT_QUOTES, 'UTF-8') ?>"
               class="w-full bg-blackx border border-blackx3 rounded-xl px-3 py-2.5 text-sm outline-none focus:border-greenx">
      </div>
      <div class="hidden md:flex md:w-auto md:ml-auto items-center gap-2">
        <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-greenx to-greenxd hover:from-greenx2 hover:to-greenxd text-white font-semibold px-4 py-2.5 whitespace-nowrap transition-all">
          <i data-lucide="sliders-horizontal" class="w-4 h-4"></i>
          Filtrar
        </button>
        <a href="<?= BASE_PATH ?>/meus_pedidos" title="Limpar filtros" aria-label="Limpar filtros" class="inline-flex items-center justify-center rounded-xl border border-blackx3 w-10 h-10 text-zinc-300 hover:border-greenx hover:text-white transition-all">
          <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
        </a>
      </div>
    </div>
    <div class="mt-3 md:hidden flex items-center gap-2">
      <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-greenx to-greenxd hover:from-greenx2 hover:to-greenxd text-white font-semibold px-4 py-2.5 transition-all">
        <i data-lucide="sliders-horizontal" class="w-4 h-4"></i>
        Filtrar
      </button>
      <a href="<?= BASE_PATH ?>/meus_pedidos" title="Limpar filtros" aria-label="Limpar filtros" class="inline-flex items-center justify-center rounded-xl border border-blackx3 w-10 h-10 text-zinc-300 hover:border-greenx hover:text-white transition-all">
        <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
      </a>
    </div>
  </form>

  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="text-zinc-400 border-b border-blackx3">
        <tr>
          <th class="text-left px-3 py-3">Pedido</th>
          <th class="text-left px-3 py-3">Produto</th>
          <th class="text-left px-3 py-3">Data</th>
          <th class="text-left px-3 py-3">Itens</th>
          <th class="text-left px-3 py-3">Total Pago</th>
          <th class="text-left px-3 py-3">Pagamento</th>
          <th class="text-left px-3 py-3">Status</th>
          <th class="text-left px-3 py-3">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$pedidos): ?>
          <tr>
            <td colspan="8" class="px-3 py-4 text-zinc-500">Nenhum pedido encontrado.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($pedidos as $p):
            $prods = $orderProducts[(int)$p['id']] ?? [];
            $firstProd = $prods[0] ?? null;
            $thumbUrl = $firstProd ? mediaResolveUrl((string)($firstProd['imagem'] ?? ''), 'https://placehold.co/48x48/1a1a1a/555?text=—') : '';
            $thumbName = $firstProd ? htmlspecialchars((string)($firstProd['nome'] ?? ''), ENT_QUOTES, 'UTF-8') : '—';
            $extraCount = max(0, count($prods) - 1);
          ?>
            <tr class="border-b border-blackx3/60 hover:bg-blackx/40 transition">
              <td class="px-3 py-3 font-medium">#<?= (int)$p['id'] ?></td>
              <td class="px-3 py-3">
                <?php if ($firstProd): ?>
                <div class="flex items-center gap-3">
                  <div class="relative flex-shrink-0">
                    <img src="<?= htmlspecialchars($thumbUrl, ENT_QUOTES, 'UTF-8') ?>" alt=""
                         class="w-11 h-11 rounded-xl object-cover border border-blackx3">
                    <?php if ($extraCount > 0): ?>
                    <span class="absolute -bottom-1 -right-1 bg-blackx2 border border-blackx3 text-[10px] text-zinc-300 font-bold rounded-full w-5 h-5 flex items-center justify-center">+<?= $extraCount ?></span>
                    <?php endif; ?>
                  </div>
                  <div class="min-w-0">
                    <p class="text-sm font-medium truncate max-w-[160px]"><?= $thumbName ?></p>
                    <?php if ($extraCount > 0): ?>
                    <p class="text-[11px] text-zinc-500">e mais <?= $extraCount ?> produto<?= $extraCount > 1 ? 's' : '' ?></p>
                    <?php endif; ?>
                  </div>
                </div>
                <?php else: ?>
                <span class="text-zinc-500">—</span>
                <?php endif; ?>
              </td>
              <td class="px-3 py-3"><?= fmtDate((string)$p['criado_em']) ?></td>
              <td class="px-3 py-3"><?= (int)$p['itens'] ?></td>
              <?php
                $grossTotal = (float)($p['gross_total'] ?? 0);
                $walletUsed = (float)($p['wallet_used'] ?? 0);
                $displayTotal = $grossTotal > 0 ? $grossTotal : (float)$p['total'];
                $pixPortion = max(0, $displayTotal - $walletUsed);
                if ($walletUsed > 0 && $pixPortion > 0) {
                    $payMethod = 'Wallet + PIX';
                    $payClass = 'bg-purple-500/15 border border-purple-400/40 text-purple-300';
                } elseif ($walletUsed > 0 && $pixPortion <= 0) {
                    $payMethod = 'Wallet';
                    $payClass = 'bg-greenx/15 border border-greenx/40 text-greenx';
                } else {
                    $payMethod = 'PIX';
                    $payClass = 'bg-greenx/15 border border-greenx/40 text-purple-300';
                }
              ?>
              <td class="px-3 py-3 font-medium">R$ <?= number_format($displayTotal, 2, ',', '.') ?></td>
              <td class="px-3 py-3"><span class="px-2 py-0.5 rounded-full text-[10px] font-medium <?= $payClass ?>"><?= $payMethod ?></span></td>
              <td class="px-3 py-3"><span class="px-2.5 py-1 rounded-full text-xs font-medium <?= $statusBadge((string)$p['status']) ?>"><?= htmlspecialchars((string)$p['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
              <td class="px-3 py-3">
                <div class="flex flex-wrap gap-2">
                  <a href="<?= BASE_PATH ?>/pedido_detalhes?id=<?= (int)$p['id'] ?>"
                     class="inline-flex rounded-lg border border-blackx3 px-3 py-1.5 text-xs hover:border-greenx">
                    Ver detalhes
                  </a>
                  <?php if (in_array(strtolower(trim((string)$p['status'])), ['pago', 'entregue', 'concluido'], true)): ?>
                  <a href="<?= BASE_PATH ?>/pedido_detalhes?id=<?= (int)$p['id'] ?>#avaliacoes"
                     class="inline-flex items-center gap-1 rounded-lg border border-yellow-500/30 bg-yellow-500/5 px-3 py-1.5 text-xs text-yellow-400 hover:border-yellow-400 hover:bg-yellow-500/10 transition-all">
                    <svg class="w-3 h-3 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    Avaliar
                  </a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php
    $paginaAtual = $pagina;
    include $ROOT . '/views/partials/pagination.php';
  ?>
</div>

<?php
include $ROOT . '/views/partials/user_layout_end.php';
include $ROOT . '/views/partials/footer.php';