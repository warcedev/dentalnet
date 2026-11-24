<?php

namespace App\Data;

use App\Enums\ToothCondition;
use App\Enums\ToothEventStatus;
use App\Enums\ToothSurface;
use Carbon\CarbonImmutable;

final readonly class ToothEventData
{
    public function __construct(
        public ?int $toothCode,
        public ?ToothSurface $surface,
        public ToothCondition $condition,
        public ToothEventStatus $status,
        public CarbonImmutable $detectedOn,
        public ?CarbonImmutable $treatedOn,
        public ?string $notes,
    ) {}

    /** @return array<string, mixed> */
    public function attributes(): array
    {
        return [
            'tooth_code' => $this->toothCode,
            'surface' => $this->surface,
            'condition' => $this->condition,
            'status' => $this->status,
            'detected_on' => $this->detectedOn,
            'treated_on' => $this->treatedOn,
            'notes' => $this->notes,
        ];
    }
}
