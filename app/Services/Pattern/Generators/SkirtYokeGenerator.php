<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * دامن یوک‌دار.
 *
 * به جای ساسون، بالای دامن با یک یوکِ منحنی قالب می‌شود: نوک ساسون‌ها روی درزِ
 * یوک گذاشته می‌شود و ساسون در همان درز بسته می‌شود، پس لبه کمرِ یوک دقیقاً به
 * اندازه یک‌چهارم دور کمر است و هیچ ساسونی روی آن نمی‌ماند.
 */
class SkirtYokeGenerator extends SkirtBaseGenerator
{
    public static function key(): string
    {
        return 'skirt_yoke';
    }

    public function label(): string
    {
        return 'دامن یوک‌دار';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->lengthParam(62, 30, 115),
            [
                'yoke_depth' => [
                    'label' => 'بلندی یوک', 'min' => 5, 'max' => 22, 'step' => 0.5, 'default' => 12,
                    'unit' => 'سانتی‌متر', 'hint' => 'از خط کمر تا درز یوک روی مرکز جلو.',
                ],
                'lower_style' => [
                    'label' => 'پایین یوک', 'type' => 'select', 'default' => 'flare',
                    'options' => ['flare' => 'کلوش', 'gather' => 'چین‌دار', 'straight' => 'راسته'],
                ],
                'flare' => [
                    'label' => 'گشادی هر پهلو در دم', 'min' => 0, 'max' => 30, 'step' => 1, 'default' => 9,
                    'unit' => 'سانتی‌متر',
                ],
                'gather_ratio' => [
                    'label' => 'نسبت پُری چین (اگر چین‌دار باشد)', 'min' => 1, 'max' => 2.5, 'step' => 0.05,
                    'default' => 1.5,
                ],
            ],
            $this->waistParams(0.6, 4),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $mx = $this->skirtMetrics($measurements, $ease, $params);
        $length = (float) $this->param($params, 'length', 62);
        $depth = min((float) $this->param($params, 'yoke_depth', 12), $mx['hip_y'] - 2);
        $style = (string) $this->param($params, 'lower_style', 'flare');
        $flare = $style === 'straight' ? 0.0 : (float) $this->param($params, 'flare', 9);
        $ratio = max(1.0, (float) $this->param($params, 'gather_ratio', 1.5));

        $pieces = [];

        foreach (['front', 'back'] as $side) {
            $isFront = $side === 'front';
            $panelWaist = $mx['quarter_waist'] + ($isFront ? $mx['balance'] : -$mx['balance']);
            $yokeX = $this->widthAt($mx, $depth);

            $pieces[] = $this->yokePiece($mx, $panelWaist, $yokeX, $depth, $side);
            $pieces[] = $this->lowerPiece($mx, $yokeX, $depth, $length, $flare, $style === 'gather' ? $ratio : 1.0, $side);
        }

        return $this->finishSkirt(array_merge($pieces, $this->bandPieces($mx, $params)), $params);
    }

    /** پهنای نیم‌پنل روی عمق دلخواه، از روی منحنی پهلوی بلوک. */
    protected function widthAt(array $mx, float $depth): float
    {
        $from = ['x' => $mx['side_waist_x'], 'y' => 0.0];
        $control = [
            'x' => $mx['side_waist_x'] + (($mx['quarter_hip'] - $mx['side_waist_x']) * 0.55),
            'y' => $mx['hip_y'] * 0.36,
        ];
        $end = ['x' => $mx['quarter_hip'], 'y' => $mx['hip_y']];

        $low = 0.0;
        $high = 1.0;

        for ($i = 0; $i < 24; $i++) {
            $mid = ($low + $high) / 2;

            if (Geometry::quadraticAt($from, $control, $end, $mid)['y'] > $depth) {
                $high = $mid;
            } else {
                $low = $mid;
            }
        }

        return Geometry::quadraticAt($from, $control, $end, ($low + $high) / 2)['x'];
    }

