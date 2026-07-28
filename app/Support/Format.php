<?php

namespace App\Support;

/** کمک‌کننده‌های نمایش اعداد و مبالغ فارسی. */
class Format
{
    /** عدد با جداکننده هزارگان و رقم فارسی. */
    public static function number(float|int|null $value, int $decimals = 0): string
    {
        if ($value === null) {
            return '—';
        }

        return Jalali::digits(number_format((float) $value, $decimals));
    }

    /** مبلغ به تومان. */
    public static function money(float|int|null $value, string $currency = 'تومان'): string
    {
        if ($value === null) {
            return '—';
        }

        return static::number(round((float) $value)).' '.$currency;
    }

    /** اندازه به سانتی‌متر. */
    public static function cm(float|int|null $value, int $decimals = 1): string
    {
        if ($value === null) {
            return '—';
        }

        $formatted = rtrim(rtrim(number_format((float) $value, $decimals, '.', ''), '0'), '.');

        return Jalali::digits($formatted === '' ? '0' : $formatted).' سانتی‌متر';
    }

    /** درصد. */
    public static function percent(float|int|null $value, int $decimals = 0): string
    {
        if ($value === null) {
            return '—';
        }

        return Jalali::digits(number_format((float) $value, $decimals)).'٪';
    }

    /** نسبت ۰..۱ را به درصد تبدیل می‌کند. */
    public static function ratio(float|int|null $value): string
    {
        return static::percent(((float) $value) * 100);
    }
}
