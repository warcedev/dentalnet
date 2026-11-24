<?php

namespace App\Services\Audit;

use Illuminate\Support\Facades\DB;

/**
 * Recorre la auditoría en orden y detecta registros alterados o faltantes.
 */
final class AuditChainVerifier
{
    public function __construct(private readonly AuditHasher $hasher) {}

    /**
     * @return list<array{id: int, problem: string}>
     */
    public function verify(): array
    {
        $problems = [];
        $previousHash = null;

        foreach (DB::table('audit_logs')->orderBy('id')->lazyById(500) as $row) {
            // Normaliza los tipos que devuelve cada driver (p. ej. enteros como texto).
            $data = [
                'id' => (int) $row->id,
                'user_id' => $row->user_id === null ? null : (int) $row->user_id,
                'event' => (string) $row->event,
                'auditable_type' => $row->auditable_type === null ? null : (string) $row->auditable_type,
                'auditable_id' => $row->auditable_id === null ? null : (string) $row->auditable_id,
                'old_values' => $row->old_values === null ? null : (string) $row->old_values,
                'new_values' => $row->new_values === null ? null : (string) $row->new_values,
                'ip_address' => $row->ip_address === null ? null : (string) $row->ip_address,
                'created_at' => (string) $row->created_at,
                'previous_hash' => $row->previous_hash === null ? null : (string) $row->previous_hash,
                'hash' => (string) $row->hash,
            ];

            if ($data['previous_hash'] !== $previousHash) {
                $problems[] = ['id' => $data['id'], 'problem' => 'La cadena está rota: falta o se alteró el registro anterior.'];
            }

            if (! hash_equals($this->hasher->hash($data), $data['hash'])) {
                $problems[] = ['id' => $data['id'], 'problem' => 'El contenido del registro fue modificado.'];
            }

            $previousHash = $data['hash'];
        }

        return $problems;
    }
}
