<?php
// filepath: c:\xampp\htdocs\mercado_admin\src\push.php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/push_debug.php';

/* ═══════════════════════════════════════════════════════════════════
 * Push Notifications — VAPID + Web Push (no external library needed)
 * ═══════════════════════════════════════════════════════════════════ */

function pushEnsureTable($conn): void
{
    static $done = false;
    if ($done) return;

    $conn->query("
        CREATE TABLE IF NOT EXISTS push_subscriptions (
            id         SERIAL PRIMARY KEY,
            user_id    INTEGER NOT NULL,
            endpoint   TEXT NOT NULL,
            p256dh     VARCHAR(500) NOT NULL,
            auth       VARCHAR(255) NOT NULL,
            criado_em  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $check = $conn->query("SELECT 1 FROM push_subscriptions LIMIT 1");
    if ($check === false) {
        error_log('[Push] WARN: push_subscriptions table could not be created.');
        return;
    }
    $done = true;

    $conn->query("CREATE UNIQUE INDEX IF NOT EXISTS idx_push_sub_ue ON push_subscriptions(user_id, endpoint)");
}

/* ─── VAPID Key Management ─────────────────────────────────────── */

/**
 * Get existing VAPID keys or generate + store a new pair.
 * @return array{publicKey:string, privatePem:string}|null
 */
function pushGetVapidKeys($conn): ?array
{
    $pub = null;
    $pem = null;

    $stPub = $conn->prepare("SELECT setting_value FROM platform_settings WHERE setting_key = ?");
    if ($stPub) {
        $k = 'vapid_public_key';
        $stPub->bind_param('s', $k);
        $stPub->execute();
        $pub = $stPub->get_result()->fetch_assoc()['setting_value'] ?? null;
        $stPub->close();
    }

    $stPem = $conn->prepare("SELECT setting_value FROM platform_settings WHERE setting_key = ?");
    if ($stPem) {
        $k = 'vapid_private_pem';
        $stPem->bind_param('s', $k);
        $stPem->execute();
        $pem = $stPem->get_result()->fetch_assoc()['setting_value'] ?? null;
        $stPem->close();
    }

    if ($pub && $pem) {
        return ['publicKey' => $pub, 'privatePem' => $pem];
    }

    // Generate new VAPID key pair
    $keys = pushGenerateVapidKeys();
    if (!$keys) {
        error_log('[Push] VAPID key generation failed — openssl EC support missing?');
        return null;
    }

    $sql = "INSERT INTO platform_settings (setting_key, setting_value)
            VALUES (?, ?)
            ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value";

    $st1 = $conn->prepare($sql);
    if ($st1) {
        $k1 = 'vapid_public_key';
        $v1 = $keys['publicKey'];
        $st1->bind_param('ss', $k1, $v1);
        $st1->execute();
        $st1->close();
    }

    $st2 = $conn->prepare($sql);
    if ($st2) {
        $k2 = 'vapid_private_pem';
        $v2 = $keys['privatePem'];
        $st2->bind_param('ss', $k2, $v2);
        $st2->execute();
        $st2->close();
    }

    error_log('[Push] Generated + stored new VAPID keys');
    return $keys;
}

/**
 * Generate ECDSA P-256 key pair for VAPID using PHP openssl.
 */
function pushGenerateVapidKeys(): ?array
{
    $key = openssl_pkey_new([
        'curve_name'       => 'prime256v1',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
    ]);
    if (!$key) return null;

    $details = openssl_pkey_get_details($key);
    if (!$details || !isset($details['ec'])) return null;

    openssl_pkey_export($key, $privPem);

    // Uncompressed public point: 0x04 || x(32) || y(32) → base64url
    $x = str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT);
    $y = str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);
    $pubB64 = pushBase64url("\x04" . $x . $y);

    return ['publicKey' => $pubB64, 'privatePem' => $privPem];
}

/* ─── Subscription CRUD ────────────────────────────────────────── */

function pushSaveSubscription($conn, int $userId, string $endpoint, string $p256dh, string $auth): bool
{
    pushEnsureTable($conn);

    $sql = "INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth)
            VALUES (?, ?, ?, ?)
            ON CONFLICT (user_id, endpoint)
            DO UPDATE SET p256dh = EXCLUDED.p256dh, auth = EXCLUDED.auth, criado_em = CURRENT_TIMESTAMP";

    $st = $conn->prepare($sql);
    if (!$st) return false;
    $st->bind_param('isss', $userId, $endpoint, $p256dh, $auth);
    $st->execute();
    $st->close();
    return true;
}

