<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/theme.php';
exigirAdmin();

$conn = (new Database())->connect();

// Handle form submission
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['set_theme'])) {
        $defs = themeDefinitions();
        $newTheme = (string)($_POST['set_theme'] ?? '');
        if (isset($defs[$newTheme])) {
            themeSettingSet($conn, 'active_theme', $newTheme);
            $msg = 'Tema alterado para ' . $defs[$newTheme]['label'] . '.';
        }
    }
    if (isset($_POST['set_mode'])) {
        $newMode = (string)($_POST['set_mode'] ?? '');
        if (in_array($newMode, ['dark', 'light'], true)) {
            themeSettingSet($conn, 'color_mode', $newMode);
            $msg = 'Modo alterado para ' . ($newMode === 'dark' ? 'escuro' : 'claro') . '.';
        }
    }
    if ($msg) {
        header('Location: ' . BASE_PATH . '/admin/temas?msg=' . urlencode($msg));
        exit;
    }
}

$msg = (string)($_GET['msg'] ?? '');
$info = themeFullInfo($conn);
$pageTitle = 'Gerenciamento de Temas';
$activeMenu = 'temas';
include __DIR__ . '/../../views/partials/header.php';
include __DIR__ . '/../../views/partials/admin_layout_start.php';
?>

<div class="space-y-6">

  <?php if ($msg): ?>
  <div class="rounded-xl bg-greenx/15 border border-greenx/40 text-greenx px-4 py-3 text-sm animate-fade-in">
    <i data-lucide="check-circle" class="w-4 h-4 inline mr-1.5"></i><?= htmlspecialchars($msg) ?>
  </div>
  <?php endif; ?>

  <!-- Mode Toggle -->
  <div class="bg-blackx2 border border-blackx3 rounded-2xl p-6">
    <h2 class="text-lg font-bold mb-4 flex items-center gap-2">
      <i data-lucide="sun-moon" class="w-5 h-5 text-greenx"></i> Modo de cor
    </h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <!-- Dark Mode -->
      <form method="post">
        <input type="hidden" name="set_mode" value="dark">
        <button type="submit" class="w-full text-left p-4 rounded-xl border-2 transition-all <?= $info['color_mode'] === 'dark' ? 'border-greenx bg-greenx/10' : 'border-blackx3 hover:border-zinc-600' ?>">
          <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-xl bg-[#0B0B0C] border border-zinc-700 flex items-center justify-center">
              <i data-lucide="moon" class="w-5 h-5 text-zinc-300"></i>
            </div>
            <div>
              <p class="font-semibold">Modo Escuro</p>
              <p class="text-xs text-zinc-400">Backgrounds escuros, texto claro</p>
            </div>
            <?php if ($info['color_mode'] === 'dark'): ?>
            <span class="ml-auto px-2 py-0.5 rounded-full text-[10px] font-bold bg-greenx/20 text-greenx border border-greenx/30">ATIVO</span>
            <?php endif; ?>
          </div>
          <div class="flex gap-1.5">
            <span class="w-8 h-5 rounded" style="background:#0B0B0C;border:1px solid #333"></span>
            <span class="w-8 h-5 rounded" style="background:#111214;border:1px solid #333"></span>
            <span class="w-8 h-5 rounded" style="background:#1A1C20;border:1px solid #333"></span>
            <span class="w-8 h-5 rounded" style="background:#FFFFFF;border:1px solid #333"></span>
            <span class="w-8 h-5 rounded" style="background:#A1A1AA;border:1px solid #333"></span>
          </div>
        </button>
      </form>

      <!-- Light Mode -->
      <form method="post">
        <input type="hidden" name="set_mode" value="light">
        <button type="submit" class="w-full text-left p-4 rounded-xl border-2 transition-all <?= $info['color_mode'] === 'light' ? 'border-greenx bg-greenx/10' : 'border-blackx3 hover:border-zinc-600' ?>">
          <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-xl bg-[#F8FAFC] border border-slate-200 flex items-center justify-center">
              <i data-lucide="sun" class="w-5 h-5 text-amber-500"></i>
            </div>
            <div>
              <p class="font-semibold">Modo Claro</p>
              <p class="text-xs text-zinc-400">Backgrounds claros, texto escuro</p>
            </div>
            <?php if ($info['color_mode'] === 'light'): ?>
            <span class="ml-auto px-2 py-0.5 rounded-full text-[10px] font-bold bg-greenx/20 text-greenx border border-greenx/30">ATIVO</span>
            <?php endif; ?>
          </div>
          <div class="flex gap-1.5">
            <span class="w-8 h-5 rounded" style="background:#F8FAFC;border:1px solid #ccc"></span>
            <span class="w-8 h-5 rounded" style="background:#FFFFFF;border:1px solid #ccc"></span>
            <span class="w-8 h-5 rounded" style="background:#E2E8F0;border:1px solid #ccc"></span>
            <span class="w-8 h-5 rounded" style="background:#0F172A;border:1px solid #ccc"></span>
            <span class="w-8 h-5 rounded" style="background:#475569;border:1px solid #ccc"></span>
          </div>
        </button>
      </form>
    </div>
  </div>

  <!-- Theme Selector -->
  <div class="bg-blackx2 border border-blackx3 rounded-2xl p-6">
    <h2 class="text-lg font-bold mb-4 flex items-center gap-2">
      <i data-lucide="palette" class="w-5 h-5 text-greenx"></i> Temas disponíveis
    </h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <?php foreach ($info['themes'] as $key => $theme): ?>
      <div class="rounded-2xl border-2 overflow-hidden transition-all <?= $theme['is_active'] ? 'border-greenx shadow-lg shadow-greenx/10' : 'border-blackx3 hover:border-zinc-600' ?>">
        <!-- Theme Preview -->
        <div class="p-4" style="background:<?= $theme['dark']['bg_body'] ?>">
          <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:linear-gradient(135deg,<?= $theme['dark']['gradient_from'] ?>,<?= $theme['dark']['gradient_to'] ?>)">
              <i data-lucide="sparkles" class="w-5 h-5" style="color:<?= $theme['dark']['text_on_accent'] ?>"></i>
            </div>
            <div>
              <p class="font-bold" style="color:#fff"><?= htmlspecialchars($theme['label']) ?></p>
              <p class="text-xs" style="color:#a1a1aa"><?= htmlspecialchars($theme['description']) ?></p>
            </div>
          </div>
          <!-- Mini preview -->
          <div class="rounded-xl p-3 mb-3" style="background:<?= $theme['dark']['bg_card'] ?>;border:1px solid <?= $theme['dark']['bg_border'] ?>">
            <div class="flex items-center gap-2 mb-2">
              <span class="w-2 h-2 rounded-full" style="background:<?= $theme['dark']['accent'] ?>"></span>
              <span class="text-xs font-medium" style="color:#fff">Preview do card</span>
            </div>
            <div class="h-2 rounded-full w-3/4 mb-1.5" style="background:<?= $theme['dark']['bg_border'] ?>"></div>
            <div class="h-2 rounded-full w-1/2" style="background:<?= $theme['dark']['bg_border'] ?>"></div>
            <div class="mt-3 flex gap-2">
              <span class="px-3 py-1 rounded-lg text-xs font-semibold" style="background:<?= $theme['dark']['accent'] ?>;color:<?= $theme['dark']['text_on_accent'] ?>">Botão</span>
              <span class="px-3 py-1 rounded-lg text-xs font-semibold" style="background:<?= $theme['dark']['accent_soft'] ?>;color:<?= $theme['dark']['accent'] ?>">Badge</span>
            </div>
          </div>
          <!-- Color palette -->
          <div class="flex gap-1.5 mb-2">
            <?php foreach ($theme['dark'] as $colorKey => $hex):
              if (strpos($colorKey, 'rgb') !== false || strpos($colorKey, 'text_on') !== false) continue;
            ?>
            <div class="group relative">
              <span class="block w-7 h-7 rounded-lg border border-white/10 cursor-help" style="background:<?= $hex ?>" title="<?= $colorKey ?>: <?= $hex ?>"></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <!-- Action -->
        <div class="px-4 py-3 border-t border-blackx3 flex items-center justify-between">
          <?php if ($theme['is_active']): ?>
            <span class="flex items-center gap-1.5 text-sm text-greenx font-semibold">
              <i data-lucide="check-circle-2" class="w-4 h-4"></i> Tema ativo
            </span>
          <?php else: ?>
            <form method="post">
              <input type="hidden" name="set_theme" value="<?= $key ?>">
              <button type="submit" class="px-4 py-2 rounded-xl bg-greenx hover:bg-greenx2 text-white font-semibold text-sm transition-all">
                Ativar tema
              </button>
            </form>
          <?php endif; ?>
          <a href="<?= BASE_PATH ?>/admin/tema_detalhes?t=<?= $key ?>" class="text-xs text-zinc-400 hover:text-greenx transition flex items-center gap-1">
            <i data-lucide="eye" class="w-3.5 h-3.5"></i> Detalhes
          </a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

</div>

<?php
include __DIR__ . '/../../views/partials/admin_layout_end.php';
include __DIR__ . '/../../views/partials/footer.php';
