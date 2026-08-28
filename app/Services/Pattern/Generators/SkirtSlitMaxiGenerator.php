<?php

namespace App\Services\Pattern\Generators;

/** دامنِ ماکسیِ راست با چاکِ بلندِ پهلو. */
class SkirtSlitMaxiGenerator extends SkirtStraightGenerator
{
    public static function key(): string
    {
        return 'skirt_slit_maxi';
    }

    public function label(): string
    {
        return 'دامن ماکسی چاک‌دار';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'length' => 100,
            'hem_change' => 0,
            'vent_length' => 45,
        ]);
    }
}