    /** یوک: کمر بی‌ساسون و لبه پایین کمی منحنی. */
    protected function yokePiece(array $mx, float $panelWaist, float $yokeX, float $depth, string $side): array
    {
        $isFront = $side === 'front';
        $dip = 0.4;

        $outline = [
            Geometry::point(0, 0),
            Geometry::point($panelWaist, 0),
            Geometry::curve($yokeX, $depth, $panelWaist + (($yokeX - $panelWaist) * 0.6), $depth * 0.4),
            Geometry::curve(0, $depth, $yokeX * 0.5, $depth + $dip),
        ];

        return $this->piece([
            'code' => 'yoke-'.$side,
            'name' => $isFront ? 'یوک جلو' : 'یوک پشت',
            'cut_quantity' => 1,
            'on_fold' => true,
            'outline' => $outline,
            'grainline' => $this->grainline($panelWaist * 0.5, 1, $depth - 1),
            'markers' => [
                $this->marker($isFront ? 'cf' : 'cb', $isFront ? 'خط مرکز جلو' : 'خط مرکز پشت', 0, 0, 0, $depth),
            ],
            'meta' => [
                'part' => 'yoke',
                'edges' => ['waist', 'side', 'default', 'default'],
                'fold_edges' => [3],
                'side' => $side,
                'waist_edges' => [0],
                'side_edges' => [1],
                'waist_target' => $mx['waist_target'],
                'waist_finished' => round($panelWaist, 2),
                'seam_length' => Geometry::edgesLength($outline, [1]),
                'seam_group' => 'yoke',
                'yoke_depth' => round($depth, 2),
                'fullness' => [],
                'notes' => [
                    'ساسون کمر داخل درز یوک بسته شده است، پس لبه کمر یوک بدون ساسون و به اندازه '
                        .$this->fa(round($panelWaist, 1)).' سانتی‌متر است.',
                ],
            ],
        ]);
    }

    /** پنل پایین یوک: کلوش، راسته یا چین‌دار. */
    protected function lowerPiece(array $mx, float $yokeX, float $depth, float $length, float $flare, float $ratio, string $side): array
    {
        $isFront = $side === 'front';
        $panelLength = $length - $depth;
        $topX = $yokeX * $ratio;
        $hemX = max(3.0, ($yokeX * $ratio) + $flare);
        $dip = 0.4;

        $build = fn (float $yHem) => [
            Geometry::point(0, 0),
            Geometry::curve($topX, 0, $topX * 0.5, -$dip),
            Geometry::point($hemX, $yHem),
            Geometry::curve(0, $panelLength, $hemX * 0.5, $panelLength),
        ];

        $yHem = $this->fitSeamLength(
            fn (float $y) => Geometry::edgesLength($build($y), [2]),
            $panelLength,
            $panelLength * 0.3,
            $panelLength,
        );

        $outline = $build($yHem);
        $fullness = [];
        $notes = [];

        if ($ratio > 1.001) {
            $fullness[] = $this->fullness('gather', 0, $topX, $yokeX, ['label' => 'چین زیر یوک']);
            $notes[] = 'لبه بالای این پنل ('.$this->fa(round($topX, 1)).' سانتی‌متر) با چین به لبه یوک ('
                .$this->fa(round($yokeX, 1)).' سانتی‌متر) دوخته می‌شود.';
        }

        return $this->piece([
            'code' => 'yoke-skirt-'.$side,
            'name' => $isFront ? 'دامن زیر یوک جلو' : 'دامن زیر یوک پشت',
            'cut_quantity' => 1,
            'on_fold' => true,
            'outline' => $outline,
            'grainline' => $this->grainline($topX * 0.4, 2, $panelLength - 2),
            'markers' => [
                $this->marker($isFront ? 'cf' : 'cb', $isFront ? 'خط مرکز جلو' : 'خط مرکز پشت', 0, 0, 0, $panelLength),
            ],
            'meta' => [
                'part' => $isFront ? 'skirt_front' : 'skirt_back',
                'edges' => ['default', 'side', 'hem', 'default'],
                'fold_edges' => [3],
                'side' => $side,
                'waist_edges' => [],
                'side_edges' => [2],
                'hem_edges' => [2],
                'seam_length' => Geometry::edgesLength($outline, [2]),
                'seam_group' => 'skirt',
                'length' => round($panelLength, 2),
                'fullness' => $fullness,
                'notes' => $notes,
            ],
        ]);
    }
}
