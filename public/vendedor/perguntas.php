<?php
// filepath: c:\xampp\htdocs\mercado_admin\public\vendedor\perguntas.php
declare(strict_types=1);

require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/questions.php';
require_once __DIR__ . '/../../src/storefront.php';
exigirLogin();

$db = new Database();
$conn = $db->connect();

$vendorId = (int)($_SESSION['user_id'] ?? 0);

// Handle answer POST (AJAX or normal)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string)($_POST['acao'] ?? '');
    $qid  = (int)($_POST['question_id'] ?? 0);
    $resp = trim((string)($_POST['resposta'] ?? ''));

    if ($acao === 'responder' && $qid > 0) {
        // Verify this question belongs to vendor's product
        $check = questionsGetById($conn, $qid);
        if (!$check) {
            $result = [false, 'Pergunta não encontrada.'];
        } else {
            $stProd = $conn->prepare("SELECT vendedor_id FROM products WHERE id = ? LIMIT 1");
            $stProd->bind_param('i', $check['product_id']);
            $stProd->execute();
            $pRow = $stProd->get_result()->fetch_assoc();
            $stProd->close();

            if (!$pRow || (int)$pRow['vendedor_id'] !== $vendorId) {
                $result = [false, 'Você não pode responder esta pergunta.'];
            } else {
                $result = questionsAnswer($conn, $qid, $vendorId, $resp);
            }
        }

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => $result[0], 'msg' => $result[1]]);
            exit;
        }
    }
}

$f = [
    'q'        => (string)($_GET['q'] ?? ''),
    'answered' => (string)($_GET['answered'] ?? ''),
    'status'   => 'ativo',
];

$tab = (string)($_GET['tab'] ?? 'recebidas');
if (!in_array($tab, ['recebidas', 'feitas'], true)) $tab = 'recebidas';

$pagina = max(1, (int)($_GET['p'] ?? 1));
$pp = in_array(($_pp = (int)($_GET['pp'] ?? 10)), [5, 10, 20]) ? $_pp : 10;

if ($tab === 'recebidas') {
    $lista = questionsListByVendor($conn, $vendorId, $f, $pagina, $pp);
    $naoRespondidas = questionsUnansweredCount($conn, $vendorId);
} else {
    $lista = questionsListByUser($conn, $vendorId, $f, $pagina, $pp);
    $naoRespondidas = 0;
}

// Stats for both tabs
$totalRecebidas = questionsUnansweredCount($conn, $vendorId);
$totalFeitas = questionsTotalByUser($conn, $vendorId);
$feitasRespondidas = questionsAnsweredCountByUser($conn, $vendorId);

// AJAX poll: return only total count as JSON
if (isset($_GET['_poll']) && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    echo json_encode(['total' => (int)$lista['total'], 'unanswered' => $totalRecebidas, 'tab' => $tab]);
    exit;
}

$pageTitle = 'Perguntas';
$activeMenu = 'perguntas';

include __DIR__ . '/../../views/partials/header.php';
include __DIR__ . '/../../views/partials/vendor_layout_start.php';
?>

