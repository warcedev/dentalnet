<?php

namespace App\Services\Reminders\Channels;

use App\Mail\ReminderMail;
use App\Models\Reminder;
use Illuminate\Support\Facades\Mail;

class MailChannel implements ReminderChannel
{
    public function unavailableReason(Reminder $reminder): ?string
    {
        return blank($reminder->patient->email) ? 'El paciente no tiene correo electrónico registrado.' : null;
    }

    public function send(Reminder $reminder, ReminderMessage $message): void
    {
        Mail::to((string) $reminder->patient->email, $reminder->patient->full_name)
            ->send(new ReminderMail($message));
    }
}
