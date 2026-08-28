<?php

namespace App\Services\Pattern\Generators;

/** پنجرهٔ باز زیرِ خطِ سینه. */
class BlouseCutoutGenerator extends BlouseBaseGenerator
{
    public static function key(): string
    {
        return 'blouse_cutout';
    }

    protected function blouse(): array
    {
        return ['prefix' => 'blouse_cutout', 'title' => 'شومیز کات‌اوت',
            'fit' => 'fitted', 'neckline' => 'u', 'collar' => 'none', 'sleeve' => 'long',
            'use' => 'party',
            'notes' => ['لبهٔ پنجره با سجافِ هم‌شکل تمام می‌شود، نه با نوارِ اریب؛ نوار روی منحنیِ بسته چروک می‌افتد.']];
    }
}
