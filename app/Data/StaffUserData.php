<?php

namespace App\Data;

/**
 * Datos validados de un integrante del personal.
 */
final readonly class StaffUserData
{
    /** @param  list<string>  $roles */
    public function __construct(
        public string $name,
        public string $email,
        public ?string $phone,
        public ?string $licenseNumber,
        public array $roles,
        public bool $isActive = true,
    ) {}
}
