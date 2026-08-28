<?php

namespace App\Services\Pattern\Generators;

/** پیراهنِ اداریِ یونیفرم با یقهٔ برگردان. */
class UniformOfficeShirtGenerator extends UniformBaseGenerator
{
    public static function key(): string
    {
        return 'uniform_office_shirt';
    }

    protected function uniform(): array
    {
        return [
            'prefix' => 'uniform_office_shirt',
            'title' => 'پیراهن اداری',
            'form' => 'top',
            'use' => 'office',
            'length' => 22,
            'grow' => 2,
            'armhole' => 3.5,
            'opening' => 'button',
            'buttons' => 7,
            'collar' => 'turn',
            'collar_height' => 6,
            'sleeve_length' => 58,
            'pocket' => false,
            'bust_dart' => true,
            'shape' => 'fitted',
        ];
    }
}
