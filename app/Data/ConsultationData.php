<?php

namespace App\Data;

use Carbon\CarbonImmutable;

final readonly class ConsultationData
{
    /**
     * @param  list<array{procedure_id: int, treatment_item_id: ?int, tooth_code: ?int, surface: ?string, notes: ?string}>  $procedures
     */
    public function __construct(
        public CarbonImmutable $consultedAt,
        public string $reason,
        public ?string $subjective,
        public ?string $objective,
        public ?string $assessment,
        public ?string $plan,
        public ?string $materials,
        public ?string $observations,
        public ?string $nextVisit,
        public ?CarbonImmutable $nextVisitOn,
        public bool $anesthesiaUsed,
        public bool $allergiesAcknowledged,
        public ?int $appointmentId,
        public array $procedures,
    ) {}
}
