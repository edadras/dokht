<?php

namespace App\Services\Pattern\Generators;

/** پیراهنِ جلوباز بچگانه با یقهٔ برگردان و دکمه. */
class ChildShirtGenerator extends ChildGarmentBaseGenerator
{
    public static function key(): string
    {
        return 'child_shirt';
    }

    protected function child(): array
    {
        return [
            'prefix' => 'child_shirt',
            'title' => 'پیراهن بچگانه',
            'form' => 'top',
            'use' => 'school',
            'length' => 16,
            'shape' => 'straight',
            'opening' => 'button',
            'buttons' => 5,
            'collar' => 'turn',
            'collar_height' => 5,
            'sleeve_length' => 38,
            'notes' => ['جلو باز است، پس یقه لازم نیست به دور سر برسد.'],
        ];
    }
}
