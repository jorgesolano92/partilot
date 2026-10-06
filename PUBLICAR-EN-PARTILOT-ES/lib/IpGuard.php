<?php

declare(strict_types=1);

final class IpGuard
{
    private \PDO $db;

    /** @var list<int> */
    private array $blockSteps;

    public function __construct(string $dataDir, array $blockSteps = [60, 300, 600])
    {
        $this->blockSteps = $blockSteps;
        $path = rtrim($dataDir, '/\\') . '/ip_blocks.sqlite';
        $this->db = new \PDO('sqlite:' . $path);
        $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS ip_blocks (
                ip TEXT PRIMARY KEY,
                fail_count INTEGER NOT NULL DEFAULT 0,
                blocked_until INTEGER NULL,
                permanent INTEGER NOT NULL DEFAULT 0,
                updated_at INTEGER NOT NULL
            )'
        );
    }

    public function assertAllowed(string $ip): ?string
    {
        $row = $this->getRow($ip);
        if (! $row) {
            return null;
        }

        if ((int) $row['permanent'] === 1) {
            return 'Acceso bloqueado de forma permanente por demasiados intentos incorrectos.';
        }

        $until = $row['blocked_until'] !== null ? (int) $row['blocked_until'] : 0;
        if ($until > time()) {
            $mins = max(1, (int) ceil(($until - time()) / 60));

            return 'Demasiados intentos incorrectos. Vuelve a intentarlo en unos ' . $mins . ' minuto(s).';
        }

        return null;
    }

    public function recordFailure(string $ip): void
    {
        $row = $this->getRow($ip);
        $now = time();
        $failCount = $row ? ((int) $row['fail_count']) + 1 : 1;

        if ($failCount > count($this->blockSteps)) {
            $this->upsert($ip, $failCount, null, 1, $now);

            return;
        }

        $stepIndex = $failCount - 1;
        $seconds = $this->blockSteps[$stepIndex] ?? end($this->blockSteps);
        $blockedUntil = $now + (int) $seconds;
        $this->upsert($ip, $failCount, $blockedUntil, 0, $now);
    }

    public function recordSuccess(string $ip): void
    {
        // QR válido: no penalizar IP
    }

    private function getRow(string $ip): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM ip_blocks WHERE ip = :ip LIMIT 1');
        $stmt->execute(['ip' => $ip]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function upsert(string $ip, int $failCount, ?int $blockedUntil, int $permanent, int $updatedAt): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO ip_blocks (ip, fail_count, blocked_until, permanent, updated_at)
             VALUES (:ip, :fail_count, :blocked_until, :permanent, :updated_at)
             ON CONFLICT(ip) DO UPDATE SET
                fail_count = excluded.fail_count,
                blocked_until = excluded.blocked_until,
                permanent = excluded.permanent,
                updated_at = excluded.updated_at'
        );
        $stmt->execute([
            'ip' => $ip,
            'fail_count' => $failCount,
            'blocked_until' => $blockedUntil,
            'permanent' => $permanent,
            'updated_at' => $updatedAt,
        ]);
    }
}
