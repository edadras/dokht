<?php

namespace App\Services\Pattern\Generators;

/** ژاکتِ کشبافِ کوتاه تا خطِ کمر. */
class KnitCardiganCropGenerator extends CardiganGenerator
{
    public static function key(): string
    {
        return 'knit_cardigan_crop';
    }

    public function label(): string
    {
        return 'ژاکت کوتاه';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'length' => 4,
        ]);
    }
}
