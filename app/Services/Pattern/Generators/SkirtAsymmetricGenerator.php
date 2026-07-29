<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * دامن نامتقارن.
 *
 * دم دامن در یک پهلو کوتاه و در پهلوی دیگر بلند است، پس هیچ‌کدام از دو قطعه روی
 * تای پارچه بریده نمی‌شود و هر قطعه کامل (جلوی کامل و پشت کامل) درفت می‌شود.
 * هر دو درز پهلو جداگانه با قد خودشان گونیا می‌شوند تا جلو و پشت دقیقاً به هم برسند.
 */
class SkirtAsymmetricGenerator extends SkirtBaseGenerator
{
    public static function key(): string
    {
        return 'skirt_asymmetric';
    }

    public function label(): string
    {
        return 'دامن نامتقارن';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            [
                'length' => [
                    'label' => 'قد پهلوی کوتاه', 'min' => 25, 'max' => 110, 'step' => 1, 'default' => 48,
                    'unit' => 'سانتی‌متر',
                ],
                'long_length' => [
                    'label' => 'قد پهلوی بلند', 'min' => 30, 'max' => 140, 'step' => 1, 'default' => 88,
                    'unit' => 'سانتی‌متر',
                ],
                'flare' => [
                    'label' => 'گشادی هر پهلو در دم', 'min' => 0, 'max' => 30, 'step' => 1, 'default' => 10,
                    'unit' => 'سانتی‌متر',
                ],
            ],
            $this->waistParams(0.5, 4),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $mx = $this->skirtMetrics($measurements, $ease, $params);
        $short = (float) $this->param($params, 'length', 48);
        $long = max($short + 2, (float) $this->param($params, 'long_length', 88));
        $flare = (float) $this->param($params, 'flare', 10);

        $pieces = [
            $this->asymPanel($mx, ['side' => 'front', 'short' => $short, 'long' => $long, 'flare' => $flare, 'dart_count' => 1]),
            $this->asymPanel($mx, ['side' => 'back', 'short' => $short, 'long' => $long, 'flare' => $flare, 'dart_count' => 2]),
        ];

        return $this->finishSkirt(array_merge($pieces, $this->bandPieces($mx, $params)), $params);
    }

    /** یک قطعه کامل (نه روی تا) با دو قد متفاوت در دو پهلو. */
    protected function asymPanel(array $mx, array $o): array
    {
        $isFront = $o['side'] === 'front';
        $short = (float) $o['short'];
        $long = (float) $o['long'];
        $flare = (float) $o['flare'];
        $hipY = min($mx['hip_y'], $short * 0.5);
        $waistX = $mx['side_waist_x'];
        $quarterHip = $mx['quarter_hip'];
        $hemX = max(3.0, $quarterHip + $flare);
        $middle = ($short + $long) / 2;

        $balance = $mx['balance'];
        $dartIntake = max(0.0, $mx['dart_total'] + ($isFront ? -$balance : $balance));
        $panelWaist = $mx['quarter_waist'] + ($isFront ? $balance : -$balance);
        $dartCount = $dartIntake > 0.8 ? max(1, (int) $o['dart_count']) : 0;

        $hipControl = $waistX + (($quarterHip - $waistX) * 0.55);

        $build = fn (float $yRight, float $yLeft) => [
            Geometry::curve(-$waistX, 0, -$hipControl, $hipY * 0.36),
            Geometry::point($waistX, 0),
            Geometry::curve($quarterHip, $hipY, $hipControl, $hipY * 0.36),
            Geometry::point($hemX, $yRight),
            Geometry::curve(0, $middle, $hemX * 0.55, $middle + (($yRight - $middle) * 0.2)),
            Geometry::curve(-$hemX, $yLeft, -$hemX * 0.55, $middle + (($yLeft - $middle) * 0.2)),
            Geometry::point(-$quarterHip, $hipY),
        ];

        $yRight = $this->fitSeamLength(
            fn (float $y) => Geometry::edgesLength($build($y, $short), [1, 2]),
            $long,
            $long * 0.3,
            $long,
        );
        $yLeft = $this->fitSeamLength(
            fn (float $y) => Geometry::edgesLength($build($yRight, $y), [5, 6]),
            $short,
            $short * 0.3,
            $short,
        );

        $outline = $build($yRight, $yLeft);
        $darts = [];

        for ($i = 0; $i < $dartCount; $i++) {
            $x = $waistX * (($i + 1) / ($dartCount + 1));

            foreach ([$x, -$x] as $index => $centerX) {
                $darts[] = $this->dart(
                    'waist',
                    'ساسون کمر '.$this->fa(($i * 2) + $index + 1),
                    0,
                    $centerX,
                    0,
                    $dartIntake / $dartCount,
                    $centerX,
                    $isFront ? $hipY * 0.55 : $hipY * 0.75,
                );
            }
        }

        return $this->piece([
            'code' => $isFront ? 'asym-front' : 'asym-back',
            'name' => $isFront ? 'دامن نامتقارن جلو' : 'دامن نامتقارن پشت',
            'cut_quantity' => 1,
            'outline' => $outline,
            'grainline' => $this->grainline(0, 3, $short - 3),
            'darts' => $darts,
            'notches' => [
                $this->notch($quarterHip, $hipY, 1, 'نشانه باسن، پهلوی بلند', 'hip_long'),
                $this->notch(-$quarterHip, $hipY, 6, 'نشانه باسن، پهلوی کوتاه', 'hip_short'),
            ],
            'markers' => [
                $this->marker('hip', 'خط باسن', -$quarterHip, $hipY, $quarterHip),
                $this->marker($isFront ? 'cf' : 'cb', $isFront ? 'خط مرکز جلو' : 'خط مرکز پشت', 0, 0, 0, $middle),
            ],
            'meta' => [
                'part' => $isFront ? 'skirt_front' : 'skirt_back',
                'edges' => ['waist', 'side', 'side', 'hem', 'hem', 'side', 'side'],
                'fold_edges' => [],
                'side' => $isFront ? 'front' : 'back',
                'waist_edges' => [0],
                'side_edges' => [1, 2, 5, 6],
                'hem_edges' => [3, 4],
                'waist_target' => $mx['waist_target'],
                'waist_finished' => round($panelWaist * 2, 2),
                'seam_long' => Geometry::edgesLength($outline, [1, 2]),
                'seam_short' => Geometry::edgesLength($outline, [5, 6]),
                'hip_y' => round($hipY, 2),
                'fullness' => [],
                'notes' => [
                    'قد پهلوی بلند '.$this->fa(round($long, 1)).' و پهلوی کوتاه '
                        .$this->fa(round($short, 1)).' سانتی‌متر است؛ دم دامن روی مرکز '
                        .$this->fa(round($middle, 1)).' سانتی‌متر می‌افتد.',
                ],
            ],
        ]);
    }
}
