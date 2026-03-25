<?php
// filepath: c:\xampp\htdocs\mercado_admin\public\admin\solicitacao_produto_detalhe.php
declare(strict_types=1);

require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/upload_paths.php';
require_once __DIR__ . '/../../src/media.php';
require_once __DIR__ . '/../../src/admin_product_approval.php';

exigirAdmin();

$conn = (new Database())->connect();
$id   = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: solicitacoes_produto');
    exit;
}

$flash = ['ok' => null, 'msg' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao   = (string)($_POST['acao'] ?? '');
    $motivo = (string)($_POST['motivo'] ?? '');
    [$ok, $msg] = decidirSolicitacaoProduto($conn, $id, $acao, $motivo);
    $flash = ['ok' => $ok, 'msg' => $msg];
}

$produto = obterProdutoParaAprovacao($conn, $id);
if (!$produto) {
    header('Location: solicitacoes_produto');
    exit;
}

$statusRaw   = mb_strtolower((string)($produto['status_aprovacao'] ?? 'aprovado'));
$statusClass = 'bg-zinc-500/20 border-zinc-400/40 text-zinc-300';
if ($statusRaw === 'pendente') {
    $statusClass = 'bg-orange-500/20 border-orange-400/40 text-orange-300';
} elseif ($statusRaw === 'aprovado') {
    $statusClass = 'bg-greenx/20 border-greenx/40 text-greenx';
} elseif ($statusRaw === 'rejeitado') {
    $statusClass = 'bg-red-600/20 border-red-500/40 text-red-300';
}

$_tipo = $produto['tipo'] ?? 'produto';
$tipoLabel = match($_tipo) {
    'dinamico' => 'Dinâmico (variantes)',
    'servico'  => 'Serviço',
    'infoproduto' => 'Infoproduto',
    default    => 'Produto físico',
};

$pageTitle   = 'Detalhes do Produto';
$activeMenu  = 'solicitacoes_produto';
$subnavItems = [
    ['label' => 'Solicitações', 'href' => 'solicitacoes_produto', 'active' => false],
    ['label' => 'Detalhes',     'href' => '#',                     'active' => true],
];

include __DIR__ . '/../../views/partials/header.php';
include __DIR__ . '/../../views/partials/admin_layout_start.php';
?>

