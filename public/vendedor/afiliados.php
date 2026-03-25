<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/affiliates.php';

exigirVendedor();

$conn = (new Database())->connect();
affEnsureTables($conn);

$uid = (int)($_SESSION['user_id'] ?? 0);
$rules = affRules($conn);
$affiliate = affGetByUserId($conn, $uid);

$msg = '';
$err = '';

// Vendor can also be an affiliate
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'register') {
        $pixType = trim((string)($_POST['pix_key_type'] ?? ''));
        $pixKey  = trim((string)($_POST['pix_key'] ?? ''));
        $bio     = trim((string)($_POST['bio'] ?? ''));
        [$ok, $message] = affRegister($conn, $uid, $pixType ?: null, $pixKey ?: null, $bio ?: null);
        if ($ok) { $msg = $message; $affiliate = affGetByUserId($conn, $uid); }
        else $err = $message;
    }
    if ($action === 'request_payout' && $affiliate) {
        [$ok, $message] = affRequestPayout($conn, (int)$affiliate['id']);
        if ($ok) $msg = $message; else $err = $message;
    }
}

// Sales-from-affiliates stats (orders on vendor's products that were driven by affiliates)
$affPage = max(1, (int)($_GET['p'] ?? 1));
$pp      = in_array(($_ppV = (int)($_GET['pp'] ?? 10)), [5,10,20]) ? $_ppV : 10;
$affOffset = ($affPage - 1) * $pp;

