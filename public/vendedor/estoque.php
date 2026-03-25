<?php
/**
 * Vendor — Estoque Automático (Stock Items Management)
 *
 * Allows vendors to add/manage individual stock items for auto-delivery.
 * Supports both simple products and dynamic products (per-variant items).
 */
declare(strict_types=1);
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/vendor_portal.php';
require_once __DIR__ . '/../../src/stock_items.php';
require_once __DIR__ . '/../../src/media.php';
exigirVendedor();

$db = new Database();
$conn = $db->connect();
$uid = (int)($_SESSION['user_id'] ?? 0);

stockEnsureTables($conn);

$productId = (int)($_GET['id'] ?? 0);
if ($productId < 1) { header('Location: produtos'); exit; }

// Verify vendor owns this product
$stP = $conn->prepare("SELECT p.id, p.nome, p.tipo, p.variantes, p.imagem, p.auto_delivery_enabled, p.auto_delivery_intro, p.auto_delivery_conclusion FROM products p WHERE p.id = ? AND p.vendedor_id = ? LIMIT 1");
$stP->bind_param('ii', $productId, $uid);
$stP->execute();
$produto = $stP->get_result()->fetch_assoc();
$stP->close();

if (!$produto) { header('Location: produtos'); exit; }

$tipo = (string)($produto['tipo'] ?? 'produto');
$variantes = [];
if ($tipo === 'dinamico') {
    $variantes = json_decode((string)($produto['variantes'] ?? ''), true) ?: [];
}

// Product image URL
$imgUrl = '';
if (!empty($produto['imagem'])) {
    if (str_starts_with($produto['imagem'], 'media:')) {
        $imgUrl = BASE_PATH . '/api/media?id=' . urlencode(substr($produto['imagem'], 6));
    } elseif (preg_match('~^https?://~i', $produto['imagem'])) {
        $imgUrl = $produto['imagem'];
    } else {
        $imgUrl = BASE_PATH . '/' . ltrim($produto['imagem'], '/');
    }
}

$msg = '';
$err = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = (string)($_POST['stock_action'] ?? '');

    // Save delivery config (intro + conclusion + enable)
    if ($postAction === 'save_config') {
        $enabled = isset($_POST['auto_delivery_enabled']);
        $intro = trim((string)($_POST['auto_delivery_intro'] ?? ''));
        $conclusion = trim((string)($_POST['auto_delivery_conclusion'] ?? ''));
        stockSaveDeliveryConfig($conn, $productId, $enabled, $intro, $conclusion);
        $produto['auto_delivery_enabled'] = $enabled;
        $produto['auto_delivery_intro'] = $intro;
        $produto['auto_delivery_conclusion'] = $conclusion;
        $msg = 'Configurações de entrega automática salvas!';
    }

    // Add items (bulk)
    if ($postAction === 'add_items') {
        $varianteNome = ($tipo === 'dinamico') ? trim((string)($_POST['variante_nome'] ?? '')) : null;
        $conteudo = trim((string)($_POST['conteudo'] ?? ''));

        if ($conteudo === '') {
            $err = 'Preencha o conteúdo dos itens.';
        } else {
            $lines = explode("\n", $conteudo);
            $added = stockAddBulk($conn, $productId, $lines, $varianteNome);
            if ($added > 0) {
                $msg = $added . ' item(ns) adicionado(s) ao estoque!';
            } else {
                $err = 'Nenhum item válido para adicionar.';
            }
        }
    }

    // Edit single item
    if ($postAction === 'edit_item') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $newContent = trim((string)($_POST['new_content'] ?? ''));
        if ($itemId < 1 || $newContent === '') {
            $err = 'Dados inválidos para edição.';
        } else {
            if (stockEditItem($conn, $itemId, $productId, $newContent)) {
                $msg = 'Item atualizado!';
            } else {
                $err = 'Não foi possível editar (item pode já ter sido vendido).';
            }
        }
    }

    // Delete single item
    if ($postAction === 'delete_item') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        if ($itemId > 0 && stockDeleteItem($conn, $itemId, $productId)) {
            $msg = 'Item removido do estoque.';
        } else {
            $err = 'Não foi possível remover (item pode já ter sido vendido).';
        }
    }
}

