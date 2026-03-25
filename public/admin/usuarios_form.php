<?php
// filepath: c:\xampp\htdocs\mercado_admin\public\admin\usuarios_form.php
declare(strict_types=1);

require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/admin_users.php';
exigirAdmin();

$db = new Database();
$conn = $db->connect();

$id = (int)($_GET['id'] ?? 0);
$editar = $id > 0 ? obterUsuarioPorIdRole($conn, $id, 'comprador') : null;

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idPost = (int)($_POST['id'] ?? 0);

    if ($idPost > 0) {
        [$success, $msg] = atualizarUsuarioPainel(
            $conn,
            $idPost,
            (string)($_POST['nome'] ?? ''),
            (string)($_POST['email'] ?? ''),
            'comprador',
            'nao_solicitado',
            (string)($_POST['nova_senha'] ?? '')
        );
    } else {
        [$success, $msg] = criarUsuarioPainel(
            $conn,
            (string)($_POST['nome'] ?? ''),
            (string)($_POST['email'] ?? ''),
            (string)($_POST['senha'] ?? ''),
            'comprador'
        );
    }

    if ($success) {
        header('Location: usuarios');
        exit;
    }
    $erro = $msg;
}

$pageTitle = $editar ? 'Editar usuário' : 'Novo usuário';
$activeMenu = 'usuarios';
$subnavItems = [
    ['label' => 'Listar', 'href' => 'usuarios', 'active' => false],
    ['label' => 'Adicionar', 'href' => 'usuarios_form', 'active' => !$editar],
    ['label' => 'Editar', 'href' => '#', 'active' => (bool)$editar],
];

include __DIR__ . '/../../views/partials/header.php';
include __DIR__ . '/../../views/partials/admin_layout_start.php';
?>

<div class="max-w-2xl mx-auto bg-blackx2 border border-blackx3 rounded-xl p-4">
  <?php if ($erro): ?><div class="mb-4 rounded-lg bg-red-600/20 border border-red-500 text-red-300 px-3 py-2 text-sm"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

  <form method="post" class="space-y-3">
    <?php if ($editar): ?><input type="hidden" name="id" value="<?= (int)$editar['id'] ?>"><?php endif; ?>
    <input name="nome" required value="<?= htmlspecialchars($editar['nome'] ?? '') ?>" placeholder="Nome" class="w-full rounded-lg bg-blackx border border-blackx3 px-3 py-2 outline-none focus:border-greenx">
    <input name="email" type="email" required value="<?= htmlspecialchars($editar['email'] ?? '') ?>" placeholder="E-mail" class="w-full rounded-lg bg-blackx border border-blackx3 px-3 py-2 outline-none focus:border-greenx">
    <?php if ($editar): ?>
      <input name="nova_senha" type="password" placeholder="Nova senha (opcional)" class="w-full rounded-lg bg-blackx border border-blackx3 px-3 py-2 outline-none focus:border-greenx">
    <?php else: ?>
      <input name="senha" type="password" required placeholder="Senha (mín. 8)" class="w-full rounded-lg bg-blackx border border-blackx3 px-3 py-2 outline-none focus:border-greenx">
    <?php endif; ?>
    <button class="w-full rounded-lg bg-greenx hover:bg-greenx2 text-white font-semibold py-2.5 transition">Salvar</button>
  </form>
</div>

<?php include __DIR__ . '/../../views/partials/admin_layout_end.php'; include __DIR__ . '/../../views/partials/footer.php'; ?>