// Count total affiliate sales
$affCountQ = $conn->prepare("
    SELECT COUNT(DISTINCT ac.id) AS total
    FROM affiliate_conversions ac
    JOIN orders o ON o.id = ac.order_id
    JOIN order_items oi ON oi.order_id = o.id
    WHERE oi.vendedor_id = ?
");
$affTotal = 0;
if ($affCountQ) {
    $affCountQ->bind_param('i', $uid);
    $affCountQ->execute();
    $affTotal = (int)($affCountQ->get_result()->fetch_assoc()['total'] ?? 0);
    $affCountQ->close();
}
$affTotalPages = max(1, (int)ceil($affTotal / $pp));

$affSalesQ = $conn->prepare("
    SELECT ac.*, a.referral_code, u.nome AS aff_nome, o.status AS order_status
    FROM affiliate_conversions ac
    JOIN affiliates a ON a.id = ac.affiliate_id
    JOIN users u ON u.id = a.user_id
    JOIN orders o ON o.id = ac.order_id
    JOIN order_items oi ON oi.order_id = o.id
    WHERE oi.vendedor_id = ?
    GROUP BY ac.id, a.referral_code, u.nome, o.status
    ORDER BY ac.created_at DESC
    LIMIT $pp OFFSET $affOffset
");
$affSales = [];
if ($affSalesQ) {
    $affSalesQ->bind_param('i', $uid);
    $affSalesQ->execute();
    $affSales = $affSalesQ->get_result()->fetch_all(MYSQLI_ASSOC);
    $affSalesQ->close();
}

// Summary of affiliate-driven sales
$summaryQ = $conn->prepare("
    SELECT COUNT(*) AS total_conversions,
           COALESCE(SUM(sub.order_total), 0) AS total_revenue,
           COALESCE(SUM(sub.commission_amount), 0) AS total_commissions
    FROM (
        SELECT DISTINCT ac.id, ac.order_total, ac.commission_amount
        FROM affiliate_conversions ac
        JOIN orders o ON o.id = ac.order_id
        JOIN order_items oi ON oi.order_id = o.id
        WHERE oi.vendedor_id = ?
    ) sub
");
$summary = ['total_conversions' => 0, 'total_revenue' => 0, 'total_commissions' => 0];
if ($summaryQ) {
    $summaryQ->bind_param('i', $uid);
    $summaryQ->execute();
    $summary = $summaryQ->get_result()->fetch_assoc() ?: $summary;
    $summaryQ->close();
}

// Own affiliate stats
$stats = null;
if ($affiliate && $affiliate['status'] === 'ativo') {
    $stats = affDashboardStats($conn, (int)$affiliate['id']);
}

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

$pageTitle = 'Afiliados';
$activeMenu = 'afiliados';

include __DIR__ . '/../../views/partials/header.php';
include __DIR__ . '/../../views/partials/vendor_layout_start.php';

$statusBadge = function(string $s): string {
    return match($s) {
        'ativo','aprovada','pago','paga' => 'bg-greenx/15 border-greenx/40 text-greenx',
        'pendente'  => 'bg-yellow-500/15 border-yellow-400/40 text-yellow-300',
        'suspenso','rejeitado','rejeitada','cancelada' => 'bg-red-500/15 border-red-400/40 text-red-300',
        default => 'bg-zinc-500/15 border-zinc-400/40 text-zinc-300',
    };
};
?>

<div class="space-y-6">

  <?php if ($msg): ?>
    <div class="rounded-xl bg-greenx/10 border border-greenx/30 p-4 text-sm text-greenx flex items-center gap-2">
      <i data-lucide="check-circle" class="w-5 h-5 flex-shrink-0"></i> <?= htmlspecialchars($msg) ?>
    </div>
  <?php endif; ?>
  <?php if ($err): ?>
    <div class="rounded-xl bg-red-500/10 border border-red-400/30 p-4 text-sm text-red-300 flex items-center gap-2">
      <i data-lucide="alert-circle" class="w-5 h-5 flex-shrink-0"></i> <?= htmlspecialchars($err) ?>
    </div>
  <?php endif; ?>

  <!-- Vendas via afiliados (vendor perspective) -->
  <div class="bg-blackx2 border border-blackx3 rounded-2xl p-5">
    <h3 class="text-sm font-semibold mb-4 flex items-center gap-2">
      <i data-lucide="users" class="w-4 h-4 text-purple-400"></i> Vendas geradas por afiliados
    </h3>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
      <div class="p-4 rounded-xl bg-blackx border border-blackx3">
        <div class="text-[11px] text-zinc-500 uppercase tracking-wider mb-1">Conversões</div>
        <div class="text-xl font-bold text-cyan-400"><?= number_format((int)$summary['total_conversions']) ?></div>
      </div>
      <div class="p-4 rounded-xl bg-blackx border border-blackx3">
        <div class="text-[11px] text-zinc-500 uppercase tracking-wider mb-1">Receita via afiliados</div>
        <div class="text-xl font-bold text-greenx">R$ <?= number_format((float)$summary['total_revenue'], 2, ',', '.') ?></div>
      </div>
      <div class="p-4 rounded-xl bg-blackx border border-blackx3">
        <div class="text-[11px] text-zinc-500 uppercase tracking-wider mb-1">Comissões pagas</div>
        <div class="text-xl font-bold text-orange-400">R$ <?= number_format((float)$summary['total_commissions'], 2, ',', '.') ?></div>
      </div>
    </div>

    <?php if (!empty($affSales)): ?>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-[11px] uppercase tracking-wider text-zinc-500 border-b border-blackx3">
            <th class="text-left px-4 py-3">Afiliado</th>
            <th class="text-center px-4 py-3">Pedido</th>
            <th class="text-right px-4 py-3">Total</th>
            <th class="text-right px-4 py-3">Comissão</th>
            <th class="text-center px-4 py-3">Status</th>
            <th class="text-center px-4 py-3">Data</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-blackx3/50">
          <?php foreach ($affSales as $s): ?>
            <tr class="hover:bg-blackx/50 transition">
              <td class="px-4 py-3">
                <div class="text-xs font-medium"><?= htmlspecialchars($s['aff_nome'] ?? '') ?></div>
                <div class="text-[10px] text-zinc-500 font-mono"><?= $s['referral_code'] ?? '' ?></div>
              </td>
              <td class="px-4 py-3 text-center text-xs">#<?= $s['order_id'] ?></td>
              <td class="px-4 py-3 text-right text-xs">R$ <?= number_format((float)$s['order_total'], 2, ',', '.') ?></td>
              <td class="px-4 py-3 text-right text-xs text-orange-400">R$ <?= number_format((float)$s['commission_amount'], 2, ',', '.') ?></td>
              <td class="px-4 py-3 text-center">
                <span class="px-2 py-0.5 rounded-full text-[11px] font-medium border <?= $statusBadge($s['status']) ?>"><?= ucfirst($s['status']) ?></span>
              </td>
              <td class="px-4 py-3 text-center text-xs text-zinc-500"><?= fmtDate($s['created_at'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <p class="text-center text-zinc-600 text-sm py-6">Nenhuma venda via afiliados registrada ainda.</p>
    <?php endif; ?>

    <?php
      $paginaAtual  = $affPage;
      $totalPaginas = $affTotalPages;
      include __DIR__ . '/../../views/partials/pagination.php';
    ?>
  </div>

  <!-- Own affiliate panel -->
  <div class="bg-blackx2 border border-blackx3 rounded-2xl p-5">
    <h3 class="text-sm font-semibold mb-4 flex items-center gap-2">
      <i data-lucide="share-2" class="w-4 h-4 text-greenx"></i> Minha conta de afiliado
    </h3>

    <?php if (!$affiliate): ?>
      <p class="text-zinc-400 text-sm mb-4">Vendedores também podem ser afiliados! Indique produtos de outros vendedores e ganhe <?= number_format($rules['commission_percent'], 1) ?>% de comissão.</p>
      <?php if ($rules['program_enabled']): ?>
      <form method="post" class="max-w-md space-y-3" data-pix-mask-group>
        <input type="hidden" name="action" value="register">
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs text-zinc-400 mb-1">Tipo chave PIX</label>
            <select name="pix_key_type" class="w-full bg-blackx border border-blackx3 rounded-xl px-3 py-2 text-sm text-white focus:border-greenx outline-none">
              <option value="">Selecione</option>
              <option value="cpf">CPF</option><option value="email">E-mail</option>
              <option value="telefone">Telefone</option><option value="aleatoria">Chave aleatória</option>
            </select>
          </div>
          <div>
            <label class="block text-xs text-zinc-400 mb-1">Chave PIX</label>
            <input type="text" name="pix_key" placeholder="Selecione o tipo da chave" disabled
                   class="w-full bg-blackx border border-blackx3 rounded-xl px-3 py-2 text-sm text-white focus:border-greenx outline-none disabled:opacity-40 disabled:cursor-not-allowed">
          </div>
        </div>
        <div>
          <label class="block text-xs text-zinc-400 mb-1">Bio (opcional)</label>
          <textarea name="bio" rows="2" class="w-full bg-blackx border border-blackx3 rounded-xl px-3 py-2 text-sm text-white focus:border-greenx outline-none resize-none"></textarea>
        </div>
        <button type="submit" class="px-6 py-2.5 rounded-xl bg-greenx hover:bg-greenx2 text-white font-bold text-sm transition">Cadastrar como Afiliado</button>
      </form>
      <?php else: ?>
      <p class="text-yellow-300 text-sm">Programa de afiliados fechado para novas inscrições.</p>
      <?php endif; ?>

    <?php elseif ($affiliate['status'] === 'pendente'): ?>
      <div class="flex items-center gap-3 p-4 rounded-xl bg-yellow-500/10 border border-yellow-400/30">
        <i data-lucide="clock" class="w-5 h-5 text-yellow-400"></i>
        <div>
          <p class="text-sm font-medium text-yellow-300">Cadastro em análise</p>
          <p class="text-xs text-zinc-500">Código reservado: <code class="text-greenx font-mono"><?= $affiliate['referral_code'] ?></code></p>
        </div>
      </div>

    <?php elseif ($affiliate['status'] === 'ativo' && $stats): ?>
      <!-- Active affiliate mini-dashboard -->
      <div class="space-y-4">
        <div class="flex gap-2" x-data="{ copied: false }">
          <input type="text" id="refLinkV" readonly
                 value="<?= htmlspecialchars($baseUrl . (defined('BASE_PATH') ? BASE_PATH : '') . '/ref/' . $affiliate['referral_code']) ?>"
                 class="flex-1 bg-blackx border border-blackx3 rounded-xl px-3 py-2 text-sm text-greenx font-mono select-all focus:border-greenx outline-none">
          <button @click="navigator.clipboard.writeText(document.getElementById('refLinkV').value); copied = true; setTimeout(() => copied = false, 2000)"
                  class="px-4 py-2 rounded-xl border border-greenx bg-greenx/15 text-greenx text-sm font-semibold hover:bg-greenx/25 transition flex items-center gap-1.5">
            <i data-lucide="copy" class="w-4 h-4" x-show="!copied"></i>
            <i data-lucide="check" class="w-4 h-4" x-show="copied" x-cloak></i>
            <span x-text="copied ? 'Copiado!' : 'Copiar'"></span>
          </button>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <div class="p-3 rounded-xl bg-blackx border border-blackx3 text-center">
            <div class="text-[10px] text-zinc-500 uppercase">Cliques</div>
            <div class="text-lg font-bold text-purple-400"><?= number_format($stats['clicks_30d']) ?></div>
          </div>
          <div class="p-3 rounded-xl bg-blackx border border-blackx3 text-center">
            <div class="text-[10px] text-zinc-500 uppercase">Conversões</div>
            <div class="text-lg font-bold text-cyan-400"><?= number_format($stats['conversions_30d']) ?></div>
          </div>
          <div class="p-3 rounded-xl bg-blackx border border-blackx3 text-center">
            <div class="text-[10px] text-zinc-500 uppercase">Ganhos 30d</div>
            <div class="text-lg font-bold text-greenx">R$ <?= number_format($stats['earned_30d'], 2, ',', '.') ?></div>
          </div>
          <div class="p-3 rounded-xl bg-blackx border border-blackx3 text-center">
            <div class="text-[10px] text-zinc-500 uppercase">Saldo</div>
            <div class="text-lg font-bold text-greenx">R$ <?= number_format($stats['balance'], 2, ',', '.') ?></div>
          </div>
        </div>
        <?php if ($stats['balance'] >= $rules['min_payout']): ?>
        <form method="post">
          <input type="hidden" name="action" value="request_payout">
          <button type="submit" class="px-6 py-2.5 rounded-xl bg-greenx hover:bg-greenx2 text-white font-bold text-sm transition">
            Solicitar Saque — R$ <?= number_format($stats['balance'], 2, ',', '.') ?>
          </button>
        </form>
        <?php endif; ?>
      </div>

    <?php else: ?>
      <div class="flex items-center gap-3 p-4 rounded-xl bg-red-500/10 border border-red-400/30">
        <i data-lucide="ban" class="w-5 h-5 text-red-400"></i>
        <p class="text-sm text-red-300">Conta de afiliado <?= $affiliate['status'] ?>.</p>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php
include __DIR__ . '/../../views/partials/vendor_layout_end.php';
?>
<script src="<?= BASE_PATH ?>/assets/js/pix-mask.js"></script>
<?php
include __DIR__ . '/../../views/partials/footer.php';
?>
