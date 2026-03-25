<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/admin_solicitacoes.php';

exigirAdmin();

$conn = (new Database())->connect();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: solicitacoes_vendedor.php');
    exit;
}

$flash = ['ok' => null, 'msg' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string)($_POST['acao'] ?? '');
    $motivo = (string)($_POST['motivo'] ?? '');
    [$ok, $msg] = decidirSolicitacaoVendedor($conn, $id, $acao, $motivo);
    $flash = ['ok' => $ok, 'msg' => $msg];
}

$solicitacao = obterSolicitacaoVendedorPorId($conn, $id);
if (!$solicitacao) {
    header('Location: solicitacoes_vendedor.php');
    exit;
}

function maskCpfCnpjAdmin(?string $value): string
{
    $digits = preg_replace('/\D+/', '', (string)$value) ?? '';
    if (strlen($digits) === 11) {
        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digits) ?? $digits;
    }
    if (strlen($digits) === 14) {
        return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $digits) ?? $digits;
    }
    return $value !== null && trim($value) !== '' ? (string)$value : '-';
}

function maskTelefoneAdmin(?string $value): string
{
    $digits = preg_replace('/\D+/', '', (string)$value) ?? '';
    if (strlen($digits) === 10) {
        return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $digits) ?? $digits;
    }
    if (strlen($digits) === 11) {
        return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $digits) ?? $digits;
    }
    return $value !== null && trim($value) !== '' ? (string)$value : '-';
}

$statusRaw = mb_strtolower((string)($solicitacao['status'] ?? ''));
$statusClass = 'bg-zinc-500/20 border-zinc-400/40 text-zinc-300';
if (in_array($statusRaw, ['pendente', 'aberto'], true)) {
    $statusClass = 'bg-orange-500/20 border-orange-400/40 text-orange-300';
} elseif ($statusRaw === 'aprovada') {
    $statusClass = 'bg-greenx/20 border-greenx/40 text-greenx';
} elseif ($statusRaw === 'rejeitada') {
    $statusClass = 'bg-red-600/20 border-red-500/40 text-red-300';
}

$pageTitle = 'Detalhes da solicitação';
$activeMenu = 'solicitacoes';
$subnavItems = [
    ['label' => 'Solicitações', 'href' => 'solicitacoes_vendedor.php', 'active' => false],
    ['label' => 'Detalhes', 'href' => '#', 'active' => true],
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

  <div class="bg-blackx2 border border-blackx3 rounded-xl p-4">
    <div class="flex items-center justify-between mb-3">
      <h2 class="text-lg font-semibold">Solicitação #<?= (int)$solicitacao['solicitacao_id'] ?></h2>
      <a href="solicitacoes_vendedor.php" class="rounded-lg border border-blackx3 px-3 py-1.5 text-xs hover:border-greenx">Voltar</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
      <div><span class="text-zinc-400">Nome:</span> <?= htmlspecialchars((string)($solicitacao['nome'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div>
      <div><span class="text-zinc-400">E-mail:</span> <?= htmlspecialchars((string)($solicitacao['email'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div>
      <div><span class="text-zinc-400">Status:</span> <span class="inline-flex rounded-full border px-2 py-1 text-xs <?= $statusClass ?>"><?= htmlspecialchars((string)($solicitacao['status'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span></div>
      <div><span class="text-zinc-400">Criado em:</span> <?= htmlspecialchars((string)($solicitacao['criado_em'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div>
      <div><span class="text-zinc-400">Atualizado em:</span> <?= htmlspecialchars((string)($solicitacao['atualizado_em'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div>
      <div><span class="text-zinc-400">Usuário ID:</span> #<?= (int)($solicitacao['user_id'] ?? 0) ?></div>
    </div>

    <?php if (!empty($solicitacao['motivo_recusa'])): ?>
      <div class="mt-3 rounded-lg border border-red-500/40 bg-red-600/15 px-3 py-2 text-sm text-red-300">
        Motivo da recusa: <?= htmlspecialchars((string)$solicitacao['motivo_recusa'], ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="bg-blackx2 border border-blackx3 rounded-xl p-4">
    <h3 class="font-semibold mb-3">Dados enviados no formulário</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
      <div><span class="text-zinc-400">Nome da loja:</span> <?= htmlspecialchars((string)($solicitacao['nome_loja'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div>
      <div><span class="text-zinc-400">CPF/CNPJ:</span> <?= htmlspecialchars(maskCpfCnpjAdmin((string)($solicitacao['documento'] ?? '')), ENT_QUOTES, 'UTF-8') ?></div>
      <div><span class="text-zinc-400">Telefone:</span> <?= htmlspecialchars(maskTelefoneAdmin((string)($solicitacao['telefone'] ?? '')), ENT_QUOTES, 'UTF-8') ?></div>
      <div><span class="text-zinc-400">Chave PIX:</span> <?= htmlspecialchars((string)($solicitacao['chave_pix'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div>
      <div class="md:col-span-2">
        <span class="text-zinc-400">Descrição:</span>
        <p class="mt-1 text-zinc-300 whitespace-pre-wrap"><?= htmlspecialchars((string)($solicitacao['bio'] ?? 'Sem descrição.'), ENT_QUOTES, 'UTF-8') ?></p>
      </div>
    </div>
  </div>

  <div class="bg-blackx2 border border-blackx3 rounded-xl p-4">
    <h3 class="font-semibold mb-3">Análise</h3>
    <?php if (in_array((string)$solicitacao['status'], ['pendente', 'aberto'], true)): ?>
      <div class="flex flex-wrap items-start gap-3">
        <form method="post">
          <input type="hidden" name="acao" value="aprovar">
          <button class="rounded-lg bg-greenx hover:bg-greenx2 text-white font-semibold px-4 py-2">Aprovar vendedor</button>
        </form>

        <form method="post" class="flex-1 min-w-[260px] space-y-2">
          <input type="hidden" name="acao" value="recusar">
          <input type="text" name="motivo" required placeholder="Motivo da recusa" class="w-full rounded-lg bg-blackx border border-blackx3 px-3 py-2 text-sm">
          <button class="rounded-lg border border-red-500 text-red-300 hover:bg-red-500/15 px-4 py-2">Recusar solicitação</button>
        </form>
      </div>
    <?php else: ?>
      <p class="text-zinc-400 text-sm">Solicitação já processada. Não há ações pendentes.</p>
    <?php endif; ?>

    <div class="mt-4 rounded-lg border border-blackx3 bg-blackx p-3 text-xs text-zinc-400">
      Espaço preparado para anexos de documentos e imagens em próximas etapas.
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../views/partials/admin_layout_end.php'; include __DIR__ . '/../../views/partials/footer.php';
