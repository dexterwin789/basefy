<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/* ========================================================================
 *  AUTO-MIGRATION — creates affiliate tables if missing
 * ======================================================================== */

function affEnsureTables($conn): void
{
    static $checked = false;
    if ($checked) return;
    $checked = true;

    $tables = ['affiliates', 'affiliate_clicks', 'affiliate_conversions', 'affiliate_payouts'];
    foreach ($tables as $t) {
        $r = $conn->query("SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = '$t' LIMIT 1");
        if ($r && $r->fetch_assoc()) continue;

        // Run schema
        $sql = file_get_contents(__DIR__ . '/../sql/affiliates.sql');
        if ($sql) {
            foreach (explode(';', $sql) as $stmt) {
                $stmt = trim($stmt);
                if ($stmt !== '') {
                    try { $conn->query($stmt); } catch (\Throwable $e) { /* ignore duplicate */ }
                }
            }
        }
        break; // all tables created at once
    }
}

/* ========================================================================
 *  SETTINGS  (stored in platform_settings, key prefix = 'affiliate.')
 * ======================================================================== */

function affSettingGet($conn, string $key, string $default = ''): string
{
    $fullKey = 'affiliate.' . $key;
    $st = $conn->prepare("SELECT setting_value FROM platform_settings WHERE setting_key = ? LIMIT 1");
    if (!$st) return $default;
    $st->bind_param('s', $fullKey);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ? (string)$row['setting_value'] : $default;
}

