<?php

namespace App\Services\Pattern\Generators;

/**
 * شومیز شیردهی.
 *
 * لایهٔ جلو دولا است: لایهٔ رو از سرشانه آزاد می‌افتد و لایهٔ زیر با چاکِ عمودی
 * باز می‌شود. پس همان راپ است، با یک لایهٔ اضافه.
 */
class BlouseNursingGenerator extends BlouseBaseGenerator
{
    public static function key(): string
    {
        return 'blouse_nursing';
    }

    public function label(): string
    {
        return 'شومیز شیردهی';
    }

    protected function blouse(): array
    {
        return ['prefix' => 'blouse-nursing', 'title' => 'شومیز شیردهی', 'fit' => 'regular',
            'neckline' => 'deep_v', 'collar' => 'none', 'sleeve' => 'short', 'use' => 'nursing',
            'opening' => 'wrap', 'body_length' => 20, 'gathers' => 6];
    }
}
