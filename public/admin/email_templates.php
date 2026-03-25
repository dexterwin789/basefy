<?php
/**
 * Admin — Email Templates Editor
 *
 * Edit the subject and body of all system emails.
 * Templates are stored in platform_settings with key 'email_tpl.<key>'.
 * The email functions fall back to hardcoded defaults if no custom template is set.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/email.php';
exigirAdmin();

$conn = (new Database())->connect();

$appName = defined('APP_NAME') ? APP_NAME : 'Basefy';

// Available template definitions
$templates = [
    'confirmacao_email' => [
        'label' => 'Confirmação de E-mail',
        'desc'  => 'Enviado quando o usuário solicita verificação de e-mail.',
        'vars'  => ['{{nome}}', '{{app_name}}', '{{link}}'],
        'default_subject' => 'Confirme seu e-mail – ' . $appName,
        'default_body' => '<h2 style="margin:0 0 16px; font-size:20px; color:#111827; font-weight:700;">Confirme seu e-mail</h2>
<p style="margin:0 0 8px; font-size:14px; color:#4b5563; line-height:1.7;">Olá <strong>{{nome}}</strong>,</p>
<p style="margin:0 0 8px; font-size:14px; color:#4b5563; line-height:1.7;">Você está prestes a se tornar membro do <strong>{{app_name}}</strong>! Para concluir a verificação do seu e-mail, falta apenas um passo: clicar no botão abaixo.</p>
<p style="margin:16px 0; text-align:center;"><a href="{{link}}" style="display:inline-block; padding:14px 40px; background-color:#8800E4; color:#fff; font-size:15px; font-weight:600; text-decoration:none; border-radius:8px;">Confirmar E-mail</a></p>
<p style="margin:0; font-size:12px; color:#9ca3af; line-height:1.6;">Se o botão não funcionar, copie e cole o link abaixo no seu navegador:<br><a href="{{link}}" style="color:#8800E4; word-break:break-all;">{{link}}</a></p>',
    ],
    'boas_vindas' => [
        'label' => 'Boas-vindas / Registro',
        'desc'  => 'Enviado após o cadastro de um novo usuário.',
        'vars'  => ['{{nome}}', '{{app_name}}', '{{link}}'],
        'default_subject' => 'Bem-vindo ao ' . $appName . '!',
        'default_body' => '<h2 style="margin:0 0 16px; font-size:20px; color:#111827; font-weight:700;">Bem-vindo(a) ao {{app_name}}!</h2>
<p style="margin:0 0 8px; font-size:14px; color:#4b5563; line-height:1.7;">Olá <strong>{{nome}}</strong>,</p>
<p style="margin:0 0 8px; font-size:14px; color:#4b5563; line-height:1.7;">Você está prestes a se tornar membro do <strong>{{app_name}}</strong>! Para concluir o seu registro, falta apenas um passo: clicar no botão abaixo.</p>
<p style="margin:16px 0; text-align:center;"><a href="{{link}}" style="display:inline-block; padding:14px 40px; background-color:#8800E4; color:#fff; font-size:15px; font-weight:600; text-decoration:none; border-radius:8px;">Confirmar minha conta</a></p>
<p style="margin:0; font-size:12px; color:#9ca3af; line-height:1.6;">Se o botão não funcionar, copie e cole este link no seu navegador:<br><a href="{{link}}" style="color:#8800E4; word-break:break-all;">{{link}}</a></p>',
    ],
    'autorizacao_dispositivo' => [
        'label' => 'Autorização de Dispositivo',
        'desc'  => 'Enviado quando há tentativa de login de dispositivo desconhecido.',
        'vars'  => ['{{nome}}', '{{app_name}}', '{{link}}', '{{dispositivo}}', '{{ip}}', '{{data_hora}}'],
        'default_subject' => 'Autorização de dispositivo – ' . $appName,
        'default_body' => '<h2 style="margin:0 0 16px; font-size:20px; color:#111827; font-weight:700;">Autorização de dispositivo</h2>
<p style="margin:0 0 8px; font-size:14px; color:#4b5563; line-height:1.7;">Olá <strong>{{nome}}</strong>,</p>
<p style="margin:0 0 12px; font-size:14px; color:#4b5563; line-height:1.7;">Houve uma tentativa de login em um dispositivo não reconhecido na sua conta do <strong>{{app_name}}</strong>. Caso tenha sido você, clique no botão abaixo para autorizar o acesso.</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0; background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:8px;">
<tr><td style="padding:16px;">
<p style="margin:0 0 6px; font-size:12px; color:#6b7280;"><strong>Dispositivo:</strong> {{dispositivo}}</p>
<p style="margin:0 0 6px; font-size:12px; color:#6b7280;"><strong>IP:</strong> {{ip}}</p>
<p style="margin:0; font-size:12px; color:#6b7280;"><strong>Data e hora:</strong> {{data_hora}}</p>
</td></tr></table>
<p style="margin:16px 0; text-align:center;"><a href="{{link}}" style="display:inline-block; padding:14px 40px; background-color:#8800E4; color:#fff; font-size:15px; font-weight:600; text-decoration:none; border-radius:8px;">Autorizar Dispositivo</a></p>
<p style="margin:0; font-size:12px; color:#ef4444; line-height:1.6;">⚠️ O link só pode ser usado uma vez e expira em <strong>5 minutos</strong>. Se você não reconhece essa atividade, altere sua senha imediatamente.</p>',
    ],
    'novo_login' => [
        'label' => 'Novo Login Detectado',
        'desc'  => 'Informativo enviado quando há login em device reconhecido.',
        'vars'  => ['{{nome}}', '{{app_name}}', '{{dispositivo}}', '{{ip}}', '{{data_hora}}', '{{localizacao}}'],
        'default_subject' => 'Novo login detectado – ' . $appName,
        'default_body' => '<h2 style="margin:0 0 16px; font-size:20px; color:#111827; font-weight:700;">Novo login detectado</h2>
<p style="margin:0 0 8px; font-size:14px; color:#4b5563; line-height:1.7;">Olá <strong>{{nome}}</strong>,</p>
<p style="margin:0 0 12px; font-size:14px; color:#4b5563; line-height:1.7;">Detectamos um novo login na sua conta do <strong>{{app_name}}</strong>.</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0; background-color:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px;">
<tr><td style="padding:16px;">
<p style="margin:0 0 6px; font-size:12px; color:#374151;"><strong>Data e hora:</strong> {{data_hora}}</p>
<p style="margin:0 0 6px; font-size:12px; color:#374151;"><strong>Dispositivo:</strong> {{dispositivo}}</p>
<p style="margin:0 0 6px; font-size:12px; color:#374151;"><strong>IP:</strong> {{ip}}</p>
</td></tr></table>
<p style="margin:0; font-size:13px; color:#6b7280; line-height:1.6;">Se foi você, pode ignorar este e-mail. Caso não reconheça esse login, recomendamos alterar sua senha imediatamente.</p>',
    ],
    'telefone_validado' => [
        'label' => 'Telefone Validado',
        'desc'  => 'Enviado quando o número de telefone é verificado.',
        'vars'  => ['{{nome}}', '{{app_name}}', '{{telefone}}'],
        'default_subject' => 'Telefone validado – ' . $appName,
        'default_body' => '<div style="text-align:center; padding:12px 0;"><div style="display:inline-block; width:64px; height:64px; background-color:#f0fdf4; border-radius:50%; line-height:64px; font-size:28px; margin-bottom:16px;">✅</div></div>
<h2 style="margin:0 0 16px; font-size:20px; color:#111827; font-weight:700; text-align:center;">Telefone validado!</h2>
<p style="margin:0 0 8px; font-size:14px; color:#4b5563; line-height:1.7; text-align:center;">Parabéns <strong>{{nome}}</strong>, agora o seu número de telefone está validado!</p>
<p style="margin:0; font-size:16px; color:#111827; font-weight:600; text-align:center; padding:8px 0;">📱 {{telefone}}</p>
<p style="margin:16px 0 0; font-size:13px; color:#6b7280; line-height:1.6; text-align:center;">Você já pode utilizar todos os recursos disponíveis no <strong>{{app_name}}</strong>.</p>',
    ],
    'produto_enviado' => [
        'label' => 'Produto Enviado para Análise',
        'desc'  => 'Enviado ao vendedor quando o anúncio vai para revisão.',
        'vars'  => ['{{nome}}', '{{app_name}}', '{{produto}}'],
        'default_subject' => 'Anúncio enviado para análise – ' . $appName,
        'default_body' => '<h2 style="margin:0 0 16px; font-size:20px; color:#111827; font-weight:700;">Anúncio enviado para análise</h2>
<p style="margin:0 0 8px; font-size:14px; color:#4b5563; line-height:1.7;">Olá <strong>{{nome}}</strong>,</p>
<p style="margin:0 0 12px; font-size:14px; color:#4b5563; line-height:1.7;">Seu anúncio <strong>"{{produto}}"</strong> foi enviado e está sendo analisado pela nossa equipe. Nossa equipe irá rapidamente revisar seu anúncio e, caso esteja tudo certo, ele será publicado na loja.</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0; background-color:#fffbeb; border:1px solid #fde68a; border-radius:8px;">
<tr><td style="padding:16px;">
<p style="margin:0 0 4px; font-size:13px; color:#92400e; font-weight:600;">⏳ Em análise</p>
<p style="margin:0; font-size:12px; color:#a16207;">Você receberá uma notificação assim que a análise for concluída.</p>
</td></tr></table>',
    ],
    'produto_aprovado' => [
        'label' => 'Produto Aprovado',
        'desc'  => 'Enviado ao vendedor quando o anúncio é aprovado.',
        'vars'  => ['{{nome}}', '{{app_name}}', '{{produto}}', '{{link}}'],
        'default_subject' => 'Anúncio aprovado – ' . $appName,
        'default_body' => '<div style="text-align:center; padding:12px 0;"><div style="display:inline-block; width:64px; height:64px; background-color:#f0fdf4; border-radius:50%; line-height:64px; font-size:28px; margin-bottom:16px;">🎉</div></div>
<h2 style="margin:0 0 16px; font-size:20px; color:#111827; font-weight:700; text-align:center;">Anúncio aprovado!</h2>
<p style="margin:0 0 8px; font-size:14px; color:#4b5563; line-height:1.7;">Olá <strong>{{nome}}</strong>,</p>
<p style="margin:0 0 12px; font-size:14px; color:#4b5563; line-height:1.7;">Seu anúncio <strong>"{{produto}}"</strong> foi analisado e <strong style="color:#8800E4;">aprovado</strong> pela nossa equipe! Ele já está disponível na loja do <strong>{{app_name}}</strong>.</p>',
    ],
    'produto_revisao' => [
        'label' => 'Produto Precisa de Revisão',
        'desc'  => 'Enviado ao vendedor quando o anúncio é rejeitado/precisa de edição.',
        'vars'  => ['{{nome}}', '{{app_name}}', '{{produto}}', '{{motivo}}', '{{link}}'],
        'default_subject' => 'Anúncio precisa de revisão – ' . $appName,
        'default_body' => '<h2 style="margin:0 0 16px; font-size:20px; color:#111827; font-weight:700;">Anúncio precisa de revisão</h2>
<p style="margin:0 0 8px; font-size:14px; color:#4b5563; line-height:1.7;">Olá <strong>{{nome}}</strong>,</p>
<p style="margin:0 0 12px; font-size:14px; color:#4b5563; line-height:1.7;">Ao analisar seu anúncio <strong>"{{produto}}"</strong>, nossa equipe encontrou informações que não estão de acordo com as diretrizes do <strong>{{app_name}}</strong>.</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0; background-color:#fef2f2; border:1px solid #fecaca; border-radius:8px;">
<tr><td style="padding:16px;">
<p style="margin:0 0 6px; font-size:13px; color:#991b1b; font-weight:600;">Motivo da revisão:</p>
<p style="margin:0; font-size:13px; color:#7f1d1d; line-height:1.6;">{{motivo}}</p>
</td></tr></table>
<p style="margin:0 0 8px; font-size:14px; color:#4b5563; line-height:1.7;">Faça os ajustes necessários e reenvie o anúncio para uma nova análise.</p>',
    ],
];

// Load saved templates from DB
function emailTplGet(object $conn, string $key): ?array {
    $fullKey = 'email_tpl.' . $key;
    try {
        $st = $conn->prepare("SELECT setting_value FROM platform_settings WHERE setting_key = ? LIMIT 1");
        if ($st) {
            $st->bind_param('s', $fullKey);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            $st->close();
            if ($row && trim((string)$row['setting_value']) !== '') {
                $data = json_decode((string)$row['setting_value'], true);
                return is_array($data) ? $data : null;
            }
        }
    } catch (\Throwable $e) {}
    return null;
}

function emailTplSet(object $conn, string $key, array $data): void {
    $fullKey = 'email_tpl.' . $key;
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    try {
        $st = $conn->prepare("INSERT INTO platform_settings (setting_key, setting_value) VALUES (?, ?)
                              ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value, updated_at = CURRENT_TIMESTAMP");
        if ($st) {
            $st->bind_param('ss', $fullKey, $json);
            $st->execute();
            $st->close();
        }
    } catch (\Throwable $e) {}
}

$msg = '';
$err = '';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tplKey = (string)($_POST['tpl_key'] ?? '');
    $action = (string)($_POST['tpl_action'] ?? 'save');

    if (isset($templates[$tplKey])) {
        if ($action === 'reset') {
            // Delete from DB to restore default
            try {
                $delKey = 'email_tpl.' . $tplKey;
                $conn->query("DELETE FROM platform_settings WHERE setting_key = '" . $conn->real_escape_string($delKey) . "'");
                $msg = 'Template "' . $templates[$tplKey]['label'] . '" restaurado ao padrão.';
            } catch (\Throwable $e) {
                $err = 'Erro ao restaurar: ' . $e->getMessage();
            }
        } else {
            $subject = trim((string)($_POST['tpl_subject'] ?? ''));
            $body    = trim((string)($_POST['tpl_body'] ?? ''));

            if ($subject === '' || $body === '') {
                $err = 'Assunto e corpo são obrigatórios.';
            } else {
                emailTplSet($conn, $tplKey, ['subject' => $subject, 'body' => $body]);
                $msg = 'Template "' . $templates[$tplKey]['label'] . '" salvo com sucesso!';
            }
        }
    } else {
        $err = 'Template inválido.';
    }
}

// Load current values
$currentValues = [];
foreach (array_keys($templates) as $tplKey) {
    $saved = emailTplGet($conn, $tplKey);
    $currentValues[$tplKey] = [
        'subject' => $saved['subject'] ?? $templates[$tplKey]['default_subject'],
        'body'    => $saved['body']    ?? $templates[$tplKey]['default_body'],
        'custom'  => $saved !== null,
    ];
}

$pageTitle  = 'Templates de E-mail';
$activeMenu = 'email_templates';
include __DIR__ . '/../../views/partials/header.php';
include __DIR__ . '/../../views/partials/admin_layout_start.php';
?>

<!-- Quill.js CDN (100% free, MIT license) -->
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
<style>
    .ql-toolbar.ql-snow{background:#111214;border-color:#1A1C20!important;border-radius:12px 12px 0 0}
    .ql-container.ql-snow{background:#0B0B0C;border-color:#1A1C20!important;border-radius:0 0 12px 12px;min-height:180px;color:#d4d4d8;font-size:14px}
    .ql-editor{min-height:180px;line-height:1.6}.ql-editor.ql-blank::before{color:#52525b;font-style:normal}
    .ql-snow .ql-stroke{stroke:#a1a1aa}.ql-snow .ql-fill{fill:#a1a1aa}.ql-snow .ql-picker{color:#a1a1aa}
    .ql-snow .ql-picker-options{background:#111214;border-color:#1A1C20}
    .ql-snow .ql-picker-label:hover,.ql-snow .ql-picker-item:hover{color:var(--t-accent)}
    .ql-snow .ql-active .ql-stroke{stroke:var(--t-accent)}.ql-snow .ql-active .ql-fill{fill:var(--t-accent)}.ql-snow .ql-active{color:var(--t-accent)}
    .ql-snow a{color:var(--t-accent)}
    .light-mode .ql-toolbar.ql-snow{background:#f4f4f5;border-color:#d4d4d8!important}
    .light-mode .ql-container.ql-snow{background:#fff;border-color:#d4d4d8!important;color:#18181b}
    .light-mode .ql-editor.ql-blank::before{color:#a1a1aa}
    .light-mode .ql-snow .ql-stroke{stroke:#52525b}.light-mode .ql-snow .ql-fill{fill:#52525b}.light-mode .ql-snow .ql-picker{color:#52525b}
    .light-mode .ql-snow .ql-picker-options{background:#fff;border-color:#d4d4d8}
</style>

<main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-4xl">

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold">Templates de E-mail</h1>
            <p class="text-sm text-zinc-400 mt-1">Personalize o assunto e o corpo dos e-mails enviados pelo sistema.</p>
        </div>
    </div>

    <?php if ($msg): ?>
    <div class="mb-4 px-4 py-3 rounded-xl bg-greenx/10 border border-greenx/20 text-greenx text-sm flex items-center gap-2">
        <i data-lucide="check-circle-2" class="w-4 h-4 flex-shrink-0"></i> <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>
    <?php if ($err): ?>
    <div class="mb-4 px-4 py-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm flex items-center gap-2">
        <i data-lucide="alert-triangle" class="w-4 h-4 flex-shrink-0"></i> <?= htmlspecialchars($err) ?>
    </div>
    <?php endif; ?>

    <!-- Guide -->
    <div class="bg-purple-500/[0.06] border border-purple-500/20 rounded-2xl p-5 mb-6">
        <h3 class="text-sm font-bold text-purple-400 mb-2 flex items-center gap-2">
            <i data-lucide="info" class="w-4 h-4"></i> Como funciona
        </h3>
        <ul class="text-sm text-zinc-300 space-y-1 list-disc list-inside">
            <li>Edite o <strong>assunto</strong> e o <strong>corpo HTML</strong> de cada tipo de e-mail.</li>
            <li>Use as <strong>variáveis</strong> listadas (ex: <code class="px-1.5 py-0.5 rounded bg-black/30 text-greenx text-xs font-mono">{<!-- -->{nome}}</code>) — elas serão substituídas automaticamente.</li>
            <li>O corpo é inserido dentro do layout padrão (cabeçalho + rodapé são automáticos).</li>
            <li>Clique em <strong>Restaurar padrão</strong> para voltar ao template original.</li>
        </ul>
    </div>

    <!-- Template list -->
    <div class="space-y-4">
    <?php foreach ($templates as $tplKey => $tplDef):
        $cv = $currentValues[$tplKey];
    ?>
        <div class="bg-blackx2 border border-blackx3 rounded-2xl overflow-hidden" x-data="{ open: false }">
            <div class="flex items-center gap-3 px-5 py-4 cursor-pointer select-none" @click="open = !open">
                <div class="w-9 h-9 rounded-xl bg-purple-500/15 border border-purple-500/30 flex items-center justify-center">
                    <i data-lucide="mail" class="w-4 h-4 text-purple-400"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-semibold"><?= htmlspecialchars($tplDef['label']) ?></h3>
                        <?php if ($cv['custom']): ?>
                            <span class="text-[10px] px-1.5 py-0.5 rounded bg-greenx/15 border border-greenx/30 text-greenx font-medium">Personalizado</span>
                        <?php else: ?>
                            <span class="text-[10px] px-1.5 py-0.5 rounded bg-zinc-500/15 border border-zinc-500/30 text-zinc-400 font-medium">Padrão</span>
                        <?php endif; ?>
                    </div>
                    <p class="text-[11px] text-zinc-500 mt-0.5"><?= htmlspecialchars($tplDef['desc']) ?></p>
                </div>
                <i data-lucide="chevron-down" class="w-4 h-4 text-zinc-500 transition-transform" :class="open && 'rotate-180'"></i>
            </div>

            <div x-show="open" x-transition x-cloak class="px-5 pb-5 border-t border-blackx3 pt-4">
                <!-- Variables reference -->
                <div class="mb-4 flex flex-wrap gap-1.5">
                    <span class="text-[11px] text-zinc-500 mr-1">Variáveis:</span>
                    <?php foreach ($tplDef['vars'] as $v): ?>
                        <code class="px-1.5 py-0.5 rounded bg-greenx/10 border border-greenx/20 text-greenx text-[11px] font-mono cursor-pointer hover:bg-greenx/20 transition" onclick="navigator.clipboard.writeText('<?= $v ?>')" title="Clique para copiar"><?= $v ?></code>
                    <?php endforeach; ?>
                </div>

                <form method="post" class="space-y-4">
                    <input type="hidden" name="tpl_key" value="<?= $tplKey ?>">
                    <input type="hidden" name="tpl_action" value="save">

                    <div>
                        <label class="text-sm text-zinc-400 mb-1 block">Assunto do e-mail</label>
                        <input type="text" name="tpl_subject" value="<?= htmlspecialchars($cv['subject'], ENT_QUOTES, 'UTF-8') ?>"
                               class="w-full rounded-xl bg-white/[0.03] border border-white/[0.08] px-4 py-3 text-sm focus:border-greenx/50 focus:outline-none transition-colors"
                               required>
                    </div>

                    <div>
                        <label class="text-sm text-zinc-400 mb-1 block">Corpo do e-mail</label>
                        <input type="hidden" name="tpl_body" id="tpl-body-<?= $tplKey ?>" value="<?= htmlspecialchars($cv['body'], ENT_QUOTES, 'UTF-8') ?>">
                        <div id="quill-<?= $tplKey ?>"></div>
                        <p class="text-[11px] text-zinc-600 mt-1">Use a barra de ferramentas para formatar. O layout de cabeçalho/rodapé é aplicado automaticamente.</p>
                    </div>

                    <!-- Preview -->
                    <div>
                        <button type="button" class="text-xs text-purple-400 hover:text-purple-300 transition flex items-center gap-1 mb-2"
                                onclick="var p=this.closest('form').querySelector('.tpl-preview'); var q=window._quillEditors&&window._quillEditors['<?= $tplKey ?>']; var html=q?q.root.innerHTML:''; if(p.classList.contains('hidden')){p.innerHTML=html;p.classList.remove('hidden');this.textContent='Ocultar preview';}else{p.classList.add('hidden');this.textContent='Ver preview';}">
                            <i data-lucide="eye" class="w-3.5 h-3.5"></i> Ver preview
                        </button>
                        <div class="tpl-preview hidden rounded-xl border border-blackx3 bg-white p-4 text-black text-sm max-h-80 overflow-y-auto"></div>
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-greenx to-greenxd text-white font-semibold px-5 py-2.5 text-sm hover:from-greenx2 hover:to-greenxd transition-all">
                            <i data-lucide="save" class="w-4 h-4"></i> Salvar
                        </button>
                        <?php if ($cv['custom']): ?>
                        <button type="submit" name="tpl_action" value="reset" onclick="return confirm('Restaurar o template ao padrão?')"
                                class="inline-flex items-center gap-2 rounded-xl border border-orange-500/30 bg-orange-500/10 text-orange-300 font-semibold px-4 py-2.5 text-sm hover:bg-orange-500/20 transition">
                            <i data-lucide="rotate-ccw" class="w-4 h-4"></i> Restaurar padrão
                        </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
    </div>

</main>

<script>
window._quillEditors = {};
document.addEventListener('DOMContentLoaded', function() {
    var tplKeys = <?= json_encode(array_keys($templates)) ?>;
    var toolbarOpts = [
        [{'header':[1,2,3,false]}],
        ['bold','italic','underline','strike'],
        [{'color':[]},{'background':[]}],
        [{'align':[]}],
        [{'list':'ordered'},{'list':'bullet'}],
        ['link','image'],
        ['blockquote','code-block'],
        ['clean']
    ];
    tplKeys.forEach(function(key) {
        var container = document.getElementById('quill-' + key);
        var hidden = document.getElementById('tpl-body-' + key);
        if (!container || !hidden) return;
        var q = new Quill(container, {
            theme: 'snow',
            placeholder: 'Escreva o corpo do e-mail...',
            modules: { toolbar: toolbarOpts }
        });
        // Load initial HTML content
        q.root.innerHTML = hidden.value;
        // Sync to hidden input on text change
        q.on('text-change', function() {
            hidden.value = q.root.innerHTML;
        });
        // Also sync on form submit
        var form = container.closest('form');
        if (form) {
            form.addEventListener('submit', function() {
                hidden.value = q.root.innerHTML;
            });
        }
        window._quillEditors[key] = q;
    });
});
</script>

<?php
include __DIR__ . '/../../views/partials/admin_layout_end.php';
include __DIR__ . '/../../views/partials/footer.php';
?>
