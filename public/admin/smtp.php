<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/email.php';

exigirAdmin();

$conn = (new Database())->connect();

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transport    = trim((string)($_POST['transport'] ?? 'smtp'));
    $smtpHost     = trim((string)($_POST['smtp_host'] ?? ''));
    $smtpPort     = trim((string)($_POST['smtp_port'] ?? '587'));
    $smtpUser     = trim((string)($_POST['smtp_user'] ?? ''));
    $smtpPass     = trim((string)($_POST['smtp_pass'] ?? ''));
    $smtpFrom     = trim((string)($_POST['smtp_from'] ?? ''));
    $smtpFromName = trim((string)($_POST['smtp_from_name'] ?? ''));
    $resendKey    = trim((string)($_POST['resend_api_key'] ?? ''));

    try {
        smtpSettingSet($conn, 'transport', $transport);
        smtpSettingSet($conn, 'host', $smtpHost);
        smtpSettingSet($conn, 'port', $smtpPort);
        smtpSettingSet($conn, 'user', $smtpUser);
        smtpSettingSet($conn, 'pass', $smtpPass);
        smtpSettingSet($conn, 'from', $smtpFrom);
        smtpSettingSet($conn, 'from_name', $smtpFromName);
        smtpSettingSet($conn, 'resend_api_key', $resendKey);
        $msg = 'Configurações salvas!';
    } catch (\Throwable $e) {
        $err = 'Erro ao salvar: ' . $e->getMessage();
    }

    // Test connection if requested
    if (isset($_POST['test_email']) && trim((string)$_POST['test_email']) !== '') {
        $testTo = trim((string)$_POST['test_email']);
        try {
            $ok = smtpSend($testTo, 'Teste E-mail — ' . date('H:i:s'), '<h2>Teste de E-mail</h2><p>Se você recebeu este e-mail, a configuração está funcionando.</p><p>Enviado em: ' . date('d/m/Y H:i:s') . '</p><p>Transporte: ' . htmlspecialchars($transport) . '</p>');
            if ($ok) {
                $msg = 'Configurações salvas e e-mail de teste enviado para ' . htmlspecialchars($testTo) . '!';
            } else {
                $lastSmtpErr = smtpLastError();
                $err = 'Configurações salvas mas falha ao enviar e-mail de teste.' . ($lastSmtpErr ? ' Erro: ' . htmlspecialchars($lastSmtpErr) : ' Verifique as credenciais.');
            }
        } catch (\Throwable $e) {
            $err = 'Configurações salvas mas erro no teste: ' . $e->getMessage();
        }
    }
}

$settings = smtpGetAllSettings($conn);
$currentTransport = smtpSettingGet($conn, 'transport', 'smtp');
$resendApiKey = smtpSettingGet($conn, 'resend_api_key', '');
$isConfigured = smtpConfigured($conn);

$pageTitle = 'Configuração de E-mail';
$activeMenu = 'smtp';
include __DIR__ . '/../../views/partials/header.php';
include __DIR__ . '/../../views/partials/admin_layout_start.php';
?>

