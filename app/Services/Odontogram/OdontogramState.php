<?php

namespace App\Services\Odontogram;

use App\Enums\ToothCondition;
use App\Enums\ToothConditionScope;
use App\Enums\ToothEventStatus;
use App\Models\ToothEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Reconstruye el odontograma a una fecha a partir de los eventos.
 *
 * Reglas:
 * - Solo cuentan los eventos vigentes: los corregidos por otro evento y las
 *   anulaciones no se consideran.
 * - Un evento aplica desde su fecha de detección. Si a la fecha consultada ya
 *   tenía fecha de tratamiento, se muestra con su estado registrado; si el
 *   tratamiento fue posterior, se muestra como "diagnosticado".
 * - En cada superficie gana el evento más reciente. En el diente completo se
 *   conserva el más reciente de cada condición (p. ej. endodoncia y corona).
 */
class OdontogramState
{
    /**
     * Eventos vigentes (sin los corregidos ni las anulaciones).
     *
     * @param  Collection<int, ToothEvent>  $events
     * @return Collection<int, ToothEvent>
     */
    public function valid(Collection $events): Collection
    {
        $corrected = $events->pluck('corrects_event_id')->filter()->flip();

        return $events
            ->reject(fn (ToothEvent $e) => $e->is_void || $corrected->has($e->id))
            ->values();
    }

    /**
     * @param  Collection<int, ToothEvent>  $events  Todos los eventos del paciente
     * @return array<int, array{absent: bool, tooth: list<array<string, mixed>>, surfaces: array<string, array<string, mixed>>}>
     */
    public function at(Collection $events, CarbonImmutable $asOf): array
    {
        $asOf = $asOf->startOfDay();
        $chart = [];

        $applicable = $this->valid($events)
            ->filter(fn (ToothEvent $e) => $e->tooth_code !== null && $e->detected_on->lte($asOf))
            ->map(fn (ToothEvent $e) => $this->effective($e, $asOf))
            ->sortBy([['date', 'asc'], ['id', 'asc']]);

        foreach ($applicable as $entry) {
            $code = $entry['tooth_code'];
            $chart[$code] ??= ['absent' => false, 'tooth' => [], 'surfaces' => []];

            if ($entry['scope'] === ToothConditionScope::Surface->value && $entry['surface'] !== null) {
                $chart[$code]['surfaces'][$entry['surface']] = $entry;
            } else {
                $chart[$code]['tooth'][$entry['condition']] = $entry;
            }
        }

        foreach ($chart as $code => $tooth) {
            $chart[$code]['tooth'] = array_values($tooth['tooth']);
            $chart[$code]['absent'] = $this->isAbsent($tooth['tooth']);
        }

        ksort($chart);

        return $chart;
    }

    /**
     * El diente está ausente si lo último entre ausencia, extracción realizada
     * e implante es una ausencia o una extracción.
     *
     * @param  array<string, array<string, mixed>>  $toothEntries
     */
    private function isAbsent(array $toothEntries): bool
    {
        $relevant = array_filter($toothEntries, fn (array $e) => match ($e['condition']) {
            ToothCondition::Missing->value => true,
            ToothCondition::Extraction->value => $e['status'] === ToothEventStatus::Treated->value,
            ToothCondition::Implant->value => true,
            default => false,
        });

        if ($relevant === []) {
            return false;
        }

        usort($relevant, fn ($a, $b) => [$a['date'], $a['id']] <=> [$b['date'], $b['id']]);
        $last = end($relevant);

        return $last['condition'] !== ToothCondition::Implant->value;
    }

    /** @return array<string, mixed> */
    private function effective(ToothEvent $event, CarbonImmutable $asOf): array
    {
        $treatedByThen = $event->treated_on !== null && $event->treated_on->lte($asOf);

        if ($treatedByThen) {
            $status = $event->status;
            $date = $event->treated_on;
        } elseif ($event->status->requiresTreatmentDate()) {
            // Se trató después de la fecha consultada: en ese momento estaba pendiente.
            $status = ToothEventStatus::Diagnosed;
            $date = $event->detected_on;
        } else {
            $status = $event->status;
            $date = $event->detected_on;
        }

        return [
            'id' => $event->id,
            'tooth_code' => $event->tooth_code,
            'surface' => $event->surface?->value,
            'scope' => $event->condition->scope()->value,
            'condition' => $event->condition->value,
            'condition_label' => $event->condition->label(),
            'status' => $status->value,
            'status_label' => $status->label(),
            'date' => $date->toDateString(),
        ];
    }
}
