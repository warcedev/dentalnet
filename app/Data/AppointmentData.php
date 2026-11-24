<?php

namespace App\Data;

use Carbon\CarbonImmutable;

final readonly class AppointmentData
{
    public function __construct(
        public int $patientId,
        public int $professionalId,
        public int $roomId,
        public int $typeId,
        public CarbonImmutable $startsAt,
        public int $durationMinutes,
        public ?string $notes,
        public ?int $waitingListEntryId = null,
    ) {}

    public function endsAt(): CarbonImmutable
    {
        return $this->startsAt->addMinutes($this->durationMinutes);
    }
}