<main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-3xl">

    <h1 class="text-2xl font-bold mb-2">Configuração de E-mail</h1>
    <p class="text-sm text-zinc-400 mb-6">Configure o envio de e-mails para verificações, notificações e recuperação de senha.</p>

    <?php if ($msg): ?>
    <div class="mb-4 px-4 py-3 rounded-xl bg-greenx/10 border border-greenx/20 text-greenx text-sm"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <?php if ($err): ?>
    <div class="mb-4 px-4 py-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm"><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>

    <?php
    // Run diagnostics if form was posted with test
    $diagLog = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email']) && trim((string)$_POST['test_email']) !== '') {
        $activeTransport = smtpSettingGet($conn, 'transport', 'smtp');
        $diagLog[] = '[INFO] Transporte: ' . strtoupper($activeTransport);

        if ($activeTransport === 'resend') {
            $rKey = smtpSettingGet($conn, 'resend_api_key', '');
            $diagLog[] = '[INFO] API Key: ' . (strlen($rKey) > 0 ? substr($rKey, 0, 8) . '...' . ' (' . strlen($rKey) . ' chars)' : '(vazia)');
            $diagLog[] = '[INFO] From: ' . ($settings['from'] ?: 'onboarding@resend.dev');

            // Test HTTPS connectivity to Resend API
            $ch = curl_init('https://api.resend.com/emails');
            curl_setopt_array($ch, [CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_SSL_VERIFYPEER => true]);
            curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            curl_close($ch);

            if ($curlErr) {
                $diagLog[] = '[ERRO] Resend API inacessível: ' . $curlErr;
            } else {
                $diagLog[] = '[OK] Resend API acessível (HTTPS porta 443)';
            }
        } else {
            $dHost = $settings['host'];
            $dPort = (int)($settings['port'] ?: 587);
            $diagLog[] = '[INFO] Host: ' . ($dHost ?: '(vazio)') . ', Porta: ' . $dPort;
            $diagLog[] = '[INFO] Usuário: ' . ($settings['user'] ?: '(vazio)');
            $diagLog[] = '[INFO] Senha: ' . (strlen($settings['pass']) > 0 ? str_repeat('*', min(8, strlen($settings['pass']))) . ' (' . strlen($settings['pass']) . ' chars)' : '(vazia)');

            if ($dHost) {
                $dIp = @gethostbyname($dHost);
                if ($dIp === $dHost) {
                    $diagLog[] = '[ERRO] DNS: não resolveu "' . $dHost . '" — verifique o hostname';
                } else {
                    $diagLog[] = '[OK] DNS: ' . $dHost . ' → ' . $dIp;
                }

                foreach (array_unique([$dPort, 465, 587]) as $testPort) {
                    $testPort = (int)$testPort;
                    $prefix = ($testPort === 465) ? 'ssl://' : 'tcp://';
                    $ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]]);
                    $sock = @stream_socket_client($prefix . $dHost . ':' . $testPort, $en, $es, 5, STREAM_CLIENT_CONNECT, $ctx);
                    if ($sock) {
                        @fclose($sock);
                        $diagLog[] = '[OK] Porta ' . $testPort . ' (' . $prefix . ') — acessível';
                    } else {
                        $diagLog[] = '[ERRO] Porta ' . $testPort . ' (' . $prefix . ') — bloqueada ou timeout: ' . ($es ?: 'sem resposta') . ' (' . $en . ')';
                    }
                }
            } else {
                $diagLog[] = '[ERRO] Host SMTP não definido';
            }
        }

        $diagLog[] = '[INFO] Último erro: ' . (smtpLastError() ?: '(nenhum)');
    }
    ?>

    <?php if (!empty($diagLog)): ?>
    <div class="mb-6 bg-blackx2 border border-blackx3 rounded-2xl p-5">
        <h3 class="text-sm font-bold text-orange-300 mb-3 flex items-center gap-2"><i data-lucide="terminal" class="w-4 h-4"></i> Diagnóstico</h3>
        <div class="bg-black rounded-xl p-4 font-mono text-xs space-y-1 max-h-64 overflow-y-auto">
            <?php foreach ($diagLog as $line):
                $cl = str_starts_with($line, '[OK]') ? 'text-greenx' : (str_starts_with($line, '[ERRO]') ? 'text-red-400' : 'text-zinc-400');
            ?>
            <div class="<?= $cl ?>"><?= htmlspecialchars($line) ?></div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Config form -->
    <form method="post" class="space-y-5" x-data="{ transport: '<?= htmlspecialchars($currentTransport) ?>' }">

        <!-- Transport selector -->
        <div class="bg-blackx2 border border-blackx3 rounded-2xl p-6">
            <h3 class="text-sm font-bold text-zinc-300 mb-4 flex items-center gap-2"><i data-lucide="route" class="w-4 h-4 text-purple-400"></i> Método de envio</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <label class="cursor-pointer rounded-xl border-2 p-4 transition-all" :class="transport === 'resend' ? 'border-greenx bg-greenx/[0.06]' : 'border-blackx3 bg-blackx/40 hover:border-zinc-600'">
                    <input type="radio" name="transport" value="resend" x-model="transport" class="hidden">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center" :class="transport === 'resend' ? 'bg-greenx/20' : 'bg-zinc-700/50'">
                            <i data-lucide="zap" class="w-5 h-5" :class="transport === 'resend' ? 'text-greenx' : 'text-zinc-400'"></i>
                        </div>
                        <div>
                            <p class="font-semibold text-sm">Resend (API HTTP)</p>
                            <p class="text-[11px] text-zinc-500">Funciona no Railway/Render. Recomendado.</p>
                        </div>
                    </div>
                </label>
                <label class="cursor-pointer rounded-xl border-2 p-4 transition-all" :class="transport === 'smtp' ? 'border-greenx bg-greenx/[0.06]' : 'border-blackx3 bg-blackx/40 hover:border-zinc-600'">
                    <input type="radio" name="transport" value="smtp" x-model="transport" class="hidden">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center" :class="transport === 'smtp' ? 'bg-greenx/20' : 'bg-zinc-700/50'">
                            <i data-lucide="mail" class="w-5 h-5" :class="transport === 'smtp' ? 'text-greenx' : 'text-zinc-400'"></i>
                        </div>
                        <div>
                            <p class="font-semibold text-sm">SMTP Direto</p>
                            <p class="text-[11px] text-zinc-500">Gmail, Outlook, etc. Bloqueado no Railway.</p>
                        </div>
                    </div>
                </label>
            </div>
        </div>

        <!-- Resend config -->
        <div x-show="transport === 'resend'" x-transition x-cloak class="bg-blackx2 border border-blackx3 rounded-2xl p-6 space-y-5">
            <div class="bg-greenx/[0.06] border border-greenx/20 rounded-xl p-4">
                <h4 class="text-sm font-bold text-purple-400 mb-2 flex items-center gap-2"><i data-lucide="info" class="w-4 h-4"></i> Como configurar o Resend</h4>
                <ol class="text-sm text-zinc-300 space-y-1.5 list-decimal list-inside">
                    <li>Crie uma conta gratuita em <a href="https://resend.com" target="_blank" class="text-purple-400 hover:underline font-medium">resend.com</a></li>
                    <li>Vá em <strong>API Keys</strong> e crie uma chave</li>
                    <li>Cole a chave abaixo (começa com <code class="px-1.5 py-0.5 rounded bg-black/30 text-greenx text-xs font-mono">re_</code>)</li>
                    <li>Para remetente customizado, adicione e verifique um domínio em <strong>Domains</strong></li>
                    <li>Gratuito: <strong>100 emails/dia</strong>, <strong>3.000/mês</strong></li>
                </ol>
            </div>

            <div>
                <label class="text-sm text-zinc-400 mb-1 block">Resend API Key *</label>
                <input type="password" name="resend_api_key" value="<?= htmlspecialchars($resendApiKey) ?>"
                       class="w-full rounded-xl bg-white/[0.03] border border-white/[0.08] px-4 py-3 text-sm focus:border-greenx/50 focus:outline-none transition-colors font-mono"
                       placeholder="re_xxxxxxxxxxxxxxxxxxxxxxxxxxxx">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="text-sm text-zinc-400 mb-1 block">E-mail remetente (From)</label>
                    <input type="email" name="smtp_from" value="<?= htmlspecialchars($settings['from']) ?>"
                           class="w-full rounded-xl bg-white/[0.03] border border-white/[0.08] px-4 py-3 text-sm focus:border-greenx/50 focus:outline-none transition-colors font-mono"
                           placeholder="onboarding@resend.dev">
                    <p class="text-xs text-zinc-500 mt-1">Use <code class="text-greenx text-xs">onboarding@resend.dev</code> para testar, ou seu domínio verificado</p>
                </div>
                <div>
                    <label class="text-sm text-zinc-400 mb-1 block">Nome do remetente</label>
                    <input type="text" name="smtp_from_name" value="<?= htmlspecialchars($settings['from_name']) ?>"
                           class="w-full rounded-xl bg-white/[0.03] border border-white/[0.08] px-4 py-3 text-sm focus:border-greenx/50 focus:outline-none transition-colors font-mono"
                           placeholder="Minha Loja">
                </div>
            </div>
        </div>

        <!-- SMTP config -->
        <div x-show="transport === 'smtp'" x-transition x-cloak class="space-y-5">
            <!-- Railway warning -->
            <div class="bg-orange-500/[0.06] border border-orange-500/20 rounded-2xl p-5">
                <h3 class="text-sm font-bold text-orange-300 mb-2 flex items-center gap-2">
                    <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                    Railway / Render / Fly.io — Portas SMTP bloqueadas
                </h3>
                <p class="text-sm text-zinc-300">Plataformas como Railway bloqueiam portas 587 e 465, causando <code class="px-1 py-0.5 rounded bg-black/30 text-red-300 text-xs font-mono">Connection timed out</code>. Use <strong>Resend (API HTTP)</strong> para contornar.</p>
            </div>

            <div class="bg-blackx2 border border-blackx3 rounded-2xl p-6 space-y-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label class="text-sm text-zinc-400 mb-1 block">Host SMTP *</label>
                        <input type="text" name="smtp_host" value="<?= htmlspecialchars($settings['host']) ?>"
                               class="w-full rounded-xl bg-white/[0.03] border border-white/[0.08] px-4 py-3 text-sm focus:border-greenx/50 focus:outline-none transition-colors font-mono"
                               placeholder="smtp.gmail.com">
                    </div>
                    <div>
                        <label class="text-sm text-zinc-400 mb-1 block">Porta *</label>
                        <input type="number" name="smtp_port" value="<?= htmlspecialchars($settings['port'] ?: '587') ?>"
                               class="w-full rounded-xl bg-white/[0.03] border border-white/[0.08] px-4 py-3 text-sm focus:border-greenx/50 focus:outline-none transition-colors font-mono"
                               placeholder="587">
                        <p class="text-xs text-zinc-500 mt-1">587 (STARTTLS) ou 465 (SSL)</p>
                    </div>
                </div>

                <div>
                    <label class="text-sm text-zinc-400 mb-1 block">Usuário / E-mail de login *</label>
                    <input type="text" name="smtp_user" value="<?= htmlspecialchars($settings['user']) ?>"
                           class="w-full rounded-xl bg-white/[0.03] border border-white/[0.08] px-4 py-3 text-sm focus:border-greenx/50 focus:outline-none transition-colors font-mono"
                           placeholder="seu@email.com">
                </div>

                <div>
                    <label class="text-sm text-zinc-400 mb-1 block">Senha / App Password *</label>
                    <input type="password" name="smtp_pass" value="<?= htmlspecialchars($settings['pass']) ?>"
                           class="w-full rounded-xl bg-white/[0.03] border border-white/[0.08] px-4 py-3 text-sm focus:border-greenx/50 focus:outline-none transition-colors font-mono"
                           placeholder="••••••••••••">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label class="text-sm text-zinc-400 mb-1 block">E-mail remetente (From)</label>
                        <input type="email" name="smtp_from" value="<?= htmlspecialchars($settings['from']) ?>"
                               class="w-full rounded-xl bg-white/[0.03] border border-white/[0.08] px-4 py-3 text-sm focus:border-greenx/50 focus:outline-none transition-colors font-mono"
                               placeholder="noreply@seusite.com">
                        <p class="text-xs text-zinc-500 mt-1">Deixe vazio para usar o e-mail de login</p>
                    </div>
                    <div>
                        <label class="text-sm text-zinc-400 mb-1 block">Nome do remetente</label>
                        <input type="text" name="smtp_from_name" value="<?= htmlspecialchars($settings['from_name']) ?>"
                               class="w-full rounded-xl bg-white/[0.03] border border-white/[0.08] px-4 py-3 text-sm focus:border-greenx/50 focus:outline-none transition-colors font-mono"
                               placeholder="Minha Loja">
                    </div>
                </div>
            </div>
        </div>

        <!-- Test area -->
        <div class="bg-blackx2 border border-blackx3 rounded-2xl p-6 space-y-4">
            <h3 class="text-sm font-bold text-zinc-300 flex items-center gap-2"><i data-lucide="mail-check" class="w-4 h-4 text-purple-400"></i> Testar configuração</h3>
            <div>
                <label class="text-sm text-zinc-400 mb-1 block">E-mail para teste (opcional)</label>
                <input type="email" name="test_email"
                       class="w-full rounded-xl bg-white/[0.03] border border-white/[0.08] px-4 py-3 text-sm focus:border-greenx/50 focus:outline-none transition-colors font-mono"
                       placeholder="teste@email.com">
                <p class="text-xs text-zinc-500 mt-1">Preencha para enviar um e-mail de teste ao salvar.</p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button class="flex items-center gap-2 bg-gradient-to-r from-greenx to-greenxd text-white font-bold px-6 py-3 rounded-xl text-sm hover:from-greenx2 hover:to-greenxd transition-all shadow-lg shadow-greenx/20">
                <i data-lucide="save" class="w-4 h-4"></i> Salvar configurações
            </button>

            <?php if ($isConfigured): ?>
            <span class="flex items-center gap-1.5 text-greenx text-sm font-medium">
                <i data-lucide="check-circle-2" class="w-4 h-4"></i> Configurado (<?= htmlspecialchars(strtoupper($currentTransport)) ?>)
            </span>
            <?php else: ?>
            <span class="flex items-center gap-1.5 text-zinc-500 text-sm">
                <i data-lucide="alert-circle" class="w-4 h-4"></i> Não configurado — e-mails desabilitados
            </span>
            <?php endif; ?>
        </div>
    </form>

</main>

<?php
include __DIR__ . '/../../views/partials/admin_layout_end.php';
include __DIR__ . '/../../views/partials/footer.php';
?>
