<?php

namespace App\Support;

use BackedEnum;

final class EnumOptions
{
    /**
     * @param  class-string<BackedEnum>  $enum
     * @return list<array{value: string, label: string}>
     */
    public static function for(string $enum): array
    {
        return array_map(
            fn (BackedEnum $case) => ['value' => (string) $case->value, 'label' => method_exists($case, 'label') ? $case->label() : (string) $case->value],
            $enum::cases(),
        );
    }
}
