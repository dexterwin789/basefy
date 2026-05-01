<?php
// filepath: c:\xampp\htdocs\mercado_admin\public\vendedor\venda_detalhe.php
declare(strict_types=1);
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/vendor_portal.php';
require_once __DIR__ . '/../../src/wallet_escrow.php';
require_once __DIR__ . '/../../src/media.php';
require_once __DIR__ . '/../../src/storefront.php';
exigirVendedor();

$db = new Database(); $conn = $db->connect();
$uid = (int)($_SESSION['user_id'] ?? 0);
$id = (int)($_GET['id'] ?? 0);

// Handle delivery code submission
$msg = '';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'verify_delivery_code') {
    $codeOrderId = (int)($_POST['order_id'] ?? 0);
    $codeInput   = trim((string)($_POST['delivery_code'] ?? ''));
    [$ok, $deliveryMsg] = escrowConfirmDeliveryByCode($conn, $codeOrderId, $uid, $codeInput);
    if ($ok) {
        $msg = $deliveryMsg;
    } else {
        $err = $deliveryMsg;
    }
}

$v = detalheMinhaVenda($conn, $uid, $id);
if (!$v) {
    header('Location: ' . BASE_PATH . '/vendedor/vendas_aprovadas');
    exit;
}

// Get order info
$orderId = (int)$v['order_id'];
$orderSt = $conn->prepare("SELECT id, status, delivery_code FROM orders WHERE id = ? LIMIT 1");
$orderSt->bind_param('i', $orderId);
$orderSt->execute();
$orderInfo = $orderSt->get_result()->fetch_assoc();
$orderSt->close();

