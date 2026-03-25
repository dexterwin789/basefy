<?php
declare(strict_types=1);

if (!function_exists('uploads_env')) {
    function uploads_env(string $key, string $default = ''): string
    {
        if (function_exists('envValue')) {
            return (string)envValue($key, $default);
        }
        $v = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($v === false || $v === null || $v === '') {
            return $default;
        }
        return (string)$v;
    }
}

if (!function_exists('uploadsBaseDiskPath')) {
    function uploadsBaseDiskPath(): string
    {
        $configured = trim(uploads_env('UPLOADS_DISK_PATH', ''));
        $base = $configured !== '' ? $configured : (dirname(__DIR__) . '/public/uploads');
        return rtrim(str_replace('\\', '/', $base), '/');
    }
}

if (!function_exists('uploadsBaseUrl')) {
    function uploadsBaseUrl(): string
    {
        $configured = trim(uploads_env('UPLOADS_BASE_URL', ''));
        if ($configured !== '') {
            return '/' . trim(str_replace('\\', '/', $configured), '/');
        }
        return BASE_PATH . '/uploads';
    }
}

if (!function_exists('uploadsEnsureSubdir')) {
    function uploadsEnsureSubdir(string $subdir): string
    {
        $subdir = trim(str_replace('\\', '/', $subdir), '/');
        $dir = uploadsBaseDiskPath() . ($subdir !== '' ? '/' . $subdir : '');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }
}

if (!function_exists('uploadsPublicUrl')) {
    function uploadsPublicUrl(string $raw, string $default = ''): string
    {
        $raw = trim(str_replace('\\', '/', $raw));
        if ($raw === '') {
            return $default;
        }
        if (preg_match('~^https?://~i', $raw)) {
            return $raw;
        }
        // Database media reference: 'media:123'
        if (str_starts_with($raw, 'media:')) {
            if (function_exists('mediaUrl')) {
                return mediaUrl((int)substr($raw, 6));
            }
            return $default;
        }

        $baseUrl = rtrim(uploadsBaseUrl(), '/');

        if (str_starts_with($raw, $baseUrl . '/')) {
            return $raw;
        }

        if (str_starts_with($raw, '/uploads/')) {
            return $baseUrl . '/' . ltrim(substr($raw, strlen('/uploads/')), '/');
        }

        if (str_starts_with($raw, 'uploads/')) {
            return $baseUrl . '/' . ltrim(substr($raw, strlen('uploads/')), '/');
        }

        if (str_starts_with($raw, 'public/uploads/')) {
            return $baseUrl . '/' . ltrim(substr($raw, strlen('public/uploads/')), '/');
        }

        if (preg_match('~^/.*/uploads/(.+)$~i', $raw, $m)) {
            return $baseUrl . '/' . ltrim((string)$m[1], '/');
        }

        return $baseUrl . '/' . ltrim($raw, '/');
    }
}
