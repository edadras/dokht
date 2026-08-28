<?php

namespace App\Services\Pattern\Generators;

/** ژاکتِ کشبافِ بلند تا میانهٔ ران. */
class KnitCardiganLongGenerator extends CardiganGenerator
{
    public static function key(): string
    {
        return 'knit_cardigan_long';
    }

    public function label(): string
    {
        return 'ژاکت بلند';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'length' => 44,
            'ease_extra' => 4,
        ]);
    }
}
