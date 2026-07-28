<?php

namespace App\Support;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * تبدیل و نمایش تاریخ شمسی بدون وابستگی بیرونی.
 *
 * الگوریتم تبدیل بر پایه روش استاندارد jalaali (محاسبه عدد روز جولیَن) است.
 */
class Jalali
{
    public const MONTHS = [
        1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور',
        7 => 'مهر', 8 => 'آبان', 9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
    ];

    /** شماره روز هفته میلادی (۰=یکشنبه) به نام فارسی. */
    public const WEEKDAYS = [
        0 => 'یکشنبه', 1 => 'دوشنبه', 2 => 'سه‌شنبه', 3 => 'چهارشنبه',
        4 => 'پنجشنبه', 5 => 'جمعه', 6 => 'شنبه',
    ];

    protected const BREAKS = [
        -61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210,
        1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178,
    ];

    /** @return array{0:int,1:int,2:int} [سال, ماه, روز] شمسی */
    public static function fromGregorian(int $gy, int $gm, int $gd): array
    {
        return static::dayToJalali(static::gregorianToDay($gy, $gm, $gd));
    }

    /** @return array{0:int,1:int,2:int} [سال, ماه, روز] میلادی */
    public static function toGregorian(int $jy, int $jm, int $jd): array
    {
        return static::dayToGregorian(static::jalaliToDay($jy, $jm, $jd));
    }

    public static function isLeapYear(int $jy): bool
    {
        return static::calendar($jy)['leap'] === 0;
    }

    public static function daysInMonth(int $jy, int $jm): int
    {
        if ($jm <= 6) {
            return 31;
        }

        if ($jm <= 11) {
            return 30;
        }

        return static::isLeapYear($jy) ? 30 : 29;
    }

    public static function isValid(int $jy, int $jm, int $jd): bool
    {
        return $jy >= 1 && $jm >= 1 && $jm <= 12 && $jd >= 1 && $jd <= static::daysInMonth($jy, $jm);
    }

    /** تاریخ شمسی به شکل ۱۴۰۳/۰۵/۱۲ یا «۱۲ مرداد ۱۴۰۳». */
    public static function date(DateTimeInterface|string|null $date, bool $long = false): string
    {
        if (($date = static::parse($date)) === null) {
            return '—';
        }

        [$jy, $jm, $jd] = static::fromGregorian(
            (int) $date->format('Y'),
            (int) $date->format('n'),
            (int) $date->format('j'),
        );

        return static::digits($long
            ? sprintf('%d %s %d', $jd, static::MONTHS[$jm], $jy)
            : sprintf('%04d/%02d/%02d', $jy, $jm, $jd));
    }

    public static function dateTime(DateTimeInterface|string|null $date): string
    {
        if (($parsed = static::parse($date)) === null) {
            return '—';
        }

        return static::date($parsed).' - '.static::digits($parsed->format('H:i'));
    }

    /** نام روز هفته. */
    public static function weekday(DateTimeInterface|string|null $date): string
    {
        if (($parsed = static::parse($date)) === null) {
            return '—';
        }

        return static::WEEKDAYS[(int) $parsed->format('w')];
    }

    /** تبدیل رشته «۱۴۰۳/۰۵/۱۲» به تاریخ میلادی. */
    public static function parseJalali(?string $value): ?DateTimeImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $parts = preg_split('/[^0-9]+/', trim(static::latinDigits($value)), -1, PREG_SPLIT_NO_EMPTY);

        if (count($parts) < 3) {
            return null;
        }

        [$jy, $jm, $jd] = array_map('intval', array_slice($parts, 0, 3));

        if (! static::isValid($jy, $jm, $jd)) {
            return null;
        }

        [$gy, $gm, $gd] = static::toGregorian($jy, $jm, $jd);

