<?php
// filepath: c:\xampp\htdocs\mercado_admin\public\dashboard.php
declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/media.php';
require_once __DIR__ . '/../src/storefront.php';

exigirUsuario();

$conn = (new Database())->connect();
$uid = (int)($_SESSION['user_id'] ?? 0);

function pickCol(array $cols, array $candidates): ?string {
    foreach ($candidates as $c) {
        if (in_array(strtolower($c), $cols, true)) return $c;
    }
    return null;
}

$cols = [];
$rs = $conn->query("SHOW COLUMNS FROM users");
if ($rs) while ($r = $rs->fetch_assoc()) $cols[] = strtolower((string)$r['Field']);

$nameCol  = pickCol($cols, ['nome', 'name', 'username']);
$emailCol = pickCol($cols, ['email', 'mail']);
$photoCol = pickCol($cols, ['foto_perfil', 'foto', 'avatar', 'profile_photo']);

$sel = ['id'];
if ($nameCol)  $sel[] = "`{$nameCol}` AS nome";
if ($emailCol) $sel[] = "`{$emailCol}` AS email";
if ($photoCol) $sel[] = "`{$photoCol}` AS foto";

$st = $conn->prepare("SELECT " . implode(', ', $sel) . " FROM users WHERE id = ? LIMIT 1");
$st->bind_param('i', $uid);
$st->execute();
$user = $st->get_result()->fetch_assoc() ?: [];
$st->close();

$foto = mediaResolveUrl(
    (string)($user['foto'] ?? ''),
    'https://placehold.co/120x120/111827/9ca3af?text=Foto'
);

$activeMenu = 'dashboard';
$pageTitle  = 'Dashboard do Usuário';

function tableExistsDash($conn, string $table): bool {
  $safe = $conn->real_escape_string($table);
  $rs = $conn->query("SHOW TABLES LIKE '{$safe}'");
  if (!$rs) return false;
  return $rs->fetch_assoc() !== null;
}

function countDash($conn, string $sql, string $types = '', array $params = []): int {
  $stmt = $conn->prepare($sql);
  if (!$stmt) return 0;
  if ($types !== '') {
    $bind = array_merge([$types], $params);
    $refs = [];
    foreach ($bind as $k => $v) $refs[$k] = &$bind[$k];
    call_user_func_array([$stmt, 'bind_param'], $refs);
  }
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc() ?: [];
  $stmt->close();
  return (int)($row['qtd'] ?? 0);
}

$cards = [
  'pedidos_total' => 0,
  'pedidos_pagos' => 0,
  'pedidos_andamento' => 0,
];

if (tableExistsDash($conn, 'orders')) {
  $cards['pedidos_total'] = countDash($conn, "SELECT COUNT(*) AS qtd FROM orders WHERE user_id = ?", 'i', [$uid]);
  $cards['pedidos_pagos'] = countDash($conn, "SELECT COUNT(*) AS qtd FROM orders WHERE user_id = ? AND status IN ('pago','paid','entregue','delivered')", 'i', [$uid]);
  $cards['pedidos_andamento'] = countDash($conn, "SELECT COUNT(*) AS qtd FROM orders WHERE user_id = ? AND status IN ('pendente','pending','aguardando_pagamento','enviado','shipped','processing')", 'i', [$uid]);
}

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/user_layout_start.php';
?>