function affSettingSet($conn, string $key, string $value): void
{
    $fullKey = 'affiliate.' . $key;
    $st = $conn->prepare("INSERT INTO platform_settings (setting_key, setting_value) VALUES (?, ?)
                          ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value");
    if ($st) {
        $st->bind_param('ss', $fullKey, $value);
        $st->execute();
        $st->close();
    }
}

function affEnsureDefaults($conn): void
{
    $defaults = [
        'commission_percent'   => '8.00',
        'cookie_days'          => '30',
        'min_payout'           => '50.00',
        'auto_approve'         => '0',
        'program_enabled'      => '1',
        'program_name'         => 'Programa de Afiliados',
        'program_description'  => 'Indique produtos e ganhe comissão por cada venda realizada através do seu link.',
        'allow_self_referral'  => '0',
    ];
    foreach ($defaults as $k => $v) {
        $val = affSettingGet($conn, $k, '');
        if ($val === '') {
            affSettingSet($conn, $k, $v);
        }
    }
}

function affRules($conn): array
{
    affEnsureDefaults($conn);
    return [
        'commission_percent'   => (float)affSettingGet($conn, 'commission_percent', '8.00'),
        'cookie_days'          => (int)affSettingGet($conn, 'cookie_days', '30'),
        'min_payout'           => (float)affSettingGet($conn, 'min_payout', '50.00'),
        'auto_approve'         => affSettingGet($conn, 'auto_approve', '0') === '1',
        'program_enabled'      => affSettingGet($conn, 'program_enabled', '1') === '1',
        'program_name'         => affSettingGet($conn, 'program_name', 'Programa de Afiliados'),
        'program_description'  => affSettingGet($conn, 'program_description', ''),
        'allow_self_referral'  => affSettingGet($conn, 'allow_self_referral', '0') === '1',
    ];
}

/* ========================================================================
 *  REFERRAL CODE GENERATION
 * ======================================================================== */

function affGenerateCode(int $length = 8): string
{
    $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

function affGenerateUniqueCode($conn, int $length = 8): string
{
    for ($i = 0; $i < 20; $i++) {
        $code = affGenerateCode($length);
        $st = $conn->prepare('SELECT id FROM affiliates WHERE referral_code = ? LIMIT 1');
        $st->bind_param('s', $code);
        $st->execute();
        $exists = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$exists) return $code;
    }
    return affGenerateCode(12);
}

/* ========================================================================
 *  AFFILIATE CRUD
 * ======================================================================== */

function affGetByUserId($conn, int $userId): ?array
{
    affEnsureTables($conn);
    $st = $conn->prepare('SELECT * FROM affiliates WHERE user_id = ? LIMIT 1');
    if (!$st) return null;
    $st->bind_param('i', $userId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ?: null;
}

function affGetById($conn, int|string $id): ?array
{
    $id = (int)$id;
    $st = $conn->prepare('SELECT a.*, u.nome, u.email, u.avatar FROM affiliates a JOIN users u ON u.id = a.user_id WHERE a.id = ? LIMIT 1');
    if (!$st) return null;
    $st->bind_param('i', $id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ?: null;
}

function affRegister($conn, int $userId, ?string $pixKeyType = null, ?string $pixKey = null, ?string $bio = null): array
{
    affEnsureTables($conn);
    $rules = affRules($conn);

    if (!$rules['program_enabled']) {
        return [false, 'O programa de afiliados não está ativo no momento.'];
    }

    $existing = affGetByUserId($conn, $userId);
    if ($existing) {
        return [false, 'Você já possui cadastro no programa de afiliados.'];
    }

    $code = affGenerateUniqueCode($conn);
    $status = $rules['auto_approve'] ? 'ativo' : 'pendente';

    $st = $conn->prepare('INSERT INTO affiliates (user_id, referral_code, status, pix_key_type, pix_key, bio) VALUES (?, ?, ?, ?, ?, ?)');
    if (!$st) return [false, 'Erro ao cadastrar afiliado.'];
    $st->bind_param('isssss', $userId, $code, $status, $pixKeyType, $pixKey, $bio);
    $st->execute();
    $st->close();

    $statusMsg = $status === 'ativo'
        ? 'Cadastro aprovado automaticamente! Seu código de afiliado: ' . $code
        : 'Cadastro enviado para aprovação. Você será notificado quando aprovado.';

    return [true, $statusMsg];
}

function affUpdateProfile($conn, int|string $affiliateId, ?string $pixKeyType, ?string $pixKey, ?string $bio): array
{
    $affiliateId = (int)$affiliateId;
    $st = $conn->prepare('UPDATE affiliates SET pix_key_type = ?, pix_key = ?, bio = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    if (!$st) return [false, 'Erro ao atualizar perfil.'];
    $st->bind_param('sssi', $pixKeyType, $pixKey, $bio, $affiliateId);
    $st->execute();
    $st->close();
    return [true, 'Perfil de afiliado atualizado.'];
}

function affSetStatus($conn, int|string $affiliateId, string $status): bool
{
    $affiliateId = (int)$affiliateId;
    $allowed = ['ativo', 'pendente', 'suspenso', 'rejeitado'];
    if (!in_array($status, $allowed, true)) return false;
    $st = $conn->prepare('UPDATE affiliates SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    if (!$st) return false;
    $st->bind_param('si', $status, $affiliateId);
    $st->execute();
    $st->close();
    return true;
}

/* ========================================================================
 *  CLICK TRACKING
 * ======================================================================== */

function affTrackClick($conn, int|string $affiliateId, int|string|null $productId = null): void
{
    $affiliateId = (int)$affiliateId;
    $productId = $productId !== null ? (int)$productId : null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $landing = $_SERVER['REQUEST_URI'] ?? '';

    $st = $conn->prepare('INSERT INTO affiliate_clicks (affiliate_id, product_id, ip_address, user_agent, referrer_url, landing_url) VALUES (?, ?, ?, ?, ?, ?)');
    if (!$st) return;
    $st->bind_param('iissss', $affiliateId, $productId, $ip, $ua, $ref, $landing);
    $st->execute();
    $st->close();

    $conn->query("UPDATE affiliates SET total_clicks = total_clicks + 1 WHERE id = $affiliateId");
}

function affSetCookie(string $referralCode, int $cookieDays): void
{
    $expires = time() + ($cookieDays * 86400);
    setcookie('aff_ref', $referralCode, [
        'expires'  => $expires,
        'path'     => '/',
        'httponly'  => false,
        'secure'   => isset($_SERVER['HTTPS']),
        'samesite' => 'Lax',
    ]);
    $_COOKIE['aff_ref'] = $referralCode;
}

function affGetReferralFromCookie(): ?string
{
    $code = $_COOKIE['aff_ref'] ?? null;
    return ($code && is_string($code) && strlen($code) >= 4) ? $code : null;
}

/* ========================================================================
 *  CONVERSION / COMMISSION
 * ======================================================================== */

function affAttributeConversion($conn, int $orderId, int $buyerId, float $orderTotal): void
{
    affEnsureTables($conn);
    $code = affGetReferralFromCookie();
    if (!$code) return;

    // Find affiliate
    $st = $conn->prepare("SELECT * FROM affiliates WHERE referral_code = ? AND status = 'ativo' LIMIT 1");
    if (!$st) return;
    $st->bind_param('s', $code);
    $st->execute();
    $aff = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$aff) return;

    $affiliateId = (int)$aff['id'];
    $affUserId = (int)$aff['user_id'];

    // Prevent self-referral (unless allowed)
    $rules = affRules($conn);
    if (!$rules['allow_self_referral'] && $affUserId === $buyerId) {
        return;
    }

    // Check duplicate
    $stDup = $conn->prepare('SELECT id FROM affiliate_conversions WHERE affiliate_id = ? AND order_id = ? LIMIT 1');
    $stDup->bind_param('ii', $affiliateId, $orderId);
    $stDup->execute();
    $dup = $stDup->get_result()->fetch_assoc();
    $stDup->close();
    if ($dup) return;

    // Calculate commission
    $rate = $aff['custom_rate'] !== null ? (float)$aff['custom_rate'] : $rules['commission_percent'];
    $commission = round($orderTotal * ($rate / 100), 2);

    if ($commission <= 0) return;

    $st = $conn->prepare('INSERT INTO affiliate_conversions (affiliate_id, order_id, buyer_id, order_total, commission_rate, commission_amount, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
    if (!$st) return;
    $convStatus = 'pendente';
    $st->bind_param('iiiddds', $affiliateId, $orderId, $buyerId, $orderTotal, $rate, $commission, $convStatus);
    $st->execute();
    $st->close();

    // Update affiliate totals
    $conn->query("UPDATE affiliates SET total_conversions = total_conversions + 1, total_earned = total_earned + $commission WHERE id = $affiliateId");
}

function affApproveConversion($conn, int|string $conversionId): bool
{
    $conversionId = (int)$conversionId;
    $st = $conn->prepare("UPDATE affiliate_conversions SET status = 'aprovada' WHERE id = ? AND status = 'pendente'");
    if (!$st) return false;
    $st->bind_param('i', $conversionId);
    $st->execute();
    $st->close();
    return true;
}

function affCancelConversion($conn, int|string $conversionId): bool
{
    $conversionId = (int)$conversionId;
    // Get details first
    $st = $conn->prepare('SELECT * FROM affiliate_conversions WHERE id = ? LIMIT 1');
    $st->bind_param('i', $conversionId);
    $st->execute();
    $conv = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$conv || $conv['status'] === 'paga') return false;

    $affId = (int)$conv['affiliate_id'];
    $amount = (float)$conv['commission_amount'];

    $st = $conn->prepare("UPDATE affiliate_conversions SET status = 'cancelada' WHERE id = ?");
    $st->bind_param('i', $conversionId);
    $st->execute();
    $st->close();

    // Deduct from totals
    $conn->query("UPDATE affiliates SET total_conversions = GREATEST(0, total_conversions - 1), total_earned = GREATEST(0, total_earned - $amount) WHERE id = $affId");
    return true;
}

/* ========================================================================
 *  PAYOUT
 * ======================================================================== */

function affAvailableBalance($conn, int|string $affiliateId): float
{
    $affiliateId = (int)$affiliateId;
    // Sum of approved conversions minus total paid
    $st = $conn->prepare("SELECT COALESCE(SUM(commission_amount), 0) AS total FROM affiliate_conversions WHERE affiliate_id = ? AND status = 'aprovada'");
    if (!$st) return 0.0;
    $st->bind_param('i', $affiliateId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    $approved = (float)($row['total'] ?? 0);

    $st2 = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS total FROM affiliate_payouts WHERE affiliate_id = ? AND status IN ('aprovado','pago')");
    if (!$st2) return $approved;
    $st2->bind_param('i', $affiliateId);
    $st2->execute();
    $row2 = $st2->get_result()->fetch_assoc();
    $st2->close();
    $paid = (float)($row2['total'] ?? 0);

    return max(0, round($approved - $paid, 2));
}

function affRequestPayout($conn, int|string $affiliateId): array
{
    $affiliateId = (int)$affiliateId;
    $aff = affGetById($conn, $affiliateId);
    if (!$aff) return [false, 'Afiliado não encontrado.'];
    if ($aff['status'] !== 'ativo') return [false, 'Conta de afiliado não está ativa.'];
    if (!$aff['pix_key_type'] || !$aff['pix_key']) return [false, 'Configure sua chave PIX antes de solicitar saque.'];

    $rules = affRules($conn);
    $balance = affAvailableBalance($conn, $affiliateId);

    if ($balance < $rules['min_payout']) {
        return [false, 'Saldo mínimo para saque: R$ ' . number_format($rules['min_payout'], 2, ',', '.') . '. Saldo disponível: R$ ' . number_format($balance, 2, ',', '.')];
    }

    // Check pending payout
    $stPend = $conn->prepare("SELECT id FROM affiliate_payouts WHERE affiliate_id = ? AND status = 'pendente' LIMIT 1");
    $stPend->bind_param('i', $affiliateId);
    $stPend->execute();
    $pend = $stPend->get_result()->fetch_assoc();
    $stPend->close();
    if ($pend) return [false, 'Você já possui um saque pendente.'];

    $pixType = $aff['pix_key_type'];
    $pixKey = $aff['pix_key'];

    $st = $conn->prepare('INSERT INTO affiliate_payouts (affiliate_id, amount, pix_key_type, pix_key, status) VALUES (?, ?, ?, ?, ?)');
    $payStatus = 'pendente';
    $st->bind_param('idsss', $affiliateId, $balance, $pixType, $pixKey, $payStatus);
    $st->execute();
    $st->close();

    return [true, 'Saque de R$ ' . number_format($balance, 2, ',', '.') . ' solicitado com sucesso.'];
}

function affApprovePayout($conn, int|string $payoutId, int|string $adminId): bool
{
    $payoutId = (int)$payoutId;
    $adminId = (int)$adminId;
    $st = $conn->prepare("UPDATE affiliate_payouts SET status = 'aprovado', processed_at = CURRENT_TIMESTAMP, processed_by = ? WHERE id = ? AND status = 'pendente'");
    if (!$st) return false;
    $st->bind_param('ii', $adminId, $payoutId);
    $st->execute();
    $affected = $st->affected_rows;
    $st->close();

    if ($affected > 0) {
        // Get payout details to update affiliate
        $st2 = $conn->prepare('SELECT affiliate_id, amount FROM affiliate_payouts WHERE id = ?');
        $st2->bind_param('i', $payoutId);
        $st2->execute();
        $payout = $st2->get_result()->fetch_assoc();
        $st2->close();

        if ($payout) {
            $affId = (int)$payout['affiliate_id'];
            $amount = (float)$payout['amount'];
            $conn->query("UPDATE affiliates SET total_paid = total_paid + $amount WHERE id = $affId");

            // Mark related conversions as paid
            $conn->query("UPDATE affiliate_conversions SET status = 'paga', paid_at = CURRENT_TIMESTAMP WHERE affiliate_id = $affId AND status = 'aprovada'");
        }
    }
    return $affected > 0;
}

function affRejectPayout($conn, int|string $payoutId, int|string $adminId, string $notes = ''): bool
{
    $payoutId = (int)$payoutId;
    $adminId = (int)$adminId;
    $st = $conn->prepare("UPDATE affiliate_payouts SET status = 'rejeitado', processed_at = CURRENT_TIMESTAMP, processed_by = ?, admin_notes = ? WHERE id = ? AND status = 'pendente'");
    if (!$st) return false;
    $st->bind_param('isi', $adminId, $notes, $payoutId);
    $st->execute();
    $st->close();
    return true;
}

/* ========================================================================
 *  LISTING / STATS
 * ======================================================================== */

function affListAll($conn, int $page = 1, int $perPage = 20, string $statusFilter = '', string $search = ''): array
{
    $offset = ($page - 1) * $perPage;

    $where = '1=1';
    $params = [];
    $types = '';

    if ($statusFilter) {
        $where .= ' AND a.status = ?';
        $params[] = $statusFilter;
        $types .= 's';
    }
    if ($search) {
        $where .= " AND (u.nome ILIKE ? OR u.email ILIKE ? OR a.referral_code ILIKE ?)";
        $s = "%$search%";
        $params[] = $s;
        $params[] = $s;
        $params[] = $s;
        $types .= 'sss';
    }

    // Count
    $stC = $conn->prepare("SELECT COUNT(*) AS total FROM affiliates a JOIN users u ON u.id = a.user_id WHERE $where");
    if ($types && $stC) {
        $stC->bind_param($types, ...$params);
    }
    $stC->execute();
    $total = (int)($stC->get_result()->fetch_assoc()['total'] ?? 0);
    $stC->close();

    // Fetch
    $st = $conn->prepare("SELECT a.*, u.nome, u.email, u.avatar FROM affiliates a JOIN users u ON u.id = a.user_id WHERE $where ORDER BY a.created_at DESC LIMIT $perPage OFFSET $offset");
    if ($types && $st) {
        $st->bind_param($types, ...$params);
    }
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();

    return ['items' => $rows, 'total' => $total, 'pages' => (int)ceil($total / $perPage), 'page' => $page];
}

function affListConversions($conn, int|string $affiliateId, int $page = 1, int $perPage = 20): array
{
    $affiliateId = (int)$affiliateId;
    $offset = ($page - 1) * $perPage;

    $stC = $conn->prepare('SELECT COUNT(*) AS total FROM affiliate_conversions WHERE affiliate_id = ?');
    $stC->bind_param('i', $affiliateId);
    $stC->execute();
    $total = (int)($stC->get_result()->fetch_assoc()['total'] ?? 0);
    $stC->close();

    $st = $conn->prepare("SELECT ac.*, o.status AS order_status FROM affiliate_conversions ac LEFT JOIN orders o ON o.id = ac.order_id WHERE ac.affiliate_id = ? ORDER BY ac.created_at DESC LIMIT $perPage OFFSET $offset");
    $st->bind_param('i', $affiliateId);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();

    return ['items' => $rows, 'total' => $total, 'pages' => (int)ceil($total / $perPage), 'page' => $page];
}

function affListPayouts($conn, int $affiliateId = 0, int $page = 1, int $perPage = 20, string $statusFilter = ''): array
{
    $offset = ($page - 1) * $perPage;
    $where = '1=1';
    $params = [];
    $types = '';

    if ($affiliateId > 0) {
        $where .= ' AND p.affiliate_id = ?';
        $params[] = $affiliateId;
        $types .= 'i';
    }
    if ($statusFilter) {
        $where .= ' AND p.status = ?';
        $params[] = $statusFilter;
        $types .= 's';
    }

    $stC = $conn->prepare("SELECT COUNT(*) AS total FROM affiliate_payouts p WHERE $where");
    if ($types) $stC->bind_param($types, ...$params);
    $stC->execute();
    $total = (int)($stC->get_result()->fetch_assoc()['total'] ?? 0);
    $stC->close();

    $st = $conn->prepare("SELECT p.*, a.referral_code, u.nome, u.email FROM affiliate_payouts p JOIN affiliates a ON a.id = p.affiliate_id JOIN users u ON u.id = a.user_id WHERE $where ORDER BY p.requested_at DESC LIMIT $perPage OFFSET $offset");
    if ($types) $st->bind_param($types, ...$params);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();

    return ['items' => $rows, 'total' => $total, 'pages' => (int)ceil($total / $perPage), 'page' => $page];
}

function affDashboardStats($conn, int|string $affiliateId): array
{
    $affiliateId = (int)$affiliateId;
    $aff = affGetById($conn, $affiliateId);
    if (!$aff) return [];

    // Clicks last 30d
    $st = $conn->prepare("SELECT COUNT(*) AS cnt FROM affiliate_clicks WHERE affiliate_id = ? AND created_at >= CURRENT_DATE - INTERVAL '30 days'");
    $st->bind_param('i', $affiliateId);
    $st->execute();
    $clicks30d = (int)($st->get_result()->fetch_assoc()['cnt'] ?? 0);
    $st->close();

    // Conversions last 30d
    $st2 = $conn->prepare("SELECT COUNT(*) AS cnt, COALESCE(SUM(commission_amount), 0) AS earned FROM affiliate_conversions WHERE affiliate_id = ? AND created_at >= CURRENT_DATE - INTERVAL '30 days'");
    $st2->bind_param('i', $affiliateId);
    $st2->execute();
    $conv30d = $st2->get_result()->fetch_assoc();
    $st2->close();

    // Daily clicks last 7d
    $st3 = $conn->prepare("SELECT created_at::date AS day, COUNT(*) AS cnt FROM affiliate_clicks WHERE affiliate_id = ? AND created_at >= CURRENT_DATE - INTERVAL '7 days' GROUP BY created_at::date ORDER BY day");
    $st3->bind_param('i', $affiliateId);
    $st3->execute();
    $dailyClicks = $st3->get_result()->fetch_all(MYSQLI_ASSOC);
    $st3->close();

    // Daily conversions last 7d
    $st4 = $conn->prepare("SELECT created_at::date AS day, COUNT(*) AS cnt, COALESCE(SUM(commission_amount),0) AS earned FROM affiliate_conversions WHERE affiliate_id = ? AND created_at >= CURRENT_DATE - INTERVAL '7 days' GROUP BY created_at::date ORDER BY day");
    $st4->bind_param('i', $affiliateId);
    $st4->execute();
    $dailyConvs = $st4->get_result()->fetch_all(MYSQLI_ASSOC);
    $st4->close();

    $balance = affAvailableBalance($conn, $affiliateId);

    return [
        'affiliate'       => $aff,
        'clicks_30d'      => $clicks30d,
        'conversions_30d' => (int)($conv30d['cnt'] ?? 0),
        'earned_30d'      => (float)($conv30d['earned'] ?? 0),
        'balance'         => $balance,
        'conversion_rate' => $clicks30d > 0 ? round(((int)($conv30d['cnt'] ?? 0)) / $clicks30d * 100, 1) : 0,
        'daily_clicks'    => $dailyClicks,
        'daily_conversions' => $dailyConvs,
    ];
}

function affAdminOverview($conn): array
{
    $totalAffiliates = 0;
    $activeAffiliates = 0;
    $pendingAffiliates = 0;
    $totalClicks = 0;
    $totalConversions = 0;
    $totalCommissions = 0.0;
    $pendingPayouts = 0;
    $pendingPayoutsAmount = 0.0;

    $r = $conn->query("SELECT COUNT(*) AS cnt FROM affiliates");
    if ($r) $totalAffiliates = (int)($r->fetch_assoc()['cnt'] ?? 0);

    $r = $conn->query("SELECT COUNT(*) AS cnt FROM affiliates WHERE status = 'ativo'");
    if ($r) $activeAffiliates = (int)($r->fetch_assoc()['cnt'] ?? 0);

    $r = $conn->query("SELECT COUNT(*) AS cnt FROM affiliates WHERE status = 'pendente'");
    if ($r) $pendingAffiliates = (int)($r->fetch_assoc()['cnt'] ?? 0);

    $r = $conn->query("SELECT COALESCE(SUM(total_clicks),0) AS cnt FROM affiliates");
    if ($r) $totalClicks = (int)($r->fetch_assoc()['cnt'] ?? 0);

    $r = $conn->query("SELECT COALESCE(SUM(total_conversions),0) AS cnt FROM affiliates");
    if ($r) $totalConversions = (int)($r->fetch_assoc()['cnt'] ?? 0);

    $r = $conn->query("SELECT COALESCE(SUM(total_earned),0) AS val FROM affiliates");
    if ($r) $totalCommissions = (float)($r->fetch_assoc()['val'] ?? 0);

    $r = $conn->query("SELECT COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS val FROM affiliate_payouts WHERE status = 'pendente'");
    if ($r) {
        $row = $r->fetch_assoc();
        $pendingPayouts = (int)($row['cnt'] ?? 0);
        $pendingPayoutsAmount = (float)($row['val'] ?? 0);
    }

    return compact('totalAffiliates','activeAffiliates','pendingAffiliates','totalClicks','totalConversions','totalCommissions','pendingPayouts','pendingPayoutsAmount');
}

function affTopAffiliates($conn, int $limit = 10): array
{
    $st = $conn->prepare("SELECT a.*, u.nome, u.email FROM affiliates a JOIN users u ON u.id = a.user_id WHERE a.status = 'ativo' ORDER BY a.total_earned DESC LIMIT $limit");
    if (!$st) return [];
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    return $rows;
}

/* ========================================================================
 *  REFERRAL MIDDLEWARE — call on every page load to track referrals
 * ======================================================================== */

function affHandleReferral($conn): void
{
    $ref = $_GET['ref'] ?? null;
    if (!$ref || !is_string($ref)) return;

    $ref = trim($ref);
    if (strlen($ref) < 4) return;

    affEnsureTables($conn);

    $st = $conn->prepare("SELECT * FROM affiliates WHERE referral_code = ? AND status = 'ativo' LIMIT 1");
    if (!$st) return;
    $st->bind_param('s', $ref);
    $st->execute();
    $aff = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$aff) return;

    $rules = affRules($conn);

    // Set cookie
    affSetCookie($ref, $rules['cookie_days']);

    // Track click
    $productId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    if ($productId <= 0) $productId = null;
    affTrackClick($conn, (int)$aff['id'], $productId);
}
