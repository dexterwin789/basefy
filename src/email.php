<?php
// filepath: c:\xampp\htdocs\mercado_admin\src\email.php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/* ════════════════════════════════════════════════════════════
   SMTP SETTINGS – DB-backed (platform_settings) with ENV fallback
   ════════════════════════════════════════════════════════════ */

function smtpSettingGet(?object $conn, string $key, string $default = ''): string
{
    if ($conn) {
        try {
            $fullKey = 'smtp.' . $key;
            $st = $conn->prepare("SELECT setting_value FROM platform_settings WHERE setting_key = ? LIMIT 1");
            if ($st) {
                $st->bind_param('s', $fullKey);
                $st->execute();
                $row = $st->get_result()->fetch_assoc();
                $st->close();
                if ($row && trim((string)$row['setting_value']) !== '') {
                    return (string)$row['setting_value'];
                }
            }
        } catch (\Throwable $e) {}
    }
    return $default;
}

function smtpSettingSet(object $conn, string $key, string $value): void
{
    $fullKey = 'smtp.' . $key;
    try {
        $st = $conn->prepare("INSERT INTO platform_settings (setting_key, setting_value) VALUES (?, ?)
                              ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value, updated_at = CURRENT_TIMESTAMP");
        if ($st) {
            $st->bind_param('ss', $fullKey, $value);
            $st->execute();
            $st->close();
        }
    } catch (\Throwable $e) {}
}

/**
 * Load a custom email template from platform_settings.
 * Returns ['subject'=>..., 'body'=>...] or null if not customised.
 */
function emailTplLoad(?object $conn, string $key): ?array
{
    if (!$conn) return null;
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

/**
 * Apply variable replacements to a custom template body.
 */
function emailTplReplace(string $body, array $vars): string
{
    return str_replace(array_keys($vars), array_values($vars), $body);
}

function smtpGetAllSettings(?object $conn = null): array
{
    $host = smtpSettingGet($conn, 'host', (string)envValue('SMTP_HOST', ''));
    $port = smtpSettingGet($conn, 'port', (string)envValue('SMTP_PORT', '587'));
    $user = smtpSettingGet($conn, 'user', (string)envValue('SMTP_USER', ''));
    $pass = smtpSettingGet($conn, 'pass', (string)envValue('SMTP_PASS', ''));
    $from = smtpSettingGet($conn, 'from', (string)envValue('SMTP_FROM', ''));
    $from_name = smtpSettingGet($conn, 'from_name', '');
    return [
        'host' => trim($host),
        'port' => trim($port),
        'user' => trim($user),
        'pass' => trim($pass),
        'from' => trim($from),
        'from_name' => trim($from_name),
    ];
}

function smtpConfigured(?object $conn = null): bool
{
    $transport = smtpSettingGet($conn, 'transport', 'smtp');
    if ($transport === 'resend') {
        $key = smtpSettingGet($conn, 'resend_api_key', (string)envValue('RESEND_API_KEY', ''));
        return trim($key) !== '';
    }
    $s = smtpGetAllSettings($conn);
    return $s['host'] !== '' && $s['user'] !== '';
}

/**
 * Get configured email transport: 'smtp' or 'resend'.
 */
function emailTransportGet(?object $conn = null): string
{
    return smtpSettingGet($conn, 'transport', 'smtp');
}

/**
 * Send an HTML e-mail via Resend HTTP API.
 * Works on Railway/Render/Fly.io since it uses HTTPS (port 443).
 */
function resendSend(string $to, string $subject, string $htmlBody, ?string $fromName = null): bool
{
    $dbConn = null;
    try { $dbConn = (new Database())->connect(); } catch (\Throwable $e) {}
    $settings = smtpGetAllSettings($dbConn);

    $apiKey  = smtpSettingGet($dbConn, 'resend_api_key', (string)envValue('RESEND_API_KEY', ''));
    $from    = $settings['from'] ?: 'onboarding@resend.dev';
    $fromName = $fromName ?? ($settings['from_name'] ?: (defined('APP_NAME') ? APP_NAME : 'Marketplace'));

    // Resend rejects free email domains (gmail, hotmail, etc.) — fall back to onboarding@resend.dev
    $freeDomains = ['gmail.com','googlemail.com','hotmail.com','outlook.com','live.com','yahoo.com','yahoo.com.br','icloud.com','aol.com','protonmail.com','mail.com'];
    $fromDomain = strtolower(trim(substr($from, strrpos($from, '@') + 1)));
    if (in_array($fromDomain, $freeDomains, true)) {
        error_log('[Resend] From domain "' . $fromDomain . '" is not verifiable — using onboarding@resend.dev');
        $from = 'onboarding@resend.dev';
    }

    if (trim($apiKey) === '') {
        error_log('[Resend] API key not configured – skipping send to ' . $to);
        smtpLastError('Resend API key não configurada.');
        return false;
    }

    error_log('[Resend] Sending to ' . $to . ' subject="' . $subject . '"');

    $fromField = $fromName . ' <' . $from . '>';

    $payload = json_encode([
        'from'    => $fromField,
        'to'     => [$to],
        'subject' => $subject,
        'html'    => $htmlBody,
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . trim($apiKey),
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        error_log('[Resend] cURL error: ' . $curlErr);
        smtpLastError('Falha na conexão com Resend: ' . $curlErr);
        return false;
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        error_log('[Resend] Sent OK to ' . $to . ': ' . $subject);
        return true;
    }

    $body = json_decode((string)$response, true);
    $errMsg = $body['message'] ?? $body['error'] ?? $body['statusCode'] ?? $response;
    error_log('[Resend] Error (' . $httpCode . '): ' . (is_string($errMsg) ? $errMsg : json_encode($errMsg)));
    smtpLastError('Resend erro (' . $httpCode . '): ' . (is_string($errMsg) ? $errMsg : json_encode($errMsg)));
    return false;
}

/**
 * Store/retrieve last SMTP error for debugging.
 */
function smtpLastError(string $set = ''): string
{
    static $err = '';
    if ($set !== '') $err = $set;
    return $err;
}

/**
 * Read all lines from SMTP socket until we get a line where char[3] is a space.
 */
function _smtpRead($sock): string
{
    $resp = '';
    while (true) {
        $line = fgets($sock, 512);
        if ($line === false) break;
        $resp .= $line;
        if (isset($line[3]) && $line[3] === ' ') break;
    }
    return $resp;
}

/**
 * Send SMTP command, read response, optionally assert expected code.
 */
function _smtpCmd($sock, string $cmd, ?int $expect = null): string
{
    fwrite($sock, $cmd . "\r\n");
    $resp = _smtpRead($sock);
    if ($expect !== null) {
        $code = (int)substr($resp, 0, 3);
        if ($code !== $expect) {
            throw new \RuntimeException("SMTP expected {$expect}, got: " . trim($resp));
        }
    }
    return $resp;
}

/**
 * Send an HTML e-mail via SMTP.
 */
function smtpSend(string $to, string $subject, string $htmlBody, ?string $fromName = null): bool
{
    // Try to get a DB connection for DB-backed settings
    $dbConn = null;
    try { $dbConn = (new Database())->connect(); } catch (\Throwable $e) {}

    // Route to Resend HTTP API if configured
    $transport = emailTransportGet($dbConn);
    if ($transport === 'resend') {
        return resendSend($to, $subject, $htmlBody, $fromName);
    }

    $settings = smtpGetAllSettings($dbConn);

    $host = $settings['host'];
    $port = (int)($settings['port'] ?: 587);
    $user = $settings['user'];
    // Strip spaces from password — Gmail app passwords are displayed with spaces
    // but should be used without them
    $pass = str_replace(' ', '', $settings['pass']);
    $from = $settings['from'] ?: $user;
    $fromName = $fromName ?? ($settings['from_name'] ?: (defined('APP_NAME') ? APP_NAME : 'Marketplace'));

    if ($host === '' || $user === '') {
        $msg = '[SMTP] Not configured (host=' . ($host ?: 'empty') . ', user=' . ($user ?: 'empty') . ') – skipping send to ' . $to;
        error_log($msg);
        smtpLastError('SMTP não configurado: host ou usuário vazio.');
        return false;
    }

    error_log('[SMTP] Attempting send to ' . $to . ' via ' . $host . ':' . $port . ' user=' . $user);

    $socket = null;
    try {
        $ctx = stream_context_create(['ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ]]);

        $prefix = ($port === 465) ? 'ssl://' : 'tcp://';
        $socket = @stream_socket_client(
            $prefix . $host . ':' . $port,
            $errno, $errstr, 15,
            STREAM_CLIENT_CONNECT,
            $ctx
        );
        // If port 587 fails, automatically try 465 (SSL)
        if (!$socket && $port === 587) {
            error_log('[SMTP] Port 587 failed (' . $errstr . '), trying ssl://' . $host . ':465 ...');
            $socket = @stream_socket_client(
                'ssl://' . $host . ':465',
                $errno, $errstr, 15,
                STREAM_CLIENT_CONNECT,
                $ctx
            );
            if ($socket) {
                $port = 465; // Mark so we skip STARTTLS below
            }
        }
        if (!$socket) {
            $detail = "host={$host}, port={$port}, errno={$errno}, errstr={$errstr}";
            error_log("[SMTP] Connection failed: {$detail}");
            throw new \RuntimeException("Connect failed: {$errstr} ({$errno}). Dica: alguns servidores bloqueiam portas 587/465. Tente alterar a porta.");
        }
        error_log('[SMTP] Connected to ' . $host . ':' . $port);
        stream_set_timeout($socket, 15);

        _smtpRead($socket); // greeting
        _smtpCmd($socket, 'EHLO ' . (gethostname() ?: 'localhost'));

        // STARTTLS for port 587
        if ($port === 587) {
            _smtpCmd($socket, 'STARTTLS', 220);
            $crypto = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                $crypto |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            }
            if (!stream_socket_enable_crypto($socket, true, $crypto)) {
                throw new \RuntimeException('STARTTLS handshake failed');
            }
            _smtpCmd($socket, 'EHLO ' . (gethostname() ?: 'localhost'));
        }

        // AUTH LOGIN
        _smtpCmd($socket, 'AUTH LOGIN', 334);
        _smtpCmd($socket, base64_encode($user), 334);
        _smtpCmd($socket, base64_encode($pass), 235);

        // Envelope
        _smtpCmd($socket, "MAIL FROM:<{$from}>", 250);
        _smtpCmd($socket, "RCPT TO:<{$to}>", 250);
        _smtpCmd($socket, 'DATA', 354);

        // Headers
        $encodedName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
        $headers  = "From: {$encodedName} <{$from}>\r\n";
        $headers .= "To: {$to}\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: 8bit\r\n";
        $headers .= "Date: " . date('r') . "\r\n";
        $headers .= "Message-ID: <" . bin2hex(random_bytes(16)) . "@" . ($host) . ">\r\n";

        // Body – escape leading dots
        $body = str_replace("\r\n.", "\r\n..", $htmlBody);
        fwrite($socket, $headers . "\r\n" . $body . "\r\n.\r\n");
        _smtpRead($socket);

        _smtpCmd($socket, 'QUIT');
        fclose($socket);

        error_log("[SMTP] Sent to {$to}: {$subject}");
        return true;
    } catch (\Throwable $e) {
        error_log('[SMTP] Error: ' . $e->getMessage());
        smtpLastError($e->getMessage());
        if ($socket && is_resource($socket)) {
            @fwrite($socket, "QUIT\r\n");
            @fclose($socket);
        }
        return false;
    }
}

/* ════════════════════════════════════════════════════════════
   EMAIL VERIFICATION TOKENS
   ════════════════════════════════════════════════════════════ */

function emailEnsureTokenTable(object $conn): void
{
    static $done = false;
    if ($done) return;
    try {
        $conn->query("CREATE TABLE IF NOT EXISTS email_tokens (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL,
            tipo VARCHAR(30) NOT NULL,
            token VARCHAR(64) NOT NULL UNIQUE,
            usado BOOLEAN DEFAULT FALSE,
            expira_em TIMESTAMP NOT NULL,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    } catch (\Throwable $e) {}
    $done = true;
}

/**
 * Generate a verification token (URL-safe, 48 chars).
 */
function gerarTokenVerificacao(object $conn, int|string $uid, string $tipo, int $ttlMinutes = 1440): string
{
    $uid = (int)$uid;
    emailEnsureTokenTable($conn);
    $token = bin2hex(random_bytes(24)); // 48-char hex
    $expira = date('Y-m-d H:i:s', time() + ($ttlMinutes * 60));
    $st = $conn->prepare("INSERT INTO email_tokens (user_id, tipo, token, expira_em) VALUES (?, ?, ?, ?)");
    $st->bind_param('isss', $uid, $tipo, $token, $expira);
    $st->execute();
    $st->close();
    return $token;
}

/**
 * Validate token. Returns user_id on success, null on failure.
 */
function validarTokenVerificacao(object $conn, string $token, string $tipo): ?int
{
    emailEnsureTokenTable($conn);
    $st = $conn->prepare("SELECT id, user_id, expira_em FROM email_tokens WHERE token = ? AND tipo = ? AND usado = FALSE LIMIT 1");
    $st->bind_param('ss', $token, $tipo);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$row) return null;
    if (strtotime($row['expira_em']) < time()) return null; // expired

    // Mark as used
    $up = $conn->prepare("UPDATE email_tokens SET usado = TRUE WHERE id = ?");
    $id = (int)$row['id'];
    $up->bind_param('i', $id);
    $up->execute();
    $up->close();

    return (int)$row['user_id'];
}

/* ════════════════════════════════════════════════════════════
   HTML EMAIL TEMPLATES
   ════════════════════════════════════════════════════════════ */

/**
 * Base email template wrapper — professional light layout.
 */
function emailBaseTemplate(string $title, string $contentHtml, string $footerExtra = ''): string
{
    $appName = defined('APP_NAME') ? APP_NAME : 'Basefy';
    $year = date('Y');

    return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$title}</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f5f7; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f5f7; padding:24px 0;">
<tr><td align="center">

<!-- Container -->
<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px; width:100%; background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.06);">

<!-- Header -->
<tr>
<td style="background: linear-gradient(135deg, #4C1D95, #8800E4); padding:28px 32px; text-align:center;">
  <h1 style="margin:0; color:#ffffff; font-size:22px; font-weight:700; letter-spacing:-0.3px;">{$appName}</h1>
</td>
</tr>

<!-- Content -->
<tr>
<td style="padding:32px 32px 24px;">
  {$contentHtml}
</td>
</tr>

<!-- Footer -->
<tr>
<td style="padding:16px 32px 28px; border-top:1px solid #e5e7eb;">
  <p style="margin:0; font-size:11px; color:#9ca3af; text-align:center; line-height:1.6;">
    {$footerExtra}
    Este e-mail foi enviado automaticamente por <strong>{$appName}</strong>.<br>
    Se você não solicitou esta ação, ignore este e-mail.<br>
    &copy; {$year} {$appName}. Todos os direitos reservados.
  </p>
</td>
</tr>

</table>
</td></tr></table>
</body>
</html>
HTML;
}

/**
 * Create a styled button for email templates.
 */
function emailButton(string $text, string $url, string $bgColor = '#8800E4'): string
{
    return <<<HTML
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px auto;">
<tr><td align="center" style="background-color:{$bgColor}; border-radius:8px;">
  <a href="{$url}" target="_blank" style="display:inline-block; padding:14px 40px; color:#ffffff; font-size:15px; font-weight:600; text-decoration:none; border-radius:8px;">{$text}</a>
</td></tr></table>
HTML;
}

function emailNotificacaoSistema(string $nome, string $tipo, string $titulo, string $mensagem = '', string $actionUrl = '', ?object $conn = null): string
{
        $appName = defined('APP_NAME') ? APP_NAME : 'Basefy';
        $tpl = emailTplLoad($conn, 'notificacao_sistema');
        if ($tpl) {
                $vars = [
                        '{{nome}}' => $nome,
                        '{{app_name}}' => $appName,
                        '{{tipo}}' => $tipo,
                        '{{titulo}}' => $titulo,
                        '{{mensagem}}' => $mensagem,
                        '{{link}}' => $actionUrl,
                ];
                $body = emailTplReplace($tpl['body'], $vars);
                return emailBaseTemplate($tpl['subject'] ?? 'Nova notificação', $body);
        }

        $safeNome = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
        $safeTipo = htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8');
        $safeTitulo = htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8');
        $safeMensagem = nl2br(htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'));
        $safeLink = htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8');

        $typeColors = [
                'Anúncio' => ['bg' => '#ede9fe', 'fg' => '#6d28d9'],
                'Venda' => ['bg' => '#dcfce7', 'fg' => '#166534'],
                'Chat' => ['bg' => '#dbeafe', 'fg' => '#1d4ed8'],
                'Ticket' => ['bg' => '#fef3c7', 'fg' => '#b45309'],
        ];
        $badge = $typeColors[$tipo] ?? ['bg' => '#e5e7eb', 'fg' => '#374151'];
        $btnHtml = $actionUrl !== '' ? emailButton('Abrir notificação', $actionUrl, '#8800E4') : '';

        $content = <<<HTML
<div style="text-align:center; margin-bottom:20px;">
    <span style="display:inline-block; padding:6px 12px; border-radius:999px; background:{$badge['bg']}; color:{$badge['fg']}; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.04em;">{$safeTipo}</span>
</div>
<h2 style="margin:0 0 16px; font-size:22px; color:#111827; font-weight:700; text-align:center;">{$safeTitulo}</h2>
<p style="margin:0 0 10px; font-size:14px; color:#4b5563; line-height:1.7;">Olá <strong>{$safeNome}</strong>,</p>
<p style="margin:0 0 12px; font-size:14px; color:#4b5563; line-height:1.7;">Você recebeu uma nova notificação no <strong>{$appName}</strong>.</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0; background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:8px;">
<tr><td style="padding:16px;">
    <p style="margin:0 0 6px; font-size:12px; color:#6b7280;"><strong>Categoria:</strong> {$safeTipo}</p>
    <p style="margin:0; font-size:14px; color:#374151; line-height:1.7;">{$safeMensagem}</p>
</td></tr></table>
{$btnHtml}
HTML;

        if ($actionUrl !== '') {
                $content .= <<<HTML
<p style="margin:0; font-size:12px; color:#9ca3af; line-height:1.6;">
    Se o botão não funcionar, copie e cole este link no navegador:<br>
    <a href="{$safeLink}" style="color:#8800E4; word-break:break-all;">{$safeLink}</a>
</p>
HTML;
        }

        return emailBaseTemplate('Nova notificação', $content);
}

/* ──────────────────────────────────────────────────────
   1. CONFIRMAÇÃO DE E-MAIL (Verificação de conta)
   ────────────────────────────────────────────────────── */
function emailConfirmacaoEmail(string $nome, string $verifyUrl, ?object $conn = null): string
{
    $appName = defined('APP_NAME') ? APP_NAME : 'Basefy';
    $tpl = emailTplLoad($conn, 'confirmacao_email');
    if ($tpl) {
        $vars = ['{{nome}}' => $nome, '{{app_name}}' => $appName, '{{link}}' => $verifyUrl];
        $body = emailTplReplace($tpl['body'], $vars);
        return emailBaseTemplate($tpl['subject'] ?? 'Confirme seu E-mail', $body);
    }
    $btn = emailButton('Confirmar E-mail', $verifyUrl, '#8800E4');
    $content = <<<HTML
<h2 style="margin:0 0 16px; font-size:20px; color:#111827; font-weight:700;">Confirme seu e-mail</h2>
<p style="margin:0 0 8px; font-size:14px; color:#4b5563; line-height:1.7;">
  Olá <strong>{$nome}</strong>,
</p>
<p style="margin:0 0 8px; font-size:14px; color:#4b5563; line-height:1.7;">
  Você está prestes a se tornar membro do <strong>{$appName}</strong>!
  Para concluir a verificação do seu e-mail, falta apenas um passo: clicar no botão abaixo.
</p>
{$btn}
<p style="margin:0; font-size:12px; color:#9ca3af; line-height:1.6;">
  Se o botão não funcionar, copie e cole o link abaixo no seu navegador:<br>
  <a href="{$verifyUrl}" style="color:#8800E4; word-break:break-all;">{$verifyUrl}</a>
</p>
HTML;
    return emailBaseTemplate('Confirme seu E-mail', $content);
}

/* ──────────────────────────────────────────────────────
   2. confirmação de CONTA (Registro / Boas-vindas)
   ────────────────────────────────────────────────────── */
function emailBoasVindas(string $nome, string $verifyUrl, ?object $conn = null): string
{
    $appName = defined('APP_NAME') ? APP_NAME : 'Basefy';
    $tpl = emailTplLoad($conn, 'boas_vindas');
    if ($tpl) {
        $vars = ['{{nome}}' => $nome, '{{app_name}}' => $appName, '{{link}}' => $verifyUrl];
        $body = emailTplReplace($tpl['body'], $vars);
        return emailBaseTemplate($tpl['subject'] ?? 'Bem-vindo ao ' . $appName . '!', $body);
    }
    $btn = emailButton('Confirmar minha conta', $verifyUrl, '#8800E4');
    $content = <<<HTML
<h2 style="margin:0 0 16px; font-size:20px; color:#111827; font-weight:700;">Bem-vindo(a) ao {$appName}!</h2>
<p style="margin:0 0 8px; font-size:14px; color:#4b5563; line-height:1.7;">
  Olá <strong>{$nome}</strong>,
</p>
<p style="margin:0 0 8px; font-size:14px; color:#4b5563; line-height:1.7;">
  Sua conta foi criada com sucesso! Para ativar sua conta e começar a usar o <strong>{$appName}</strong>,
  confirme seu e-mail clicando no botão abaixo.
</p>
{$btn}
<p style="margin:0; font-size:12px; color:#9ca3af; line-height:1.6;">
  Se o botão não funcionar, copie e cole este link no seu navegador:<br>
  <a href="{$verifyUrl}" style="color:#8800E4; word-break:break-all;">{$verifyUrl}</a>
</p>
HTML;
    return emailBaseTemplate('Bem-vindo ao ' . $appName . '!', $content);
}

/* ──────────────────────────────────────────────────────
   3. AUTORIZAÇÃO DE DISPOSITIVO (login em device novo)
   ────────────────────────────────────────────────────── */
function emailAutorizacaoDispositivo(string $nome, string $authUrl, string $device, string $ip, string $dataHora, ?object $conn = null): string
{
    $appName = defined('APP_NAME') ? APP_NAME : 'Basefy';
    $tpl = emailTplLoad($conn, 'autorizacao_dispositivo');
    if ($tpl) {
        $vars = ['{{nome}}' => $nome, '{{app_name}}' => $appName, '{{link}}' => $authUrl, '{{dispositivo}}' => $device, '{{ip}}' => $ip, '{{data_hora}}' => $dataHora];
        $body = emailTplReplace($tpl['body'], $vars);
        return emailBaseTemplate($tpl['subject'] ?? 'Autorização de Dispositivo', $body);
    }
    $btn = emailButton('Autorizar Dispositivo', $authUrl, '#8800E4');
    $content = <<<HTML
<h2 style="margin:0 0 16px; font-size:20px; color:#111827; font-weight:700;">Autorização de dispositivo</h2>
<p style="margin:0 0 8px; font-size:14px; color:#4b5563; line-height:1.7;">
  Olá <strong>{$nome}</strong>,
</p>
<p style="margin:0 0 12px; font-size:14px; color:#4b5563; line-height:1.7;">
  Houve uma tentativa de login em um dispositivo não reconhecido na sua conta do <strong>{$appName}</strong>.
  Caso tenha sido você, clique no botão abaixo para autorizar o acesso.
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0; background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:8px;">
<tr><td style="padding:16px;">
  <p style="margin:0 0 6px; font-size:12px; color:#6b7280;"><strong>Dispositivo:</strong> {$device}</p>
  <p style="margin:0 0 6px; font-size:12px; color:#6b7280;"><strong>IP:</strong> {$ip}</p>
  <p style="margin:0; font-size:12px; color:#6b7280;"><strong>Data e hora:</strong> {$dataHora}</p>
</td></tr></table>

{$btn}

<p style="margin:0; font-size:12px; color:#ef4444; line-height:1.6;">
  ⚠️ O link só pode ser usado uma vez e expira em <strong>5 minutos</strong>.
  Se você não reconhece essa atividade, altere sua senha imediatamente.
</p>
HTML;
    return emailBaseTemplate('Autorização de Dispositivo', $content);
}

/* ──────────────────────────────────────────────────────
   4. NOVO LOGIN DETECTADO (informativo)
   ────────────────────────────────────────────────────── */
function emailNovoLogin(string $nome, string $device, string $ip, string $dataHora, string $localizacao = '', ?object $conn = null): string
{
    $appName = defined('APP_NAME') ? APP_NAME : 'Basefy';
    $tpl = emailTplLoad($conn, 'novo_login');
    if ($tpl) {
        $vars = ['{{nome}}' => $nome, '{{app_name}}' => $appName, '{{dispositivo}}' => $device, '{{ip}}' => $ip, '{{data_hora}}' => $dataHora, '{{localizacao}}' => $localizacao];
        $body = emailTplReplace($tpl['body'], $vars);
        return emailBaseTemplate($tpl['subject'] ?? 'Novo login detectado', $body);
    }
    $locHtml = $localizacao ? "<p style=\"margin:0 0 6px; font-size:12px; color:#6b7280;\"><strong>Localização:</strong> {$localizacao}</p>" : '';
    $content = <<<HTML
<h2 style="margin:0 0 16px; font-size:20px; color:#111827; font-weight:700;">Novo login detectado</h2>
<p style="margin:0 0 8px; font-size:14px; color:#4b5563; line-height:1.7;">
  Olá <strong>{$nome}</strong>,
</p>
<p style="margin:0 0 12px; font-size:14px; color:#4b5563; line-height:1.7;">
  Detectamos um novo login na sua conta do <strong>{$appName}</strong>.
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0; background-color:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px;">
<tr><td style="padding:16px;">
  <p style="margin:0 0 6px; font-size:12px; color:#374151;"><strong>Data e hora:</strong> {$dataHora}</p>
  <p style="margin:0 0 6px; font-size:12px; color:#374151;"><strong>Dispositivo:</strong> {$device}</p>
  <p style="margin:0 0 6px; font-size:12px; color:#374151;"><strong>IP:</strong> {$ip}</p>
  {$locHtml}
</td></tr></table>

<p style="margin:0; font-size:13px; color:#6b7280; line-height:1.6;">
  Se foi você, pode ignorar este e-mail. Caso não reconheça esse login, recomendamos alterar sua senha imediatamente.
</p>
HTML;
    return emailBaseTemplate('Novo login detectado', $content);
}

/* ──────────────────────────────────────────────────────
   5. TELEFONE VALIDADO
   ────────────────────────────────────────────────────── */
function emailTelefoneValidado(string $nome, string $telefone, ?object $conn = null): string
{
    $appName = defined('APP_NAME') ? APP_NAME : 'Basefy';
    $tpl = emailTplLoad($conn, 'telefone_validado');
    if ($tpl) {
        $vars = ['{{nome}}' => $nome, '{{app_name}}' => $appName, '{{telefone}}' => $telefone];
        $body = emailTplReplace($tpl['body'], $vars);
        return emailBaseTemplate($tpl['subject'] ?? 'Telefone Validado', $body);
    }
    $content = <<<HTML
<div style="text-align:center; padding:12px 0;">
  <div style="display:inline-block; width:64px; height:64px; background-color:#f0fdf4; border-radius:50%; line-height:64px; font-size:28px; margin-bottom:16px;">✅</div>
</div>
<h2 style="margin:0 0 16px; font-size:20px; color:#111827; font-weight:700; text-align:center;">Telefone validado!</h2>
<p style="margin:0 0 8px; font-size:14px; color:#4b5563; line-height:1.7; text-align:center;">
  Parabéns <strong>{$nome}</strong>, agora o seu número de telefone está validado!
</p>
<p style="margin:0; font-size:16px; color:#111827; font-weight:600; text-align:center; padding:8px 0;">
  📱 {$telefone}
</p>
<p style="margin:16px 0 0; font-size:13px; color:#6b7280; line-height:1.6; text-align:center;">
  Você já pode utilizar todos os recursos disponíveis no <strong>{$appName}</strong>.
</p>
HTML;
    return emailBaseTemplate('Telefone Validado', $content);
}

/* ──────────────────────────────────────────────────────
   6. PRODUTO ENVIADO PARA ANÁLISE
   ────────────────────────────────────────────────────── */
function emailProdutoEnviado(string $nome, string $nomeProduto, ?object $conn = null): string
{
    $appName = defined('APP_NAME') ? APP_NAME : 'Basefy';
    $tpl = emailTplLoad($conn, 'produto_enviado');
    if ($tpl) {
        $vars = ['{{nome}}' => $nome, '{{app_name}}' => $appName, '{{produto}}' => $nomeProduto];
        $body = emailTplReplace($tpl['body'], $vars);
        return emailBaseTemplate($tpl['subject'] ?? 'Anúncio em análise', $body);
    }
    $content = <<<HTML
<h2 style="margin:0 0 16px; font-size:20px; color:#111827; font-weight:700;">Anúncio enviado para análise</h2>
<p style="margin:0 0 8px; font-size:14px; color:#4b5563; line-height:1.7;">
  Olá <strong>{$nome}</strong>,
</p>
<p style="margin:0 0 12px; font-size:14px; color:#4b5563; line-height:1.7;">
  Seu anúncio <strong>"{$nomeProduto}"</strong> foi enviado e está sendo analisado pela nossa equipe.
  Nossa equipe irá rapidamente revisar seu anúncio e, caso esteja tudo certo, ele será publicado na loja.
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0; background-color:#fffbeb; border:1px solid #fde68a; border-radius:8px;">
<tr><td style="padding:16px;">
  <p style="margin:0 0 4px; font-size:13px; color:#92400e; font-weight:600;">⏳ Em análise</p>
  <p style="margin:0; font-size:12px; color:#a16207;">Você receberá uma notificação assim que a análise for concluída.</p>
</td></tr></table>
HTML;
    return emailBaseTemplate('Anúncio em análise', $content);
}

/* ──────────────────────────────────────────────────────
   7. PRODUTO APROVADO
   ────────────────────────────────────────────────────── */
function emailProdutoAprovado(string $nome, string $nomeProduto, string $produtoUrl = '', ?object $conn = null): string
{
    $appName = defined('APP_NAME') ? APP_NAME : 'Basefy';
    $tpl = emailTplLoad($conn, 'produto_aprovado');
    if ($tpl) {
        $vars = ['{{nome}}' => $nome, '{{app_name}}' => $appName, '{{produto}}' => $nomeProduto, '{{link}}' => $produtoUrl];
        $body = emailTplReplace($tpl['body'], $vars);
        return emailBaseTemplate($tpl['subject'] ?? 'Anúncio aprovado', $body);
    }
    $btnHtml = $produtoUrl ? emailButton('Ver meu anúncio', $produtoUrl, '#8800E4') : '';
    $content = <<<HTML
<div style="text-align:center; padding:12px 0;">
  <div style="display:inline-block; width:64px; height:64px; background-color:#f0fdf4; border-radius:50%; line-height:64px; font-size:28px; margin-bottom:16px;">🎉</div>
</div>
<h2 style="margin:0 0 16px; font-size:20px; color:#111827; font-weight:700; text-align:center;">Anúncio aprovado!</h2>
<p style="margin:0 0 8px; font-size:14px; color:#4b5563; line-height:1.7;">
  Olá <strong>{$nome}</strong>,
</p>
<p style="margin:0 0 12px; font-size:14px; color:#4b5563; line-height:1.7;">
  Seu anúncio <strong>"{$nomeProduto}"</strong> foi analisado e <strong style="color:#8800E4;">aprovado</strong> pela nossa equipe!
  Ele já está disponível na loja do <strong>{$appName}</strong>.
</p>
{$btnHtml}
HTML;
    return emailBaseTemplate('Anúncio aprovado', $content);
}

/* ──────────────────────────────────────────────────────
   8. PRODUTO PRECISA DE REVISÃO
   ────────────────────────────────────────────────────── */
function emailProdutoRevisao(string $nome, string $nomeProduto, string $motivo, string $editUrl = '', ?object $conn = null): string
{
    $appName = defined('APP_NAME') ? APP_NAME : 'Basefy';
    $tpl = emailTplLoad($conn, 'produto_revisao');
    if ($tpl) {
        $vars = ['{{nome}}' => $nome, '{{app_name}}' => $appName, '{{produto}}' => $nomeProduto, '{{motivo}}' => $motivo, '{{link}}' => $editUrl];
        $body = emailTplReplace($tpl['body'], $vars);
        return emailBaseTemplate($tpl['subject'] ?? 'Anúncio precisa de revisão', $body);
    }
    $btnHtml = $editUrl ? emailButton('Editar anúncio', $editUrl, '#f59e0b') : '';
    $content = <<<HTML
<h2 style="margin:0 0 16px; font-size:20px; color:#111827; font-weight:700;">Anúncio precisa de revisão</h2>
<p style="margin:0 0 8px; font-size:14px; color:#4b5563; line-height:1.7;">
  Olá <strong>{$nome}</strong>,
</p>
<p style="margin:0 0 12px; font-size:14px; color:#4b5563; line-height:1.7;">
  Ao analisar seu anúncio <strong>"{$nomeProduto}"</strong>, nossa equipe encontrou informações que
  não estão de acordo com as diretrizes do <strong>{$appName}</strong>.
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0; background-color:#fef2f2; border:1px solid #fecaca; border-radius:8px;">
<tr><td style="padding:16px;">
  <p style="margin:0 0 6px; font-size:13px; color:#991b1b; font-weight:600;">Motivo da revisão:</p>
  <p style="margin:0; font-size:13px; color:#7f1d1d; line-height:1.6;">{$motivo}</p>
</td></tr></table>

<p style="margin:0 0 8px; font-size:14px; color:#4b5563; line-height:1.7;">
  Faça os ajustes necessários e reenvie o anúncio para uma nova análise.
</p>
{$btnHtml}
HTML;
    return emailBaseTemplate('Anúncio precisa de revisão', $content);
}

/* ════════════════════════════════════════════════════════════
   CONVENIENCE: send verification e-mail for user account
   ════════════════════════════════════════════════════════════ */

/**
 * Sends the email verification link to the user.
 * Returns true on success, string error message on failure.
 */
function enviarEmailVerificacao(object $conn, int|string $uid, string $template = 'confirmacao'): bool|string
{
    $uid = (int)$uid;
    error_log('[enviarEmailVerificacao] START uid=' . $uid . ' template=' . $template);

    if (!smtpConfigured($conn)) {
        error_log('[enviarEmailVerificacao] SMTP not configured');
        return 'SMTP não configurado. Configure o SMTP no painel administrativo.';
    }

    // Get user data
    $st = $conn->prepare("SELECT id, email, nome FROM users WHERE id = ? LIMIT 1");
    if (!$st) {
        $st = $conn->prepare("SELECT id, email FROM users WHERE id = ? LIMIT 1");
    }
    if (!$st) {
        error_log('[enviarEmailVerificacao] prepare failed for uid=' . $uid);
        return 'Erro interno ao buscar dados do usuário.';
    }
    $st->bind_param('i', $uid);
    $st->execute();
    $user = $st->get_result()->fetch_assoc();
    $st->close();

    if (!$user || empty($user['email'])) {
        error_log('[enviarEmailVerificacao] user not found or no email for uid=' . $uid);
        return 'Nenhum e-mail cadastrado na sua conta.';
    }

    $email = (string)$user['email'];
    $nome  = (string)($user['nome'] ?? 'Usuário');
    error_log('[enviarEmailVerificacao] user found: email=' . $email . ' nome=' . $nome);

    // Generate token (24h expiry)
    try {
        $token = gerarTokenVerificacao($conn, $uid, 'email_verify', 1440);
    } catch (\Throwable $e) {
        error_log('[enviarEmailVerificacao] token generation failed: ' . $e->getMessage());
        return 'Erro ao gerar token de verificação: ' . $e->getMessage();
    }
    $verifyUrl = rtrim(APP_URL, '/') . '/verificar-email?token=' . $token;

    // Build & send — use welcome template for new registrations
    try {
        if ($template === 'boas_vindas') {
            $html = emailBoasVindas($nome, $verifyUrl, $conn);
            $subject = 'Bem-vindo(a) ao ' . APP_NAME . '!';
        } else {
            $html = emailConfirmacaoEmail($nome, $verifyUrl, $conn);
            $subject = 'Confirme seu e-mail – ' . APP_NAME;
        }
    } catch (\Throwable $e) {
        error_log('[enviarEmailVerificacao] template build failed: ' . $e->getMessage());
        return 'Erro ao montar template de e-mail: ' . $e->getMessage();
    }

    error_log('[enviarEmailVerificacao] sending to=' . $email . ' subject=' . $subject);
    $sent = smtpSend($email, $subject, $html);
    error_log('[enviarEmailVerificacao] smtpSend returned=' . ($sent ? 'true' : 'false') . ' lastError=' . smtpLastError());

    return $sent ? true : 'Falha ao enviar o e-mail de verificação. ' . (smtpLastError() ?: 'Verifique a configuração de e-mail no painel administrativo.');
}
