<?php
// filepath: c:\xampp\htdocs\mercado_admin\src\config.php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

function envValue(string $key, mixed $default = null): mixed
{
	$value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
	if ($value === false || $value === null || $value === '') {
		return $default;
	}
	return $value;
}

function envBool(string $key, bool $default): bool
{
	$value = envValue($key, null);
	if ($value === null) {
		return $default;
	}
	return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
}

function resolveDbDriver(): string
{
	$fromEnv = strtolower(trim((string)envValue('DB_DRIVER', envValue('DB_CONNECTION', 'pgsql'))));
	if (in_array($fromEnv, ['pgsql', 'postgres', 'postgresql'], true)) {
		return 'pgsql';
	}

	return 'pgsql';
}

$dbDriver = resolveDbDriver();

if (!defined('DB_DRIVER')) {
	define('DB_DRIVER', $dbDriver);
}

if (!defined('DB_HOST')) {
	define('DB_HOST', (string)envValue('DB_HOST', envValue('PGHOST', envValue('POSTGRES_HOST', envValue('PG_HOST', '127.0.0.1')))));
}
if (!defined('DB_PORT')) {
	define('DB_PORT', (int)envValue('DB_PORT', envValue('PGPORT', envValue('POSTGRES_PORT', 5432))));
}
if (!defined('DB_NAME')) {
	define('DB_NAME', (string)envValue('DB_DATABASE', envValue('PGDATABASE', envValue('POSTGRES_DB', envValue('PG_DB', envValue('DB_NAME', 'railway'))))));
}
if (!defined('DB_USER')) {
	define('DB_USER', (string)envValue('DB_USERNAME', envValue('PGUSER', envValue('POSTGRES_USER', envValue('PG_USER', envValue('DB_USER', 'postgres'))))));
}
if (!defined('DB_PASS')) {
	define('DB_PASS', (string)envValue('DB_PASSWORD', envValue('PGPASSWORD', envValue('POSTGRES_PASSWORD', envValue('PG_PASS', envValue('DB_PASS', ''))))));
}

if (!defined('APP_NAME')) {
	define('APP_NAME', (string)envValue('APP_NAME', 'Basefy'));
}
if (!defined('APP_URL')) {
	define('APP_URL', (string)envValue('APP_URL', 'http://localhost'));
}

if (!defined('BASE_PATH')) {
	define('BASE_PATH', rtrim((string)parse_url(APP_URL, PHP_URL_PATH), '/'));
}

if (!defined('SESSION_LIFETIME')) {
	define('SESSION_LIFETIME', (int)envValue('SESSION_LIFETIME', 21600)); // 6 horas
}

if (!function_exists('applySessionLifetime')) {
	function applySessionLifetime(): void
	{
		$lifetime = max(1800, (int)SESSION_LIFETIME);

		@ini_set('session.gc_maxlifetime', (string)$lifetime);
		@ini_set('session.cookie_lifetime', '0');  // browser-session cookie (expires when browser closes)
		@ini_set('session.use_strict_mode', '1');

		if (session_status() === PHP_SESSION_NONE) {
			$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
				|| (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');

			session_set_cookie_params([
				'lifetime' => 0,  // browser-session cookie
				'path' => '/',
				'domain' => '',
				'secure' => $isHttps,
				'httponly' => true,
				'samesite' => 'Lax',
			]);
		}
	}
}

if (!defined('BLACKCAT_BASE_URL')) {
	define('BLACKCAT_BASE_URL', (string)envValue('BLACKCAT_BASE_URL', 'https://api.blackcatpagamentos.online/api'));
}
if (!defined('BLACKCAT_API_KEY')) {
	define('BLACKCAT_API_KEY', (string)envValue('BLACKCAT_API_KEY', ''));
}
if (!defined('WALLET_ESCROW_ENABLED')) {
	define('WALLET_ESCROW_ENABLED', envBool('WALLET_ESCROW_ENABLED', true));
}

/**
 * Format a database timestamp, stripping microseconds.
 * Returns 'd/m/Y H:i' by default.
 */
if (!function_exists('fmtDate')) {
	function fmtDate(?string $dt, string $fmt = 'd/m/Y H:i'): string
	{
		if ($dt === null || $dt === '' || $dt === '-') return $dt ?? '-';
		$ts = strtotime($dt);
		return $ts !== false ? date($fmt, $ts) : $dt;
	}
}

// Register DB-backed session handler so sessions survive Railway restarts
require_once __DIR__ . '/session.php';
registerDbSessionHandler();