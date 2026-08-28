<?php

namespace App\Services\Pattern\Generators;

/** دامنِ جینِ کوتاه با چاکِ جلو. */
class SkirtDenimMiniGenerator extends SkirtStraightGenerator
{
    public static function key(): string
    {
        return 'skirt_denim_mini';
    }

    public function label(): string
    {
        return 'دامن جین کوتاه';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'length' => 36,
            'hem_change' => 2,
            'vent_length' => 10,
        ]);
    }
}
