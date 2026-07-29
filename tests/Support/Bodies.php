<?php

namespace Tests\Support;

use App\Support\Measurements;

/**
 * بدن‌های آزمون.
 *
 * چرا این فایل هست: `Measurements::fromSize()` فقط سایزهای جدولی ۳۴ تا ۴۸ را
 * می‌شناسد و برای هر کلید ناشناخته **بی‌صدا سایز ۴۰ را برمی‌گرداند**. آزمونی که
 * `fromSize('کودک')` صدا بزند، خیال می‌کند مدل را روی بدن کودک سنجیده، در حالی
 * که دوباره همان سایز ۴۰ را سنجیده است. اینجا بدنِ خواسته‌شده یا واقعاً وجود
 * دارد یا آزمون سر و صدا می‌کند.
 *
 * بدن‌های سفارشی همان‌هایی‌اند که ممیزی کاتالوگ استفاده می‌کند و عمداً «سخت»اند:
 * تنه کوتاه، سرشانه باریک نسبت به سینه، و اختلاف زیاد سینه تا کمر.
 */
final class Bodies
{
    /** @var array<string, array<string, float|int>> */
    public const BESPOKE = [
        'کودک' => ['height' => 116, 'bust' => 60, 'waist' => 56, 'hip' => 64, 'shoulder_width' => 27, 'arm_length' => 38],
        'سینه‌درشت' => ['height' => 168, 'bust' => 118, 'waist' => 70, 'hip' => 100, 'shoulder_width' => 34, 'arm_length' => 59],
        'بلندقد' => ['height' => 195, 'bust' => 84, 'waist' => 66, 'hip' => 90, 'shoulder_width' => 44, 'arm_length' => 72],
        'کوتاه و درشت' => ['height' => 148, 'bust' => 124, 'waist' => 118, 'hip' => 126, 'shoulder_width' => 40, 'arm_length' => 50],
    ];

    /**
     * اندازه کامل یک بدن، چه سایز جدولی باشد چه سفارشی.
     *
     * @return array<string, mixed>
     */
    public static function body(string $name): array
    {
        if (isset(self::BESPOKE[$name])) {
            return Measurements::complete(self::BESPOKE[$name]);
        }

        if (! array_key_exists($name, Measurements::SIZE_TABLE)) {
            throw new \InvalidArgumentException("بدن «{$name}» نه سایز جدولی است نه بدن سفارشی آزمون.");
        }

        return Measurements::complete(Measurements::SIZE_TABLE[$name]);
    }

    /**
     * پنج بدنی که هر کاتالوگ باید رویشان بایستد.
     *
     * @return array<int, string>
     */
    public static function bodies(): array
    {
        return ['34', '40', '48', 'کودک', 'بلندقد'];
    }
}
