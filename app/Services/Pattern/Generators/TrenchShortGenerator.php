<?php

namespace App\Services\Pattern\Generators;

/** بارانیِ کوتاه تا بالای زانو. */
class TrenchShortGenerator extends CoatTrenchGenerator
{
    public static function key(): string
    {
        return 'trench_short';
    }

    public function label(): string
    {
        return 'بارانی کوتاه';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'length' => 62,
        ]);
    }
}
