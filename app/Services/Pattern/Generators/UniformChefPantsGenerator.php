<?php

namespace App\Services\Pattern\Generators;

/** پیش‌بندِ بلندِ آشپزخانه با سینه‌بند. */
class UniformChefPantsGenerator extends UniformBaseGenerator
{
    public static function key(): string
    {
        return 'uniform_chef_apron';
    }

    protected function uniform(): array
    {
        return [
            'prefix' => 'uniform_chef_apron',
            'title' => 'پیش‌بند آشپزخانه',
            'form' => 'apron',
            'use' => 'kitchen',
            'bib_width' => 30,
            'bib_height' => 32,
            'skirt_width' => 66,
            'skirt_length' => 72,
            'pocket_count' => 2,
        ];
    }
}
