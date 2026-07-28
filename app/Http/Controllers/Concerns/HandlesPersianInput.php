<?php

namespace App\Http\Controllers\Concerns;

use App\Support\Jalali;
use DateTimeImmutable;
use Illuminate\Http\Request;

/**
 * تبدیل ورودی فارسی کاربر به داده قابل ذخیره.
 *
 * کاربر با کیبورد فارسی عدد می‌نویسد؛ پیش از اعتبارسنجی همه رقم‌ها لاتین می‌شوند
 * تا قاعده‌های numeric و date درست کار کنند.
 */
trait HandlesPersianInput
{
    /** رقم‌های فارسی چند فیلد (و آرایه‌ها) را لاتین می‌کند. */
    protected function normalizeDigits(Request $request, array $keys): void
    {
        $request->merge($this->rewrite($request, $keys, fn (string $value) => (string) Jalali::latinDigits($value)));
    }

    /** مثل بالا، ولی جداکننده هزارگان و فاصله را هم پاک می‌کند (مبلغ و اندازه). */
    protected function normalizeNumbers(Request $request, array $keys): void
    {
        $request->merge($this->rewrite($request, $keys, fn (string $value) => $this->numeric($value)));
    }

    /** رشته عددی فارسی را به رشته عددی لاتین تمیز تبدیل می‌کند. */
    protected function numeric(?string $value): string
    {
        $value = (string) Jalali::latinDigits((string) $value);

        return trim(str_replace([',', '٫', ' ', "\u{200c}"], ['', '.', '', ''], $value));
    }

    /** تاریخ شمسی «۱۴۰۵/۰۵/۱۲» را به تاریخ میلادی تبدیل می‌کند. */
    protected function jalaliDate(?string $value): ?DateTimeImmutable
    {
        return Jalali::parseJalali($value);
    }

    /** مقدار پولی وارد‌شده را به عدد تبدیل می‌کند. */
    protected function amount(mixed $value): float
    {
        return (float) $this->numeric(is_scalar($value) ? (string) $value : '');
    }

    /** @return array<string, mixed> */
    private function rewrite(Request $request, array $keys, callable $callback): array
    {
        $replace = [];

        foreach ($keys as $key) {
            if (! $request->has($key)) {
                continue;
            }

            $value = $request->input($key);

            if (is_array($value)) {
                $replace[$key] = array_map(
                    fn ($item) => is_string($item) ? $callback($item) : $item,
                    $value,
                );
            } elseif (is_string($value)) {
                $replace[$key] = $callback($value);
            }
        }

        return $replace;
    }
}
