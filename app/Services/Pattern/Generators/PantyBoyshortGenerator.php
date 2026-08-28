<?php

namespace App\Services\Pattern\Generators;

/** شورتِ پاچه‌دار: درزِ پهلوی بلند و خطِ پای پایین‌تر، مثلِ شورتِ ورزشی. */
class PantyBoyshortGenerator extends PantyBriefGenerator
{
    public static function key(): string
    {
        return 'panty_boyshort';
    }

    public function label(): string
    {
        return 'شورت پاچه‌دار';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'rise_drop' => 4,
            'side_seam' => 14,
            'coverage' => 'full',
            'seat' => 4,
        ]);
    }
}
