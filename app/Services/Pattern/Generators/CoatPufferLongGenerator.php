<?php

namespace App\Services\Pattern\Generators;

/** کاپشنِ پافرِ بلند تا زیرِ زانو. */
class CoatPufferLongGenerator extends JacketPufferGenerator
{
    public static function key(): string
    {
        return 'coat_puffer_long';
    }

    public function label(): string
    {
        return 'کاپشن پافر بلند';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'length' => 78,
            'baffle_spacing' => 13,
        ]);
    }
}
