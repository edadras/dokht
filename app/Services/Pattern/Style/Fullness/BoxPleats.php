<?php

namespace App\Services\Pattern\Style\Fullness;

/**
 * پیلی جعبه‌ای: هر پیلی از دو تای روبه‌رو ساخته می‌شود.
 *
 * چون دو تا دارد، جای هر پیلی روی پارچه چهار برابر ژرفای تاست.
 */
class BoxPleats extends AddedPleats
{
    public static function key(): string
    {
        return 'fullness_box_pleats';
    }

    public function label(): string
    {
        return 'پیلی جعبه‌ای';
    }

    public function description(): string
    {
        return 'دو تای روبه‌رو که پشت هم می‌خوابند؛ حجم بیشتر و افت سنگین‌تر.';
    }

    public function paramsSchema(): array
    {
        return $this->pleatParams(2, 4);
    }

    protected function pleatType(): string
    {
        return 'box';
    }
}