// Load data for display
$filterVariante = ($tipo === 'dinamico') ? (string)($_GET['variante'] ?? '') : '';
$filterStatus = (string)($_GET['status'] ?? 'todos');
$pagina = max(1, (int)($_GET['p'] ?? 1));
$pp = in_array((int)($_GET['pp'] ?? 10), [5, 10, 20], true) ? (int)($_GET['pp'] ?? 10) : 10;

$statusArg = ($filterStatus !== '' && $filterStatus !== 'todos') ? $filterStatus : '';
$varArg = ($filterVariante !== '' && $filterVariante !== 'todos') ? $filterVariante : null;

$result = stockListItems($conn, $productId, $varArg, $statusArg, $pagina, $pp);
$items = $result['items'];
$totalItems = $result['total'];
$totalPaginas = $result['total_pages'];

$summary = stockSummaryByVariant($conn, $productId);
$totalDisponivel = 0;
foreach ($summary as $v) $totalDisponivel += ($v['disponivel'] ?? 0);

// For dynamic products, also account for variant quantities from product config
// If no stock items exist but variants have quantities, show variant quantities as available stock
$variantQtd = [];
if ($tipo === 'dinamico' && !empty($variantes)) {
    foreach ($variantes as $v) {
        $vNome = (string)($v['nome'] ?? '');
        $vQtd  = (int)($v['quantidade'] ?? 0);
        if ($vNome !== '') {
            $variantQtd[$vNome] = $vQtd;
            // If no stock items exist for this variant, show variant quantity as reference
            if (!isset($summary[$vNome])) {
                $summary[$vNome] = ['disponivel' => 0, 'vendido' => 0, 'total' => 0, 'config_qtd' => $vQtd];
            } else {
                $summary[$vNome]['config_qtd'] = $vQtd;
            }
        }
    }
}

// Recalculate total: use max of stock items vs configured quantity per variant
$totalDisponivel = 0;
if ($tipo === 'dinamico' && !empty($variantQtd)) {
    foreach ($variantes as $v) {
        $vNome = (string)($v['nome'] ?? '');
        $stockAvail = $summary[$vNome]['disponivel'] ?? 0;
        $configQtd  = $variantQtd[$vNome] ?? 0;
        $totalDisponivel += max($stockAvail, $configQtd);
    }
} else {
    foreach ($summary as $v) $totalDisponivel += ($v['disponivel'] ?? 0);
}

$deliveryConfig = stockGetDeliveryConfig($conn, $productId);

$pageTitle = 'Estoque Automático';
$activeMenu = 'produtos';
$subnavItems = [
    ['label' => 'Meus Produtos', 'href' => 'produtos', 'active' => false],
    ['label' => 'Estoque', 'href' => 'estoque?id=' . $productId, 'active' => true],
];

include __DIR__ . '/../../views/partials/header.php';
include __DIR__ . '/../../views/partials/vendor_layout_start.php';
?>

