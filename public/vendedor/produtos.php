<?php
// filepath: c:\xampp\htdocs\mercado_admin\public\vendedor\produtos.php
declare(strict_types=1);
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/vendor_portal.php';
exigirVendedor();

$db = new Database(); $conn = $db->connect();
$uid = (int)($_SESSION['user_id'] ?? 0);
$itens = listarMeusProdutos($conn, $uid, ['q' => (string)($_GET['q'] ?? '')]);

header('Content-Type: text/html; charset=UTF-8');
$pageTitle = 'Meus Produtos';
$activeMenu = 'produtos';
$topActions = [['label' => 'Adicionar', 'href' => 'produtos_form.php']];
$subnavItems = [
  ['label' => 'Listar', 'href' => 'produtos.php', 'active' => true],
  ['label' => 'Adicionar', 'href' => 'produtos_form.php', 'active' => false],
];

include __DIR__.'/../../views/partials/header.php';
include __DIR__.'/../../views/partials/vendor_layout_start.php';
?>
<div class="max-w-7xl mx-auto">
  <div class="bg-blackx2 border border-blackx3 rounded-2xl p-5">
    <form method="get" class="mb-4">
      <input
        type="text"
        name="q"
        value="<?= htmlspecialchars((string)($_GET['q'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
        placeholder="Buscar produto"
        class="w-full md:w-80 bg-blackx border border-blackx3 rounded-xl px-4 py-2 outline-none focus:border-greenx"
      >
    </form>

    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-zinc-400 border-b border-blackx3">
            <th class="text-left py-3 pr-3">ID</th>
            <th class="text-left py-3 pr-3">Imagem</th>
            <th class="text-left py-3 pr-3">Nome</th>
            <th class="text-left py-3 pr-3">Preço</th>
            <th class="text-left py-3 pr-3">Status</th>
            <th class="text-left py-3">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($itens as $p): ?>
            <tr class="border-b border-blackx3/50 hover:bg-blackx/40 transition">
              <td class="py-3 pr-3">#<?= (int)$p['id'] ?></td>
              <td class="py-3 pr-3">
                <img
                  src="<?= htmlspecialchars(vpThumbUrl((string)($p['imagem'] ?? '')), ENT_QUOTES, 'UTF-8') ?>"
                  alt="thumb"
                  class="w-12 h-12 rounded-lg object-cover border border-blackx3"
                  onerror="this.onerror=null;this.src='https://placehold.co/80x80/111827/9ca3af?text=Sem+Img';"
                />
              </td>
              <td class="py-3 pr-3 max-w-[380px]">
                <span class="block truncate" title="<?= htmlspecialchars((string)$p['nome'], ENT_QUOTES, 'UTF-8') ?>">
                  <?= htmlspecialchars((string)$p['nome'], ENT_QUOTES, 'UTF-8') ?>
                </span>
              </td>
              <td class="py-3 pr-3 font-medium">R$ <?= number_format((float)$p['preco'], 2, ',', '.') ?></td>
              <td class="py-3 pr-3">
                <span class="px-2.5 py-1 rounded-full text-xs font-medium <?= ((int)$p['ativo'] === 1) ? 'bg-greenx/15 border border-greenx/40 text-greenx' : 'bg-orange-500/15 border border-orange-400/40 text-orange-300' ?>">
                  <?= ((int)$p['ativo'] === 1) ? 'Ativo' : 'Inativo' ?>
                </span>
              </td>
              <td class="py-3">
                <a href="/mercado_admin/public/vendedor/produtos_form.php?id=<?= (int)$p['id'] ?>" class="text-greenx hover:underline">
                  Editar
                </a>
              </td>
            </tr>
          <?php endforeach; ?>

          <?php if (empty($itens)): ?>
            <tr>
              <td colspan="6" class="py-6 text-zinc-500">Nenhum produto encontrado.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php

include __DIR__ . '/../../views/partials/vendor_layout_end.php';
include __DIR__ . '/../../views/partials/footer.php';

function vpThumbUrl(string $raw): string
{
    $raw = trim(str_replace('\\', '/', $raw));
    if ($raw === '') {
        return 'https://placehold.co/80x80/111827/9ca3af?text=Sem+Img';
    }

    // URL externa
    if (preg_match('~^https?://~i', $raw)) {
        return $raw;
    }

    // já é URL completa do projeto
    if (str_starts_with($raw, '/mercado_admin/')) {
        return $raw;
    }

    // padrão salvo no banco: /uploads/produtos/arquivo.png
    if (str_starts_with($raw, '/uploads/')) {
        return '/mercado_admin/public' . $raw;
    }

    // sem barra inicial: uploads/produtos/arquivo.png
    if (str_starts_with($raw, 'uploads/')) {
        return '/mercado_admin/public/' . $raw;
    }

    // public/uploads/...
    if (str_starts_with($raw, 'public/')) {
        return '/mercado_admin/' . $raw;
    }

    // só nome de arquivo
    if (!str_contains($raw, '/')) {
        return '/mercado_admin/public/uploads/produtos/' . $raw;
    }

    return '/mercado_admin/public/' . ltrim($raw, '/');
}
