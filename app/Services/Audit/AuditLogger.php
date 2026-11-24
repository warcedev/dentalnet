<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Único punto de escritura de la auditoría.
 */
class AuditLogger
{
    public function __construct(private readonly AuditHasher $hasher) {}

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function record(
        string $event,
        ?Model $auditable = null,
        ?array $old = null,
        ?array $new = null,
        ?int $userId = null,
    ): AuditLog {
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        $actor = auth()->guard('web')->user();
        $auditableKey = $auditable?->getKey();

        $row = [
            'user_id' => $userId ?? ($actor instanceof User ? $actor->id : null),
            'event' => $event,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => is_int($auditableKey) || is_string($auditableKey) ? $auditableKey : null,
            'old_values' => $this->encode($old),
            'new_values' => $this->encode($new),
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 250, '') : null,
            'route' => $request?->route()?->getName(),
            'created_at' => now()->format('Y-m-d H:i:s'),
        ];

        // El candado evita que dos procesos encadenen sobre el mismo hash anterior.
        return Cache::lock('audit-log-chain', 10)->block(10, function () use ($row) {
            return DB::transaction(function () use ($row) {
                $row['previous_hash'] = AuditLog::query()->latest('id')->value('hash');
                $row['hash'] = $this->hasher->hash($row);

                $id = DB::table('audit_logs')->insertGetId($row);

                return AuditLog::query()->findOrFail($id);
            });
        });
    }

    /** @param  array<string, mixed>|null  $values */
    private function encode(?array $values): ?string
    {
        if ($values === null || $values === []) {
            return null;
        }

        $normalized = array_map(fn ($value) => match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
            default => $value,
        }, $values);

        return json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
