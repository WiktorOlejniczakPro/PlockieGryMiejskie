<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Request;

/**
 * Dziennik ważnych operacji (tabela audit_logs).
 * Nigdy nie zapisuj tu haseł, tokenów ani pełnych danych formularzy.
 * Błąd zapisu do dziennika nie przerywa właściwej operacji.
 */
final class AuditLog
{
    public static function record(string $action, ?int $userId = null, ?string $entityType = null,
                                  ?int $entityId = null, array $details = []): void
    {
        try {
            Database::query(
                'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address, user_agent, details)
                 VALUES (?, ?, ?, ?, INET6_ATON(?), ?, ?)',
                [
                    $userId, $action, $entityType, $entityId,
                    PHP_SAPI === 'cli' ? '127.0.0.1' : Request::ip(),
                    PHP_SAPI === 'cli' ? 'cli' : Request::userAgent(),
                    $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
                ]
            );
        } catch (\Throwable $e) {
            error_log('[PGM] Nie udało się zapisać audit_logs: ' . $e->getMessage());
        }
    }
}
