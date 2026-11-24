<?php

namespace App\Services\Reminders\Channels;

use App\Models\Reminder;

/**
 * Canal de envío de recordatorios. Para agregar WhatsApp o SMS basta con
 * implementar esta interfaz y registrarla en ChannelRegistry.
 */
interface ReminderChannel
{
    /**
     * Devuelve null si el paciente no puede recibir por este canal (p. ej. sin
     * correo); el recordatorio se marca como omitido con ese motivo.
     */
    public function unavailableReason(Reminder $reminder): ?string;

    /** Envía. Lanza una excepción si falla (se reintenta). */
    public function send(Reminder $reminder, ReminderMessage $message): void;
}
