<?php

namespace App\Services\Pattern\Generators;

/** دامنِ ساتنِ اریب‌بر: روی اریب بریده می‌شود و روی بدن می‌ریزد. */
class SkirtSatinBiasGenerator extends SkirtStraightGenerator
{
    public static function key(): string
    {
        return 'skirt_satin_bias';
    }

    public function label(): string
    {
        return 'دامن ساتن اریب';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'length' => 78,
            'hem_change' => 6,
            'vent_length' => 0,
        ]);
    }
}
