<?php

namespace App\Actions\Agenda;

use App\Models\Appointment;
use App\Models\ScheduleBlock;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class CreateScheduleBlock
{
    public function handle(User $actor, CarbonImmutable $start, CarbonImmutable $end, ?int $professionalId, ?int $roomId, string $reason): ScheduleBlock
    {
        return BookingLock::run(function () use ($actor, $start, $end, $professionalId, $roomId, $reason) {
            // No se bloquea un horario que ya tiene citas: hay que reprogramarlas antes.
            $clashes = Appointment::query()
                ->overlapping($start, $end)
                ->when($professionalId !== null || $roomId !== null, fn ($q) => $q->where(fn ($w) => $w
                    ->when($professionalId, fn ($p, $id) => $p->orWhere('professional_id', $id))
                    ->when($roomId, fn ($r, $id) => $r->orWhere('room_id', $id))))
                ->count();

            if ($clashes > 0) {
                throw ValidationException::withMessages([
                    'starts_at' => $clashes === 1
                        ? 'Hay 1 cita en ese horario. Reprográmela o cancélela antes de bloquear.'
                        : "Hay {$clashes} citas en ese horario. Reprográmelas o cancélelas antes de bloquear.",
                ]);
            }

            return ScheduleBlock::create([
                'professional_id' => $professionalId,
                'room_id' => $roomId,
                'starts_at' => $start,
                'ends_at' => $end,
                'reason' => $reason,
                'created_by' => $actor->id,
            ]);
        });
    }
}
