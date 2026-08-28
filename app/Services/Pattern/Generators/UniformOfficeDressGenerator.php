<?php

namespace App\Services\Pattern\Generators;

/** پیراهنِ یونیفرمِ اداری تا زیرِ زانو. */
class UniformOfficeDressGenerator extends UniformBaseGenerator
{
    public static function key(): string
    {
        return 'uniform_office_dress';
    }

    protected function uniform(): array
    {
        return [
            'prefix' => 'uniform_office_dress',
            'title' => 'پیراهن یونیفرم اداری',
            'form' => 'dress',
            'use' => 'office',
            'length' => 58,
            'length_max' => 110,
            'grow' => 1.5,
            'armhole' => 3,
            'opening' => 'button',
            'buttons' => 8,
            'collar' => 'turn',
            'collar_height' => 5,
            'sleeve_length' => 24,
            'pocket' => false,
            'bust_dart' => true,
            'shape' => 'fitted',
        ];
    }
}
