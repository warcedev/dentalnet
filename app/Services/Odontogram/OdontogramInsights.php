<?php

namespace App\Services\Odontogram;

use App\Enums\ToothCondition;
use App\Enums\ToothEventStatus;
use App\Models\ToothEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Consultas rápidas derivadas de los eventos del odontograma.
 */
class OdontogramInsights
{
    public function __construct(private readonly OdontogramState $state) {}

    /** @param  Collection<int, ToothEvent>  $events */
    public function lastCleaning(Collection $events): ?CarbonImmutable
    {
        /** @var CarbonImmutable|null $last */
        $last = $this->treated($events)
            ->filter(fn (ToothEvent $e) => $e->condition === ToothCondition::Cleaning)
            ->max('treated_on');

        return $last;
    }

    /**
     * Regla de negocio 5: la próxima limpieza se calcula desde la última registrada.
     *
     * @param  Collection<int, ToothEvent>  $events
     */
    public function nextCleaning(Collection $events): ?CarbonImmutable
    {
        return $this->lastCleaning($events)?->addMonthsNoOverflow((int) config('clinic.cleaning_interval_months'));
    }

    /**
     * Última intervención realizada en cada diente.
     *
     * @param  Collection<int, ToothEvent>  $events
     * @return array<int, array{date: string, condition: string, surface: string|null, event_id: int}>
     */
    public function lastInterventionByTooth(Collection $events): array
    {
        return $this->treated($events)
            ->filter(fn (ToothEvent $e) => $e->tooth_code !== null)
            ->sortBy([['treated_on', 'asc'], ['id', 'asc']])
            ->groupBy('tooth_code')
            ->map(function (Collection $byTooth) {
                /** @var ToothEvent $last */
                $last = $byTooth->last();

                return [
                    'date' => $last->treated_on?->toDateString() ?? '',
                    'condition' => $last->condition->label(),
                    'surface' => $last->surface?->labelFor($last->tooth_code),
                    'event_id' => $last->id,
                ];
            })
            ->sortKeys()
            ->all();
    }

    /**
     * Tratamientos realizados (arreglos) con su fecha, del más reciente al más antiguo.
     *
     * @param  Collection<int, ToothEvent>  $events
     * @return Collection<int, ToothEvent>
     */
    public function treated(Collection $events): Collection
    {
        return $this->state->valid($events)
            ->filter(fn (ToothEvent $e) => $e->status === ToothEventStatus::Treated && $e->treated_on !== null)
            ->sortByDesc(fn (ToothEvent $e) => $e->treated_on->toDateString().sprintf('%010d', $e->id))
            ->values();
    }
}
