<?php

namespace App\Services\Pattern\Generators;

use InvalidArgumentException;

/**
 * پیاده‌سازی مشترکِ خانواده‌های جدولی.
 *
 * کلاسی که این را به کار می‌برد فقط `variants()` را می‌نویسد و شناسنامهٔ خودش
 * را از `spec()` می‌گیرد. باقی — کلید، نام، و اینکه رجیستری چطور هر ردیف را یک
 * مدلِ مستقل ببیند — همین‌جا یک بار حل شده است.
 */
trait HasVariants
{
    /** ردیفی که این نمونه روی آن تنظیم شده است. */
    protected string $variant = '';

    public function forVariant(string $key): static
    {
        if (! array_key_exists($key, static::variants())) {
            throw new InvalidArgumentException('ردیف «'.$key.'» در این خانواده نیست.');
        }

        $clone = clone $this;
        $clone->variant = $key;

        return $clone;
    }

    /** کلیدِ ردیفِ جاری. */
    public function variantKey(): string
    {
        return $this->variant !== '' ? $this->variant : (string) array_key_first(static::variants());
    }

    /**
     * شناسنامهٔ ردیفِ جاری.
     *
     * @return array<string, mixed>
     */
    protected function spec(): array
    {
        $key = $this->variantKey();

        return array_merge(['prefix' => $key], static::variants()[$key] ?? []);
    }

    /**
     * کلیدِ ژنراتور.
     *
     * برای یک خانواده معنای «کلیدِ یگانه» ندارد؛ رجیستری هم از variants
     * می‌خواندش نه از این. همین که ردیفِ جاری را برگرداند کافی است و هر جای
     * دیگری که کلید بخواهد (مثلاً پیشوندِ کدِ قطعه) درست کار می‌کند.
     */
    public static function key(): string
    {
        return (string) array_key_first(static::variants());
    }
}