        return new DateTimeImmutable(sprintf('%04d-%02d-%02d 00:00:00', $gy, $gm, $gd));
    }

    /** تبدیل اعداد لاتین به فارسی. */
    public static function digits(string|int|float|null $value): string
    {
        return strtr((string) $value, [
            '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
            '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
        ]);
    }

    /** تبدیل اعداد فارسی/عربی به لاتین برای ذخیره در دیتابیس. */
    public static function latinDigits(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '،' => ',',
        ]);
    }

    /** فاصله زمانی به فارسی: «۳ روز پیش» یا «۲ روز دیگر». */
    public static function ago(DateTimeInterface|string|null $date): string
    {
        if (($parsed = static::parse($date)) === null) {
            return '—';
        }

        $seconds = time() - $parsed->getTimestamp();

        if ($seconds < 0) {
            $seconds = -$seconds;

            return match (true) {
                $seconds < 3600 => static::digits(max(1, intdiv($seconds, 60))).' دقیقه دیگر',
                $seconds < 86400 => static::digits(intdiv($seconds, 3600)).' ساعت دیگر',
                default => static::digits(intdiv($seconds, 86400)).' روز دیگر',
            };
        }

        return match (true) {
            $seconds < 60 => 'همین حالا',
            $seconds < 3600 => static::digits(intdiv($seconds, 60)).' دقیقه پیش',
            $seconds < 86400 => static::digits(intdiv($seconds, 3600)).' ساعت پیش',
            $seconds < 2592000 => static::digits(intdiv($seconds, 86400)).' روز پیش',
            $seconds < 31536000 => static::digits(intdiv($seconds, 2592000)).' ماه پیش',
            default => static::digits(intdiv($seconds, 31536000)).' سال پیش',
        };
    }

    protected static function parse(DateTimeInterface|string|null $date): ?DateTimeImmutable
    {
        if ($date === null || $date === '') {
            return null;
        }

        if (is_string($date)) {
            try {
                return new DateTimeImmutable($date);
            } catch (\Exception) {
                return null;
            }
        }

        return DateTimeImmutable::createFromInterface($date);
    }

    /** @return array{leap:int, gy:int, march:int} */
    protected static function calendar(int $jy): array
    {
        $breaks = static::BREAKS;
        $count = count($breaks);
        $gy = $jy + 621;
        $leapJ = -14;
        $jp = $breaks[0];
        $jump = 0;

        for ($i = 1; $i < $count; $i++) {
            $jm = $breaks[$i];
            $jump = $jm - $jp;

            if ($jy < $jm) {
                break;
            }

            $leapJ += intdiv($jump, 33) * 8 + intdiv($jump % 33, 4);
            $jp = $jm;
        }

        $n = $jy - $jp;
        $leapJ += intdiv($n, 33) * 8 + intdiv(($n % 33) + 3, 4);

        if (($jump % 33) === 4 && ($jump - $n) === 4) {
            $leapJ++;
        }

        $leapG = intdiv($gy, 4) - intdiv((intdiv($gy, 100) + 1) * 3, 4) - 150;
        $march = 20 + $leapJ - $leapG;

        if (($jump - $n) < 6) {
            $n = $n - $jump + intdiv($jump + 4, 33) * 33;
        }

        $leap = (((($n + 1) % 33) - 1) % 4);

        if ($leap === -1) {
            $leap = 4;
        }

        return ['leap' => $leap, 'gy' => $gy, 'march' => $march];
    }

    protected static function jalaliToDay(int $jy, int $jm, int $jd): int
    {
        $r = static::calendar($jy);

        return static::gregorianToDay($r['gy'], 3, $r['march'])
            + ($jm - 1) * 31
            - intdiv($jm, 7) * ($jm - 7)
            + $jd - 1;
    }

    /** @return array{0:int,1:int,2:int} */
    protected static function dayToJalali(int $jdn): array
    {
        $gy = static::dayToGregorian($jdn)[0];
        $jy = $gy - 621;
        $r = static::calendar($jy);
        $jdn1f = static::gregorianToDay($gy, 3, $r['march']);

        if ($jdn < $jdn1f) {
            $jy--;
            $r = static::calendar($jy);
            $jdn1f = static::gregorianToDay($gy - 1, 3, $r['march']);
        }

        $k = $jdn - $jdn1f;

        if ($k <= 185) {
            return [$jy, 1 + intdiv($k, 31), ($k % 31) + 1];
        }

        $k -= 186;

        return [$jy, 7 + intdiv($k, 30), ($k % 30) + 1];
    }

    protected static function gregorianToDay(int $gy, int $gm, int $gd): int
    {
        $day = intdiv(($gy + intdiv($gm - 8, 6) + 100100) * 1461, 4)
            + intdiv(153 * (($gm + 9) % 12) + 2, 5)
            + $gd - 34840408;

        return $day - intdiv(intdiv($gy + 100100 + intdiv($gm - 8, 6), 100) * 3, 4) + 752;
    }

    /** @return array{0:int,1:int,2:int} */
    protected static function dayToGregorian(int $jdn): array
    {
        $j = 4 * $jdn + 139361631;
        $j += intdiv(intdiv(4 * $jdn + 183187720, 146097) * 3, 4) * 4 - 3908;
        $i = intdiv($j % 1461, 4) * 5 + 308;
        $gd = intdiv($i % 153, 5) + 1;
        $gm = intdiv($i, 153) % 12 + 1;
        $gy = intdiv($j, 1461) - 100100 + intdiv(8 - $gm, 6);

        return [$gy, $gm, $gd];
    }
}
