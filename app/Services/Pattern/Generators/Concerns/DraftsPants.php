<?php

namespace App\Services\Pattern\Generators\Concerns;

use App\Services\Pattern\Geometry;

/**
 * درفت مشترک شلوار.
 *
 * پای جلو باریک‌تر و فاق آن کوتاه‌تر از پای پشت است. برای هر پا مبدأ روی نقطه فاق
 * قرار می‌گیرد و خط مرکزی (CF/CB) به اندازه «پنجه فاق» از آن فاصله دارد.
 */
trait DraftsPants
{
    /**
     * یک پای شلوار (جلو یا پشت).
     *
     * گزینه‌ها: side، knee_total (دور زانوی نهایی)، hem_total (دور دم پا)، pleat، darts.
     *
     * @return array<string, mixed>
     */
    protected function legPanel(array $m, array $ease, array $params, array $o = []): array
    {
        $isFront = ($o['side'] ?? 'front') === 'front';
        $hip = $this->m($m, 'hip', 98) + $this->ease($ease, 'hip', 6);
        $waist = $this->m($m, 'waist', 74) + $this->ease($ease, 'waist', 4);
        $inseam = max(30.0, $this->m($m, 'inseam', 75) + (float) $this->param($params, 'length_extra', 0));

        $quarterHip = max(12.0, $hip / 4);
        $quarterWaist = max(9.0, $waist / 4);
        $crotchDepth = ($hip / 4) + 2.5 + (float) $this->param($params, 'rise_extra', 0);
        $hipY = min($crotchDepth - 5, max(10.0, $this->m($m, 'waist_to_hip', 21)));

        $panelWidth = $quarterHip + ($isFront ? -1 : 1);
        $crotchExtension = $isFront ? $hip / 16 : $hip / 10;
        $pleat = $isFront ? (float) $this->param($params, 'front_pleat', 0) : 0.0;
        $dartCount = $isFront ? 0 : max(0, (int) $this->param($params, 'back_darts', 1));
        $dartIntake = $dartCount > 0 ? 2.0 : 0.0;

        $waistWidth = min($panelWidth, $quarterWaist + ($isFront ? $pleat : $dartIntake * $dartCount) + ($isFront ? -0.5 : 0.5));
        $backRise = $isFront ? 0.0 : 2.0;

        $kneeTotal = (float) ($o['knee_total'] ?? ($this->m($m, 'knee', 37) + (float) $this->param($params, 'knee_ease', 6)));
        $hemTotal = (float) ($o['hem_total'] ?? ($this->m($m, 'ankle', 23.5) + (float) $this->param($params, 'hem_ease', 10)));

        $kneeWidth = max(10.0, ($kneeTotal / 2) + ($isFront ? -1 : 1));
        $hemWidth = max(9.0, ($hemTotal / 2) + ($isFront ? -1 : 1));

        $centerX = ($crotchExtension + $panelWidth) / 2;
        $kneeY = $crotchDepth + ($inseam * 0.47);
        $hemY = $crotchDepth + $inseam;

        $kneeOuter = $centerX + ($kneeWidth / 2);
        $kneeInner = max(0.5, $centerX - ($kneeWidth / 2));
        $hemOuter = $centerX + ($hemWidth / 2);
        $hemInner = max(0.5, $centerX - ($hemWidth / 2));
        $sideX = $crotchExtension + $panelWidth;

        $outline = [
            Geometry::point($crotchExtension, 0),
            Geometry::point($crotchExtension + $waistWidth, $backRise),
            Geometry::curve($sideX, $hipY, $crotchExtension + $waistWidth + (($sideX - $crotchExtension - $waistWidth) * 0.6), $hipY * 0.45),
            Geometry::curve($kneeOuter, $kneeY, $sideX + 0.4, $hipY + (($kneeY - $hipY) * 0.5)),
            Geometry::point($hemOuter, $hemY),
            Geometry::point($hemInner, $hemY),
            Geometry::curve($kneeInner, $kneeY, $hemInner - 0.3, $kneeY + (($hemY - $kneeY) * 0.5)),
            Geometry::curve(0, $crotchDepth, $kneeInner * 0.55, $crotchDepth + (($kneeY - $crotchDepth) * 0.45)),
            Geometry::curve($crotchExtension, $hipY, $crotchExtension * ($isFront ? 0.2 : 0.32), $crotchDepth * 0.99),
        ];

        $edges = ['waist', 'side', 'side', 'side', 'hem', 'side', 'side', 'side', 'default'];

        $darts = [];

        for ($i = 0; $i < $dartCount; $i++) {
            $centerDartX = $crotchExtension + ($waistWidth * (($i + 1) / ($dartCount + 1)));
            $darts[] = $this->dart(
                'waist',
                $dartCount > 1 ? 'ساسون کمر '.($i + 1) : 'ساسون کمر',
                0,
                $centerDartX,
                $backRise * (($centerDartX - $crotchExtension) / max(0.1, $waistWidth)),
                $dartIntake,
                $centerDartX,
                $hipY * 0.55,
            );
        }

        $pleats = [];

        if ($isFront && $pleat > 0) {
            $pleats[] = [
                'label' => 'پیلی جلو',
                'depth' => round($pleat, 2),
                'from' => Geometry::point($centerX, 0),
                'to' => Geometry::point($centerX, $hipY * 0.8),
            ];
        }

        return $this->piece([
            'code' => $o['code'] ?? ($isFront ? 'leg-front' : 'leg-back'),
            'name' => $o['name'] ?? ($isFront ? 'پای جلو' : 'پای پشت'),
            'cut_quantity' => 2,
            'mirror' => true,
            'outline' => $outline,
            'grainline' => $this->grainline($centerX, $hipY, $hemY - 4),
            'darts' => $darts,
            'pleats' => $pleats,
            'notches' => [
                $this->notch($sideX, $hipY, 2, 'نشانه باسن روی پهلو', 'side'),
                $this->notch($kneeOuter, $kneeY, 3, 'نشانه زانو (پهلو)', 'knee_out'),
                $this->notch($kneeInner, $kneeY, 6, 'نشانه زانو (فاق)', 'knee_in'),
                $this->notch(0, $crotchDepth, 7, 'نقطه فاق', 'crotch'),
            ],
            'markers' => [
                $this->marker('hip', 'خط باسن', $crotchExtension, $hipY, $sideX),
                $this->marker('crotch', 'خط فاق', 0, $crotchDepth, $sideX),
                $this->marker('knee', 'خط زانو', $kneeInner, $kneeY, $kneeOuter),
                $this->marker(
                    $isFront ? 'cf' : 'cb',
                    $isFront ? 'خط مرکز جلو' : 'خط مرکز پشت',
                    $crotchExtension,
                    0,
                    $crotchExtension,
                    $hipY,
                ),
            ],
            'meta' => [
                'part' => $isFront ? 'front_leg' : 'back_leg',
                'edges' => $edges,
                'fold_edges' => [],
                'side' => $isFront ? 'front' : 'back',
                'crotch_depth' => round($crotchDepth, 2),
                'knee_y' => round($kneeY, 2),
                'hem_y' => round($hemY, 2),
                'panel_width' => round($panelWidth, 2),
                'knee_width' => round($kneeWidth, 2),
                'hem_width' => round($hemWidth, 2),
            ],
        ]);
    }

    /**
     * پای جلو، پای پشت و کمربند.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function pantsPieces(array $m, array $ease, array $params, array $o = []): array
    {
        $pieces = [
            $this->legPanel($m, $ease, $params, array_merge($o, ['side' => 'front'])),
            $this->legPanel($m, $ease, $params, array_merge($o, ['side' => 'back'])),
        ];

        if ($this->flag($params, 'waistband', true)) {
            $pieces[] = $this->waistbandPiece($m, $ease, $params);
        }

        return $pieces;
    }
}
