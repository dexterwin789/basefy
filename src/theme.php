<?php
declare(strict_types=1);
/**
 * Theme Engine — manages color themes (green / blue) with light/dark mode.
 * Settings stored in platform_settings with prefix 'theme.'
 */

require_once __DIR__ . '/db.php';

/* ========================================================================
 *  THEME DEFINITIONS
 * ======================================================================== */

function themeDefinitions(): array
{
    return [
        'green' => [
            'label' => 'Verde Premium',
            'description' => 'Tema padrão do marketplace com destaque verde esmeralda.',
            'dark' => [
                'bg_body'        => '#0B0B0C',
                'bg_card'        => '#111214',
                'bg_border'      => '#1A1C20',
                'accent'         => '#22C55E',
                'accent_hover'   => '#16A34A',
                'accent_soft'    => '#22C55E26',
                'accent_rgb'     => '34,197,94',
                'gradient_from'  => '#22C55E',
                'gradient_to'    => '#059669',
                'text_on_accent' => '#000000',
                'scrollbar_hover'=> '#22C55E40',
                'pulse_rgb'      => '34,197,94',
                'text_primary'   => '#FFFFFF',
                'text_secondary' => '#A1A1AA',
                'text_muted'     => '#71717A',
            ],
            'light' => [
                'bg_body'        => '#F8FAFC',
                'bg_card'        => '#FFFFFF',
                'bg_border'      => '#D1D5DB',
                'accent'         => '#16A34A',
                'accent_hover'   => '#15803D',
                'accent_soft'    => '#16A34A1A',
                'accent_rgb'     => '22,163,74',
                'gradient_from'  => '#16A34A',
                'gradient_to'    => '#059669',
                'text_on_accent' => '#FFFFFF',
                'scrollbar_hover'=> '#16A34A60',
                'pulse_rgb'      => '22,163,74',
                'text_primary'   => '#111827',
                'text_secondary' => '#374151',
                'text_muted'     => '#6B7280',
            ],
        ],
        'blue' => [
            'label' => 'Azul GGMax',
            'description' => 'Tema moderno com destaque azul inspirado no GGMax.',
            'dark' => [
                'bg_body'        => '#0F1117',
                'bg_card'        => '#161822',
                'bg_border'      => '#1E2030',
                'accent'         => '#2196F3',
                'accent_hover'   => '#1976D2',
                'accent_soft'    => '#2196F326',
                'accent_rgb'     => '33,150,243',
                'gradient_from'  => '#2196F3',
                'gradient_to'    => '#1565C0',
                'text_on_accent' => '#FFFFFF',
                'scrollbar_hover'=> '#2196F340',
                'pulse_rgb'      => '33,150,243',
                'text_primary'   => '#FFFFFF',
                'text_secondary' => '#A1A1AA',
                'text_muted'     => '#71717A',
            ],
            'light' => [
                'bg_body'        => '#F5F7FA',
                'bg_card'        => '#FFFFFF',
                'bg_border'      => '#D1D5DB',
                'accent'         => '#1976D2',
                'accent_hover'   => '#1565C0',
                'accent_soft'    => '#1976D21A',
                'accent_rgb'     => '25,118,210',
                'gradient_from'  => '#1976D2',
                'gradient_to'    => '#1565C0',
                'text_on_accent' => '#FFFFFF',
                'scrollbar_hover'=> '#1976D260',
                'pulse_rgb'      => '25,118,210',
                'text_primary'   => '#111827',
                'text_secondary' => '#374151',
                'text_muted'     => '#6B7280',
            ],
        ],
        'basefy' => [
            'label' => 'Basefy Purple',
            'description' => 'Tema roxo moderno da Basefy com identidade premium.',
            'dark' => [
                'bg_body'        => '#0E0324',
                'bg_card'        => '#160636',
                'bg_border'      => '#221048',
                'accent'         => '#8800E4',
                'accent_hover'   => '#7200C0',
                'accent_soft'    => '#8800E426',
                'accent_rgb'     => '136,0,228',
                'gradient_from'  => '#8800E4',
                'gradient_to'    => '#6200AA',
                'text_on_accent' => '#FFFFFF',
                'scrollbar_hover'=> '#8800E440',
                'pulse_rgb'      => '136,0,228',
                'text_primary'   => '#FFFFFF',
                'text_secondary' => '#B8A8CC',
                'text_muted'     => '#7E6D94',
            ],
            'light' => [
                'bg_body'        => '#F7F6F9',
                'bg_card'        => '#FFFFFF',
                'bg_border'      => '#DBC9FF',
                'accent'         => '#8800E4',
                'accent_hover'   => '#7200C0',
                'accent_soft'    => '#8800E41A',
                'accent_rgb'     => '136,0,228',
                'gradient_from'  => '#8800E4',
                'gradient_to'    => '#6200AA',
                'text_on_accent' => '#FFFFFF',
                'scrollbar_hover'=> '#8800E460',
                'pulse_rgb'      => '136,0,228',
                'text_primary'   => '#111827',
                'text_secondary' => '#374151',
                'text_muted'     => '#6B7280',
            ],
        ],
    ];
}