function pushRemoveSubscription($conn, int $userId, string $endpoint): bool
{
    pushEnsureTable($conn);

    $st = $conn->prepare("DELETE FROM push_subscriptions WHERE user_id = ? AND endpoint = ?");
    if (!$st) return false;
    $st->bind_param('is', $userId, $endpoint);
    $st->execute();
    $ok = $st->affected_rows > 0;
    $st->close();
    return $ok;
}

function pushGetUserSubscriptions($conn, int $userId): array
{
    pushEnsureTable($conn);

    try {
        pushDebugLog('    pushGetUserSubscriptions: querying', ['userId' => $userId]);
        $st = $conn->prepare("SELECT endpoint, p256dh, auth FROM push_subscriptions WHERE user_id = ?");
        if (!$st) {
            pushDebugLog('    pushGetUserSubscriptions: prepare FAILED', ['userId' => $userId]);
            return [];
        }
        $st->bind_param('i', $userId);
        $st->execute();
        $result = $st->get_result();
        pushDebugLog('    pushGetUserSubscriptions: execute done, getting rows');
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $st->close();
        pushDebugLog('    pushGetUserSubscriptions: found ' . count($rows) . ' sub(s)', [
            'userId' => $userId,
            'endpoints' => array_map(fn($r) => substr($r['endpoint'] ?? '', 0, 60), $rows),
        ]);
        return $rows;
    } catch (\Throwable $e) {
        pushDebugLog('    pushGetUserSubscriptions EXCEPTION', [
            'userId' => $userId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        return [];
    }
}

/* ─── Push Sending ─────────────────────────────────────────────── */

/**
 * Send a push notification with encrypted payload to all of a user's subscriptions.
 * Uses Web Push encryption (RFC 8291 / aes128gcm) so the Service Worker
 * receives the actual notification content without needing to fetch the API.
 * Returns number of successful sends.
 */
function pushSendToUser($conn, int $userId, string $title = '', string $body = '', string $url = ''): int
{
    pushDebugLog('  pushSendToUser START', ['userId' => $userId, 'title' => $title]);

    $subs = pushGetUserSubscriptions($conn, $userId);
    if (empty($subs)) {
        pushDebugLog('  pushSendToUser: NO SUBSCRIPTIONS — push skipped', ['userId' => $userId]);
        return 0;
    }

    $vapid = pushGetVapidKeys($conn);
    if (!$vapid) {
        pushDebugLog('  pushSendToUser: NO VAPID KEYS — push skipped', ['userId' => $userId]);
        return 0;
    }
    pushDebugLog('  pushSendToUser: VAPID OK, publicKey=' . substr($vapid['publicKey'], 0, 20) . '...');

    $payload = json_encode([
        'title' => $title ?: 'Nova notificação',
        'body'  => $body ?: 'Você tem uma nova notificação.',
        'url'   => $url ?: '/',
    ], JSON_UNESCAPED_UNICODE);

    $sent = 0;
    foreach ($subs as $idx => $sub) {
        pushDebugLog('  pushSendToUser: encrypting+sending sub #' . $idx, [
            'endpoint' => substr($sub['endpoint'], 0, 60),
        ]);
        $ok = pushSendEncrypted($sub['endpoint'], $sub['p256dh'], $sub['auth'], $payload, $vapid);
        if ($ok) {
            $sent++;
            pushDebugLog('  pushSendToUser: sub #' . $idx . ' → SUCCESS');
        } else {
            pushDebugLog('  pushSendToUser: sub #' . $idx . ' → FAILED');
        }
    }

    pushDebugLog('  pushSendToUser DONE', ['userId' => $userId, 'sent' => $sent, 'total' => count($subs)]);
    return $sent;
}

/**
 * Send an encrypted push message (RFC 8291 / aes128gcm).
 * Uses the subscriber's p256dh + auth keys for end-to-end encryption.
 */
function pushSendEncrypted(string $endpoint, string $p256dh, string $auth, string $payload, array $vapid): bool
{
    $parsed = parse_url($endpoint);
    if (!$parsed || empty($parsed['scheme']) || empty($parsed['host'])) {
        pushDebugLog('    pushSendEncrypted: invalid endpoint', ['endpoint' => substr($endpoint, 0, 80)]);
        return false;
    }

    $audience = $parsed['scheme'] . '://' . $parsed['host'];
    $jwt = pushCreateVapidJwt($audience, $vapid['privatePem']);
    if (!$jwt) {
        pushDebugLog('    pushSendEncrypted: JWT creation failed', ['audience' => $audience]);
        return false;
    }

    // Decrypt subscriber keys from base64url
    $userPublicKey = pushBase64urlDecode($p256dh);
    $userAuth      = pushBase64urlDecode($auth);

    if (strlen($userPublicKey) !== 65 || strlen($userAuth) !== 16) {
        pushDebugLog('    pushSendEncrypted: invalid subscriber keys', [
            'p256dh_len' => strlen($userPublicKey),
            'auth_len' => strlen($userAuth),
        ]);
        return false;
    }

    // Encrypt the payload using Web Push encryption (aes128gcm)
    $encrypted = pushEncryptPayload($payload, $userPublicKey, $userAuth);
    if ($encrypted === null) {
        pushDebugLog('    pushSendEncrypted: encryption failed');
        return false;
    }

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $encrypted['body'],
        CURLOPT_HTTPHEADER     => [
            'TTL: 86400',
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'Content-Length: ' . strlen($encrypted['body']),
            'Authorization: vapid t=' . $jwt . ', k=' . $vapid['publicKey'],
            'Urgency: normal',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_FOLLOWLOCATION => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    pushDebugLog('    pushSendEncrypted: cURL done', [
        'httpCode' => $httpCode,
        'curlErr' => $curlErr ?: '(none)',
        'response' => substr((string)$response, 0, 120),
        'endpoint' => substr($endpoint, 0, 60),
    ]);

    if ($httpCode >= 200 && $httpCode < 300) {
        return true;
    }

    if ($httpCode === 404 || $httpCode === 410) {
        pushDebugLog('    pushSendEncrypted: subscription expired/removed', ['endpoint' => substr($endpoint, 0, 80)]);
    }

    return false;
}

/**
 * Encrypt payload for Web Push using aes128gcm (RFC 8291 + RFC 8188).
 * @return array{body: string}|null
 */
function pushEncryptPayload(string $payload, string $userPublicKey, string $userAuth): ?array
{
    // Generate a local ECDH key pair (ephemeral)
    $localKey = openssl_pkey_new([
        'curve_name'       => 'prime256v1',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
    ]);
    if (!$localKey) return null;

    $localDetails = openssl_pkey_get_details($localKey);
    if (!$localDetails || !isset($localDetails['ec'])) return null;

    // Local public key (uncompressed: 0x04 || x || y)
    $localX = str_pad($localDetails['ec']['x'], 32, "\0", STR_PAD_LEFT);
    $localY = str_pad($localDetails['ec']['y'], 32, "\0", STR_PAD_LEFT);
    $localPublicKey = "\x04" . $localX . $localY;

    // ECDH shared secret
    $sharedSecret = pushEcdhSharedSecret($localKey, $userPublicKey);
    if ($sharedSecret === null) return null;

    // Generate 16-byte salt
    $salt = random_bytes(16);

    // Key derivation (RFC 8291 Section 3.4)
    // IKM = HKDF(auth_secret, ecdh_secret, "WebPush: info" || 0x00 || ua_public || as_public, 32)
    $ikm_info = "WebPush: info\x00" . $userPublicKey . $localPublicKey;
    $ikm = pushHkdf($userAuth, $sharedSecret, $ikm_info, 32);

    // Content Encryption Key (CEK) — 16 bytes
    // Info string ends with 0x00 only; the counter byte 0x01 is appended by pushHkdf internally
    $cek_info = "Content-Encoding: aes128gcm\x00";
    $cek = pushHkdf($salt, $ikm, $cek_info, 16);

    // Nonce — 12 bytes
    $nonce_info = "Content-Encoding: nonce\x00";
    $nonce = pushHkdf($salt, $ikm, $nonce_info, 12);

    // Pad the payload (add delimiter 0x02 + optional zero padding)
    $paddedPayload = $payload . "\x02";

    // Encrypt with AES-128-GCM
    $tag = '';
    $encrypted = openssl_encrypt($paddedPayload, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
    if ($encrypted === false) return null;

    // Build aes128gcm body:
    // header: salt(16) || rs(4, big-endian uint32) || idlen(1) || keyid(65) || encrypted_record
    $rs = strlen($paddedPayload) + 16 + 1; // record size (content + tag + padding delimiter minimum)
    $rs = max($rs, 4096); // minimum reasonable record size
    $header = $salt . pack('N', $rs) . chr(65) . $localPublicKey;

    return ['body' => $header . $encrypted . $tag];
}

/**
 * Compute ECDH shared secret between local private key and remote public key.
 */
function pushEcdhSharedSecret($localPrivKey, string $remotePublicKeyRaw): ?string
{
    // Create a PEM from the raw uncompressed public key
    // The DER encoding for a P-256 public key:
    // SEQUENCE { SEQUENCE { OID(ecPublicKey), OID(prime256v1) }, BIT STRING { 0x00 || uncompressed_point } }
    $ecPublicKeyOid = "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01"; // 1.2.840.10045.2.1
    $prime256v1Oid  = "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"; // 1.2.840.10045.3.1.7

    $algId = "\x30" . chr(strlen($ecPublicKeyOid . $prime256v1Oid)) . $ecPublicKeyOid . $prime256v1Oid;
    $bitStr = "\x03" . chr(strlen($remotePublicKeyRaw) + 1) . "\x00" . $remotePublicKeyRaw;
    $derPub = "\x30" . chr(strlen($algId . $bitStr)) . $algId . $bitStr;

    $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($derPub), 64, "\n") . "-----END PUBLIC KEY-----\n";

    $remotePubKey = openssl_pkey_get_public($pem);
    if (!$remotePubKey) {
        error_log('[Push] Failed to parse remote public key PEM');
        return null;
    }

    // openssl_pkey_derive(peer_public_key, local_private_key)
    $shared = openssl_pkey_derive($remotePubKey, $localPrivKey);
    if ($shared === false) {
        error_log("[Push] ECDH derive failed: " . openssl_error_string());
        return null;
    }

    return $shared;
}

/**
 * HKDF-SHA256 extract + expand (RFC 5869)
 */
function pushHkdf(string $salt, string $ikm, string $info, int $length): string
{
    $prk = hash_hmac('sha256', $ikm, $salt, true);
    $t = '';
    $output = '';
    for ($i = 1; strlen($output) < $length; $i++) {
        $t = hash_hmac('sha256', $t . $info . chr($i), $prk, true);
        $output .= $t;
    }
    return substr($output, 0, $length);
}

/**
 * Decode base64url string to raw bytes.
 */
function pushBase64urlDecode(string $data): string
{
    $data = strtr($data, '-_', '+/');
    $pad = (4 - strlen($data) % 4) % 4;
    $data .= str_repeat('=', $pad);
    return base64_decode($data, true) ?: '';
}

/* ─── VAPID JWT ────────────────────────────────────────────────── */

/**
 * Create a signed VAPID JWT (ES256 / ECDSA P-256 SHA-256).
 */
function pushCreateVapidJwt(string $audience, string $privatePem): ?string
{
    $headerB64  = pushBase64url(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $payloadB64 = pushBase64url(json_encode([
        'aud' => $audience,
        'exp' => time() + 86400,
        'sub' => 'mailto:noreply@mercadoadmin.com',
    ]));

    $signingInput = "{$headerB64}.{$payloadB64}";

    $privKey = openssl_pkey_get_private($privatePem);
    if (!$privKey) {
        error_log('[Push] Invalid VAPID private PEM');
        return null;
    }

    $ok = openssl_sign($signingInput, $derSig, $privKey, OPENSSL_ALGO_SHA256);
    if (!$ok || empty($derSig)) {
        error_log('[Push] ECDSA signing failed');
        return null;
    }

    $rawSig = pushDerToRaw($derSig);
    if ($rawSig === null) return null;

    return "{$signingInput}." . pushBase64url($rawSig);
}

/**
 * Convert DER-encoded ECDSA signature → raw r||s (64 bytes).
 *
 * DER structure:
 *   SEQUENCE { INTEGER r, INTEGER s }
 *   0x30 <len> 0x02 <rLen> <r> 0x02 <sLen> <s>
 */
function pushDerToRaw(string $der): ?string
{
    $len = strlen($der);
    if ($len < 8) return null;

    $offset = 0;
    if (ord($der[$offset]) !== 0x30) return null;
    $offset++;

    // Total length (skip; may be multi-byte but won't be for P-256)
    $tl = ord($der[$offset]);
    $offset++;
    if ($tl & 0x80) {
        $n = $tl & 0x7F;
        $offset += $n; // skip multi-byte length bytes
    }

    // r
    if (ord($der[$offset]) !== 0x02) return null;
    $offset++;
    $rLen = ord($der[$offset]);
    $offset++;
    $r = substr($der, $offset, $rLen);
    $offset += $rLen;

    // s
    if ($offset >= $len || ord($der[$offset]) !== 0x02) return null;
    $offset++;
    $sLen = ord($der[$offset]);
    $offset++;
    $s = substr($der, $offset, $sLen);

    // Strip leading 0x00 padding, left-pad to 32 bytes
    $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
    $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);

    return $r . $s;
}

/* ─── Helpers ──────────────────────────────────────────────────── */

function pushBase64url(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
