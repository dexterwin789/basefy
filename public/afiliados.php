<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/affiliates.php';

exigirLogin();

$conn = (new Database())->connect();
affEnsureTables($conn);

$uid = (int)($_SESSION['user_id'] ?? 0);
$rules = affRules($conn);

$msg = '';
$err = '';
$affiliate = affGetByUserId($conn, $uid);

// POST
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

    if ($action === 'update_profile' && $affiliate) {
        $pixType = trim((string)($_POST['pix_key_type'] ?? ''));
        $pixKey  = trim((string)($_POST['pix_key'] ?? ''));
        $bio     = trim((string)($_POST['bio'] ?? ''));
        [$ok, $message] = affUpdateProfile($conn, (int)$affiliate['id'], $pixType ?: null, $pixKey ?: null, $bio ?: null);
        if ($ok) { $msg = $message; $affiliate = affGetByUserId($conn, $uid); }
        else $err = $message;
    }

    if ($action === 'request_payout' && $affiliate) {
        [$ok, $message] = affRequestPayout($conn, (int)$affiliate['id']);
        if ($ok) $msg = $message; else $err = $message;
    }
}

// Stats
$stats = null;
$conversions = null;
$payouts = null;
if ($affiliate) {
    $stats = affDashboardStats($conn, (int)$affiliate['id']);
    $pp    = in_array(($_ppU = (int)($_GET['pp'] ?? 10)), [5,10,20]) ? $_ppU : 10;
    $convPage = max(1, (int)($_GET['conv_page'] ?? 1));
    $conversions = affListConversions($conn, (int)$affiliate['id'], $convPage, $pp);
    $payoutPage = max(1, (int)($_GET['pay_page'] ?? 1));
    $payouts = affListPayouts($conn, (int)$affiliate['id'], $payoutPage, $pp);
}

$pageTitle = 'Programa de Afiliados';
$activeMenu = 'afiliados';

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/user_layout_start.php';

$statusBadge = function(string $s): string {
    return match($s) {
        'ativo','aprovada','pago','paga' => 'bg-greenx/15 border-greenx/40 text-greenx',
        'pendente'  => 'bg-yellow-500/15 border-yellow-400/40 text-yellow-300',
        'suspenso','rejeitado','rejeitada','cancelada' => 'bg-red-500/15 border-red-400/40 text-red-300',
        default => 'bg-zinc-500/15 border-zinc-400/40 text-zinc-300',
    };
};

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
?>

