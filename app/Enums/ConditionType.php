<?php

namespace App\Enums;

/**
 * Condiciones de riesgo que deben alertarse en todas las pantallas del paciente.
 */
enum ConditionType: string
{
    case Hypertension = 'hipertension';
    case Diabetes = 'diabetes';
    case Anticoagulants = 'anticoagulantes';
    case HeartDisease = 'cardiopatia';
    case BleedingDisorder = 'coagulopatia';
    case Pregnancy = 'embarazo';
    case Breastfeeding = 'lactancia';
    case Asthma = 'asma';
    case Epilepsy = 'epilepsia';
    case Bisphosphonates = 'bifosfonatos';
    case Immunosuppression = 'inmunosupresion';
    case InfectiousDisease = 'enfermedad_infecciosa';
    case Other = 'otra';

    public function label(): string
    {
        return match ($this) {
            self::Hypertension => 'Hipertensión',
            self::Diabetes => 'Diabetes',
            self::Anticoagulants => 'Toma anticoagulantes',
            self::HeartDisease => 'Cardiopatía',
            self::BleedingDisorder => 'Trastorno de coagulación',
            self::Pregnancy => 'Embarazo',
            self::Breastfeeding => 'Lactancia',
            self::Asthma => 'Asma',
            self::Epilepsy => 'Epilepsia',
            self::Bisphosphonates => 'Toma bifosfonatos',
            self::Immunosuppression => 'Inmunosupresión',
            self::InfectiousDisease => 'Enfermedad infecciosa',
            self::Other => 'Otra condición',
        };
    }

    /** Condiciones que alteran directamente la anestesia, el sangrado o la medicación. */
    public function isHighRisk(): bool
    {
        return in_array($this, [
            self::Anticoagulants,
            self::BleedingDisorder,
            self::HeartDisease,
            self::Pregnancy,
            self::Bisphosphonates,
        ], true);
    }
}
