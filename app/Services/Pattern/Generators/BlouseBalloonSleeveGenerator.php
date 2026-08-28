<?php

namespace App\Services\Pattern\Generators;

/** آستینِ بادکنکی که از آرنج پف می‌کند و در مچ جمع می‌شود. */
class BlouseBalloonSleeveGenerator extends BlouseBaseGenerator
{
    public static function key(): string
    {
        return 'blouse_balloon_sleeve';
    }

    protected function blouse(): array
    {
        return ['prefix' => 'blouse_balloon_sleeve', 'title' => 'شومیز آستین‌بادکنکی',
            'fit' => 'regular', 'neckline' => 'round', 'collar' => 'stand', 'sleeve' => 'balloon',
            'use' => 'daily'];
    }
}
