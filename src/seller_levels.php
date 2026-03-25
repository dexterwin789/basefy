<?php
// filepath: c:\xampp\htdocs\mercado_admin\src\seller_levels.php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/wallet_escrow.php';

/* ════════════════════════════════════════════════════════════
   SELLER LEVELS — tiered fee system based on seller revenue
   ════════════════════════════════════════════════════════════
   Nível 1 (default):  14.99%  (0 – threshold2)
   Nível 2:            12.99%  (threshold2 – threshold3)
   Nível 3:             9.99%  (threshold3+)
   Lead fee (fixed):    4.99%  (always added on top)
   ──────────────────────────────────────────────────────── */

function sellerLevelsDefaults(): array
{
    return [
        'taxas.enabled'           => '1',
        'taxas.nivel1_percent'    => '14.99',
        'taxas.nivel2_percent'    => '12.99',
        'taxas.nivel2_threshold'  => '20000.00',
        'taxas.nivel3_percent'    => '9.99',
        'taxas.nivel3_threshold'  => '40000.00',
        'taxas.lead_fee_percent'  => '4.99',
    ];
}

/**
 * Ensure all seller-level keys exist in platform_settings.
 */
function sellerLevelsEnsure($conn): void
{
    foreach (sellerLevelsDefaults() as $key => $default) {
        $current = escrowSettingGet($conn, $key, '');
        if ($current === '') {
            escrowSettingSet($conn, $key, $default);
        }
    }
}

/**
 * Fetch the full level/tax config from DB.
 */
function sellerLevelsConfig($conn): array
{
    sellerLevelsEnsure($conn);

    return [
        'enabled'          => escrowSettingGet($conn, 'taxas.enabled', '1') === '1',
        'nivel1_percent'   => max(0.0, (float)escrowSettingGet($conn, 'taxas.nivel1_percent', '14.99')),
        'nivel2_percent'   => max(0.0, (float)escrowSettingGet($conn, 'taxas.nivel2_percent', '12.99')),
        'nivel2_threshold' => max(0.0, (float)escrowSettingGet($conn, 'taxas.nivel2_threshold', '20000.00')),
        'nivel3_percent'   => max(0.0, (float)escrowSettingGet($conn, 'taxas.nivel3_percent', '9.99')),
        'nivel3_threshold' => max(0.0, (float)escrowSettingGet($conn, 'taxas.nivel3_threshold', '40000.00')),
        'lead_fee_percent' => max(0.0, (float)escrowSettingGet($conn, 'taxas.lead_fee_percent', '4.99')),
    ];
}

/**
 * Calculate total approved revenue for a seller.
 */
function sellerTotalRevenue($conn, int $vendorId): float
{
    $sql = "SELECT COALESCE(SUM(subtotal), 0) AS total
            FROM order_items
            WHERE vendedor_id = ?
              AND moderation_status = 'aprovada'";
    $st = $conn->prepare($sql);
    $st->bind_param('i', $vendorId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return (float)($row['total'] ?? 0);
}

/**
 * Determine the seller's current level (1, 2 or 3) and associated info.
 *
 * @return array{level: int, label: string, fee_percent: float, lead_fee_percent: float, total_fee_percent: float, revenue: float, next_threshold: float|null}
 */
function sellerLevelCalc($conn, int $vendorId): array
{
    $cfg = sellerLevelsConfig($conn);

    if (!$cfg['enabled']) {
        // Levels disabled — use Nível 1 rate, lead fee moved to buyer
        return [
            'level'             => 1,
            'label'             => 'Nível 1 (padrão)',
            'fee_percent'       => $cfg['nivel1_percent'],
            'lead_fee_percent'  => 0.0,
            'total_fee_percent' => $cfg['nivel1_percent'],
            'revenue'           => 0.0,
            'next_threshold'    => null,
        ];
    }

    $revenue = sellerTotalRevenue($conn, $vendorId);

    if ($revenue >= $cfg['nivel3_threshold']) {
        $level   = 3;
        $label   = 'Nível 3';
        $feePct  = $cfg['nivel3_percent'];
        $nextThr = null;
    } elseif ($revenue >= $cfg['nivel2_threshold']) {
        $level   = 2;
        $label   = 'Nível 2';
        $feePct  = $cfg['nivel2_percent'];
        $nextThr = $cfg['nivel3_threshold'];
    } else {
        $level   = 1;
        $label   = 'Nível 1';
        $feePct  = $cfg['nivel1_percent'];
        $nextThr = $cfg['nivel2_threshold'];
    }

    // Lead fee was moved to the buyer (service fee at checkout).
    // Sellers only pay their tier-based fee now.
    $leadFee  = 0.0;
    $totalFee = round($feePct + $leadFee, 2);

    return [
        'level'             => $level,
        'label'             => $label,
        'fee_percent'       => $feePct,
        'lead_fee_percent'  => $leadFee,
        'total_fee_percent' => $totalFee,
        'revenue'           => $revenue,
        'next_threshold'    => $nextThr,
    ];
}

/**
 * Calculate fee amounts for a given gross value using the seller's level.
 *
 * @return array{fee_percent: float, lead_fee_percent: float, total_fee_percent: float, fee_amount: float, lead_fee_amount: float, total_fee_amount: float, net_amount: float, level: int, label: string}
 */
function sellerFeeCalc($conn, int $vendorId, float $gross): array
{
    $info = sellerLevelCalc($conn, $vendorId);

    $levelFeeAmount = round($gross * ($info['fee_percent'] / 100), 2);
    $leadFeeAmount  = round($gross * ($info['lead_fee_percent'] / 100), 2);
    $totalFeeAmount = round($levelFeeAmount + $leadFeeAmount, 2);

    // Clamp
    if ($totalFeeAmount < 0) $totalFeeAmount = 0;
    if ($totalFeeAmount > $gross) $totalFeeAmount = $gross;

    $netAmount = round($gross - $totalFeeAmount, 2);

    return [
        'fee_percent'       => $info['fee_percent'],
        'lead_fee_percent'  => $info['lead_fee_percent'],
        'total_fee_percent' => $info['total_fee_percent'],
        'fee_amount'        => $levelFeeAmount,
        'lead_fee_amount'   => $leadFeeAmount,
        'total_fee_amount'  => $totalFeeAmount,
        'net_amount'        => $netAmount,
        'level'             => $info['level'],
        'label'             => $info['label'],
    ];
}

/**
 * Get the buyer service fee percentage (the former lead_fee moved to buyer).
 */
function buyerServiceFeePercent($conn): float
{
    $cfg = sellerLevelsConfig($conn);
    return max(0.0, $cfg['lead_fee_percent']);
}
