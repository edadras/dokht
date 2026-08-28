<?php

namespace App\Services\Pattern\Generators;

/** جلیقهٔ سرو و پذیرش: بی‌آستین، دکمه‌دار. */
class UniformServiceVestGenerator extends UniformBaseGenerator
{
    public static function key(): string
    {
        return 'uniform_service_vest';
    }

    protected function uniform(): array
    {
        return [
            'prefix' => 'uniform_service_vest',
            'title' => 'جلیقه سرو',
            'form' => 'top',
            'use' => 'service',
            'length' => 16,
            'grow' => 1.5,
            'armhole' => 2.5,
            'opening' => 'button',
            'buttons' => 5,
            'collar' => 'none',
            'sleeve' => 'none',
            'sleeve_length' => 0,
            'pocket' => false,
            'shape' => 'fitted',
        ];
    }
}
