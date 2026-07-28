<?php

namespace App\Http\Controllers\Concerns;

use App\Support\Jalali;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * گروه‌بندی ردیف‌ها بر ماه شمسی.
 *
 * محاسبه در PHP انجام می‌شود، نه در دیتابیس، تا هم sqlite و هم MySQL کار کند.
 */
trait GroupsByJalaliMonth
{
    /**
     * بازه میلادی یک ماه شمسی؛ offset=0 ماه جاری، offset=1 ماه پیش.
     *
     * @return array{key:string, label:string, start:Carbon, end:Carbon}
     */
    protected function jalaliMonth(int $offset = 0): array
    {
        [$jy, $jm] = Jalali::fromGregorian((int) date('Y'), (int) date('n'), (int) date('j'));

        $jm -= $offset;

        while ($jm < 1) {
            $jm += 12;
            $jy--;
        }

        [$gy, $gm, $gd] = Jalali::toGregorian($jy, $jm, 1);

        $start = Carbon::create($gy, $gm, $gd)->startOfDay();

        return [
            'key' => $jy.'-'.str_pad((string) $jm, 2, '0', STR_PAD_LEFT),
            'label' => Jalali::MONTHS[$jm].' '.Jalali::digits((string) $jy),
            'start' => $start,
            'end' => $start->copy()->addDays(Jalali::daysInMonth($jy, $jm) - 1)->endOfDay(),
        ];
    }

    /**
     * چند ماه شمسی اخیر، از قدیم به جدید.
     *
     * @return Collection<int, array{key:string, label:string, start:Carbon, end:Carbon}>
     */
    protected function recentJalaliMonths(int $count = 6): Collection
    {
        return collect(range($count - 1, 0))->map(fn (int $offset) => $this->jalaliMonth($offset));
    }

    /** کلید ماه شمسی یک تاریخ میلادی: «۱۴۰۵-۰۵» به شکل لاتین. */
    protected function jalaliMonthKey(mixed $date): ?string
    {
        if (! $date instanceof \DateTimeInterface) {
            return null;
        }

        [$jy, $jm] = Jalali::fromGregorian(
            (int) $date->format('Y'),
            (int) $date->format('n'),
            (int) $date->format('j'),
        );

        return $jy.'-'.str_pad((string) $jm, 2, '0', STR_PAD_LEFT);
    }
}
