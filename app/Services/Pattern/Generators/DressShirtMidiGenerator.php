<?php

namespace App\Services\Pattern\Generators;

/** پیراهنِ پیرهنیِ میدی با کمربندِ پارچه‌ای. */
class DressShirtMidiGenerator extends DressCatalogBaseGenerator
{
    public static function key(): string
    {
        return 'dress_shirt_midi';
    }

    protected function dress(): array
    {
        return [
            'prefix' => 'dress_shirt_midi',
            'title' => 'پیراهن پیرهنی میدی',
            'form' => 'waisted',
            'skirt' => 'skirt_a_line',
            'skirt_length' => 74,
            'skirt_params' => ['flare' => 30],
            'closure' => 'buttons',
            'sleeve' => 'set_in',
            'sleeve_length' => 22
        ];
    }
}