<div>
  <!-- Tabs -->
  <div class="flex gap-2 mb-5">
    <a href="?tab=recebidas" class="rounded-xl px-4 py-2 text-sm font-semibold transition-all <?= $tab === 'recebidas' ? 'bg-greenx/15 border border-greenx/40 text-greenx' : 'border border-blackx3 text-zinc-400 hover:border-zinc-600 hover:text-zinc-200' ?>">
      <span class="flex items-center gap-2"><i data-lucide="inbox" class="w-4 h-4"></i> Recebidas <?php if ($totalRecebidas > 0): ?><span class="bg-orange-500/20 text-orange-400 px-1.5 py-0.5 rounded-full text-[10px] font-bold"><?= $totalRecebidas ?></span><?php endif; ?></span>
    </a>
    <a href="?tab=feitas" class="rounded-xl px-4 py-2 text-sm font-semibold transition-all <?= $tab === 'feitas' ? 'bg-greenx/15 border border-greenx/40 text-greenx' : 'border border-blackx3 text-zinc-400 hover:border-zinc-600 hover:text-zinc-200' ?>">
      <span class="flex items-center gap-2"><i data-lucide="send" class="w-4 h-4"></i> Feitas <span class="text-zinc-600 text-[10px]">(<?= $totalFeitas ?>)</span></span>
    </a>
  </div>

  <!-- Stats -->
  <?php if ($tab === 'recebidas'): ?>
  <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-5">
    <div class="bg-blackx2 border border-blackx3 rounded-2xl p-4 text-center">
      <p class="text-[10px] uppercase tracking-wider text-zinc-500 font-semibold">Total</p>
      <p class="text-2xl font-bold mt-1"><?= (int)$lista['total'] ?></p>
    </div>
    <div class="bg-blackx2 border border-blackx3 rounded-2xl p-4 text-center">
      <p class="text-[10px] uppercase tracking-wider text-orange-400 font-semibold">Sem resposta</p>
      <p class="text-2xl font-bold mt-1 text-orange-400"><?= $totalRecebidas ?></p>
    </div>
    <div class="bg-blackx2 border border-blackx3 rounded-2xl p-4 text-center hidden md:block">
      <p class="text-[10px] uppercase tracking-wider text-greenx font-semibold">Respondidas</p>
      <p class="text-2xl font-bold mt-1 text-greenx"><?= max(0, (int)$lista['total'] - $totalRecebidas) ?></p>
    </div>
  </div>
  <?php else: ?>
  <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-5">
    <div class="bg-blackx2 border border-blackx3 rounded-2xl p-4 text-center">
      <p class="text-[10px] uppercase tracking-wider text-zinc-500 font-semibold">Feitas</p>
      <p class="text-2xl font-bold mt-1"><?= $totalFeitas ?></p>
    </div>
    <div class="bg-blackx2 border border-blackx3 rounded-2xl p-4 text-center">
      <p class="text-[10px] uppercase tracking-wider text-greenx font-semibold">Respondidas</p>
      <p class="text-2xl font-bold mt-1 text-greenx"><?= $feitasRespondidas ?></p>
    </div>
    <div class="bg-blackx2 border border-blackx3 rounded-2xl p-4 text-center hidden md:block">
      <p class="text-[10px] uppercase tracking-wider text-orange-400 font-semibold">Aguardando</p>
      <p class="text-2xl font-bold mt-1 text-orange-400"><?= max(0, $totalFeitas - $feitasRespondidas) ?></p>
    </div>
  </div>
  <?php endif; ?>

  <div class="bg-blackx2 border border-blackx3 rounded-2xl p-5">
    <?php if (isset($result)): ?>
      <div class="mb-3 rounded-lg <?= $result[0] ? 'bg-greenx/20 border-greenx text-greenx' : 'bg-red-600/20 border-red-500 text-red-300' ?> border px-3 py-2 text-sm"><?= htmlspecialchars($result[1]) ?></div>
    <?php endif; ?>

    <!-- Filter -->
    <form method="get" class="mb-4 rounded-2xl border border-blackx3 bg-blackx/50 p-3 md:p-4">
      <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
      <div class="flex flex-col md:flex-row md:items-end gap-3">
        <div class="md:flex-1">
          <label class="block text-xs text-zinc-500 mb-1">Busca</label>
          <input name="q" value="<?= htmlspecialchars($f['q']) ?>" placeholder="Pergunta, produto, ou usuário" class="w-full bg-blackx border border-blackx3 rounded-xl px-4 py-2.5 outline-none focus:border-greenx">
        </div>
        <div class="md:w-40">
          <label class="block text-xs text-zinc-500 mb-1">Filtrar</label>
          <select name="answered" class="w-full bg-blackx border border-blackx3 rounded-xl px-3 py-2.5 outline-none focus:border-greenx">
            <option value="">Todas</option>
            <option value="no" <?= $f['answered'] === 'no' ? 'selected' : '' ?>>Sem resposta</option>
            <option value="yes" <?= $f['answered'] === 'yes' ? 'selected' : '' ?>>Respondidas</option>
          </select>
        </div>
        <div class="flex items-center gap-2 md:ml-auto">
          <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-greenx to-greenxd hover:from-greenx2 hover:to-greenxd text-white font-semibold px-4 py-2.5 transition-all">
            <i data-lucide="sliders-horizontal" class="w-4 h-4"></i> Filtrar
          </button>
          <a href="perguntas?tab=<?= $tab ?>" title="Limpar" class="inline-flex items-center justify-center rounded-xl border border-blackx3 w-10 h-10 text-zinc-300 hover:border-greenx hover:text-white transition-all">
            <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
          </a>
        </div>
      </div>
    </form>

    <!-- Questions list (card-based for better UX) -->
    <div class="space-y-4">
      <?php if (!$lista['itens']): ?>
        <p class="text-zinc-500 py-8 text-center text-sm"><?= $tab === 'feitas' ? 'Você ainda não fez nenhuma pergunta.' : 'Nenhuma pergunta encontrada.' ?></p>
      <?php endif; ?>

      <?php if ($tab === 'recebidas'): ?>
      <?php foreach ($lista['itens'] as $row): ?>
        <?php $answered = !empty($row['resposta']); ?>
        <div id="q-card-<?= (int)$row['id'] ?>" class="rounded-2xl border <?= $answered ? 'border-blackx3/50 bg-blackx/30' : 'border-orange-500/30 bg-orange-500/5' ?> p-4 transition">
          <!-- Header -->
          <div class="flex flex-wrap items-center gap-2 mb-3 text-xs text-zinc-400">
            <div class="flex items-center gap-2">
              <?php if (!empty($row['produto_imagem'])): ?>
                <img src="<?= htmlspecialchars(sfImageUrl((string)$row['produto_imagem'])) ?>" alt="" class="w-7 h-7 rounded-lg object-cover border border-blackx3">
              <?php endif; ?>
              <span class="font-medium text-zinc-200 text-sm"><?= htmlspecialchars((string)($row['produto_nome'] ?? 'Produto')) ?></span>
            </div>
            <span class="text-zinc-600">•</span>
            <span><?= questionsTimeAgo((string)$row['criado_em']) ?></span>
            <?php if (!$answered): ?>
              <span class="ml-auto px-2 py-0.5 rounded-full bg-orange-500/15 border border-orange-400/40 text-orange-400 text-[10px] font-semibold uppercase tracking-wider">Aguardando</span>
            <?php else: ?>
              <span class="ml-auto px-2 py-0.5 rounded-full bg-greenx/15 border border-greenx/40 text-greenx text-[10px] font-semibold uppercase tracking-wider">Respondida</span>
            <?php endif; ?>
          </div>

          <!-- Question -->
          <div class="flex gap-3 mb-3">
            <div class="flex-shrink-0 w-8 h-8 rounded-full bg-greenx/20 border border-greenx/40 flex items-center justify-center text-purple-400">
              <?php
                $buyerAvatarRaw = trim((string)($row['user_avatar'] ?? ''));
                $buyerAvatarUrl = $buyerAvatarRaw !== '' ? mediaResolveUrl($buyerAvatarRaw) : '';
              ?>
              <?php if ($buyerAvatarUrl !== ''): ?>
                <img src="<?= htmlspecialchars($buyerAvatarUrl) ?>" alt="" class="w-8 h-8 rounded-full object-cover">
              <?php else: ?>
                <i data-lucide="user" class="w-4 h-4"></i>
              <?php endif; ?>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-xs font-semibold text-purple-300 mb-1"><?= htmlspecialchars((string)($row['user_nome_full'] ?: $row['user_nome'])) ?></p>
              <p class="text-sm leading-relaxed"><?= nl2br(htmlspecialchars((string)$row['pergunta'])) ?></p>
            </div>
          </div>

          <?php if ($answered): ?>
            <!-- Existing answer -->
            <div class="flex gap-3 ml-6 pl-4 border-l-2 border-greenx/30">
              <div class="flex-shrink-0 w-8 h-8 rounded-full bg-greenx/20 border border-greenx/40 flex items-center justify-center text-greenx">
                <i data-lucide="store" class="w-4 h-4"></i>
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-xs font-semibold text-greenx mb-1">Você (Vendedor)</p>
                <p class="text-sm leading-relaxed text-zinc-300"><?= nl2br(htmlspecialchars((string)$row['resposta'])) ?></p>
                <?php if (!empty($row['respondido_em'])): ?>
                  <p class="text-[10px] text-zinc-500 mt-1"><?= questionsTimeAgo((string)$row['respondido_em']) ?></p>
                <?php endif; ?>
              </div>
            </div>
          <?php else: ?>
            <!-- Answer form -->
            <form class="js-answer-form ml-6 pl-4 border-l-2 border-orange-400/30" data-id="<?= (int)$row['id'] ?>">
              <textarea name="resposta" rows="2" placeholder="Escreva sua resposta..." class="w-full bg-blackx border border-blackx3 rounded-xl px-4 py-2.5 outline-none focus:border-greenx text-sm resize-none mb-2"></textarea>
              <div class="flex items-center gap-2">
                <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-greenx to-greenxd hover:from-greenx2 hover:to-greenxd text-white font-semibold px-4 py-2 text-sm transition-all">
                  <i data-lucide="send" class="w-3.5 h-3.5"></i> Responder
                </button>
                <span class="js-answer-msg text-xs"></span>
              </div>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php else: ?>
      <!-- Feitas (questions I asked) -->
      <?php foreach ($lista['itens'] as $row): ?>
        <?php $answered = !empty($row['resposta']); ?>
        <div class="rounded-2xl border <?= $answered ? 'border-greenx/20 bg-greenx/[0.02]' : 'border-blackx3/50 bg-blackx/30' ?> p-4 transition">
          <!-- Header -->
          <div class="flex flex-wrap items-center gap-2 mb-3 text-xs text-zinc-400">
            <div class="flex items-center gap-2">
              <?php if (!empty($row['produto_imagem'])): ?>
                <img src="<?= htmlspecialchars(sfImageUrl((string)$row['produto_imagem'])) ?>" alt="" class="w-7 h-7 rounded-lg object-cover border border-blackx3">
              <?php endif; ?>
              <span class="font-medium text-zinc-200 text-sm"><?= htmlspecialchars((string)($row['produto_nome'] ?? 'Produto')) ?></span>
            </div>
            <span class="text-zinc-600">•</span>
            <span><?= questionsTimeAgo((string)$row['criado_em']) ?></span>
            <?php if ($answered): ?>
              <span class="ml-auto px-2 py-0.5 rounded-full bg-greenx/15 border border-greenx/40 text-greenx text-[10px] font-semibold uppercase tracking-wider">Respondida</span>
            <?php else: ?>
              <span class="ml-auto px-2 py-0.5 rounded-full bg-zinc-500/15 border border-zinc-500/40 text-zinc-400 text-[10px] font-semibold uppercase tracking-wider">Aguardando</span>
            <?php endif; ?>
          </div>

          <!-- My question -->
          <div class="flex gap-3 mb-3">
            <div class="flex-shrink-0 w-8 h-8 rounded-full bg-greenx/20 border border-greenx/40 flex items-center justify-center text-purple-400">
              <i data-lucide="user" class="w-4 h-4"></i>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-xs font-semibold text-purple-300 mb-1">Você</p>
              <p class="text-sm leading-relaxed"><?= nl2br(htmlspecialchars((string)$row['pergunta'])) ?></p>
            </div>
          </div>

          <?php if ($answered): ?>
            <!-- Vendor response -->
            <div class="flex gap-3 ml-6 pl-4 border-l-2 border-greenx/30">
              <div class="flex-shrink-0 w-8 h-8 rounded-full bg-greenx/20 border border-greenx/40 flex items-center justify-center text-greenx">
                <i data-lucide="store" class="w-4 h-4"></i>
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-xs font-semibold text-greenx mb-1"><?= htmlspecialchars((string)($row['respondido_por_nome'] ?? 'Vendedor')) ?></p>
                <p class="text-sm leading-relaxed text-zinc-300"><?= nl2br(htmlspecialchars((string)$row['resposta'])) ?></p>
                <?php if (!empty($row['respondido_em'])): ?>
                  <p class="text-[10px] text-zinc-500 mt-1"><?= questionsTimeAgo((string)$row['respondido_em']) ?></p>
                <?php endif; ?>
              </div>
            </div>
          <?php else: ?>
            <div class="ml-6 pl-4 border-l-2 border-zinc-700/50">
              <p class="text-xs text-zinc-500 italic">Aguardando resposta do vendedor...</p>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <?php
      $paginaAtual  = (int)($lista['pagina'] ?? $pagina);
      $totalPaginas = (int)($lista['total_paginas'] ?? 1);
      include __DIR__ . '/../../views/partials/pagination.php';
    ?>
  </div>
