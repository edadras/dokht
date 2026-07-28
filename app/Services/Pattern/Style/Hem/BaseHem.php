<?php

namespace App\Services\Pattern\Style\Hem;

use App\Services\Pattern\Geometry;
use App\Services\Pattern\Style\Detail\DetailStyle;

/**
 * پایه مشترک سبک‌های لبه (دامنه لباس).
 *
 * لبه روی همان قطعه‌هایی اجرا می‌شود که «دم» دارند: اگر لباس پایین‌تنه دارد روی
 * دامن یا شلوار، وگرنه روی خود بالاتنه. هر تغییری که طول درز پهلو را عوض کند روی
 * جلو و پشت با هم اجرا می‌شود تا دو درز باز هم هم‌اندازه بمانند.
 */
abstract class BaseHem extends DetailStyle
{
    public static function group(): string
    {
        return 'hem';
    }

    /**
     * پارامتر مشترک: پهنای لبه‌ای که تو برگردانده می‌شود.
     *
     * کمینه و گام هم پارامتر است، چون لبه گرد و لبه نوک‌دار «لبه باریک» می‌خواهند
     * و پیش‌فرضشان (۱٫۲ و ۰٫۸ سانتی‌متر) روی شبکه درشتِ نیم‌سانتی نمی‌نشیند. هر
     * پیش‌فرضی که این‌جا داده می‌شود باید دقیقاً روی کمینه + n×گام بیفتد، وگرنه
     * فرم با مقداری باز می‌شود که خودش نمی‌پذیرد و دکمه «بساز» بی‌صدا کار نمی‌کند.
     */
    protected function allowanceParam(float $default = 3.0, float $min = 0.5, float $max = 10.0, float $step = 0.5): array
    {
        return [
            'label' => 'پهنای تو گذاشتن لبه', 'min' => $min, 'max' => $max, 'step' => $step,
            'default' => $this->onGrid($default, $min, $max, $step),
            'unit' => 'سانتی‌متر', 'hint' => 'همین اندازه پارچه بیشتر لازم است.',
        ];
    }

    /** نزدیک‌ترین مقدار روی شبکه «کمینه + n×گام» که از بازه بیرون نزند. */
    protected function onGrid(float $value, float $min, float $max, float $step): float
    {
        if ($step <= 0) {
            return round(max($min, min($max, $value)), 4);
        }

        $steps = round((max($min, min($max, $value)) - $min) / $step);
        $steps = max(0.0, min(floor(($max - $min) / $step), $steps));

        return round($min + ($steps * $step), 4);
    }

    public function supports(array $pieces, array $context): true|string
    {
        if ($this->hemHostIndexes($pieces) === []) {
            return 'این لباس لبه‌ای برای شکل‌دادن ندارد؛ اول یک بالاتنه یا پایین‌تنه انتخاب کنید.';
        }

        return true;
    }

    /**
     * دو سر لبه دم: کدام روی خط مرکز است و کدام روی درز پهلو.
     *
     * @return array{center: int, side: int}
     */
    protected function hemEnds(array $piece, int $edge): array
    {
        $outline = array_values($piece['outline']);
        $count = count($outline);
        $a = $edge % $count;
        $b = ($edge + 1) % $count;

        return ((float) $outline[$a]['x']) <= ((float) $outline[$b]['x'])
            ? ['center' => $a, 'side' => $b]
            : ['center' => $b, 'side' => $a];
    }

    /**
     * شکل‌دادن لبه دم: بالا یا پایین بردن دو سر و کشیدن یک منحنی بینشان.
     *
     * مقدار مثبت یعنی بلندتر (پایین‌تر) و منفی یعنی کوتاه‌تر.
     */
    protected function shapeHem(array $piece, int $edge, float $centerDelta, float $sideDelta, float $curve = 0.0): array
    {
        $outline = array_values($piece['outline']);
        $count = count($outline);
        $ends = $this->hemEnds($piece, $edge);

        foreach (['center' => $centerDelta, 'side' => $sideDelta] as $which => $delta) {
            if (abs($delta) < 0.01) {
                continue;
            }

            $index = $ends[$which];
            $outline[$index]['y'] = round(((float) $outline[$index]['y']) + $delta, 3);

            // نقطه کنترل درزی که به این نقطه می‌رسد هم‌راه می‌شود تا درز صاف بماند
            if (isset($outline[$index]['cx'], $outline[$index]['cy'])) {
                $outline[$index]['cy'] = round(((float) $outline[$index]['cy']) + ($delta * 0.5), 3);
            }
        }

        // منحنی خود لبه: نقطه کنترل روی نقطه پایانی لبه ذخیره می‌شود
        $end = ($edge + 1) % $count;
        $start = $edge % $count;

        if (abs($curve) > 0.01) {
            $middle = Geometry::lerp(
                ['x' => (float) $outline[$start]['x'], 'y' => (float) $outline[$start]['y']],
                ['x' => (float) $outline[$end]['x'], 'y' => (float) $outline[$end]['y']],
                0.5,
            );

            $outline[$end]['curve'] = true;
            $outline[$end]['cx'] = round($middle['x'], 3);
            $outline[$end]['cy'] = round($middle['y'] + $curve, 3);
        }

        $piece['outline'] = $outline;

        return Geometry::normalizePiece($piece);
    }

    /** ثبت پهنای لبه‌ای که تو برگردانده می‌شود (روی جای دوخت لبه‌های «دم» اثر می‌گذارد). */
    protected function setHemAllowance(array $piece, float $allowance): array
    {
        $piece['meta']['hem_allowance'] = round($allowance, 2);
        $piece['meta']['allowance_overrides']['hem'] = round($allowance, 2);

        return $piece;
    }

    /**
     * شیب‌دادن همه سرهای لبه دم بین چپ‌ترین و راست‌ترین نقطه.
     *
     * برای لبه نامتقارن؛ چون روی جلو و پشت یکسان اجرا می‌شود، درزهای پهلو باز هم
     * هم‌اندازه می‌مانند.
     */
    protected function slantHem(array $piece, array $edges, float $leftDelta, float $rightDelta): array
    {
        $outline = array_values($piece['outline']);
        $count = count($outline);
        $indexes = [];

        foreach ($edges as $edge) {
            $indexes[$edge % $count] = true;
            $indexes[($edge + 1) % $count] = true;
        }

        $indexes = array_keys($indexes);
        $xs = array_map(fn ($index) => (float) $outline[$index]['x'], $indexes);
        $minX = min($xs);
        $maxX = max($xs);
        $span = max(0.01, $maxX - $minX);

        foreach ($indexes as $index) {
            $ratio = (((float) $outline[$index]['x']) - $minX) / $span;
            $delta = $leftDelta + (($rightDelta - $leftDelta) * $ratio);

            $outline[$index]['y'] = round(((float) $outline[$index]['y']) + $delta, 3);

            if (isset($outline[$index]['cy'])) {
                $outline[$index]['cy'] = round(((float) $outline[$index]['cy']) + ($delta * 0.5), 3);
            }
        }

        $piece['outline'] = $outline;

        return Geometry::normalizePiece($piece);
    }

    /** بلندی فعلی قطعه؛ برای گزارش‌کردن تغییر قد لباس. */
    protected function heightOf(array $piece): float
    {
        return Geometry::height($piece['outline'] ?? []);
    }
}