<div class="space-y-6">

  <?php if ($msg): ?>
    <div class="rounded-xl bg-greenx/10 border border-greenx/30 p-4 text-sm text-greenx flex items-center gap-2 animate-fade-in">
      <i data-lucide="check-circle" class="w-5 h-5 flex-shrink-0"></i> <?= htmlspecialchars($msg) ?>
    </div>
  <?php endif; ?>
  <?php if ($err): ?>
    <div class="rounded-xl bg-red-500/10 border border-red-400/30 p-4 text-sm text-red-300 flex items-center gap-2 animate-fade-in">
      <i data-lucide="alert-circle" class="w-5 h-5 flex-shrink-0"></i> <?= htmlspecialchars($err) ?>
    </div>
  <?php endif; ?>

  <?php if (!$affiliate): ?>
  <!-- ===== SIGN UP ===== -->
  <div class="bg-blackx2 border border-blackx3 rounded-2xl overflow-hidden">
    <div class="p-6 md:p-8 text-center">
      <div class="w-16 h-16 rounded-2xl bg-greenx/10 border border-greenx/30 flex items-center justify-center mx-auto mb-4">
        <i data-lucide="share-2" class="w-8 h-8 text-greenx"></i>
      </div>
      <h2 class="text-2xl font-bold mb-2"><?= htmlspecialchars($rules['program_name']) ?></h2>
      <p class="text-zinc-400 max-w-lg mx-auto mb-6"><?= htmlspecialchars($rules['program_description']) ?></p>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 max-w-2xl mx-auto mb-8">
        <div class="p-4 rounded-xl bg-blackx border border-blackx3">
          <i data-lucide="link" class="w-6 h-6 text-greenx mx-auto mb-2"></i>
          <p class="text-sm font-semibold mb-1">1. Cadastre-se</p>
          <p class="text-xs text-zinc-500">Receba seu link exclusivo de indicação</p>
        </div>
        <div class="p-4 rounded-xl bg-blackx border border-blackx3">
          <i data-lucide="share-2" class="w-6 h-6 text-purple-400 mx-auto mb-2"></i>
          <p class="text-sm font-semibold mb-1">2. Compartilhe</p>
          <p class="text-xs text-zinc-500">Divulgue para amigos e seguidores</p>
        </div>
        <div class="p-4 rounded-xl bg-blackx border border-blackx3">
          <i data-lucide="coins" class="w-6 h-6 text-yellow-400 mx-auto mb-2"></i>
          <p class="text-sm font-semibold mb-1">3. Ganhe</p>
          <p class="text-xs text-zinc-500"><?= number_format($rules['commission_percent'], 1) ?>% de comissão por venda</p>
        </div>
      </div>

      <?php if ($rules['program_enabled']): ?>
      <form method="post" class="max-w-md mx-auto space-y-4 text-left" data-pix-mask-group>
        <input type="hidden" name="action" value="register">
        <div>
          <label class="block text-xs text-zinc-400 mb-1">Tipo da chave PIX</label>
          <select name="pix_key_type" class="w-full bg-blackx border border-blackx3 rounded-xl px-3 py-2.5 text-sm text-white focus:border-greenx outline-none">
            <option value="">Selecione (opcional)</option>
            <option value="cpf">CPF</option>
            <option value="email">E-mail</option>
            <option value="telefone">Telefone</option>
            <option value="aleatoria">Chave aleatória</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-zinc-400 mb-1">Chave PIX</label>
          <input type="text" name="pix_key" placeholder="Selecione o tipo da chave" disabled
                 class="w-full bg-blackx border border-blackx3 rounded-xl px-3 py-2.5 text-sm text-white focus:border-greenx outline-none disabled:opacity-40 disabled:cursor-not-allowed">
        </div>
        <div>
          <label class="block text-xs text-zinc-400 mb-1">Bio / Descrição <span class="text-zinc-600">(opcional)</span></label>
          <textarea name="bio" rows="2" placeholder="Conte como pretende divulgar os produtos..."
                    class="w-full bg-blackx border border-blackx3 rounded-xl px-3 py-2.5 text-sm text-white focus:border-greenx outline-none resize-none"></textarea>
        </div>
        <button type="submit" class="w-full py-3 rounded-xl bg-greenx hover:bg-greenx2 text-white font-bold text-sm transition">
          Quero ser Afiliado
        </button>
        <p class="text-[11px] text-zinc-600 text-center">Cookie de rastreamento: <?= $rules['cookie_days'] ?> dias • Saque mínimo: R$ <?= number_format($rules['min_payout'], 2, ',', '.') ?></p>
      </form>
      <?php else: ?>
      <div class="p-4 rounded-xl bg-yellow-500/10 border border-yellow-400/30 text-yellow-300 text-sm max-w-md mx-auto">
        O programa de afiliados está temporariamente fechado para novos cadastros.
      </div>
      <?php endif; ?>
    </div>
  </div>

  <?php elseif ($affiliate['status'] === 'pendente'): ?>
  <!-- ===== PENDING ===== -->
  <div class="bg-blackx2 border border-blackx3 rounded-2xl p-6 md:p-8 text-center">
    <div class="w-16 h-16 rounded-2xl bg-yellow-500/10 border border-yellow-400/30 flex items-center justify-center mx-auto mb-4">
      <i data-lucide="clock" class="w-8 h-8 text-yellow-400"></i>
    </div>
    <h2 class="text-xl font-bold mb-2">Cadastro em Análise</h2>
    <p class="text-zinc-400 max-w-md mx-auto mb-2">Seu cadastro no programa de afiliados está sendo analisado pela nossa equipe.</p>
    <p class="text-sm text-zinc-500">Código reservado: <code class="bg-blackx border border-blackx3 rounded px-2 py-0.5 text-greenx font-mono"><?= htmlspecialchars($affiliate['referral_code']) ?></code></p>
  </div>

  <?php elseif ($affiliate['status'] === 'suspenso' || $affiliate['status'] === 'rejeitado'): ?>
  <!-- ===== SUSPENDED / REJECTED ===== -->
  <div class="bg-blackx2 border border-blackx3 rounded-2xl p-6 md:p-8 text-center">
    <div class="w-16 h-16 rounded-2xl bg-red-500/10 border border-red-400/30 flex items-center justify-center mx-auto mb-4">
      <i data-lucide="ban" class="w-8 h-8 text-red-400"></i>
    </div>
    <h2 class="text-xl font-bold mb-2">Conta <?= ucfirst($affiliate['status']) ?></h2>
    <p class="text-zinc-400 max-w-md mx-auto">Sua conta de afiliado está <?= $affiliate['status'] ?>. Entre em contato com o suporte para mais informações.</p>
  </div>

  <?php else: ?>
  <!-- ===== ACTIVE DASHBOARD ===== -->

  <!-- Referral Link -->
  <div class="bg-blackx2 border border-blackx3 rounded-2xl p-5" x-data="{ copied: false }">
    <div class="flex items-center gap-3 mb-3">
      <div class="w-10 h-10 rounded-xl bg-greenx/10 border border-greenx/30 flex items-center justify-center">
        <i data-lucide="link" class="w-5 h-5 text-greenx"></i>
      </div>
      <div>
        <h3 class="text-sm font-semibold">Seu link de indicação</h3>
        <p class="text-xs text-zinc-500">Compartilhe este link para ganhar comissão</p>
      </div>
    </div>
    <div class="flex gap-2">
      <input type="text" id="refLink" readonly
             value="<?= htmlspecialchars($baseUrl . (defined('BASE_PATH') ? BASE_PATH : '') . '/ref/' . $affiliate['referral_code']) ?>"
             class="flex-1 bg-blackx border border-blackx3 rounded-xl px-3 py-2.5 text-sm text-greenx font-mono select-all focus:border-greenx outline-none">
      <button @click="navigator.clipboard.writeText(document.getElementById('refLink').value); copied = true; setTimeout(() => copied = false, 2000)"
              class="px-4 py-2.5 rounded-xl border border-greenx bg-greenx/15 text-greenx text-sm font-semibold hover:bg-greenx/25 transition flex items-center gap-1.5">
        <i data-lucide="copy" class="w-4 h-4" x-show="!copied"></i>
        <i data-lucide="check" class="w-4 h-4" x-show="copied" x-cloak></i>
        <span x-text="copied ? 'Copiado!' : 'Copiar'"></span>
      </button>
    </div>
    <p class="text-[11px] text-zinc-600 mt-2">Código: <code class="text-greenx"><?= $affiliate['referral_code'] ?></code> • Também funciona: <code class="text-zinc-400"><?= htmlspecialchars($baseUrl . (defined('BASE_PATH') ? BASE_PATH : '') . '/?ref=' . $affiliate['referral_code']) ?></code></p>
  </div>

  <!-- KPI Cards -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
    <?php
    $kpis = [
        ['label' => 'Cliques (30d)',       'value' => number_format($stats['clicks_30d'] ?? 0),                         'icon' => 'mouse-pointer-click', 'color' => 'text-purple-400'],
        ['label' => 'Conversões (30d)',     'value' => number_format($stats['conversions_30d'] ?? 0),                    'icon' => 'shopping-cart',       'color' => 'text-cyan-400'],
        ['label' => 'Taxa conversão',       'value' => ($stats['conversion_rate'] ?? 0) . '%',                          'icon' => 'percent',             'color' => 'text-purple-400'],
        ['label' => 'Ganhos (30d)',         'value' => 'R$ ' . number_format($stats['earned_30d'] ?? 0, 2, ',', '.'),   'icon' => 'trending-up',         'color' => 'text-greenx'],
        ['label' => 'Total de cliques',     'value' => number_format((int)($affiliate['total_clicks'] ?? 0)),            'icon' => 'eye',                 'color' => 'text-zinc-400'],
        ['label' => 'Total conversões',     'value' => number_format((int)($affiliate['total_conversions'] ?? 0)),       'icon' => 'check-circle',        'color' => 'text-greenx'],
        ['label' => 'Total ganho',          'value' => 'R$ ' . number_format((float)($affiliate['total_earned'] ?? 0), 2, ',', '.'), 'icon' => 'coins',  'color' => 'text-yellow-400'],
        ['label' => 'Saldo disponível',     'value' => 'R$ ' . number_format($stats['balance'] ?? 0, 2, ',', '.'),     'icon' => 'wallet',              'color' => 'text-greenx'],
    ];
    foreach ($kpis as $i => $kpi): ?>
    <div class="bg-blackx2 border border-blackx3 rounded-xl p-4">
      <div class="flex items-center gap-2 mb-2">
        <i data-lucide="<?= $kpi['icon'] ?>" class="w-4 h-4 <?= $kpi['color'] ?>"></i>
        <span class="text-[11px] text-zinc-500 uppercase tracking-wider"><?= $kpi['label'] ?></span>
      </div>
      <div class="text-lg font-bold <?= $kpi['color'] ?>"><?= $kpi['value'] ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Chart: Daily Clicks / Conversions (7d) -->
  <?php if (!empty($stats['daily_clicks']) || !empty($stats['daily_conversions'])): ?>
  <div class="bg-blackx2 border border-blackx3 rounded-2xl p-5">
    <h3 class="text-sm font-semibold mb-4 flex items-center gap-2"><i data-lucide="bar-chart-3" class="w-4 h-4 text-greenx"></i> Últimos 7 dias</h3>
    <div class="flex items-end gap-1 h-32">
      <?php
      $days = [];
      for ($d = 6; $d >= 0; $d--) $days[] = date('Y-m-d', strtotime("-{$d} days"));
      $clickMap = [];
      foreach ($stats['daily_clicks'] as $dc) $clickMap[$dc['day']] = (int)$dc['cnt'];
      $convMap = [];
      foreach ($stats['daily_conversions'] as $dc) $convMap[$dc['day']] = (int)$dc['cnt'];
      $maxVal = max(1, max(array_values($clickMap) ?: [0]), max(array_values($convMap) ?: [0]));
      foreach ($days as $day):
        $cl = $clickMap[$day] ?? 0;
        $cv = $convMap[$day] ?? 0;
        $clH = max(4, (int)($cl / $maxVal * 100));
        $cvH = max(0, (int)($cv / $maxVal * 100));
      ?>
        <div class="flex-1 flex flex-col items-center gap-0.5" title="<?= date('d/m', strtotime($day)) ?>: <?= $cl ?> cliques, <?= $cv ?> conversões">
          <div class="w-full flex items-end gap-px justify-center h-24">
            <div class="w-1/3 bg-purple-500/40 rounded-t" style="height:<?= $clH ?>%"></div>
            <div class="w-1/3 bg-greenx/50 rounded-t" style="height:<?= $cvH ?>%"></div>
          </div>
          <span class="text-[9px] text-zinc-600"><?= date('d/m', strtotime($day)) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="flex items-center gap-4 mt-3 text-[10px] text-zinc-500">
      <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-purple-500/40"></span> Cliques</span>
      <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-greenx/50"></span> Conversões</span>
    </div>
  </div>
  <?php endif; ?>

  <!-- Payout Request + Profile -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <!-- Payout -->
    <div class="bg-blackx2 border border-blackx3 rounded-2xl p-5">
      <h3 class="text-sm font-semibold mb-3 flex items-center gap-2"><i data-lucide="wallet" class="w-4 h-4 text-greenx"></i> Solicitar saque</h3>
      <div class="space-y-3">
        <div class="flex justify-between text-sm">
          <span class="text-zinc-400">Saldo disponível</span>
          <span class="font-bold text-greenx">R$ <?= number_format($stats['balance'] ?? 0, 2, ',', '.') ?></span>
        </div>
        <div class="flex justify-between text-sm">
          <span class="text-zinc-400">Mínimo para saque</span>
          <span class="text-zinc-300">R$ <?= number_format($rules['min_payout'], 2, ',', '.') ?></span>
        </div>
        <div class="flex justify-between text-sm">
          <span class="text-zinc-400">Chave PIX</span>
          <span class="text-zinc-300 text-xs"><?= htmlspecialchars(strtoupper($affiliate['pix_key_type'] ?? '') . ': ' . ($affiliate['pix_key'] ?? 'Não configurada')) ?></span>
        </div>
        <form method="post">
          <input type="hidden" name="action" value="request_payout">
          <button type="submit" <?= ($stats['balance'] ?? 0) < $rules['min_payout'] ? 'disabled' : '' ?>
                  class="w-full py-2.5 rounded-xl bg-greenx hover:bg-greenx2 text-white font-bold text-sm transition disabled:opacity-30 disabled:cursor-not-allowed">
            Solicitar Saque
          </button>
        </form>
      </div>
    </div>

    <!-- Profile Update -->
    <div class="bg-blackx2 border border-blackx3 rounded-2xl p-5">
      <h3 class="text-sm font-semibold mb-3 flex items-center gap-2"><i data-lucide="settings" class="w-4 h-4 text-zinc-400"></i> Perfil de afiliado</h3>
      <form method="post" class="space-y-3" data-pix-mask-group>
        <input type="hidden" name="action" value="update_profile">
        <div>
          <label class="block text-xs text-zinc-400 mb-1">Tipo chave PIX</label>
          <select name="pix_key_type" class="w-full bg-blackx border border-blackx3 rounded-xl px-3 py-2 text-sm text-white focus:border-greenx outline-none">
            <option value="">Selecione</option>
            <?php foreach (['cpf'=>'CPF','email'=>'E-mail','telefone'=>'Telefone','aleatoria'=>'Chave aleatória'] as $v => $l): ?>
              <option value="<?= $v ?>" <?= ($affiliate['pix_key_type'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs text-zinc-400 mb-1">Chave PIX</label>
          <input type="text" name="pix_key" value="<?= htmlspecialchars($affiliate['pix_key'] ?? '') ?>"
                 class="w-full bg-blackx border border-blackx3 rounded-xl px-3 py-2 text-sm text-white focus:border-greenx outline-none disabled:opacity-40 disabled:cursor-not-allowed">
        </div>
        <div>
          <label class="block text-xs text-zinc-400 mb-1">Bio</label>
          <textarea name="bio" rows="2" class="w-full bg-blackx border border-blackx3 rounded-xl px-3 py-2 text-sm text-white focus:border-greenx outline-none resize-none"><?= htmlspecialchars($affiliate['bio'] ?? '') ?></textarea>
        </div>
        <button type="submit" class="w-full py-2 rounded-xl border border-greenx text-greenx text-sm font-semibold hover:bg-greenx/10 transition">
          Salvar
        </button>
      </form>
    </div>
  </div>

  <!-- Conversions Table -->
  <div class="bg-blackx2 border border-blackx3 rounded-2xl overflow-hidden">
    <div class="p-4 border-b border-blackx3 flex items-center gap-2">
      <i data-lucide="shopping-cart" class="w-4 h-4 text-cyan-400"></i>
      <h3 class="text-sm font-semibold">Minhas conversões</h3>
      <span class="ml-auto text-xs text-zinc-500"><?= $conversions['total'] ?> no total</span>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-[11px] uppercase tracking-wider text-zinc-500 border-b border-blackx3">
            <th class="text-center px-4 py-3">Pedido</th>
            <th class="text-right px-4 py-3">Total</th>
            <th class="text-center px-4 py-3">Taxa</th>
            <th class="text-right px-4 py-3">Comissão</th>
            <th class="text-center px-4 py-3">Status</th>
            <th class="text-center px-4 py-3">Data</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-blackx3/50">
          <?php if (empty($conversions['items'])): ?>
            <tr><td colspan="6" class="px-4 py-8 text-center text-zinc-600">Nenhuma conversão ainda. Compartilhe seu link!</td></tr>
          <?php endif; ?>
          <?php foreach ($conversions['items'] as $c): ?>
            <tr class="hover:bg-blackx/50 transition">
              <td class="px-4 py-3 text-center text-xs">#<?= $c['order_id'] ?></td>
              <td class="px-4 py-3 text-right text-xs">R$ <?= number_format((float)$c['order_total'], 2, ',', '.') ?></td>
              <td class="px-4 py-3 text-center text-xs"><?= number_format((float)$c['commission_rate'], 1) ?>%</td>
              <td class="px-4 py-3 text-right font-medium text-greenx text-xs">R$ <?= number_format((float)$c['commission_amount'], 2, ',', '.') ?></td>
              <td class="px-4 py-3 text-center">
                <span class="px-2 py-0.5 rounded-full text-[11px] font-medium border <?= $statusBadge($c['status']) ?>"><?= ucfirst($c['status']) ?></span>
              </td>
              <td class="px-4 py-3 text-center text-xs text-zinc-500"><?= fmtDate($c['created_at'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
      $paginaAtual  = $convPage;
      $totalPaginas = (int)($conversions['pages'] ?? 1);
      include __DIR__ . '/../views/partials/pagination.php';
    ?>
  </div>

  <!-- Payouts Table -->
  <?php if (!empty($payouts['items'])): ?>
  <div class="bg-blackx2 border border-blackx3 rounded-2xl overflow-hidden">
    <div class="p-4 border-b border-blackx3 flex items-center gap-2">
      <i data-lucide="banknote" class="w-4 h-4 text-greenx"></i>
      <h3 class="text-sm font-semibold">Meus saques</h3>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-[11px] uppercase tracking-wider text-zinc-500 border-b border-blackx3">
            <th class="text-right px-4 py-3">Valor</th>
            <th class="text-left px-4 py-3">Chave PIX</th>
            <th class="text-center px-4 py-3">Status</th>
            <th class="text-center px-4 py-3">Solicitado</th>
            <th class="text-center px-4 py-3">Processado</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-blackx3/50">
          <?php foreach ($payouts['items'] as $po): ?>
            <tr class="hover:bg-blackx/50 transition">
              <td class="px-4 py-3 text-right font-bold text-greenx">R$ <?= number_format((float)$po['amount'], 2, ',', '.') ?></td>
              <td class="px-4 py-3 text-xs"><span class="text-zinc-500"><?= strtoupper($po['pix_key_type'] ?? '') ?>:</span> <?= htmlspecialchars($po['pix_key'] ?? '') ?></td>
              <td class="px-4 py-3 text-center">
                <span class="px-2 py-0.5 rounded-full text-[11px] font-medium border <?= $statusBadge($po['status']) ?>"><?= ucfirst($po['status']) ?></span>
              </td>
              <td class="px-4 py-3 text-center text-xs text-zinc-500"><?= fmtDate($po['requested_at'] ?? '') ?></td>
              <td class="px-4 py-3 text-center text-xs text-zinc-500"><?= $po['processed_at'] ? fmtDate($po['processed_at']) : '—' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
      $paginaAtual  = $payoutPage;
      $totalPaginas = (int)($payouts['pages'] ?? 1);
      include __DIR__ . '/../views/partials/pagination.php';
    ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>

</div>

<?php
include __DIR__ . '/../views/partials/user_layout_end.php';
?>
<script src="<?= BASE_PATH ?>/assets/js/pix-mask.js"></script>
<?php
include __DIR__ . '/../views/partials/footer.php';
?>