// Get all vendor items in this order
$itemsSt = $conn->prepare("
    SELECT oi.id, oi.product_id, oi.quantidade, oi.preco_unit, oi.subtotal,
           oi.moderation_status, oi.delivery_content, oi.delivered_at,
           p.nome AS produto_nome, p.imagem AS produto_imagem
    FROM order_items oi
    LEFT JOIN products p ON p.id = oi.product_id
    WHERE oi.order_id = ? AND oi.vendedor_id = ?
    ORDER BY oi.id ASC
");
$itemsSt->bind_param('ii', $orderId, $uid);
$itemsSt->execute();
$items = $itemsSt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
$itemsSt->close();

$hasPending = false;
foreach ($items as $item) {
    if ((string)($item['moderation_status'] ?? '') === 'pendente') {
        $hasPending = true;
        break;
    }
}

$pageTitle = 'Detalhes da Venda #' . $id;
$activeMenu = ($v['moderation_status'] === 'aprovada') ? 'vendas_aprovadas' : 'vendas_analise';
include __DIR__ . '/../../views/partials/header.php';
include __DIR__ . '/../../views/partials/vendor_layout_start.php';
?>

<div class="max-w-4xl mx-auto space-y-4">

  <a href="<?= BASE_PATH ?>/vendedor/vendas_aprovadas" class="inline-flex rounded-lg border border-blackx3 px-3 py-1.5 text-xs hover:border-greenx transition">← Voltar</a>

  <?php if ($msg): ?><div class="rounded-xl bg-greenx/15 border border-greenx/40 text-greenx px-4 py-3 text-sm animate-fade-in"><i data-lucide="check-circle" class="w-4 h-4 inline mr-1.5"></i><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="rounded-xl bg-red-500/15 border border-red-400/40 text-red-300 px-4 py-3 text-sm"><?= htmlspecialchars($err) ?></div><?php endif; ?>

  <!-- Order Summary -->
  <div class="bg-blackx2 border border-blackx3 rounded-2xl p-5">
    <h2 class="text-lg font-bold mb-3">Pedido #<?= $orderId ?></h2>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
      <div><span class="text-zinc-400">Status:</span> <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-greenx/15 border border-greenx/40 text-greenx"><?= htmlspecialchars((string)($orderInfo['status'] ?? '')) ?></span></div>
      <div><span class="text-zinc-400">Itens seus:</span> <?= count($items) ?></div>
      <div><span class="text-zinc-400">Total:</span> R$ <?= number_format((float)($v['subtotal'] ?? 0), 2, ',', '.') ?></div>
      <div><span class="text-zinc-400">Moderação:</span> <?= htmlspecialchars($v['moderation_status'] ?? '') ?></div>
    </div>
  </div>

  <!-- Items -->
  <div class="bg-blackx2 border border-blackx3 rounded-2xl p-5">
    <h3 class="text-base font-semibold mb-4">Itens do pedido</h3>
    <div class="space-y-3">
      <?php foreach ($items as $it):
        $imgUrl = mediaResolveUrl((string)($it['produto_imagem'] ?? ''), 'https://placehold.co/64x64/1a1a1a/555?text=Foto');
        $modStatus = strtolower(trim((string)($it['moderation_status'] ?? '')));
      ?>
      <div class="flex items-center gap-4 p-3 rounded-xl bg-blackx/50 border border-blackx3/60">
        <img src="<?= htmlspecialchars($imgUrl) ?>" class="w-14 h-14 rounded-xl object-cover border border-blackx3">
        <div class="flex-1 min-w-0">
          <p class="font-semibold text-sm truncate"><?= htmlspecialchars((string)($it['produto_nome'] ?? 'Produto')) ?></p>
          <div class="flex gap-3 text-xs text-zinc-400 mt-1">
            <span>Qtd: <b class="text-zinc-200"><?= (int)$it['quantidade'] ?></b></span>
            <span>R$ <?= number_format((float)$it['subtotal'], 2, ',', '.') ?></span>
          </div>
          <?php if (!empty($it['delivery_content'])): ?>
          <p class="text-xs text-greenx mt-1"><i data-lucide="check-circle" class="w-3 h-3 inline mr-0.5"></i> Entrega digital enviada</p>
          <?php endif; ?>
        </div>
        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold <?= $modStatus === 'aprovada' ? 'bg-greenx/15 text-greenx' : ($modStatus === 'pendente' ? 'bg-orange-500/15 text-orange-300' : 'bg-red-500/15 text-red-300') ?>"><?= ucfirst($modStatus) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Delivery Code Verification -->
  <?php if ($hasPending): ?>
  <div class="bg-blackx2 border border-blackx3 rounded-2xl p-5">
    <div class="flex items-center gap-3 mb-4">
      <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-greenx to-greenxd flex items-center justify-center">
        <i data-lucide="key-round" class="w-5 h-5 text-white"></i>
      </div>
      <div>
        <h3 class="text-base font-bold">Confirmar entrega</h3>
        <p class="text-xs text-zinc-400">Insira o código de 6 dígitos fornecido pelo comprador</p>
      </div>
    </div>

    <form method="post" class="space-y-4">
      <input type="hidden" name="action" value="verify_delivery_code">
      <input type="hidden" name="order_id" value="<?= $orderId ?>">

      <div class="flex justify-center gap-2"
           x-data="{
             codes:['','','','','',''],
             focus(i){ $nextTick(()=>$refs['c'+i]?.focus()) },
             handlePaste(e){
               const raw = (e.clipboardData?.getData('text') || '').toUpperCase().replace(/[^A-Z0-9]/g,'').slice(0,6);
               if(!raw){ return }
               e.preventDefault();
               for(let j=0;j<6;j++){
                 this.codes[j] = raw[j] || '';
                 const ref = $refs['c'+j];
                 if(ref){ ref.value = raw[j] || ''; }
               }
               this.focus(Math.min(raw.length,5));
             }
           }">
        <?php for ($i = 0; $i < 6; $i++): ?>
        <input type="text" maxlength="1" x-ref="c<?= $i ?>"
               x-model="codes[<?= $i ?>]"
               @input.prevent="codes[<?= $i ?>]=$event.target.value.toUpperCase().slice(0,1);$event.target.value=codes[<?= $i ?>];if(codes[<?= $i ?>])focus(<?= $i + 1 ?>)"
               @keydown.backspace="if(!codes[<?= $i ?>]){focus(<?= $i - 1 ?>)}"
               @paste="handlePaste($event)"
               autocomplete="off" spellcheck="false" inputmode="text"
               class="w-12 h-14 text-center text-xl font-mono font-bold bg-blackx border-2 border-blackx3 rounded-xl focus:border-greenx focus:ring-1 focus:ring-greenx/30 text-greenx transition-all"
               name="code_digit_<?= $i ?>">
        <?php endfor; ?>
      </div>

      <input type="hidden" name="delivery_code" id="deliveryCodeHidden" value="">
      <script>
      document.querySelector('form[action=""][method="post"]')?.closest('form')?.addEventListener('submit', function(e){
        var digits = [];
        for(var i=0;i<6;i++){var inp=this.querySelector('[name="code_digit_'+i+'"]');digits.push(inp?inp.value:'');}
        this.querySelector('#deliveryCodeHidden').value = digits.join('');
      });
      // More reliable approach:
      document.addEventListener('submit', function(e){
        var form = e.target;
        var hidden = form.querySelector('#deliveryCodeHidden');
        if(!hidden) return;
        var digits = [];
        for(var i=0;i<6;i++){var inp=form.querySelector('[name="code_digit_'+i+'"]');digits.push(inp?inp.value:'');}
        hidden.value = digits.join('');
      });
      </script>

      <div class="flex justify-center">
        <button type="submit" class="px-6 py-3 rounded-xl bg-gradient-to-r from-greenx to-greenxd hover:from-greenx2 hover:to-greenxd text-white font-bold text-sm shadow-lg shadow-greenx/20 hover:shadow-greenx/30 transition-all flex items-center gap-2">
          <i data-lucide="shield-check" class="w-4 h-4"></i>
          Verificar código e liberar pagamento
        </button>
      </div>
    </form>

    <div class="mt-4 p-3 rounded-xl bg-orange-500/5 border border-orange-400/20">
      <p class="text-xs text-orange-300 flex items-center gap-1.5">
        <i data-lucide="info" class="w-3.5 h-3.5 flex-shrink-0"></i>
        Peça o código ao comprador após realizar a entrega. Isso libera o escrow e credita seu saldo.
      </p>
    </div>
  </div>
  <?php endif; ?>

</div>

<?php
include __DIR__ . '/../../views/partials/vendor_layout_end.php';
include __DIR__ . '/../../views/partials/footer.php';