<div class="max-w-7xl mx-auto space-y-4">
  <?php if ($flash['ok'] !== null): ?>
    <div class="rounded-lg border px-3 py-2 text-sm <?= $flash['ok'] ? 'bg-greenx/20 border-greenx text-greenx' : 'bg-red-600/20 border-red-500 text-red-300' ?>">
      <?= htmlspecialchars((string)$flash['msg'], ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <!-- Product Info Card -->
  <div class="bg-blackx2 border border-blackx3 rounded-xl p-4">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-lg font-semibold">Produto #<?= (int)$produto['id'] ?></h2>
      <a href="solicitacoes_produto" class="rounded-lg border border-blackx3 px-3 py-1.5 text-xs hover:border-greenx transition">Voltar</a>
    </div>

    <div class="flex flex-col md:flex-row gap-5">
      <!-- Product Image -->
      <div class="flex-shrink-0">
        <?php $_imgUrl = mediaResolveUrl((string)($produto['imagem'] ?? ''), ''); ?>
        <?php if ($_imgUrl !== ''): ?>
          <img src="<?= htmlspecialchars($_imgUrl) ?>" alt="" class="w-40 h-40 rounded-xl object-cover border border-blackx3">
        <?php else: ?>
          <div class="w-40 h-40 rounded-xl bg-blackx3 flex items-center justify-center">
            <i data-lucide="package" class="w-12 h-12 text-zinc-600"></i>
          </div>
        <?php endif; ?>
      </div>

      <!-- Product Details -->
      <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
        <div><span class="text-zinc-400">Nome:</span> <span class="font-medium"><?= htmlspecialchars((string)($produto['nome'] ?? '-')) ?></span></div>
        <div><span class="text-zinc-400">Tipo:</span> <?= $tipoLabel ?></div>
        <div><span class="text-zinc-400">Preço:</span> <span class="text-greenx font-semibold">
        <?php
          $_preco = (float)($produto['preco'] ?? 0);
          if ($_preco <= 0 && $_tipo === 'dinamico' && !empty($produto['variantes'])) {
              $_vars = is_string($produto['variantes']) ? json_decode($produto['variantes'], true) : (is_array($produto['variantes']) ? $produto['variantes'] : []);
              $_prices = array_filter(array_map(fn($v) => (float)($v['preco'] ?? 0), $_vars ?: []), fn($p) => $p > 0);
              if ($_prices) {
                  $_min = min($_prices); $_max = max($_prices);
                  echo $_min === $_max
                      ? 'R$ ' . number_format($_min, 2, ',', '.')
                      : 'R$ ' . number_format($_min, 2, ',', '.') . ' ~ R$ ' . number_format($_max, 2, ',', '.');
              } else {
                  echo 'R$ 0,00';
              }
          } else {
              echo 'R$ ' . number_format($_preco, 2, ',', '.');
          }
        ?>
        </span></div>
        <div><span class="text-zinc-400">Quantidade:</span>
        <?php
          $_qtd = (int)($produto['quantidade'] ?? 0);
          if ($_tipo === 'dinamico' && !empty($produto['variantes'])) {
              $_vars2 = is_string($produto['variantes']) ? json_decode($produto['variantes'], true) : (is_array($produto['variantes']) ? $produto['variantes'] : []);
              $_totalStock = array_sum(array_map(fn($v) => (int)($v['quantidade'] ?? 0), $_vars2 ?: []));
              echo $_totalStock;
          } else {
              echo $_qtd;
          }
        ?>
        </div>
        <div><span class="text-zinc-400">Status:</span> <span class="inline-flex rounded-full border px-2 py-1 text-xs <?= $statusClass ?>"><?= ucfirst($statusRaw) ?></span></div>
        <div><span class="text-zinc-400">Ativo:</span> <?= ((int)($produto['ativo'] ?? 0)) ? '<span class="text-greenx">Sim</span>' : '<span class="text-zinc-500">Não</span>' ?></div>
        <div><span class="text-zinc-400">Slug:</span> <span class="text-zinc-300"><?= htmlspecialchars((string)($produto['slug'] ?? '-')) ?></span></div>
        <div><span class="text-zinc-400">Criado em:</span> <?= fmtDate((string)($produto['criado_em'] ?? '-')) ?></div>
      </div>
    </div>

    <!-- Variants -->
    <?php if ($_tipo === 'dinamico' && !empty($produto['variantes'])):
        $_vList = is_string($produto['variantes']) ? json_decode($produto['variantes'], true) : (is_array($produto['variantes']) ? $produto['variantes'] : []);
        if ($_vList): ?>
      <div class="mt-4 border-t border-blackx3 pt-3">
        <span class="text-zinc-400 text-sm">Variantes (<?= count($_vList) ?>):</span>
        <div class="mt-2 grid gap-2">
          <?php foreach ($_vList as $_v): ?>
            <div class="flex items-center justify-between bg-blackx border border-blackx3 rounded-lg px-3 py-2 text-sm">
              <span class="text-zinc-200 font-medium"><?= htmlspecialchars((string)($_v['nome'] ?? '-')) ?></span>
              <span class="flex gap-4 text-zinc-400">
                <span>R$&nbsp;<?= number_format((float)($_v['preco'] ?? 0), 2, ',', '.') ?></span>
                <span>Estoque: <?= (int)($_v['quantidade'] ?? 0) ?></span>
              </span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; endif; ?>

    <!-- Description -->
    <?php if (!empty($produto['descricao'])): ?>
      <div class="mt-4 border-t border-blackx3 pt-3">
        <span class="text-zinc-400 text-sm">Descrição:</span>
        <div class="mt-1 text-zinc-300 text-sm prose prose-invert prose-sm max-w-none"><?= $produto['descricao'] ?></div>
      </div>
    <?php endif; ?>

    <?php if (!empty($produto['motivo_recusa'])): ?>
      <div class="mt-3 rounded-lg border border-red-500/40 bg-red-600/15 px-3 py-2 text-sm text-red-300">
        <strong>Motivo da recusa:</strong> <?= htmlspecialchars((string)$produto['motivo_recusa'], ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Vendor Info Card -->
  <div class="bg-blackx2 border border-blackx3 rounded-xl p-4">
    <h3 class="font-semibold mb-3">Dados do Vendedor</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
      <div><span class="text-zinc-400">Nome:</span> <?= htmlspecialchars((string)($produto['vendedor_nome'] ?? '-')) ?></div>
      <div><span class="text-zinc-400">E-mail:</span> <?= htmlspecialchars((string)($produto['vendedor_email'] ?? '-')) ?></div>
      <div><span class="text-zinc-400">Vendedor ID:</span> #<?= (int)($produto['uid_vendedor'] ?? 0) ?></div>
    </div>
  </div>

  <!-- Action Card -->
  <div class="bg-blackx2 border border-blackx3 rounded-xl p-4">
    <h3 class="font-semibold mb-3">Análise</h3>
    <?php if ($statusRaw === 'pendente'): ?>
      <div class="flex flex-wrap items-start gap-3">
        <form method="post">
          <input type="hidden" name="acao" value="aprovar">
          <button class="rounded-lg bg-greenx hover:bg-greenx2 text-white font-semibold px-4 py-2 transition">Aprovar produto</button>
        </form>

        <form method="post" class="flex-1 min-w-[260px] space-y-2">
          <input type="hidden" name="acao" value="recusar">
          <input type="text" name="motivo" required placeholder="Motivo da recusa" class="w-full rounded-lg bg-blackx border border-blackx3 px-3 py-2 text-sm outline-none focus:border-red-400">
          <button class="rounded-lg border border-red-500 text-red-300 hover:bg-red-500/15 px-4 py-2 transition">Recusar produto</button>
        </form>
      </div>
    <?php else: ?>
      <p class="text-zinc-400 text-sm">Este produto já foi processado. Não há ações pendentes.</p>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../../views/partials/admin_layout_end.php'; include __DIR__ . '/../../views/partials/footer.php'; ?>
