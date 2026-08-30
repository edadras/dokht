<?php

namespace App\Services\Simulation;

/**
 * همان مانکنی که مرورگر می‌سازد، ولی روی سرور.
 *
 * بسته «چیدن اولیه» فقط نامِ تراز را نمی‌فرستد، عددش را می‌فرستد؛ ولی مرورگر
 * قطعه را روی بدنی می‌نشاند که خودش از همین اندازه‌ها ساخته. پس این دو باید یک
 * بدن باشند. فرمول‌ها مو‌به‌مو از buildBody() و drapeBody() در
 * resources/js/lib/mannequin.js برداشته شده‌اند.
 *
 * تا امروز این‌جا یک جدولِ *ثابتِ* کسری از قد بود — یادگارِ مانکنِ قدیمی که همه را
 * با یک نسبت می‌ساخت. مانکن که از روی اندازه‌های خودِ مشتری بازنویسی شد، این
 * جدول جا ماند و کسی متوجه نشد: سرور قطعه‌ها را روی یک بدن می‌چید و مرورگر بدنِ
 * دیگری می‌کشید. اندازه گرفته شد، برای سایز ۴۰: کمرِ جدول ۳۸٫۶ سانتی‌متر زیرِ
 * مهرهٔ گردن بود و کمرِ مشتری ۴۱٫۲، و باسن ۵ سانتی‌متر بالاتر از جایش. روی هم
 * ۷٫۶ سانتی‌متر. بالاتنه با لبهٔ دامن جور درنمی‌آمد و درزِ کمر کلِ لباس را ده
 * سانتی‌متر پایین می‌کشید — پیش از آنکه یک قدمِ شبیه‌سازی برداشته شود.
 *
 * @internal
 */
final class DrapeBody
{
    /** قد بدن به سانتی‌متر. */
    public readonly float $height;

    /** @var array<string, float> ارتفاع هر تراز، ضریبی از قد */
    public readonly array $levels;

    /** @var array<string, float> شعاع هر تراز به سانتی‌متر */
    public readonly array $radii;

    /** بلندی دست به سانتی‌متر (برای پیدا کردن تراز مچ). */
    public readonly float $armLength;

    /**
     * @param  array<string, float|int|string>  $body  اندازه‌های کامل‌شده بدن
     */
    public function __construct(array $body)
    {
        $get = fn (string $key, float $fallback) => ((float) ($body[$key] ?? 0)) > 0
            ? (float) $body[$key]
            : $fallback;

        $this->height = max(80.0, $get('height', 165.0));

        $bust = $get('bust', 92.0);
        $hip = $get('hip', 98.0);

        $this->levels = $this->measure(
            $bust,
            $get('waist', 74.0),
            $hip,
            $get('back_length', $this->height * 0.245),
            $get('waist_to_hip', $this->height * 0.125),
            $get('inseam', $this->height * 0.45),
        );

        $this->radii = [
            'hip' => self::radius($hip),
            'highHip' => self::radius($get('high_hip', $hip - 8)),
            'waist' => self::radius($get('waist', 74.0)),
            'underBust' => self::radius($get('under_bust', $bust - 14)),
            'bust' => self::radius($bust),
            'neck' => self::radius($get('neck', 36.0)),
            'armhole' => self::radius($get('armhole', 40.0)),
            'bicep' => self::radius($get('bicep', 28.0)),
            'wrist' => self::radius($get('wrist', 16.0)),
            'thigh' => self::radius($get('thigh', 56.0)),
            'knee' => self::radius($get('knee', 37.0)),
            'ankle' => self::radius($get('ankle', 23.0)),
            'shoulder' => $get('shoulder_width', 39.0) / 2,
        ];

        $this->armLength = $get('arm_length', 58.0);
    }

    /** شعاع یک دور بدن. */
    public static function radius(float $girth): float
    {
        return max(1.0, $girth) / (2 * M_PI);
    }

