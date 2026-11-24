<?php

namespace App\Services\Reminders;

use App\Enums\AppointmentStatus;
use App\Enums\ReminderChannelType;
use App\Enums\ReminderStatus;
use App\Enums\ReminderType;
use App\Enums\ToothCondition;
use App\Enums\ToothEventStatus;
use App\Enums\TreatmentStatus;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Reminder;
use App\Models\ToothEvent;
use App\Models\TreatmentItem;
use App\Services\Odontogram\OdontogramInsights;
use App\Services\Odontogram\OdontogramState;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Decide qué recordatorios corresponden y los crea una sola vez (clave de
 * idempotencia). No envía nada: el envío lo hace ReminderDispatcher.
 */
class ReminderPlanner
{
    public function __construct(
        private readonly OdontogramState $state,
        private readonly OdontogramInsights $insights,
    ) {}

    /**
     * Ajusta los recordatorios de una cita tras crearla, reprogramarla o cambiar su estado.
     */
    public function syncForAppointment(Appointment $appointment): void
    {
        $appointment->loadMissing('patient');
        $stamp = $appointment->starts_at->format('YmdHi');
        $active = $appointment->status->occupiesSlot() && ! $appointment->status->isFinal() && $appointment->status !== AppointmentStatus::InProgress;

        // Cancela los pendientes que ya no corresponden (otra fecha o cita inactiva).
        $appointment->reminders()
            ->whereIn('status', [ReminderStatus::Pending, ReminderStatus::Queued])
            ->get()
            ->filter(fn (Reminder $r) => ! $active || ! str_ends_with($r->idempotency_key, ':'.$stamp))
            ->each(fn (Reminder $r) => $r->update(['status' => ReminderStatus::Cancelled, 'last_error' => 'La cita cambió o fue cancelada.']));

        if (! $active || ! $appointment->patient->accepts_communications) {
            return;
        }

        $now = CarbonImmutable::now();
        $upcomingAt = $appointment->starts_at->subDays((int) config('clinic.reminders.upcoming_days_before'));
        $confirmAt = $appointment->starts_at->subHours((int) config('clinic.reminders.confirmation_hours_before'));

        // Solo si todavía falta: si la cita se agendó con poca anticipación no tiene sentido.
        if ($upcomingAt->isAfter($now) && $upcomingAt->lt($confirmAt)) {
            $this->create($appointment->patient, ReminderType::UpcomingAppointment, $upcomingAt, "proxima_cita:{$appointment->id}:{$stamp}", $appointment);
        }

        if ($confirmAt->isAfter($now)) {
            $this->create($appointment->patient, ReminderType::Confirmation, $confirmAt, "confirmacion_24h:{$appointment->id}:{$stamp}", $appointment);
        }
    }

    /**
     * Recordatorios que no dependen de una cita: limpieza, controles y cumpleaños.
     * Se puede ejecutar muchas veces: nunca duplica.
     *
     * @return array{cleaning: int, control: int, birthday: int, warranty: int}
     */
    public function planRecurring(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();

        return [
            'cleaning' => $this->planCleanings($now),
            'control' => $this->planControls($now),
            'birthday' => $this->planBirthdays($now),
            'warranty' => $this->planWarranties($now),
        ];
    }

    /** Regla de negocio 5: la limpieza se calcula desde la última registrada. */
    private function planCleanings(CarbonImmutable $now): int
    {
        $created = 0;
        $daysBefore = (int) config('clinic.reminders.cleaning_days_before');

        $byPatient = ToothEvent::query()
            ->where('condition', ToothCondition::Cleaning)
            ->whereIn('patient_id', Patient::query()->where('accepts_communications', true)->select('id'))
            ->get()
            ->groupBy('patient_id');

        foreach ($byPatient as $patientId => $events) {
            /** @var Collection<int, ToothEvent> $events */
            $next = $this->insights->nextCleaning($events);

            if ($next === null || $now->lt($next->subDays($daysBefore)->startOfDay())) {
                continue;
            }

            $hasCleaningBooked = Appointment::query()
                ->where('patient_id', $patientId)
                ->where('starts_at', '>=', $now->format('Y-m-d H:i:s'))
                ->whereIn('status', array_map(fn ($s) => $s->value, AppointmentStatus::occupying()))
                ->whereHas('type', fn ($t) => $t->where('is_cleaning', true))
                ->exists();

            if ($hasCleaningBooked) {
                continue;
            }

            $patient = Patient::query()->whereKey($patientId)->first();

            if ($patient !== null && $this->create($patient, ReminderType::Cleaning, $this->sendTime($next->subDays($daysBefore), $now), "limpieza:{$patientId}:{$next->toDateString()}")) {
                $created++;
            }
        }

        return $created;
    }