</div>

<script>
(function(){
  function toast(m,ok){
    var box=document.getElementById('vendor-toast');
    if(!box){box=document.createElement('div');box.id='vendor-toast';box.className='fixed top-8 right-4 z-[9999] px-4 py-2 rounded-lg border text-sm shadow-lg transition-opacity duration-200 opacity-0';document.body.appendChild(box);}
    box.classList.remove('border-greenx/40','bg-greenx/10','text-greenx','border-red-500/40','bg-red-500/10','text-red-300');
    box.classList.add(...(ok?['border-greenx/40','bg-greenx/10','text-greenx']:['border-red-500/40','bg-red-500/10','text-red-300']));
    box.textContent=m;box.style.opacity='1';
    clearTimeout(window.__vendorToastTimer);
    window.__vendorToastTimer=setTimeout(function(){box.style.opacity='0'},2500);
  }

  document.querySelectorAll('.js-answer-form').forEach(function(form){
    form.addEventListener('submit', async function(e){
      e.preventDefault();
      var id=form.dataset.id;
      var ta=form.querySelector('textarea');
      var msg=form.querySelector('.js-answer-msg');
      var resposta=(ta.value||'').trim();
      if(!resposta){msg.textContent='Escreva uma resposta.';msg.className='js-answer-msg text-xs text-red-400';return;}

      var btn=form.querySelector('button[type=submit]');
      btn.disabled=true;btn.style.opacity='0.5';

      try{
        var fd=new FormData();
        fd.append('acao','responder');
        fd.append('question_id',id);
        fd.append('resposta',resposta);
        var r=await fetch('perguntas',{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}});
        var j=await r.json();
        if(!j.ok){msg.textContent=j.msg;msg.className='js-answer-msg text-xs text-red-400';btn.disabled=false;btn.style.opacity='1';return;}

        toast(j.msg||'Resposta enviada!',true);
        // Replace form with answer display
        var card=document.getElementById('q-card-'+id);
        if(card){
          card.classList.remove('border-orange-500/30','bg-orange-500/5');
          card.classList.add('border-blackx3/50','bg-blackx/30');
          // Update badge
          var badge=card.querySelector('.bg-orange-500\\/15');
          if(badge){badge.outerHTML='<span class="ml-auto px-2 py-0.5 rounded-full bg-greenx/15 border border-greenx/40 text-greenx text-[10px] font-semibold uppercase tracking-wider">Respondida</span>';}
          // Replace form
          form.outerHTML='<div class="ml-6 pl-4 border-l-2 border-greenx/30 flex gap-3"><div class="flex-shrink-0 w-8 h-8 rounded-full bg-greenx/20 border border-greenx/40 flex items-center justify-center text-greenx"><i data-lucide="store" class="w-4 h-4"></i></div><div class="flex-1"><p class="text-xs font-semibold text-greenx mb-1">Você (Vendedor)</p><p class="text-sm leading-relaxed text-zinc-300">'+resposta.replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>')+'</p><p class="text-[10px] text-zinc-500 mt-1">agora</p></div></div>';
          if(window.lucide)lucide.createIcons();
        }
      }catch(err){msg.textContent='Erro.';msg.className='js-answer-msg text-xs text-red-400';btn.disabled=false;btn.style.opacity='1';}
    });
  });

  /* ── Auto-poll for new questions every 15s ──────────────────────────── */
  var knownTotal = <?= (int)$lista['total'] ?>;
  var pollInterval = setInterval(async function(){
    try {
      var r = await fetch('perguntas?_poll=1&pp=<?= $pp ?>&answered=<?= urlencode($f['answered']) ?>&q=<?= urlencode($f['q']) ?>', {
        headers: {'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}
      });
      if (!r.ok) return;
      var ct = r.headers.get('content-type') || '';
      if (ct.indexOf('json') === -1) return;
      var data = await r.json();
      if (data && data.total !== undefined && data.total > knownTotal) {
        // New questions arrived — show notification bar and auto-reload
        var bar = document.getElementById('newQuestionsBar');
        if (!bar) {
          bar = document.createElement('div');
          bar.id = 'newQuestionsBar';
          bar.className = 'mb-4 p-3 rounded-xl bg-greenx/10 border border-greenx/30 text-purple-300 text-sm flex items-center gap-2 animate-fade-in cursor-pointer';
          bar.innerHTML = '<i data-lucide="message-circle-plus" class="w-4 h-4"></i> Novas perguntas recebidas! <span class="ml-auto text-xs underline">Atualizar</span>';
          bar.addEventListener('click', function(){ location.reload(); });
          var container = document.querySelector('.space-y-4');
          if (container) container.parentNode.insertBefore(bar, container);
          if (window.lucide) lucide.createIcons();
        }
      }
    } catch(e) {}
  }, 15000);
})();
</script>

<?php include __DIR__ . '/../../views/partials/vendor_layout_end.php'; include __DIR__ . '/../../views/partials/footer.php'; ?>
