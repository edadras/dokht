<?php

namespace App\Services\Pattern\Style\Fullness;

/**
 * پیلی آکاردئونی: تاهای ریز و پیوسته که با حرارت روی پارچه ثابت می‌شوند.
 *
 * چون تاها ریز و زیادند، پیش‌فرض تعداد بالا و ژرفای کم است. این پیلی روی پارچه
 * تخت اتو نمی‌شود و باید پیش از دوخت پیلی‌زنی شود، پس دم لباس هم پیلی می‌ماند.
 */
class AccordionPleats extends AddedPleats
{
    public static function key(): string
    {
        return 'fullness_accordion_pleats';
    }

    public function label(): string
    {
        return 'پیلی آکاردئونی';
    }

    public function description(): string
    {
        return 'تاهای ریز و پیوسته از کمر تا دم؛ پارچه باید پیش از دوخت پیلی‌زنی شود.';
    }

    public function paramsSchema(): array
    {
        $params = $this->pleatParams(12, 1.5);
        $params['to_hem']['default'] = true;

        return $params;
    }

    protected function pleatType(): string
    {
        return 'accordion';
    }
}
