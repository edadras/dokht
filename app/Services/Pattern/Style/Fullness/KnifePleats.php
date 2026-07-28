<?php

namespace App\Services\Pattern\Style\Fullness;

/**
 * پیلی تیغه‌ای: همه تاها به یک سمت می‌خوابند.
 *
 * جای هر پیلی روی پارچه دو برابر ژرفای تاست.
 */
class KnifePleats extends AddedPleats
{
    public static function key(): string
    {
        return 'fullness_knife_pleats';
    }

    public function label(): string
    {
        return 'پیلی تیغه‌ای';
    }

    public function description(): string
    {
        return 'تاهای یک‌طرفه و اتوشده؛ ساده‌ترین راه دادن حجم به یک پنل صاف.';
    }

    public function paramsSchema(): array
    {
        return $this->pleatParams(4, 4);
    }

    protected function pleatType(): string
    {
        return 'knife';
    }
}
