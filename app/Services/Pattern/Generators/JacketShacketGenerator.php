<?php

namespace App\Services\Pattern\Generators;

/** شکت: چیزی میانِ پیراهن و کت؛ گشاد، دکمه‌دار، با دو جیبِ سینه. */
class JacketShacketGenerator extends JacketWorkGenerator
{
    public static function key(): string
    {
        return 'jacket_shacket';
    }

    public function label(): string
    {
        return 'شکت (پیراهن‌کت)';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'ease_extra' => 5,
            'armhole_depth_extra' => 6,
        ]);
    }
}
