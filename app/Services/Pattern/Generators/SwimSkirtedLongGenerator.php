<?php

namespace App\Services\Pattern\Generators;

/** مایوی دامن‌دارِ بلند: دامن تا بالای زانو. */
class SwimSkirtedLongGenerator extends SwimSkirtedGenerator
{
    public static function key(): string
    {
        return 'swim_skirted_long';
    }

    public function label(): string
    {
        return 'مایو دامن‌دار بلند';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'skirt_length' => 34,
            'skirt_flare' => 18,
            'neck_drop' => 5,
            'back_drop' => 8,
        ]);
    }
}
