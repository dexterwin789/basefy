<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/theme.php';
exigirAdmin();

$conn = (new Database())->connect();
$themeKey = (string)($_GET['t'] ?? 'green');
$defs = themeDefinitions();
if (!isset($defs[$themeKey])) {
    header('Location: ' . BASE_PATH . '/admin/temas');
    exit;
}

$theme = $defs[$themeKey];
$active = themeGetActive($conn);

$pageTitle = 'Detalhes: ' . $theme['label'];
$activeMenu = 'temas';
include __DIR__ . '/../../views/partials/header.php';
include __DIR__ . '/../../views/partials/admin_layout_start.php';

$colorLabels = [
    'bg_body'        => ['Fundo principal',     'Body background', 'body, .bg-blackx'],
    'bg_card'        => ['Fundo de cards',      'Cards, sidebars, popups', '.bg-blackx2, aside, .glass'],
    'bg_border'      => ['Bordas / separadores','Dividers, borders', '.border-blackx3, hr'],
    'accent'         => ['Cor de destaque',     'CTAs, links, badges ativos', '.bg-greenx, .text-greenx, buttons'],
    'accent_hover'   => ['Destaque hover',      'Hover em botões e links', '.hover:bg-greenx2'],
    'accent_soft'    => ['Destaque suave',       'Backgrounds translúcidos de badges', '.bg-greenx/15, .bg-greenx/10'],
    'gradient_from'  => ['Gradiente início',     'Gradient buttons, hero sections', 'from-greenx'],
    'gradient_to'    => ['Gradiente fim',        'Gradient end color', 'to-greenx'],
    'text_on_accent' => ['Texto sobre destaque', 'Text on accent backgrounds', '.text-black (green), .text-white (blue)'],
    'scrollbar_hover'=> ['Scrollbar hover',      'Custom scrollbar thumb hover', '::-webkit-scrollbar-thumb:hover'],
];
?>

<div class="max-w-4xl mx-auto space-y-6">

  <div class="flex items-center gap-3 mb-2">
    <a href="<?= BASE_PATH ?>/admin/temas" class="rounded-lg border border-blackx3 px-3 py-1.5 text-xs hover:border-greenx transition">← Voltar</a>
    <h1 class="text-xl font-bold"><?= htmlspecialchars($theme['label']) ?></h1>
    <?php if ($themeKey === $active['active_theme']): ?>
    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-greenx/20 text-greenx border border-greenx/30">ATIVO</span>
    <?php endif; ?>
  </div>

  <p class="text-sm text-zinc-400"><?= htmlspecialchars($theme['description']) ?></p>

  <?php foreach (['dark' => 'Modo Escuro', 'light' => 'Modo Claro'] as $mode => $modeLabel): ?>
  <div class="bg-blackx2 border border-blackx3 rounded-2xl p-6">
    <h2 class="text-base font-bold mb-4 flex items-center gap-2">
      <i data-lucide="<?= $mode === 'dark' ? 'moon' : 'sun' ?>" class="w-4 h-4 text-greenx"></i>
      <?= $modeLabel ?>
    </h2>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <?php foreach ($theme[$mode] as $token => $hex):
        if (strpos($token, 'rgb') !== false || strpos($token, 'pulse') !== false) continue;
        $label = $colorLabels[$token] ?? [$token, '', ''];
      ?>
      <div class="rounded-xl border border-blackx3 p-3 hover:border-greenx/30 transition">
        <div class="flex items-center gap-3 mb-2">
          <span class="w-10 h-10 rounded-xl border border-white/10 flex-shrink-0" style="background:<?= $hex ?>"></span>
          <div class="min-w-0">
            <p class="font-semibold text-sm truncate"><?= htmlspecialchars($label[0]) ?></p>
            <p class="text-[10px] text-zinc-500 font-mono"><?= htmlspecialchars($hex) ?></p>
          </div>
        </div>
        <p class="text-xs text-zinc-400 mb-1"><?= htmlspecialchars($label[1]) ?></p>
        <?php if (!empty($label[2])): ?>
        <p class="text-[10px] text-zinc-600 font-mono">Classes: <?= htmlspecialchars($label[2]) ?></p>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>

</div>

<?php
include __DIR__ . '/../../views/partials/admin_layout_end.php';
include __DIR__ . '/../../views/partials/footer.php';
