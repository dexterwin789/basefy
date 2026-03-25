<?php
// filepath: c:\xampp\htdocs\mercado_admin\public\admin\push_config.php
declare(strict_types=1);

require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/push.php';
require_once __DIR__ . '/../../src/notifications.php';

iniciarSessao();
exigirAdmin();

$conn = (new Database())->connect();
pushEnsureTable($conn);

$msg = '';
$msgType = '';

// ── Actions ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ob_start(); // buffer any stray warnings so headers can still redirect
    $action = (string)($_POST['action'] ?? '');

    // Generate / regenerate VAPID keys
    if ($action === 'generate_vapid') {
        $keys = pushGenerateVapidKeys();
        if ($keys) {
            $sql = "INSERT INTO platform_settings (setting_key, setting_value)
                    VALUES (?, ?)
                    ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value";
            $st1 = $conn->prepare($sql);
            if ($st1) {
                $k = 'vapid_public_key';
                $v = $keys['publicKey'];
                $st1->bind_param('ss', $k, $v);
                $st1->execute();
                $st1->close();
            }
            $st2 = $conn->prepare($sql);
            if ($st2) {
                $k = 'vapid_private_pem';
                $v = $keys['privatePem'];
                $st2->bind_param('ss', $k, $v);
                $st2->execute();
                $st2->close();
            }
            $msg = 'Chaves VAPID geradas com sucesso!';
            $msgType = 'success';
        } else {
            $msg = 'Erro ao gerar chaves VAPID. Verifique se a extensão OpenSSL está ativa.';
            $msgType = 'error';
        }
    }

    // Send test push
    if ($action === 'test_push') {
        $testUserId = (int)($_POST['user_id'] ?? 0);
        $testTitle   = trim((string)($_POST['titulo'] ?? 'Teste Push'));
        $testBody    = trim((string)($_POST['mensagem'] ?? 'Se você viu esta notificação, o Web Push está funcionando!'));

        if ($testUserId > 0) {
            // Create notification in DB
            $nid = notificationsCreate($conn, $testUserId, 'anuncio', $testTitle, $testBody, '/dashboard');
            if ($nid > 0) {
                $msg = "Notificação criada (ID #{$nid}) e push enviado para o usuário #{$testUserId}.";
                $msgType = 'success';
            } else {
                $msg = 'Falha ao criar a notificação.';
                $msgType = 'error';
            }
        } else {
            $msg = 'Informe um ID de usuário válido.';
            $msgType = 'error';
        }
    }

    // Broadcast push
    if ($action === 'broadcast') {
        $bcTitle = trim((string)($_POST['titulo'] ?? ''));
        $bcBody  = trim((string)($_POST['mensagem'] ?? ''));

        if ($bcTitle !== '') {
            notificationsBroadcast($conn, 'anuncio', $bcTitle, $bcBody, '/dashboard');

            // Send push to all users with subscriptions
            $allSubs = $conn->query("SELECT DISTINCT user_id FROM push_subscriptions");
            $vapid = pushGetVapidKeys($conn);
            $sentCount = 0;
            if ($allSubs && $vapid) {
                while ($row = $allSubs->fetch_assoc()) {
                    $uid = (int)$row['user_id'];
                    $sentCount += pushSendToUser($conn, $uid, $bcTitle, $bcBody, '/dashboard');
                }
            }
            $msg = "Broadcast enviado! Push enviado para {$sentCount} inscrição(ões).";
            $msgType = 'success';
        } else {
            $msg = 'O título é obrigatório.';
            $msgType = 'error';
        }
    }

    // Delete subscription
    if ($action === 'delete_sub') {
        $subId = (int)($_POST['sub_id'] ?? 0);
        if ($subId > 0) {
            $st = $conn->prepare("DELETE FROM push_subscriptions WHERE id = ?");
            if ($st) {
                $st->bind_param('i', $subId);
                $st->execute();
                $st->close();
            }
            $msg = 'Inscrição removida.';
            $msgType = 'success';
        }
    }

    // Push diagnostic (no redirect — show inline results)
    if ($action === 'push_diag') {
        $diagUserId = (int)($_POST['diag_user_id'] ?? 0);
        $diagResult = ['user_id' => $diagUserId, 'steps' => []];

        if ($diagUserId <= 0) {
            $diagResult['steps'][] = ['step' => 'Validação', 'status' => 'FAIL', 'detail' => 'user_id inválido'];
        } else {
            // Step 1: Check subscriptions
            $subs = pushGetUserSubscriptions($conn, $diagUserId);
            $diagResult['steps'][] = [
                'step' => '1. Inscrições push',
                'status' => count($subs) > 0 ? 'OK' : 'FAIL',
                'detail' => count($subs) . ' inscrição(ões) encontrada(s)',
                'endpoints' => array_map(fn($s) => substr($s['endpoint'], 0, 80) . '…', $subs)
            ];

            // Step 2: Check VAPID keys
            $vapidD = pushGetVapidKeys($conn);
            $diagResult['steps'][] = [
                'step' => '2. Chaves VAPID',
                'status' => $vapidD ? 'OK' : 'FAIL',
                'detail' => $vapidD ? 'Chave pública: ' . substr($vapidD['publicKey'], 0, 30) . '…' : 'Nenhuma chave VAPID configurada'
            ];

            // Step 3: Try sending
            if (count($subs) > 0 && $vapidD) {
                try {
                    $sent = pushSendToUser($conn, $diagUserId, 'Diagnóstico Push', 'Se você recebeu esta notificação, o push automático está funcionando!', '/dashboard');
                    $diagResult['steps'][] = [
                        'step' => '3. Envio push',
                        'status' => $sent > 0 ? 'OK' : 'FAIL',
                        'detail' => "Enviado para {$sent}/" . count($subs) . " inscrição(ões)"
                    ];
                } catch (\Throwable $de) {
                    $diagResult['steps'][] = [
                        'step' => '3. Envio push',
                        'status' => 'ERROR',
                        'detail' => $de->getMessage()
                    ];
                }

                // Also create a notification to test the full chain
                try {
                    $nid = notificationsCreate($conn, $diagUserId, 'anuncio', 'Diagnóstico automático', 'Teste da cadeia completa: notificação + push.', '/dashboard');
                    $diagResult['steps'][] = [
                        'step' => '4. notificationsCreate chain',
                        'status' => $nid > 0 ? 'OK' : 'WARN',
                        'detail' => 'Notification ID=' . $nid . ' (push disparado dentro da função)'
                    ];
                } catch (\Throwable $de2) {
                    $diagResult['steps'][] = [
                        'step' => '4. notificationsCreate chain',
                        'status' => 'ERROR',
                        'detail' => $de2->getMessage()
                    ];
                }
            } else {
                $diagResult['steps'][] = [
                    'step' => '3. Envio push',
                    'status' => 'SKIP',
                    'detail' => 'Sem inscrições ou sem VAPID — não é possível enviar'
                ];
            }
        }

        $_SESSION['push_diag'] = $diagResult;
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    // PRG redirect
    $_SESSION['push_msg']      = $msg;
    $_SESSION['push_msg_type'] = $msgType;
    ob_end_clean(); // discard any buffered warnings
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Flash message
if (!empty($_SESSION['push_msg'])) {
    $msg     = $_SESSION['push_msg'];
    $msgType = $_SESSION['push_msg_type'] ?? 'success';
    unset($_SESSION['push_msg'], $_SESSION['push_msg_type']);
}

// Flash push diagnostic result
$diagFlash = $_SESSION['push_diag'] ?? null;
unset($_SESSION['push_diag']);

// ── Data ─────────────────────────────────────────────────────────────────
$vapidKeys = pushGetVapidKeys($conn);

// Count subscriptions
$subCountRow = $conn->query("SELECT COUNT(*) AS cnt FROM push_subscriptions")->fetch_assoc();
$subCount = (int)($subCountRow['cnt'] ?? 0);

// ── Pagination & filter for subscriptions ──
$pp        = in_array((int)($_GET['pp'] ?? 10), [5, 10, 20], true) ? (int)($_GET['pp'] ?? 10) : 10;
$subPage   = max(1, (int)($_GET['p'] ?? 1));
$subLimit  = $pp;
$subOffset = ($subPage - 1) * $subLimit;
$subSearch = trim((string)($_GET['busca'] ?? ''));

$subWhere  = '';
$subTypes  = '';
$subParams = [];

if ($subSearch !== '') {
    $subWhere = " WHERE (u.nome ILIKE ? OR u.email ILIKE ? OR ps.endpoint ILIKE ?)";
    $subTypes = 'sss';
    $like = '%' . $subSearch . '%';
    $subParams = [$like, $like, $like];
}

// Total filtered count
$stCntFiltered = $conn->prepare("SELECT COUNT(*) AS cnt FROM push_subscriptions ps LEFT JOIN users u ON u.id = ps.user_id" . $subWhere);
if ($stCntFiltered) {
    if ($subTypes !== '') {
        $bind = array_merge([$subTypes], $subParams);
        $refs = [];
        foreach ($bind as $k => $v) $refs[$k] = &$bind[$k];
        call_user_func_array([$stCntFiltered, 'bind_param'], $refs);
    }
    $stCntFiltered->execute();
    $filteredCount = (int)($stCntFiltered->get_result()->fetch_assoc()['cnt'] ?? 0);
    $stCntFiltered->close();
} else {
    $filteredCount = $subCount;
}

$totalSubPages = max(1, (int)ceil($filteredCount / $subLimit));

// List subscriptions (paginated)
$subsRows = [];
$stSubs = $conn->prepare("
    SELECT ps.id, ps.user_id, ps.endpoint, ps.criado_em, u.nome AS user_nome, u.email AS user_email
    FROM push_subscriptions ps
    LEFT JOIN users u ON u.id = ps.user_id
    {$subWhere}
    ORDER BY ps.criado_em DESC
    LIMIT {$subLimit} OFFSET {$subOffset}
");
if ($stSubs) {
    if ($subTypes !== '') {
        $bind = array_merge([$subTypes], $subParams);
        $refs = [];
        foreach ($bind as $k => $v) $refs[$k] = &$bind[$k];
        call_user_func_array([$stSubs, 'bind_param'], $refs);
    }
    $stSubs->execute();
    $subsRows = $stSubs->get_result()->fetch_all(MYSQLI_ASSOC);
    $stSubs->close();
}

$pageTitle  = 'Web Push Notifications';
$activeMenu = 'push_config';
include __DIR__ . '/../../views/partials/header.php';
include __DIR__ . '/../../views/partials/admin_layout_start.php';
?>

<div class="space-y-6">

    <?php if ($msg !== ''): ?>
    <div class="flex items-center gap-3 rounded-xl border px-4 py-3 text-sm <?= $msgType === 'success' ? 'border-greenx/30 bg-greenx/10 text-greenx' : 'border-red-500/30 bg-red-500/10 text-red-400' ?>">
        <i data-lucide="<?= $msgType === 'success' ? 'check-circle' : 'alert-circle' ?>" class="w-4 h-4 shrink-0"></i>
        <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <!-- VAPID Keys -->
    <div class="bg-blackx2 border border-blackx3 rounded-2xl p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold inline-flex items-center gap-2">
                <i data-lucide="key-round" class="w-5 h-5 text-greenx"></i>
                Chaves VAPID
            </h2>
            <form method="post">
                <input type="hidden" name="action" value="generate_vapid">
                <button class="px-4 py-2 rounded-xl border border-blackx3 bg-blackx text-sm font-semibold text-zinc-300 hover:border-greenx/40 hover:text-greenx transition-all">
                    <i data-lucide="refresh-cw" class="w-3.5 h-3.5 inline mr-1"></i>
                    <?= $vapidKeys ? 'Regenerar Chaves' : 'Gerar Chaves' ?>
                </button>
            </form>
        </div>

        <?php if ($vapidKeys): ?>
        <div class="space-y-3">
            <div>
                <label class="text-xs text-zinc-500 uppercase font-bold tracking-wide">Chave Pública (Application Server Key)</label>
                <div class="mt-1 flex items-center gap-2">
                    <input type="text" readonly value="<?= htmlspecialchars($vapidKeys['publicKey']) ?>"
                           class="flex-1 px-3 py-2 rounded-lg bg-blackx border border-blackx3 text-xs text-zinc-300 font-mono" id="vapidPubKey">
                    <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('vapidPubKey').value).then(()=>this.textContent='Copiado!')"
                            class="px-3 py-2 rounded-lg border border-blackx3 text-xs text-zinc-400 hover:text-greenx hover:border-greenx/30 transition-all shrink-0">
                        Copiar
                    </button>
                </div>
            </div>
            <div>
                <label class="text-xs text-zinc-500 uppercase font-bold tracking-wide">Chave Privada (PEM)</label>
                <textarea readonly rows="4"
                          class="w-full mt-1 px-3 py-2 rounded-lg bg-blackx border border-blackx3 text-xs text-zinc-400 font-mono resize-none"><?= htmlspecialchars($vapidKeys['privatePem']) ?></textarea>
                <p class="text-[10px] text-zinc-600 mt-1">Nunca compartilhe a chave privada. Ela é usada internamente para assinar os tokens JWT do VAPID.</p>
            </div>
        </div>
        <?php else: ?>
        <p class="text-sm text-zinc-500">Nenhuma chave VAPID configurada. Clique em "Gerar Chaves" para começar.</p>
        <?php endif; ?>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-blackx2 border border-blackx3 rounded-2xl p-5 text-center">
            <p class="text-3xl font-black text-greenx"><?= $subCount ?></p>
            <p class="text-xs text-zinc-500 uppercase font-bold mt-1">Inscrições Push</p>
        </div>
        <div class="bg-blackx2 border border-blackx3 rounded-2xl p-5 text-center">
            <p class="text-3xl font-black <?= $vapidKeys ? 'text-greenx' : 'text-red-400' ?>"><?= $vapidKeys ? 'Ativo' : 'Inativo' ?></p>
            <p class="text-xs text-zinc-500 uppercase font-bold mt-1">Status VAPID</p>
        </div>
        <div class="bg-blackx2 border border-blackx3 rounded-2xl p-5 text-center">
            <?php
            $uniqueUsersRow = $conn->query("SELECT COUNT(DISTINCT user_id) AS cnt FROM push_subscriptions")->fetch_assoc();
            $uniqueUsersCount = (int)($uniqueUsersRow['cnt'] ?? 0);
            ?>
            <p class="text-3xl font-black text-purple-400"><?= $uniqueUsersCount ?></p>
            <p class="text-xs text-zinc-500 uppercase font-bold mt-1">Usuários Inscritos</p>
        </div>
    </div>

    <!-- Test Push & Broadcast -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Test Push to User -->
        <div class="bg-blackx2 border border-blackx3 rounded-2xl p-6">
            <h3 class="text-sm font-bold uppercase tracking-wide mb-4 inline-flex items-center gap-2">
                <i data-lucide="send" class="w-4 h-4 text-purple-400"></i>
                Enviar Push (teste)
            </h3>
            <form method="post" class="space-y-3">
                <input type="hidden" name="action" value="test_push">
                <div>
                    <label class="text-xs text-zinc-400 mb-1 block">ID do Usuário</label>
                    <input type="number" name="user_id" required min="1" placeholder="Ex: 1"
                           class="w-full px-3 py-2 rounded-lg bg-blackx border border-blackx3 text-sm focus:border-greenx/50 focus:outline-none transition-colors">
                </div>
                <div>
                    <label class="text-xs text-zinc-400 mb-1 block">Título</label>
                    <input type="text" name="titulo" value="Teste Push" required
                           class="w-full px-3 py-2 rounded-lg bg-blackx border border-blackx3 text-sm focus:border-greenx/50 focus:outline-none transition-colors">
                </div>
                <div>
                    <label class="text-xs text-zinc-400 mb-1 block">Mensagem</label>
                    <textarea name="mensagem" rows="2" class="w-full px-3 py-2 rounded-lg bg-blackx border border-blackx3 text-sm focus:border-greenx/50 focus:outline-none resize-none transition-colors">Se você viu esta notificação, o Web Push está funcionando!</textarea>
                </div>
                <button class="w-full flex items-center justify-center gap-2 rounded-xl bg-greenx hover:bg-greenx2 text-white font-bold px-4 py-2.5 text-sm transition-all">
                    <i data-lucide="send" class="w-4 h-4"></i>
                    Enviar Push de Teste
                </button>
            </form>
        </div>

        <!-- Broadcast -->
        <div class="bg-blackx2 border border-blackx3 rounded-2xl p-6">
            <h3 class="text-sm font-bold uppercase tracking-wide mb-4 inline-flex items-center gap-2">
                <i data-lucide="megaphone" class="w-4 h-4 text-amber-400"></i>
                Broadcast (todos os usuários)
            </h3>
            <form method="post" class="space-y-3">
                <input type="hidden" name="action" value="broadcast">
                <div>
                    <label class="text-xs text-zinc-400 mb-1 block">Título *</label>
                    <input type="text" name="titulo" required placeholder="Ex: Novidade na plataforma!"
                           class="w-full px-3 py-2 rounded-lg bg-blackx border border-blackx3 text-sm focus:border-greenx/50 focus:outline-none transition-colors">
                </div>
                <div>
                    <label class="text-xs text-zinc-400 mb-1 block">Mensagem</label>
                    <textarea name="mensagem" rows="2" placeholder="Descrição da notificação..." class="w-full px-3 py-2 rounded-lg bg-blackx border border-blackx3 text-sm focus:border-greenx/50 focus:outline-none resize-none transition-colors"></textarea>
                </div>
                <button class="w-full flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-amber-600 to-amber-500 hover:from-amber-500 hover:to-amber-400 text-black font-bold px-4 py-2.5 text-sm transition-all">
                    <i data-lucide="megaphone" class="w-4 h-4"></i>
                    Enviar Broadcast
                </button>
            </form>
        </div>
    </div>

    <!-- Push Diagnostic -->
    <div class="bg-blackx2 border border-blackx3 rounded-2xl p-6">
        <h3 class="text-sm font-bold uppercase tracking-wide mb-4 inline-flex items-center gap-2">
            <i data-lucide="stethoscope" class="w-4 h-4 text-purple-400"></i>
            Diagnóstico Push
        </h3>
        <p class="text-xs text-zinc-500 mb-3">Testa cada etapa da cadeia: inscrição &rarr; VAPID &rarr; envio direto &rarr; envio via notificationsCreate.</p>
        <form method="post" class="flex items-end gap-3">
            <input type="hidden" name="action" value="push_diag">
            <div class="flex-1">
                <label class="text-xs text-zinc-400 mb-1 block">ID do Usuário</label>
                <input type="number" name="diag_user_id" required min="1" placeholder="Ex: 1"
                       class="w-full px-3 py-2 rounded-lg bg-blackx border border-blackx3 text-sm focus:border-purple-400/50 focus:outline-none transition-colors">
            </div>
            <button class="px-5 py-2 rounded-xl border border-purple-500 text-purple-300 hover:bg-purple-500/15 font-bold text-sm transition-all shrink-0">
                <i data-lucide="activity" class="w-4 h-4 inline mr-1"></i>
                Diagnosticar
            </button>
        </form>

        <?php if ($diagFlash): ?>
        <div class="mt-4 border-t border-blackx3 pt-4 space-y-2">
            <p class="text-xs text-zinc-400 font-bold mb-2">Resultado para user_id=<?= (int)$diagFlash['user_id'] ?></p>
            <?php foreach ($diagFlash['steps'] as $ds): ?>
            <div class="flex items-start gap-2 text-sm">
                <?php
                $ico = match($ds['status']) {
                    'OK' => '<span class="text-greenx font-bold">OK</span>',
                    'FAIL' => '<span class="text-red-400 font-bold">FAIL</span>',
                    'WARN' => '<span class="text-amber-400 font-bold">WARN</span>',
                    'SKIP' => '<span class="text-zinc-500 font-bold">SKIP</span>',
                    default => '<span class="text-red-400 font-bold">ERR</span>',
                };
                ?>
                <span class="w-12 shrink-0"><?= $ico ?></span>
                <span class="text-zinc-300"><?= htmlspecialchars($ds['step']) ?>:</span>
                <span class="text-zinc-400"><?= htmlspecialchars($ds['detail']) ?></span>
            </div>
            <?php if (!empty($ds['endpoints'])): ?>
                <?php foreach ($ds['endpoints'] as $ep): ?>
                <div class="ml-14 text-[10px] text-zinc-600 font-mono truncate"><?= htmlspecialchars($ep) ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Debug Log Link -->
    <div class="flex items-center justify-center">
        <a href="push_debug_log" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-purple-500/30 text-purple-300 hover:bg-purple-500/10 text-sm font-semibold transition-all">
            <i data-lucide="file-text" class="w-4 h-4"></i>
            Ver Push Debug Log (diagnóstico detalhado)
        </a>
    </div>

    <!-- Subscriptions List -->
    <div class="bg-blackx2 border border-blackx3 rounded-2xl p-6">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-4">
            <h3 class="text-sm font-bold uppercase tracking-wide inline-flex items-center gap-2">
                <i data-lucide="users" class="w-4 h-4 text-greenx"></i>
                Inscrições Push (<?= $filteredCount ?><?= $subSearch !== '' ? ' filtrado(s)' : '' ?>)
            </h3>
            <!-- Search filter -->
            <form method="get" class="flex items-center gap-2 w-full sm:w-auto">
                <div class="relative flex-1 sm:flex-none">
                    <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-zinc-500"></i>
                    <input type="text" name="busca" value="<?= htmlspecialchars($subSearch) ?>" placeholder="Buscar nome, email ou endpoint..."
                           class="w-full sm:w-64 pl-9 pr-3 py-2 rounded-lg bg-blackx border border-blackx3 text-xs focus:border-greenx/50 focus:outline-none transition-colors">
                </div>
                <button class="px-3 py-2 rounded-lg border border-blackx3 bg-blackx text-xs font-semibold text-zinc-300 hover:border-greenx/40 hover:text-greenx transition-all shrink-0">
                    Filtrar
                </button>
                <?php if ($subSearch !== ''): ?>
                <a href="<?= strtok($_SERVER['REQUEST_URI'], '?') ?>" class="px-3 py-2 rounded-lg border border-blackx3 bg-blackx text-xs text-zinc-400 hover:text-red-400 hover:border-red-400/30 transition-all shrink-0">
                    Limpar
                </a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (empty($subsRows)): ?>
        <p class="text-sm text-zinc-500 text-center py-8">
            <?= $subSearch !== '' ? 'Nenhuma inscrição encontrada para esta busca.' : 'Nenhuma inscrição push registrada ainda.' ?>
        </p>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-blackx3 text-left text-[10px] uppercase tracking-wide text-zinc-500">
                        <th class="pb-2 px-3">ID</th>
                        <th class="pb-2 px-3">Usuário</th>
                        <th class="pb-2 px-3">Endpoint</th>
                        <th class="pb-2 px-3">Data</th>
                        <th class="pb-2 px-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-blackx3">
                    <?php foreach ($subsRows as $sub): ?>
                    <tr class="hover:bg-white/[0.02] transition-colors">
                        <td class="px-3 py-2.5 text-zinc-400"><?= (int)$sub['id'] ?></td>
                        <td class="px-3 py-2.5">
                            <div class="font-semibold text-zinc-200">#<?= (int)$sub['user_id'] ?> — <?= htmlspecialchars((string)($sub['user_nome'] ?? '?')) ?></div>
                            <div class="text-[10px] text-zinc-500"><?= htmlspecialchars((string)($sub['user_email'] ?? '')) ?></div>
                        </td>
                        <td class="px-3 py-2.5 text-[10px] text-zinc-500 max-w-[200px] truncate font-mono"><?= htmlspecialchars(substr((string)$sub['endpoint'], 0, 80)) ?>…</td>
                        <td class="px-3 py-2.5 text-zinc-400 text-xs whitespace-nowrap"><?= date('d/m/Y H:i', strtotime((string)$sub['criado_em'])) ?></td>
                        <td class="px-3 py-2.5">
                            <form method="post" onsubmit="return confirm('Remover esta inscrição?')">
                                <input type="hidden" name="action" value="delete_sub">
                                <input type="hidden" name="sub_id" value="<?= (int)$sub['id'] ?>">
                                <button class="text-red-400 hover:text-red-300 transition-colors" title="Remover">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="mt-4 pt-4 border-t border-blackx3">
            <p class="text-xs text-zinc-500 mb-2"><?= $filteredCount ?> inscrição(ões)</p>
            <?php
            $paginaAtual  = $subPage;
            $totalPaginas = $totalSubPages;
            // $pp já definido acima
            include __DIR__ . '/../../views/partials/pagination.php';
            ?>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php
include __DIR__ . '/../../views/partials/admin_layout_end.php';
include __DIR__ . '/../../views/partials/footer.php';
?>
