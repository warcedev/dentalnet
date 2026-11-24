<?php

namespace App\Services\Periodontal;

use App\Models\PeriodontalMeasurement;
use App\Models\PeriodontalRecord;

/**
 * Indicadores de un examen periodontal: sangrado al sondaje (BOP), índice
 * de placa, profundidad media y sitios con bolsas.
 */
class PeriodontalSummary
{
    /**
     * @return array{teeth: int, sites: int, bleeding_pct: float, plaque_pct: float, mean_depth: float, sites_4_plus: int, sites_6_plus: int}
     */
    public function for(PeriodontalRecord $record): array
    {
        $measurements = $record->relationLoaded('measurements') ? $record->measurements : $record->measurements()->get();
        $present = $measurements->reject(fn (PeriodontalMeasurement $m) => $m->is_missing);

        $depths = $present->flatMap(fn (PeriodontalMeasurement $m) => array_filter((array) $m->probing_depth, fn ($v) => $v !== null));
        $bleeding = $present->flatMap(fn (PeriodontalMeasurement $m) => (array) $m->bleeding);
        $plaque = $present->flatMap(fn (PeriodontalMeasurement $m) => (array) $m->plaque);
        $sites = $present->count() * 6;

        return [
            'teeth' => $present->count(),
            'sites' => $sites,
            'bleeding_pct' => $sites > 0 ? round($bleeding->filter()->count() / $sites * 100, 1) : 0.0,
            'plaque_pct' => $sites > 0 ? round($plaque->filter()->count() / $sites * 100, 1) : 0.0,
            'mean_depth' => $depths->isNotEmpty() ? round((float) $depths->avg(), 1) : 0.0,
            'sites_4_plus' => $depths->filter(fn ($d) => $d >= 4)->count(),
            'sites_6_plus' => $depths->filter(fn ($d) => $d >= 6)->count(),
        ];
    }
}
