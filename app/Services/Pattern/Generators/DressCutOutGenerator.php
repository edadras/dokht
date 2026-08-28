<?php

namespace App\Services\Pattern\Generators;

/** پیراهنِ کات‌اوت: پنجرهٔ باز روی پهلو. */
class DressCutOutGenerator extends DressCatalogBaseGenerator
{
    public static function key(): string
    {
        return 'dress_cut_out';
    }

    protected function dress(): array
    {
        return [
            'prefix' => 'dress_cut_out',
            'title' => 'پیراهن کات‌اوت',
            'form' => 'onepiece',
            'shape' => 'fitted',
            'length' => 46,
            'hem_flare' => 0,
            'waist_dart' => true,
            'fit' => 'fitted',
            'ease' => ['bust' => 3.0, 'waist' => 2.0, 'hip' => 3.0],
            'notes' => ['پنجرهٔ پهلو با سجافِ هم‌شکل تمام می‌شود؛ نوار اریب روی منحنیِ بسته چروک می‌افتد.']
        ];
    }
}
