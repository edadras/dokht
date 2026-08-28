<?php

namespace App\Services\Pattern\Generators;

/** روپوشِ آزمایشگاه: بلند، جلوباز، با سه جیب. */
class UniformLabCoatGenerator extends UniformBaseGenerator
{
    public static function key(): string
    {
        return 'uniform_lab_coat';
    }

    protected function uniform(): array
    {
        return [
            'prefix' => 'uniform_lab_coat',
            'title' => 'روپوش آزمایشگاه',
            'form' => 'top',
            'use' => 'medical',
            'length' => 58,
            'length_max' => 110,
            'grow' => 4,
            'armhole' => 5,
            'opening' => 'button',
            'buttons' => 6,
            'collar' => 'turn',
            'collar_height' => 7,
            'sleeve_length' => 58,
            'pocket' => true,
            'notes' => ['روی لباس روزمره پوشیده می‌شود، پس آزادی‌اش از یک روپوش معمولی بیشتر است.'],
        ];
    }
}
