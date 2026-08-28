<?php

namespace App\Services\Pattern\Generators;

/** پیراهنِ کار: پارچهٔ سنگین، جیبِ سینه، حلقهٔ گود. */
class UniformWorkShirtGenerator extends UniformBaseGenerator
{
    public static function key(): string
    {
        return 'uniform_work_shirt';
    }

    protected function uniform(): array
    {
        return [
            'prefix' => 'uniform_work_shirt',
            'title' => 'پیراهن کار',
            'form' => 'top',
            'use' => 'workshop',
            'length' => 22,
            'grow' => 3.5,
            'armhole' => 5,
            'opening' => 'button',
            'buttons' => 7,
            'collar' => 'turn',
            'collar_height' => 6,
            'sleeve_length' => 58,
            'pocket' => true,
        ];
    }
}
