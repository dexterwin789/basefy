<?php
/**
 * Pagination partial – reusable across all dashboards.
 *
 * Expected variables (set before include):
 *   $paginaAtual   int   – current page (1-based)
 *   $totalPaginas  int   – total pages
 *   $pp            int   – items per page (current)
 *   $baseUrl       string – base URL WITHOUT ?p= (query params preserved)
 *
 * Layout: page arrows/numbers on LEFT, per-page selector on RIGHT.
 */

$paginaAtual  = (int)($paginaAtual ?? 1);
$totalPaginas = (int)($totalPaginas ?? 1);
$pp           = (int)($pp ?? 10);

// rebuild current query string without 'p' and 'pp'
$_qp = $_GET;
unset($_qp['p'], $_qp['pp']);
$_qs = http_build_query($_qp);
$_sep = $_qs !== '' ? '&' : '';

$prevPage = max(1, $paginaAtual - 1);
$nextPage = min($totalPaginas, $paginaAtual + 1);

// Determine visible page range (max 7 buttons)
$maxButtons = 7;
$half = (int)floor($maxButtons / 2);
$startPage = max(1, $paginaAtual - $half);
$endPage   = min($totalPaginas, $startPage + $maxButtons - 1);
if ($endPage - $startPage + 1 < $maxButtons) {
    $startPage = max(1, $endPage - $maxButtons + 1);
}

$ppOptions = [5, 10, 20];
$_ppUid = 'ppSel_' . mt_rand(1000,9999);
?>

<nav class="mt-5 flex items-center justify-between gap-3 flex-wrap">
  <!-- Left: page navigation -->
  <div class="flex items-center gap-1">
    <?php if ($totalPaginas > 1): ?>
      <?php if ($paginaAtual > 1): ?>
        <a href="?<?= $_qs . $_sep ?>p=1&pp=<?= $pp ?>" class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-blackx3 text-zinc-400 hover:border-greenx hover:text-white transition text-xs" title="Primeira">&laquo;</a>
        <a href="?<?= $_qs . $_sep ?>p=<?= $prevPage ?>&pp=<?= $pp ?>" class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-blackx3 text-zinc-400 hover:border-greenx hover:text-white transition text-xs" title="Anterior">&lsaquo;</a>
      <?php endif; ?>

      <?php for ($pg = $startPage; $pg <= $endPage; $pg++): ?>
        <?php if ($pg === $paginaAtual): ?>
          <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-gradient-to-r from-greenx to-greenxd text-white font-bold text-xs"><?= $pg ?></span>
        <?php else: ?>
          <a href="?<?= $_qs . $_sep ?>p=<?= $pg ?>&pp=<?= $pp ?>" class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-blackx3 text-zinc-400 hover:border-greenx hover:text-white transition text-xs"><?= $pg ?></a>
        <?php endif; ?>
      <?php endfor; ?>

      <?php if ($paginaAtual < $totalPaginas): ?>
        <a href="?<?= $_qs . $_sep ?>p=<?= $nextPage ?>&pp=<?= $pp ?>" class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-blackx3 text-zinc-400 hover:border-greenx hover:text-white transition text-xs" title="Próxima">&rsaquo;</a>
        <a href="?<?= $_qs . $_sep ?>p=<?= $totalPaginas ?>&pp=<?= $pp ?>" class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-blackx3 text-zinc-400 hover:border-greenx hover:text-white transition text-xs" title="Última">&raquo;</a>
      <?php endif; ?>
    <?php else: ?>
      <span class="text-xs text-zinc-500">Página 1 de 1</span>
    <?php endif; ?>
  </div>

  <!-- Right: per-page selector -->
  <div class="flex items-center gap-2">
    <label class="text-xs text-zinc-500 whitespace-nowrap">Por página</label>
    <select id="<?= $_ppUid ?>" class="rounded-lg bg-white/[0.04] border border-white/[0.08] px-2.5 py-1.5 text-xs text-zinc-300 focus:outline-none focus:border-greenx/50 cursor-pointer">
      <?php foreach ($ppOptions as $opt): ?>
        <option value="<?= $opt ?>" <?= $opt === $pp ? 'selected' : '' ?>><?= $opt ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</nav>
<script>
(function(){
  var sel=document.getElementById('<?= $_ppUid ?>');
  if(!sel)return;
  sel.addEventListener('change',function(){
    try{
      var url=new URL(window.location.href);
      url.searchParams.set('pp',sel.value);
      url.searchParams.set('p','1');
      window.location.assign(url.toString());
    }catch(e){
      window.location.href=window.location.pathname+'?pp='+sel.value+'&p=1';
    }
  });
})();
</script>
