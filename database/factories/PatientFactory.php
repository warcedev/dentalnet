<?php

namespace Database\Factories;

use App\Enums\DocumentType;
use App\Enums\Sex;
use App\Enums\SignerRelationship;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    public const FEMALE_NAMES = ['María José', 'Ana', 'Rocío', 'Fátima', 'Mirta', 'Liz', 'Natalia', 'Sofía', 'Laura', 'Gabriela', 'Lourdes', 'Romina', 'Andrea', 'Carmen', 'Belén'];

    public const MALE_NAMES = ['Juan Carlos', 'Luis', 'Diego', 'Osvaldo', 'Rodrigo', 'Gustavo', 'Fernando', 'Matías', 'Hugo', 'Andrés', 'Derlis', 'Óscar', 'Ramón', 'Nelson', 'Sebastián'];

    public const SURNAMES = ['González', 'Benítez', 'Ramírez', 'Giménez', 'Duarte', 'Martínez', 'Ortiz', 'Acosta', 'Ayala', 'Villalba', 'Cáceres', 'Báez', 'Rojas', 'Fernández', 'Sanabria', 'Riveros', 'Aquino', 'Cabrera', 'Espínola', 'Galeano', 'Vera', 'Núñez', 'Ocampos', 'Insfrán'];

    public const CITIES = ['Asunción', 'San Lorenzo', 'Luque', 'Lambaré', 'Fernando de la Mora', 'Capiatá', 'Limpio', 'Mariano Roque Alonso', 'Ñemby'];

    public function definition(): array
    {
        $sex = fake()->randomElement([Sex::Female, Sex::Male]);
        $first = fake()->randomElement($sex === Sex::Female ? self::FEMALE_NAMES : self::MALE_NAMES);

        return [
            'first_name' => $first,
            'last_name' => fake()->randomElement(self::SURNAMES).' '.fake()->randomElement(self::SURNAMES),
            'document_type' => DocumentType::Ci,
            'document_number' => (string) fake()->unique()->numberBetween(800000, 7900000),
            'birth_date' => fake()->dateTimeBetween('-80 years', '-19 years')->format('Y-m-d'),
            'sex' => $sex,
            'phone' => '09'.fake()->randomElement(['71', '72', '81', '82', '83', '84', '85', '91', '92', '94', '95', '96', '97', '98', '99']).' '.fake()->numerify('### ###'),
            'email' => fake()->boolean(60) ? fake()->unique()->safeEmail() : null,
            'address' => fake()->boolean(70) ? 'Calle '.fake()->numberBetween(1, 999).' c/ '.fake()->randomElement(['Mcal. López', 'España', 'Eusebio Ayala', 'Artigas', 'Brasilia', 'Sacramento']) : null,
            'city' => fake()->randomElement(self::CITIES),
            'emergency_contact_name' => fake()->name(),
            'emergency_contact_phone' => '09'.fake()->numerify('## ### ###'),
            'emergency_contact_relationship' => fake()->randomElement(['Cónyuge', 'Hijo/a', 'Madre', 'Padre', 'Hermano/a']),
            'insurer' => fake()->optional(0.6)->randomElement(['IPS', 'Asismed', 'Santa Clara', 'Migone']),
            'insurance_number' => fake()->optional(0.5)->numerify('######'),
            'accepts_communications' => fake()->boolean(70),
        ];
    }

    public function minor(): static
    {
        return $this->state(fn () => [
            'birth_date' => fake()->dateTimeBetween('-16 years', '-4 years')->format('Y-m-d'),
            'guardian_name' => fake()->randomElement(self::FEMALE_NAMES).' '.fake()->randomElement(self::SURNAMES),
            'guardian_document' => (string) fake()->numberBetween(1000000, 6000000),
            'guardian_relationship' => SignerRelationship::Parent,
            'guardian_phone' => '0981 '.fake()->numerify('### ###'),
        ]);
    }
}
