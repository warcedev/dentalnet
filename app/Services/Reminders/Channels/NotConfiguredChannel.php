<?php

namespace App\Services\Reminders\Channels;

use App\Models\Reminder;
use RuntimeException;

/**
 * Lugar reservado para WhatsApp y SMS: requieren contratar un proveedor
 * (y el consentimiento del paciente para ese canal).
 */
class NotConfiguredChannel implements ReminderChannel
{
    public function __construct(private readonly string $name) {}

    public function unavailableReason(Reminder $reminder): ?string
    {
        return null;
    }

    public function send(Reminder $reminder, ReminderMessage $message): void
    {
        throw new RuntimeException("El canal {$this->name} no está configurado.");
    }
}
