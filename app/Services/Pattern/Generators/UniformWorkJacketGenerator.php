<?php

namespace App\Services\Pattern\Generators;

/** کتِ کار: کوتاه، محکم، با چهار جیب. */
class UniformWorkJacketGenerator extends UniformBaseGenerator
{
    public static function key(): string
    {
        return 'uniform_work_jacket';
    }

    protected function uniform(): array
    {
        return [
            'prefix' => 'uniform_work_jacket',
            'title' => 'کت کار',
            'form' => 'top',
            'use' => 'workshop',
            'length' => 16,
            'grow' => 5,
            'armhole' => 6,
            'opening' => 'zip',
            'collar' => 'stand',
            'collar_height' => 6,
            'sleeve_length' => 58,
            'pocket' => true,
        ];
    }
}