<div class="space-y-5" x-data="stockPage()">
  <?php if ($msg): ?>
    <div class="rounded-2xl border border-greenx/30 bg-greenx/[0.08] px-5 py-3.5 text-sm text-greenx flex items-center gap-3">
      <i data-lucide="check-circle-2" class="w-5 h-5 flex-shrink-0"></i>
      <span><?= htmlspecialchars($msg) ?></span>
    </div>
  <?php endif; ?>
  <?php if ($err): ?>
    <div class="rounded-2xl border border-red-500/30 bg-red-600/[0.08] px-5 py-3.5 text-sm text-red-300 flex items-center gap-3">
      <i data-lucide="alert-triangle" class="w-5 h-5 flex-shrink-0"></i>
      <span><?= htmlspecialchars($err) ?></span>
    </div>
  <?php endif; ?>

  <!-- Product header -->
  <div class="bg-blackx2 border border-blackx3 rounded-2xl p-5 flex items-center gap-4">
    <?php if ($imgUrl): ?>
      <img src="<?= htmlspecialchars($imgUrl) ?>" class="w-16 h-16 rounded-xl object-cover border border-blackx3 flex-shrink-0" alt="">
    <?php else: ?>
      <div class="w-16 h-16 rounded-xl bg-blackx3 flex items-center justify-center flex-shrink-0"><i data-lucide="package" class="w-6 h-6 text-zinc-600"></i></div>
    <?php endif; ?>
    <div class="flex-1 min-w-0">
      <h2 class="text-base font-bold truncate"><?= htmlspecialchars($produto['nome']) ?></h2>
      <p class="text-xs text-zinc-500 mt-0.5">Estoque disponível: <span class="text-greenx font-semibold"><?= $totalDisponivel ?></span></p>
    </div>
    <div class="flex items-center gap-2 flex-shrink-0">
      <a href="produtos_form?id=<?= $productId ?>" class="inline-flex items-center gap-1.5 rounded-xl border border-blackx3 hover:border-greenx px-3 py-2 text-xs text-zinc-300 hover:text-white transition">
        <i data-lucide="pencil" class="w-3.5 h-3.5"></i> Editar produto
      </a>
    </div>
  </div>

  <!-- Auto-delivery config -->
  <div class="bg-blackx2 border border-blackx3 rounded-2xl p-5" x-data="{ configOpen: false }">
    <div class="flex items-center justify-between cursor-pointer" @click="configOpen = !configOpen">
      <h3 class="text-sm font-semibold flex items-center gap-2">
        <i data-lucide="settings" class="w-4 h-4 text-amber-400"></i> Configurações de Entrega Automática
      </h3>
      <i data-lucide="chevron-down" class="w-4 h-4 text-zinc-500 transition-transform" :class="configOpen && 'rotate-180'"></i>
    </div>
    <div x-show="configOpen" x-transition x-cloak class="mt-4">
      <form method="post" class="space-y-4">
        <input type="hidden" name="stock_action" value="save_config">

        <label class="flex items-center gap-3 cursor-pointer select-none">
          <div class="relative">
            <input type="checkbox" name="auto_delivery_enabled" value="1" class="sr-only peer" <?= $deliveryConfig['enabled'] ? 'checked' : '' ?>>
            <div class="w-11 h-6 bg-blackx3 peer-focus:ring-2 peer-focus:ring-greenx/40 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-zinc-500 after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-greenx peer-checked:after:bg-white"></div>
          </div>
          <span class="text-sm text-zinc-300">Ativar entrega automática</span>
        </label>

        <div>
          <label class="block text-sm text-zinc-400 mb-1">Introdução <span class="text-zinc-600">(mensagem enviada antes do item)</span></label>
          <textarea name="auto_delivery_intro" rows="3" class="w-full bg-blackx border border-blackx3 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-greenx resize-y" placeholder="Obrigado pela compra! Segue seu item:"><?= htmlspecialchars($deliveryConfig['intro']) ?></textarea>
        </div>
        <div>
          <label class="block text-sm text-zinc-400 mb-1">Conclusão <span class="text-zinc-600">(mensagem enviada após o item)</span></label>
          <textarea name="auto_delivery_conclusion" rows="3" class="w-full bg-blackx border border-blackx3 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-greenx resize-y" placeholder="Qualquer dúvida, entre em contato. Boas compras!"><?= htmlspecialchars($deliveryConfig['conclusion']) ?></textarea>
        </div>

        <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-greenx to-greenxd text-white font-semibold px-5 py-2.5 text-sm hover:from-greenx2 hover:to-greenxd transition-all">
          <i data-lucide="save" class="w-4 h-4"></i> Salvar
        </button>
      </form>
    </div>
  </div>

  <!-- Add items section -->
  <div class="bg-blackx2 border border-blackx3 rounded-2xl p-5">
    <h3 class="text-sm font-semibold flex items-center gap-2 mb-4">
      <i data-lucide="plus-circle" class="w-4 h-4 text-greenx"></i> Adicionar item(s)
    </h3>
    <p class="text-xs text-zinc-500 mb-4">Adicione os itens/produtos que serão entregues ao comprador de forma automática assim que o pagamento for aprovado. Cada linha = 1 item.</p>

    <form method="post" class="space-y-4">
      <input type="hidden" name="stock_action" value="add_items">

      <?php if ($tipo === 'dinamico' && !empty($variantes)): ?>
      <div>
        <label class="block text-sm text-zinc-400 mb-1">Item do anúncio dinâmico *</label>
        <select name="variante_nome" required class="w-full bg-blackx border border-blackx3 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-greenx">
          <?php foreach ($variantes as $v):
            $vNome = (string)($v['nome'] ?? '');
            $vPreco = number_format((float)($v['preco'] ?? 0), 2, ',', '.');
            $vDisp = stockCountAvailable($conn, $productId, $vNome);
            $vConfigQtd = (int)($v['quantidade'] ?? 0);
          ?>
          <option value="<?= htmlspecialchars($vNome) ?>"><?= htmlspecialchars($vNome) ?> — R$ <?= $vPreco ?>/un — <?= $vDisp ?> itens prontos<?= $vConfigQtd > 0 ? " / {$vConfigQtd} configurado" : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>

      <div>
        <label class="block text-sm text-zinc-400 mb-1">O que deve ser enviado ao comprador? <span class="text-zinc-600">(um por linha)</span></label>
        <textarea name="conteudo" rows="6" required
                  class="w-full bg-blackx border border-blackx3 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-greenx resize-y font-mono"
                  placeholder="cole-aqui-a-chave-1&#10;cole-aqui-a-chave-2&#10;https://link-download.com&#10;email:senha&#10;..."></textarea>
        <p class="text-xs text-zinc-600 mt-1">Cada linha será um item separado no estoque.</p>
      </div>

      <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-greenx to-greenxd text-white font-semibold px-5 py-2.5 text-sm hover:from-greenx2 hover:to-greenxd transition-all">
        <i data-lucide="plus" class="w-4 h-4"></i> Adicionar
      </button>
    </form>
  </div>

  <!-- Stock summary -->
  <?php if ($summary): ?>
  <div class="bg-blackx2 border border-blackx3 rounded-2xl p-5">
    <h3 class="text-sm font-semibold flex items-center gap-2 mb-3">
      <i data-lucide="bar-chart-3" class="w-4 h-4 text-purple-400"></i> Resumo do estoque
    </h3>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
      <?php foreach ($summary as $varName => $stats): ?>
      <div class="rounded-xl border border-blackx3 bg-blackx/60 px-4 py-3">
        <p class="text-xs text-zinc-400 font-semibold mb-1"><?= htmlspecialchars((string)$varName) ?></p>
        <div class="flex items-center gap-3 text-xs flex-wrap">
          <?php if (isset($stats['config_qtd']) && $stats['config_qtd'] > 0): ?>
            <span class="text-purple-400 font-bold"><?= $stats['config_qtd'] ?> configurado</span>
          <?php endif; ?>
          <span class="text-greenx font-bold"><?= $stats['disponivel'] ?> itens prontos</span>
          <span class="text-zinc-500"><?= $stats['vendido'] ?? 0 ?> vendido</span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Items list -->
  <div class="bg-blackx2 border border-blackx3 rounded-2xl p-5">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
      <h3 class="text-sm font-semibold flex items-center gap-2">
        <i data-lucide="list" class="w-4 h-4 text-zinc-400"></i> Itens cadastrados
        <span class="text-zinc-500 font-normal">(<?= $totalItems ?>)</span>
      </h3>

      <!-- Filters -->
      <form method="get" class="flex items-center gap-2 text-xs">
        <input type="hidden" name="id" value="<?= $productId ?>">
        <select name="status" onchange="this.form.submit()" class="bg-blackx border border-blackx3 rounded-lg px-2.5 py-1.5 text-xs outline-none focus:border-greenx">
          <option value="todos" <?= $filterStatus === 'todos' ? 'selected' : '' ?>>Todos</option>
          <option value="disponivel" <?= $filterStatus === 'disponivel' ? 'selected' : '' ?>>Disponível</option>
          <option value="vendido" <?= $filterStatus === 'vendido' ? 'selected' : '' ?>>Vendido</option>
        </select>
        <?php if ($tipo === 'dinamico' && !empty($variantes)): ?>
        <select name="variante" onchange="this.form.submit()" class="bg-blackx border border-blackx3 rounded-lg px-2.5 py-1.5 text-xs outline-none focus:border-greenx">
          <option value="todos">Todas variantes</option>
          <?php foreach ($variantes as $v): ?>
          <option value="<?= htmlspecialchars((string)$v['nome']) ?>" <?= $filterVariante === (string)$v['nome'] ? 'selected' : '' ?>><?= htmlspecialchars((string)$v['nome']) ?></option>
          <?php endforeach; ?>
        </select>
        <?php endif; ?>
      </form>
    </div>

    <?php if (empty($items)): ?>
      <div class="text-center py-8 text-zinc-500">
        <i data-lucide="package-x" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
        <p class="text-sm">Nenhum item no estoque.</p>
        <p class="text-xs text-zinc-600 mt-1">Adicione itens acima para ativar a entrega automática.</p>
      </div>
    <?php else: ?>
      <div class="space-y-2">
        <?php foreach ($items as $item):
          $iStatus = (string)($item['status'] ?? 'disponivel');
          $isAvail = $iStatus === 'disponivel';
          $statusCls = $isAvail
            ? 'text-greenx'
            : ($iStatus === 'vendido' ? 'text-purple-400' : 'text-zinc-500');
          $statusLabel = match($iStatus) {
              'disponivel' => 'Aguardando comprador',
              'vendido' => 'Entregue',
              default => ucfirst($iStatus),
          };
        ?>
        <div class="rounded-xl border border-blackx3 bg-blackx/40 px-4 py-3 flex flex-col sm:flex-row sm:items-center gap-2" x-data="{ editing: false }">
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 text-xs text-zinc-500 mb-1">
              <span class="font-medium text-zinc-400">#<?= (int)$item['id'] ?></span>
              <?php if (!empty($item['variante_nome'])): ?>
                <span class="px-1.5 py-0.5 rounded bg-greenx/15 text-purple-300 text-[10px]"><?= htmlspecialchars($item['variante_nome']) ?></span>
              <?php endif; ?>
            </div>

            <!-- View mode -->
            <div x-show="!editing">
              <p class="text-sm text-zinc-300 font-mono truncate" title="<?= htmlspecialchars($item['conteudo']) ?>">
                <?= htmlspecialchars(mb_strimwidth($item['conteudo'], 0, 80, '...')) ?>
              </p>
            </div>

            <!-- Edit mode -->
            <div x-show="editing" x-cloak>
              <form method="post" class="flex items-center gap-2">
                <input type="hidden" name="stock_action" value="edit_item">
                <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                <input type="text" name="new_content" value="<?= htmlspecialchars($item['conteudo']) ?>" class="flex-1 bg-blackx border border-blackx3 rounded-lg px-3 py-1.5 text-sm font-mono outline-none focus:border-greenx">
                <button type="submit" class="text-greenx text-xs font-semibold hover:text-white transition">Salvar</button>
                <button type="button" @click="editing = false" class="text-zinc-500 text-xs hover:text-white transition">Cancelar</button>
              </form>
            </div>

            <div class="flex items-center gap-3 mt-1 text-[11px]">
              <span class="text-zinc-600">Cadastrado em: <?= date('d/m/Y H:i', strtotime($item['criado_em'])) ?></span>
              <span class="<?= $statusCls ?> font-semibold"><?= $statusLabel ?></span>
              <?php if (!empty($item['entregue_em'])): ?>
                <span class="text-zinc-600">Entregue: <?= date('d/m/Y H:i', strtotime($item['entregue_em'])) ?></span>
              <?php endif; ?>
            </div>
          </div>

          <?php if ($isAvail): ?>
          <div class="flex items-center gap-1.5 flex-shrink-0">
            <button type="button" @click="editing = !editing" class="inline-flex items-center gap-1 rounded-lg border border-blackx3 hover:border-greenx px-2.5 py-1.5 text-xs text-zinc-400 hover:text-white transition">
              <i data-lucide="pencil" class="w-3 h-3"></i> Editar
            </button>
            <form method="post" onsubmit="return confirm('Remover este item do estoque?')">
              <input type="hidden" name="stock_action" value="delete_item">
              <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
              <button type="submit" class="inline-flex items-center gap-1 rounded-lg border border-red-500/30 hover:border-red-400 bg-red-500/10 px-2.5 py-1.5 text-xs text-red-300 hover:text-red-200 transition">
                <i data-lucide="trash-2" class="w-3 h-3"></i>
              </button>
            </form>
          </div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>

      <?php if ($totalPaginas > 1): ?>
      <div class="mt-4">
        <?php
          $paginaAtual = $pagina;
          include __DIR__ . '/../../views/partials/pagination.php';
        ?>
      </div>
      <?php endif; ?>
    <?php endif; ?>

    <div class="mt-3 text-xs text-zinc-600 flex items-center gap-2">
      <i data-lucide="info" class="w-3.5 h-3.5"></i>
      Mostrando <?= count($items) ?> de <?= $totalItems ?> itens registrados.
    </div>
  </div>
</div>

<script>
function stockPage() {
    return {};
}
</script>

<?php
include __DIR__ . '/../../views/partials/vendor_layout_end.php';
include __DIR__ . '/../../views/partials/footer.php';
?>
