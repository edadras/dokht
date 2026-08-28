<?php

namespace App\Services\Pattern\Generators;

/** دامنِ نامتقارن که یک پهلویش بلندتر است. */
class SkirtAsymWrapGenerator extends SkirtAsymmetricGenerator
{
    public static function key(): string
    {
        return 'skirt_asym_wrap';
    }

    public function label(): string
    {
        return 'دامن نامتقارن';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'length' => 70,
        ]);
    }
}
