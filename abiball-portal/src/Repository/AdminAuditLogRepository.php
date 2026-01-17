<?php
declare(strict_types=1);

// src/Repository/AdminAuditLogRepository.php

require_once __DIR__ . '/../Auth/AdminContext.php';
require_once __DIR__ . '/../Http/Request.php';

final class AdminAuditLogRepository
{
    private static function filePath(): string
    {
        $path = __DIR__ . '/../../storage/logs/admin_audit.log';
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $path;
    }

    /** @param array<string,mixed> $data */
    public static function append(string $action, array $data = []): void
    {
        $adminId = null;
        if (method_exists(AdminContext::class, 'adminId')) {
            try { $adminId = AdminContext::adminId(); } catch (Throwable $e) { $adminId = null; }
        }

        $entry = [
            'ts' => (new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin')))->format(DateTimeInterface::ATOM),
            'admin' => [
                'id' => $adminId,
                'name' => AdminContext::adminName(),
            ],
            'ip' => Request::ip(),
            'action' => $action,
            'data' => $data,
        ];

        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($line === false) return;

        @file_put_contents(self::filePath(), $line . "\n", FILE_APPEND | LOCK_EX);
    }

    /** @return array<int, array<string,mixed>> */
    public static function latest(int $limit = 200): array
    {
        $file = self::filePath();
        if (!is_file($file)) return [];

        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines) || $lines === []) return [];

        $lines = array_slice($lines, -max(1, $limit));
        $out = [];

        foreach ($lines as $ln) {
            $row = json_decode((string)$ln, true);
            if (is_array($row)) $out[] = $row;
        }

        return array_reverse($out); // newest first
    }
}
