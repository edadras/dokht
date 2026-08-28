<?php

namespace App\Services\Pattern\Generators;

/** پالتوی پشمیِ بلند تا زیرِ زانو با یقهٔ برگردانِ پهن. */
class CoatWoolLongGenerator extends CoatOvercoatGenerator
{
    public static function key(): string
    {
        return 'coat_wool_long';
    }

    public function label(): string
    {
        return 'پالتو پشمی بلند';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'length' => 105,
            'collar_height' => 11,
        ]);
    }
}
