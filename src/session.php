<?php
declare(strict_types=1);

/**
 * Database-backed session handler for PostgreSQL.
 * Prevents session loss on Railway server restarts (ephemeral filesystem).
 *
 * Table auto-created on first use:
 *   sessions(id VARCHAR(128) PK, data TEXT, last_activity INTEGER)
 */
final class DbSessionHandler implements SessionHandlerInterface
{
    private ?PDO $pdo = null;

    private function getPdo(): PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', DB_HOST, DB_PORT, DB_NAME);
        $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return $this->pdo;
    }

    public function open(string $path, string $name): bool
    {
        try {
            $this->getPdo()->exec("
                CREATE TABLE IF NOT EXISTS sessions (
                    id            VARCHAR(128) NOT NULL PRIMARY KEY,
                    data          TEXT         NOT NULL DEFAULT '',
                    last_activity INTEGER      NOT NULL DEFAULT 0
                );
                CREATE INDEX IF NOT EXISTS idx_sessions_last_activity
                    ON sessions(last_activity);
            ");
            return true;
        } catch (\Throwable $e) {
            error_log('[DbSessionHandler::open] ' . $e->getMessage());
            return false;
        }
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        try {
            $stmt = $this->getPdo()->prepare(
                'SELECT data FROM sessions WHERE id = $1 LIMIT 1'
            );
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            return $row ? (string)$row['data'] : '';
        } catch (\Throwable $e) {
            error_log('[DbSessionHandler::read] ' . $e->getMessage());
            return '';
        }
    }

    public function write(string $id, string $data): bool
    {
        try {
            $stmt = $this->getPdo()->prepare('
                INSERT INTO sessions (id, data, last_activity)
                VALUES ($1, $2, $3)
                ON CONFLICT (id) DO UPDATE
                    SET data          = EXCLUDED.data,
                        last_activity = EXCLUDED.last_activity
            ');
            $stmt->execute([$id, $data, time()]);
            return true;
        } catch (\Throwable $e) {
            error_log('[DbSessionHandler::write] ' . $e->getMessage());
            return false;
        }
    }

    public function destroy(string $id): bool
    {
        try {
            $stmt = $this->getPdo()->prepare(
                'DELETE FROM sessions WHERE id = $1'
            );
            $stmt->execute([$id]);
            return true;
        } catch (\Throwable $e) {
            error_log('[DbSessionHandler::destroy] ' . $e->getMessage());
            return false;
        }
    }

    public function gc(int $max_lifetime): int|false
    {
        try {
            $cutoff = time() - $max_lifetime;
            $stmt   = $this->getPdo()->prepare(
                'DELETE FROM sessions WHERE last_activity < $1'
            );
            $stmt->execute([$cutoff]);
            return $stmt->rowCount();
        } catch (\Throwable $e) {
            error_log('[DbSessionHandler::gc] ' . $e->getMessage());
            return false;
        }
    }
}

/**
 * Register the DB session handler once.
 * Must be called before any session_start().
 */
function registerDbSessionHandler(): void
{
    static $registered = false;
    if ($registered || session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_save_handler(new DbSessionHandler(), true);
    $registered = true;
}
