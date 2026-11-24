<?php

namespace App\Services\Reminders;

use App\Enums\ReminderStatus;
use App\Jobs\SendReminder;
use App\Models\Reminder;
use Carbon\CarbonImmutable;

/**
 * Pone en cola los recordatorios vencidos. Marcarlos "en cola" antes de
 * despacharlos evita que una segunda ejecución los envíe otra vez.
 */
class ReminderDispatcher
{
    public function dispatchDue(?CarbonImmutable $now = null, int $limit = 200): int
    {
        $now ??= CarbonImmutable::now();
        $count = 0;

        Reminder::query()
            ->where('status', ReminderStatus::Pending)
            ->where('scheduled_for', '<=', $now->format('Y-m-d H:i:s'))
            ->orderBy('scheduled_for')
            ->limit($limit)
            ->pluck('id')
            ->each(function (int $id) use (&$count) {
                $claimed = Reminder::query()
                    ->whereKey($id)
                    ->where('status', ReminderStatus::Pending)
                    ->update(['status' => ReminderStatus::Queued]);

                if ($claimed === 1) {
                    SendReminder::dispatch($id);
                    $count++;
                }
            });

        return $count;
    }
}
