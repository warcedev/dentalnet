<?php

namespace App\Jobs;

use App\Enums\ReminderStatus;
use App\Models\Appointment;
use App\Models\Reminder;
use App\Services\Reminders\Channels\ChannelRegistry;
use App\Services\Reminders\ReminderComposer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Envía un recordatorio y registra el intento. Si falla, lo devuelve a
 * "pendiente" con una espera creciente; tras el máximo de intentos queda
 * "fallido" y se ve en la pantalla de recordatorios para reintentarlo.
 */
class SendReminder implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $reminderId) {}

    public function handle(ChannelRegistry $channels, ReminderComposer $composer): void
    {
        $reminder = Reminder::query()->with(['patient', 'remindable'])->find($this->reminderId);

        if ($reminder === null || ! in_array($reminder->status, [ReminderStatus::Queued, ReminderStatus::Pending], true)) {
            return;
        }

        $attempt = $reminder->attempts + 1;

        if ($reason = $this->skipReason($reminder)) {
            $this->finish($reminder, $attempt, ReminderStatus::Skipped, $reason);

            return;
        }

        $channel = $channels->for($reminder->channel);

        if ($reason = $channel->unavailableReason($reminder)) {
            $this->finish($reminder, $attempt, ReminderStatus::Skipped, $reason);

            return;
        }

        try {
            $channel->send($reminder, $composer->compose($reminder));
        } catch (Throwable $e) {
            $this->fail($reminder, $attempt, $e);

            return;
        }

        $reminder->update(['sent_at' => now()]);
        $this->finish($reminder, $attempt, ReminderStatus::Sent, 'Enviado.');
    }

    /** Motivos para no enviar aunque el recordatorio esté pendiente. */
    private function skipReason(Reminder $reminder): ?string
    {
        if ($reminder->patient->trashed()) {
            return 'El paciente está archivado.';
        }

        if (! $reminder->patient->accepts_communications) {
            return 'El paciente no acepta comunicaciones.';
        }

        if ($reminder->remindable instanceof Appointment) {
            $appointment = $reminder->remindable;

            if (! $appointment->status->occupiesSlot() || $appointment->status->isFinal()) {
                return 'La cita ya no está activa.';
            }

            if ($appointment->starts_at->isPast()) {
                return 'La cita ya pasó.';
            }
        }

        return null;
    }

    private function fail(Reminder $reminder, int $attempt, Throwable $e): void
    {
        $max = (int) config('clinic.reminders.max_attempts');
        $message = mb_substr($e->getMessage(), 0, 450);

        if ($attempt >= $max) {
            $this->finish($reminder, $attempt, ReminderStatus::Failed, $message);

            return;
        }

        // Reintento con espera creciente: 5, 10, 15… minutos.
        $reminder->update([
            'status' => ReminderStatus::Pending,
            'attempts' => $attempt,
            'last_error' => $message,
            'scheduled_for' => now()->addMinutes(5 * $attempt),
        ]);

        $reminder->logs()->create(['attempt' => $attempt, 'status' => 'reintento', 'channel' => $reminder->channel->value, 'message' => $message]);
    }

    private function finish(Reminder $reminder, int $attempt, ReminderStatus $status, string $message): void
    {
        $reminder->update([
            'status' => $status,
            'attempts' => $attempt,
            'last_error' => $status === ReminderStatus::Sent ? null : $message,
        ]);

        $reminder->logs()->create(['attempt' => $attempt, 'status' => $status->value, 'channel' => $reminder->channel->value, 'message' => $message]);
    }
}
