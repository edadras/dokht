<?php

namespace App\Services\Pattern\Generators;

/** پیراهنِ چین‌خوردهٔ پهلو. */
class DressRuchedGenerator extends DressCatalogBaseGenerator
{
    public static function key(): string
    {
        return 'dress_ruched';
    }

    protected function dress(): array
    {
        return [
            'prefix' => 'dress_ruched',
            'title' => 'پیراهن چین پهلو',
            'form' => 'onepiece',
            'shape' => 'fitted',
            'length' => 50,
            'hem_flare' => 0,
            'waist_dart' => true,
            'fit' => 'fitted',
            'notes' => ['چینِ پهلو با نخِ کشِ داخلِ درز جمع می‌شود؛ اندازهٔ بریده همان اندازهٔ الگوست.']
        ];
    }
}