    /**
     * ترازهای بدن از روی اندازه‌های خودِ شخص، ضریبی از قد.
     *
     * مانکن ترازها را از *مهرهٔ گردن* می‌شمارد، نه از نوکِ شانه: «قد بالاتنهٔ
     * پشت» از همان مهره تا کمر اندازه گرفته می‌شود. اندازه‌های کوچک‌ترین فاصله
     * (gap) هم برای این است که دو تراز روی هم نیفتند وقتی اندازه‌ها با هم
     * نمی‌خوانند.
     *
     * @return array<string, float>
     */
    protected function measure(
        float $bust,
        float $waist,
        float $hip,
        float $backLength,
        float $waistToHip,
        float $inseam,
    ): array {
        $height = $this->height;
        $clamp = fn (float $value) => max(0.0, min(1.0, $value));
        $lerp = fn (float $from, float $to, float $t) => $from + ($to - $from) * $t;

        // کودکانگی: قد کوتاه و دورهای نزدیک به هم — سرِ کودک نسبت به قدش بزرگ‌تر است
        $childish = $clamp((150 - $height) / 55) * $clamp(1 - (abs($hip - $bust) / 18));

        $headHeight = $height * $lerp(0.128, 0.165, $childish);
        $neckY = $headHeight * 1.14;
        $shoulderY = $neckY + ($height * $lerp(0.038, 0.03, $childish));

        $gap = $height * 0.022;
        $bustY = max($neckY + ($backLength * 0.58), $shoulderY + $gap);
        $underBustY = max($neckY + ($backLength * 0.75), $bustY + $gap);
        $waistY = max($neckY + $backLength, $underBustY + $gap);
        $highHipY = $waistY + ($waistToHip * 0.45);
        $hipY = $waistY + $waistToHip;
        $crotchY = $hipY + ($height * 0.045);

        // مانکن نباید از خودِ مشتری بلندتر شود؛ قدِ گفته‌شده حرفِ آخر را می‌زند
        $ankleY = min($crotchY + ($inseam * 0.95), $height * 0.985);

        // حلقهٔ آستین میانِ سرشانه و سینه است؛ کسی جای دقیقش را اندازه نمی‌گیرد
        $armholeY = $lerp($shoulderY, $bustY, 0.47);
        $kneeY = $crotchY + (($ankleY - $crotchY) * 0.5);

        // مانکن از بالای سر به پایین می‌شمارد و بسته از کف به بالا
        $up = fn (float $y) => ($height - $y) / $height;

        return [
            'ankle' => $up($ankleY),
            'knee' => $up($kneeY),
            'crotch' => $up($crotchY),
            'hip' => $up($hipY),
            'highHip' => $up($highHipY),
            'waist' => $up($waistY),
            'underBust' => $up($underBustY),
            'bust' => $up($bustY),
            'armhole' => $up($armholeY),
            'shoulder' => $up($shoulderY),
            'neck' => $up($neckY),
            'chin' => $up($headHeight * 0.92),
            'top' => 1.0,
        ];
    }

    /** ارتفاع یک تراز، ضریبی از قد. */
    public function level(string $key): float
    {
        return $this->levels[$key] ?? $this->levels['waist'];
    }

    /** ارتفاع مچ دست، ضریبی از قد (دست آویزان از تراز حلقه آستین). */
    public function wristLevel(): float
    {
        return max(0.06, $this->levels['armhole'] - ($this->armLength / $this->height));
    }

    /**
     * نزدیک‌ترین تراز بدن به یک ارتفاع، از میان نامزدهای داده‌شده.
     *
     * @param  array<int, string>  $candidates  نام شعاع‌ها (کلید جدول radii)
     */
    public function nearestRadius(float $atHeight, array $candidates, array $levels = []): string
    {
        $best = $candidates[0] ?? 'bust';
        $bestGap = INF;

        foreach ($candidates as $key) {
            $level = $levels[$key] ?? $this->levels[$key] ?? $this->levels['waist'];
            $gap = abs($level - $atHeight);

            if ($gap < $bestGap) {
                $bestGap = $gap;
                $best = $key;
            }
        }

        return $best;
    }
}
