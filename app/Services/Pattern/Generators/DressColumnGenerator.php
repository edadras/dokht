<?php

namespace App\Services\Pattern\Generators;

/** پیراهنِ ستونیِ بلندِ راست. */
class DressColumnGenerator extends DressCatalogBaseGenerator
{
    public static function key(): string
    {
        return 'dress_column';
    }

    protected function dress(): array
    {
        return [
            'prefix' => 'dress_column',
            'title' => 'پیراهن ستونی',
            'form' => 'onepiece',
            'shape' => 'straight',
            'length' => 92,
            'hem_flare' => 0,
            'waist_dart' => true,
            'fit' => 'fitted',
            'sleeve' => 'none',
            'notes' => ['چاکِ پشت لازم است؛ با این دمِ راست بی چاک نمی‌شود قدم برداشت.']
        ];
    }
}