    /** Controles de registros del odontograma que siguen "en seguimiento". */
    private function planControls(CarbonImmutable $now): int
    {
        $created = 0;
        $afterDays = (int) config('clinic.reminders.control_after_days');

        $patientIds = ToothEvent::query()
            ->where('status', ToothEventStatus::FollowUp)
            ->whereIn('patient_id', Patient::query()->where('accepts_communications', true)->select('id'))
            ->distinct()
            ->pluck('patient_id');

        foreach ($patientIds as $patientId) {
            $events = ToothEvent::query()->where('patient_id', $patientId)->get();
            $chart = $this->state->at($events, $now);

            foreach ($chart as $tooth) {
                foreach ([...$tooth['tooth'], ...array_values($tooth['surfaces'])] as $entry) {
                    if ($entry['status'] !== ToothEventStatus::FollowUp->value) {
                        continue;
                    }

                    $due = CarbonImmutable::parse($entry['date'])->addDays($afterDays);

                    if ($now->lt($due->startOfDay())) {
                        continue;
                    }

                    $patient = Patient::query()->whereKey($patientId)->first();

                    if ($patient !== null && $this->create($patient, ReminderType::TreatmentControl, $this->sendTime($due, $now), "control:{$entry['id']}")) {
                        $created++;
                    }
                }
            }
        }

        return $created;
    }

    /** Control antes de que venza la garantía de un tratamiento completado. */
    private function planWarranties(CarbonImmutable $now): int
    {
        $created = 0;
        $daysBefore = (int) config('clinic.reminders.warranty_days_before');

        TreatmentItem::query()
            ->with(['adjustments', 'patient'])
            ->where('status', TreatmentStatus::Completed)
            ->whereNotNull('completed_on')
            ->whereHas('patient', fn ($q) => $q->where('accepts_communications', true))
            ->each(function (TreatmentItem $item) use ($now, $daysBefore, &$created) {
                $until = $item->warrantyUntil();

                if ($until === null || $until->lt($now->startOfDay()) || $now->lt($until->subDays($daysBefore)->startOfDay())) {
                    return;
                }

                $patient = Patient::query()->whereKey($item->patient_id)->first();

                if ($patient !== null && $this->create($patient, ReminderType::WarrantyControl, $this->sendTime($until->subDays($daysBefore), $now), "garantia:{$item->id}:{$until->toDateString()}")) {
                    $created++;
                }
            });

        return $created;
    }

    private function planBirthdays(CarbonImmutable $now): int
    {
        $created = 0;

        Patient::query()
            ->where('accepts_communications', true)
            ->whereMonth('birth_date', $now->month)
            ->whereDay('birth_date', $now->day)
            ->each(function (Patient $patient) use ($now, &$created) {
                if ($this->create($patient, ReminderType::Birthday, $this->sendTime($now, $now), "cumpleanos:{$patient->id}:{$now->year}")) {
                    $created++;
                }
            });

        return $created;
    }

    /** Envío a la hora configurada del día indicado, o ya mismo si esa hora pasó. */
    private function sendTime(CarbonImmutable $day, CarbonImmutable $now): CarbonImmutable
    {
        $at = $day->startOfDay()->setTime((int) config('clinic.reminders.send_hour'), 0);

        return $at->lt($now) ? $now : $at;
    }

    /** Crea el recordatorio si no existe. Devuelve true si lo creó. */
    private function create(Patient $patient, ReminderType $type, CarbonImmutable $when, string $key, ?Model $remindable = null): bool
    {
        $reminder = Reminder::query()->firstOrCreate(['idempotency_key' => $key], [
            'patient_id' => $patient->id,
            'type' => $type,
            'channel' => ReminderChannelType::from((string) config('clinic.reminders.channel')),
            'remindable_type' => $remindable?->getMorphClass(),
            'remindable_id' => $remindable?->getKey(),
            'scheduled_for' => $when,
            'status' => ReminderStatus::Pending,
        ]);

        return $reminder->wasRecentlyCreated;
    }
}