<div class="max-w-7xl mx-auto space-y-4">
  <div class="bg-blackx2 border border-blackx3 rounded-2xl p-6">
    <div class="flex items-center gap-4">
      <img src="<?= htmlspecialchars($foto, ENT_QUOTES, 'UTF-8') ?>" class="w-16 h-16 rounded-full object-cover border border-blackx3" alt="avatar">
      <div>
        <p class="text-lg font-semibold"><?= htmlspecialchars((string)($user['nome'] ?? 'Usuário'), ENT_QUOTES, 'UTF-8') ?></p>
        <p class="text-zinc-400 text-sm"><?= htmlspecialchars((string)($user['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
      </div>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="bg-blackx2 border border-blackx3 rounded-2xl p-4">
      <p class="text-zinc-400 text-sm">Pedidos totais</p>
      <p class="text-2xl font-bold mt-1"><?= $cards['pedidos_total'] ?></p>
    </div>
    <div class="bg-blackx2 border border-blackx3 rounded-2xl p-4">
      <p class="text-zinc-400 text-sm">Pedidos pagos</p>
      <p class="text-2xl font-bold mt-1"><?= $cards['pedidos_pagos'] ?></p>
    </div>
    <div class="bg-blackx2 border border-blackx3 rounded-2xl p-4">
      <p class="text-zinc-400 text-sm">Em andamento</p>
      <p class="text-2xl font-bold mt-1"><?= $cards['pedidos_andamento'] ?></p>
    </div>
  </div>

  <div class="bg-blackx2 border border-blackx3 rounded-2xl p-4">
    <h3 class="font-semibold mb-3">Ações rápidas</h3>
    <div class="flex flex-wrap gap-2">
      <a href="/mercado_admin/public/meus_pedidos.php" class="rounded-xl border border-blackx3 px-3 py-2 text-sm hover:border-greenx transition">Ver meus pedidos</a>
      <a href="/mercado_admin/public/wallet.php" class="rounded-xl border border-blackx3 px-3 py-2 text-sm hover:border-greenx transition">Abrir carteira</a>
      <a href="/mercado_admin/public/minha_conta.php" class="rounded-xl border border-blackx3 px-3 py-2 text-sm hover:border-greenx transition">Atualizar conta</a>
    </div>
  </div>

  <?php if ($cards['pedidos_total'] === 0): ?>
    <div class="bg-blackx2 border border-blackx3 rounded-2xl p-6 text-center">
      <p class="text-zinc-300 font-medium">Você ainda não possui pedidos.</p>
      <p class="text-zinc-500 text-sm mt-1">Quando realizar sua primeira compra, o resumo aparecerá aqui.</p>
    </div>
  <?php endif; ?>

  <?php
    $dashCats = array_values(array_filter(sfListCategories($conn), fn($c) => ($c['tipo'] ?? 'produto') !== 'blog'));
    if ($dashCats):
  ?>
  <div class="bg-blackx2 border border-blackx3 rounded-2xl p-4">
    <div class="flex items-center justify-between mb-3">
      <h3 class="font-semibold">Categorias em Destaque</h3>
      <a href="<?= BASE_PATH ?>/categorias" class="text-xs text-greenx hover:underline">Ver todas</a>
    </div>
    <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-2">
      <?php
        $dIcons = ['package','monitor','gamepad-2','shirt','book-open','music','camera','cpu'];
        foreach ($dashCats as $di => $dc):
          $dcImg = trim((string)($dc['imagem'] ?? ''));
          $dcUrl = $dcImg !== '' ? sfImageUrl($dcImg) : '';
      ?>
      <a href="<?= sfCategoryUrl($dc) ?>"
         class="group relative overflow-hidden rounded-xl border border-white/[0.06] <?= $dcUrl ? 'bg-blackx' : 'bg-white/[0.03]' ?> hover:border-greenx/30 transition-all">
        <?php if ($dcUrl): ?>
        <img src="<?= htmlspecialchars($dcUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" class="absolute inset-0 w-full h-full object-cover opacity-30 group-hover:opacity-50 group-hover:scale-110 transition-all duration-500">
        <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent"></div>
        <?php endif; ?>
        <div class="aspect-square flex flex-col items-center justify-center p-2 text-center relative z-10">
          <?php if (!$dcUrl): ?>
          <div class="w-8 h-8 rounded-lg bg-white/[0.06] border border-white/[0.08] flex items-center justify-center mb-1 group-hover:scale-110 transition-transform">
            <i data-lucide="<?= $dIcons[$di % count($dIcons)] ?>" class="w-4 h-4 text-greenx"></i>
          </div>
          <?php endif; ?>
          <p class="text-[10px] sm:text-xs font-medium <?= $dcUrl ? 'text-white drop-shadow-lg mt-auto' : 'text-zinc-300' ?> group-hover:text-white transition-colors line-clamp-2 leading-tight"><?= htmlspecialchars((string)$dc['nome'], ENT_QUOTES, 'UTF-8') ?></p>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php
include __DIR__ . '/../views/partials/user_layout_end.php';
include __DIR__ . '/../views/partials/footer.php';