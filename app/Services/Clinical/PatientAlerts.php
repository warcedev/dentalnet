<?php

namespace App\Services\Clinical;

use App\Enums\Severity;
use App\Models\Allergy;
use App\Models\Patient;
use App\Models\PatientCondition;

/**
 * Alertas clínicas visibles en todas las pantallas del paciente (alergias y
 * condiciones de riesgo activas).
 */
class PatientAlerts
{
    /**
     * @return array{has_allergies: bool, items: list<array{kind: string, level: string, label: string, detail: string|null}>}
     */
    public function for(Patient $patient): array
    {
        $allergies = Allergy::query()->whereBelongsTo($patient)->active()->get();
        $conditions = PatientCondition::query()->whereBelongsTo($patient)->active()->get();

        $items = [];

        foreach ($allergies->sortByDesc(fn (Allergy $a) => $a->severity === Severity::Severe) as $allergy) {
            $items[] = [
                'kind' => 'alergia',
                'level' => 'critical',
                'label' => 'Alergia: '.$allergy->allergen,
                'detail' => trim($allergy->type->label().' · '.$allergy->severity->label().($allergy->reaction ? ' · '.$allergy->reaction : '')),
            ];
        }

        foreach ($conditions->sortByDesc(fn (PatientCondition $c) => $c->condition->isHighRisk()) as $condition) {
            $items[] = [
                'kind' => 'condicion',
                'level' => $condition->condition->isHighRisk() ? 'critical' : 'warning',
                'label' => $condition->condition->label(),
                'detail' => $condition->notes,
            ];
        }

        return ['has_allergies' => $allergies->isNotEmpty(), 'items' => $items];
    }
}
