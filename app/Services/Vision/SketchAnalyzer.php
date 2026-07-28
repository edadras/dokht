<?php

namespace App\Services\Vision;

use App\Support\Jalali;
use InvalidArgumentException;

/**
 * تحلیل طرح دستی کاربر.
 *
 * ورودی نقطه‌های خود خط‌هاست، نه عکس آن‌ها. این عمداً است: وقتی کاربر روی بوم
 * می‌کشد، مختصات دقیق قلم را داریم و دیگر لازم نیست همان تصویر را دوباره
 * پردازش و از زمینه جدا کنیم. نتیجه بسیار پایدارتر است.
 *
 * چندضلعی‌ها با روش «زوج و فرد» پر می‌شوند و به همان نقاب دودویی تبدیل می‌شوند
 * که تحلیل عکس می‌سازد، پس استخراج ویژگی‌ها و دسته‌بندی دقیقاً یکی است.
 */
class SketchAnalyzer
{
    /** بزرگ‌ترین ضلع شبکه کاری. */
    public const RASTER = 180;

    /** حاشیه خالی دور شکل تا عملیات ریخت‌شناسی به لبه قاب نخورد. */
    public const PADDING = 6;

    public function __construct(
        protected GarmentClassifier $classifier = new GarmentClassifier,
        protected DesignProposal $proposals = new DesignProposal,
    ) {}

    /**
     * تحلیل کامل طرح و ساخت پیشنهاد.
     *
     * @param  array<int, mixed>  $strokes  یک چندضلعی یا فهرستی از چندضلعی‌ها
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function analyze(array $strokes, array $options = []): array
    {
        [$mask, $notes] = $this->silhouette($strokes);
        $features = SilhouetteFeatures::extract($mask, $notes);
        $classification = $this->classifier->classify($features);

        return $this->proposals->build('sketch', $mask, $features, $classification, $options);
    }

    /** اندازه‌گیری ویژگی‌ها از نقطه‌های طرح. */
    public function features(array $strokes): SilhouetteFeatures
    {
        [$mask, $notes] = $this->silhouette($strokes);

        return SilhouetteFeatures::extract($mask, $notes);
    }

    /**
     * تبدیل نقطه‌های طرح به نقاب دودویی.
     *
     * @return array{0: Silhouette, 1: array<int, string>}
     */
    public function silhouette(array $strokes): array
    {
        $polygons = $this->normalize($strokes);

        if ($polygons === []) {
            throw new InvalidArgumentException('طرح خالی است؛ دست‌کم یک خط بسته بکشید.');
        }

        $points = array_merge(...$polygons);
        $xs = array_column($points, 'x');
        $ys = array_column($points, 'y');
        $left = min($xs);
        $top = min($ys);
        $spanX = max(1e-6, max($xs) - $left);
        $spanY = max(1e-6, max($ys) - $top);
        $scale = (self::RASTER - 2 * self::PADDING) / max($spanX, $spanY);

        $width = (int) round($spanX * $scale) + 2 * self::PADDING + 1;
        $height = (int) round($spanY * $scale) + 2 * self::PADDING + 1;

        $mapped = [];

        foreach ($polygons as $polygon) {
            $mapped[] = array_map(fn ($point) => [
                'x' => ($point['x'] - $left) * $scale + self::PADDING,
                'y' => ($point['y'] - $top) * $scale + self::PADDING,
            ], $polygon);
        }

        $bits = array_fill(0, $width * $height, 0);

        foreach ($mapped as $polygon) {
            $this->fillPolygon($bits, $polygon, $width, $height);
            $this->strokePolygon($bits, $polygon, $width, $height);
        }

        $mask = (new Silhouette($width, $height, $bits))->closed()->largestComponent()->fillHoles();

        $notes = [
            'شکل مستقیم از نقطه‌های قلم شما ساخته شد ('
                .Jalali::digits(count($points)).' نقطه)، پس هیچ خطای جداسازی از زمینه در کار نیست.',
        ];

        if (count($mapped) > 1) {
            $notes[] = 'چند خط جدا کشیده بودید؛ فقط بزرگ‌ترین شکل بسته اندازه‌گیری شد.';
        }

        if ($mask->area() < 40) {
            $notes[] = 'طرح خیلی کوچک یا باز است؛ خط را ببندید تا شکل پر شود.';
        }

        return [$mask, $notes];
    }

    /**
     * یکدست‌کردن ورودی: یک چندضلعی یا فهرستی از چندضلعی‌ها.
     *
     * @return array<int, array<int, array{x: float, y: float}>>
     */
    protected function normalize(array $strokes): array
    {
        if ($strokes === []) {
            return [];
        }

        $first = reset($strokes);
        $groups = (is_array($first) && ! isset($first['x'])) ? $strokes : [$strokes];
        $polygons = [];

        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }

            $points = [];

            foreach ($group as $point) {
                if (! is_array($point) || ! isset($point['x'], $point['y']) || ! is_numeric($point['x']) || ! is_numeric($point['y'])) {
                    continue;
                }

                $points[] = ['x' => (float) $point['x'], 'y' => (float) $point['y']];
            }

            if (count($points) >= 3) {
                $polygons[] = $points;
            }
        }

        return $polygons;
    }

    /** پرکردن چندضلعی با روش زوج و فرد (خط جاروب افقی). */
    protected function fillPolygon(array &$bits, array $polygon, int $width, int $height): void
    {
        $count = count($polygon);

        for ($y = 0; $y < $height; $y++) {
            $centre = $y + 0.5;
            $crossings = [];

            for ($i = 0; $i < $count; $i++) {
                $a = $polygon[$i];
                $b = $polygon[($i + 1) % $count];

                if (($a['y'] <= $centre && $b['y'] > $centre) || ($b['y'] <= $centre && $a['y'] > $centre)) {
                    $t = ($centre - $a['y']) / ($b['y'] - $a['y']);
                    $crossings[] = $a['x'] + $t * ($b['x'] - $a['x']);
                }
            }

            if ($crossings === []) {
                continue;
            }

            sort($crossings);

            for ($i = 0; $i + 1 < count($crossings); $i += 2) {
                $from = max(0, (int) ceil($crossings[$i] - 0.5));
                $to = min($width - 1, (int) floor($crossings[$i + 1] - 0.5));

                for ($x = $from; $x <= $to; $x++) {
                    $bits[$y * $width + $x] = 1;
                }
            }
        }
    }

    /** کشیدن خود خط دور چندضلعی تا شکل‌های خیلی باریک هم گم نشوند. */
    protected function strokePolygon(array &$bits, array $polygon, int $width, int $height): void
    {
        $count = count($polygon);

        for ($i = 0; $i < $count; $i++) {
            $a = $polygon[$i];
            $b = $polygon[($i + 1) % $count];
            $steps = (int) max(1, ceil(max(abs($b['x'] - $a['x']), abs($b['y'] - $a['y']))));

            for ($step = 0; $step <= $steps; $step++) {
                $x = (int) round($a['x'] + ($b['x'] - $a['x']) * $step / $steps);
                $y = (int) round($a['y'] + ($b['y'] - $a['y']) * $step / $steps);

                if ($x >= 0 && $y >= 0 && $x < $width && $y < $height) {
                    $bits[$y * $width + $x] = 1;
                }
            }
        }
    }
}
