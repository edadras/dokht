<?php

namespace App\Services\Pattern\Generators;

/** پیراهنِ یقه‌چهارگوش با آستینِ پفی. */
class DressSquareNeckGenerator extends DressCatalogBaseGenerator
{
    public static function key(): string
    {
        return 'dress_square_neck';
    }

    protected function dress(): array
    {
        return [
            'prefix' => 'dress_square_neck',
            'title' => 'پیراهن یقه‌چهارگوش',
            'form' => 'waisted',
            'skirt' => 'skirt_gathered',
            'skirt_length' => 62,
            'sleeve' => 'set_in',
            'sleeve_length' => 16,
            'fit' => 'fitted',
            'block' => ['neck_width_extra' => 4, 'front_neck_depth_extra' => 6]
        ];
    }
}