/* ========================================================================
 *  SETTINGS  (stored in platform_settings, key prefix = 'theme.')
 * ======================================================================== */

function themeSettingGet($conn, string $key, string $default = ''): string
{
    $fullKey = 'theme.' . $key;
    $st = $conn->prepare("SELECT setting_value FROM platform_settings WHERE setting_key = ? LIMIT 1");
    if (!$st) return $default;
    $st->bind_param('s', $fullKey);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ? (string)$row['setting_value'] : $default;
}

function themeSettingSet($conn, string $key, string $value): void
{
    $fullKey = 'theme.' . $key;
    $st = $conn->prepare("INSERT INTO platform_settings (setting_key, setting_value) VALUES (?, ?)
                          ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value");
    if ($st) {
        $st->bind_param('ss', $fullKey, $value);
        $st->execute();
        $st->close();
    }
}

function themeGetActive($conn): array
{
    $defs = themeDefinitions();
    $activeTheme = 'basefy';
    $colorMode = 'dark';

    return [
        'active_theme' => $activeTheme,
        'color_mode'   => $colorMode,
        'colors'       => $defs[$activeTheme][$colorMode],
        'label'        => $defs[$activeTheme]['label'],
        'description'  => $defs[$activeTheme]['description'],
    ];
}

function themeGetColors($conn): array
{
    $active = themeGetActive($conn);
    return $active['colors'];
}

/**
 * Generate CSS custom properties block for the active theme.
 * Called from header.php.
 */
function themeRenderCSSVars($conn): string
{
    $active = themeGetActive($conn);
    $c = $active['colors'];
    $mode = $active['color_mode'];

    $vars = ":root {\n";
    foreach ($c as $key => $val) {
        $cssProp = '--t-' . str_replace('_', '-', $key);
        $vars .= "  {$cssProp}: {$val};\n";
    }
    // Mode indicator
    $vars .= "  --t-mode: {$mode};\n";
    $vars .= "}\n";

    return $vars;
}

/**
 * Generate Tailwind config colors object.
 */
function themeTailwindColors($conn): array
{
    $c = themeGetColors($conn);
    return [
        'blackx'  => $c['bg_body'],
        'blackx2' => $c['bg_card'],
        'blackx3' => $c['bg_border'],
        'greenx'  => $c['accent'],
        'greenx2' => $c['accent_hover'],
        'greenxd' => $c['gradient_to'],
    ];
}

/**
 * Returns theme info for admin theme page.
 */
function themeFullInfo($conn): array
{
    $defs = themeDefinitions();
    $active = themeGetActive($conn);

    $result = [];
    foreach ($defs as $key => $theme) {
        $result[$key] = [
            'key'         => $key,
            'label'       => $theme['label'],
            'description' => $theme['description'],
            'is_active'   => ($key === $active['active_theme']),
            'dark'        => $theme['dark'],
            'light'       => $theme['light'],
        ];
    }

    return [
        'themes'       => $result,
        'active_theme' => $active['active_theme'],
        'color_mode'   => 'dark',
    ];
}
