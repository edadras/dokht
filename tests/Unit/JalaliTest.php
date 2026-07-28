<?php

namespace Tests\Unit;

use App\Support\Jalali;
use PHPUnit\Framework\TestCase;

class JalaliTest extends TestCase
{
    public function test_it_converts_gregorian_dates_to_jalali(): void
    {
        $this->assertSame([1403, 1, 1], Jalali::fromGregorian(2024, 3, 20));
        $this->assertSame([1404, 1, 1], Jalali::fromGregorian(2025, 3, 21));
        $this->assertSame([1405, 5, 6], Jalali::fromGregorian(2026, 7, 28));
        $this->assertSame([1378, 10, 10], Jalali::fromGregorian(1999, 12, 31));
    }

    public function test_it_converts_jalali_dates_back_to_gregorian(): void
    {
        $this->assertSame([2024, 3, 20], Jalali::toGregorian(1403, 1, 1));
        $this->assertSame([2026, 7, 28], Jalali::toGregorian(1405, 5, 6));
    }

    public function test_conversion_round_trips_for_a_whole_year(): void
    {
        for ($month = 1; $month <= 12; $month++) {
            $days = Jalali::daysInMonth(1404, $month);

            foreach ([1, (int) ceil($days / 2), $days] as $day) {
                [$gy, $gm, $gd] = Jalali::toGregorian(1404, $month, $day);

                $this->assertSame(
                    [1404, $month, $day],
                    Jalali::fromGregorian($gy, $gm, $gd),
                    "round trip failed for 1404/{$month}/{$day}"
                );
            }
        }
    }

    public function test_it_knows_leap_years_and_month_lengths(): void
    {
        $this->assertTrue(Jalali::isLeapYear(1403));
        $this->assertFalse(Jalali::isLeapYear(1404));
        $this->assertSame(30, Jalali::daysInMonth(1403, 12));
        $this->assertSame(29, Jalali::daysInMonth(1404, 12));
        $this->assertSame(31, Jalali::daysInMonth(1404, 1));
        $this->assertSame(30, Jalali::daysInMonth(1404, 7));
    }

    public function test_it_formats_dates_in_persian(): void
    {
        $this->assertSame('۱۴۰۵/۰۵/۰۶', Jalali::date('2026-07-28'));
        $this->assertSame('۶ مرداد ۱۴۰۵', Jalali::date('2026-07-28', long: true));
        $this->assertSame('—', Jalali::date(null));
    }

    public function test_it_parses_a_persian_date_string(): void
    {
        $this->assertSame('2026-07-28', Jalali::parseJalali('۱۴۰۵/۰۵/۰۶')?->format('Y-m-d'));
        $this->assertSame('2026-07-28', Jalali::parseJalali('1405-05-06')?->format('Y-m-d'));
        $this->assertNull(Jalali::parseJalali('1405/13/01'));
        $this->assertNull(Jalali::parseJalali(''));
    }

    public function test_it_normalises_digits_in_both_directions(): void
    {
        $this->assertSame('۰۹۱۲۳۴۵۶۷۸۹', Jalali::digits('09123456789'));
        $this->assertSame('09123456789', Jalali::latinDigits('۰۹۱۲۳۴۵۶۷۸۹'));
        $this->assertSame('09123456789', Jalali::latinDigits('٠٩١٢٣٤٥٦٧٨٩'));
        $this->assertNull(Jalali::latinDigits(null));
    }

    public function test_it_describes_relative_time_in_persian(): void
    {
        $this->assertSame('همین حالا', Jalali::ago(new \DateTimeImmutable('-10 seconds')));
        $this->assertSame('۲ روز پیش', Jalali::ago(new \DateTimeImmutable('-2 days -1 hour')));
        $this->assertSame('۳ روز دیگر', Jalali::ago(new \DateTimeImmutable('+3 days +1 hour')));
    }
}
