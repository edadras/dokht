<?php

namespace App\Services\Pattern\Generators;

/** روپوشِ یک‌تکهٔ پرستاری تا زیرِ زانو. */
class UniformNurseDressGenerator extends UniformBaseGenerator
{
    public static function key(): string
    {
        return 'uniform_nurse_dress';
    }

    protected function uniform(): array
    {
        return [
            'prefix' => 'uniform_nurse_dress',
            'title' => 'روپوش پرستاری',
            'form' => 'dress',
            'use' => 'medical',
            'length' => 62,
            'length_max' => 110,
            'grow' => 3,
            'armhole' => 4.5,
            'opening' => 'button',
            'buttons' => 7,
            'collar' => 'turn',
            'collar_height' => 6,
            'sleeve_length' => 24,
            'hem_flare' => 5,
            'pocket' => true,
        ];
    }
}
