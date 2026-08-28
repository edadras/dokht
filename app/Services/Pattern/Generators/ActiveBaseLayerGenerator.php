<?php

namespace App\Services\Pattern\Generators;

/** لایهٔ اولِ زمستانی: چسبان، آستین‌بلند، یقهٔ بسته. */
class ActiveBaseLayerGenerator extends ActiveTopBaseGenerator
{
    public static function key(): string
    {
        return 'active_base_layer';
    }

    protected function active(): array
    {
        return [
            'prefix' => 'active_base_layer',
            'title' => 'لایه اول ورزشی',
            'use' => 'outdoor',
            'stretch' => 0.86,
            'length' => 16,
            'back_drop' => 4,
            'sleeve' => 'set_in',
            'sleeve_length' => 60,
            'neck_width_extra' => 0.5,
            'neck_depth_extra' => 0,
            'notes' => ['روی پوست پوشیده می‌شود؛ درزها باید تخت‌دوزی شوند تا نسایند.'],
        ];
    }
}
