<?php

namespace App\Services\Reminders\Channels;

/** Contenido de un recordatorio, independiente del canal. */
final readonly class ReminderMessage
{
    /** @param  list<string>  $lines */
    public function __construct(
        public string $subject,
        public string $greeting,
        public array $lines,
    ) {}

    /** Versión en texto plano (para SMS o WhatsApp). */
    public function toText(): string
    {
        return $this->greeting."\n\n".implode("\n", $this->lines);
    }
}
