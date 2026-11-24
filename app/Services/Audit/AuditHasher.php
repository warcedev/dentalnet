<?php

namespace App\Services\Audit;

/**
 * Calcula el hash encadenado de un registro de auditoría a partir de sus
 * valores crudos tal como se guardan en la base.
 */
final class AuditHasher
{
    /**
     * @param  array{user_id: int|null, event: string, auditable_type: string|null, auditable_id: int|string|null, old_values: string|null, new_values: string|null, ip_address: string|null, created_at: string, previous_hash: string|null}  $row
     */
    public function hash(array $row): string
    {
        $payload = implode("\x1F", [
            $row['previous_hash'] ?? '',
            (string) ($row['user_id'] ?? ''),
            $row['event'],
            $row['auditable_type'] ?? '',
            (string) ($row['auditable_id'] ?? ''),
            $row['old_values'] ?? '',
            $row['new_values'] ?? '',
            $row['ip_address'] ?? '',
            $row['created_at'],
        ]);

        return hash_hmac('sha256', $payload, (string) config('app.key'));
    }
}
