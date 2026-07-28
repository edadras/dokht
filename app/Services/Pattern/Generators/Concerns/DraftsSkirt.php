<?php

namespace App\Services\Pattern\Generators\Concerns;

use App\Services\Pattern\Geometry;

/**
 * درفت مشترک دامن.
 *
 * پهنای کمر و باسن روی یک‌چهارم بدن حساب می‌شود؛ اختلاف باسن و کمر بین درز پهلو و
 * ساسون تقسیم می‌شود و پایین قطعه به اندازه «گشادی» باز یا به اندازه «تنگی» جمع می‌شود.
 */
trait DraftsSkirt
{
    /**
     * یک پنل دامن (جلو یا پشت).
     *
     * گزینه‌ها: side، length، hem_delta (مثبت = گشاد، منفی = تنگ)، dart_count،
     * vent (بلندی چاک پشت)، code، name.
     *
     * @return array<string, mixed>
     */
    protected function skirtPanel(array $m, array $ease, array $params, array $o = []): array
    {
        $isFront = ($o['side'] ?? 'front') === 'front';
        $waist = $this->m($m, 'waist', 74) + $this->ease($ease, 'waist', 4);
        $hip = $this->m($m, 'hip', 98) + $this->ease($ease, 'hip', 6);
        $hipY = max(12.0, $this->m($m, 'waist_to_hip', 21));
        $length = max(20.0, (float) ($o['length'] ?? 60));
        $hemDelta = (float) ($o['hem_delta'] ?? 8);

        $quarterWaist = max(8.0, $waist / 4);
        $quarterHip = max(10.0, $hip / 4);
        $intake = max(0.0, $quarterHip - $quarterWaist);
        $dartShare = min(0.9, max(0.0, (float) $this->param($params, 'dart_share', 0.5)));
        $dartIntake = $intake * $dartShare;
        $sideWaistX = $quarterWaist + $dartIntake;
        $hemX = max(6.0, $quarterHip + $hemDelta);
        $dartCount = max(0, (int) ($o['dart_count'] ?? 1));
        $vent = (float) ($o['vent'] ?? 0);

        $outline = [
            Geometry::point(0, 0),
            Geometry::point($sideWaistX, 0),
            Geometry::curve($quarterHip, $hipY, $sideWaistX + (($quarterHip - $sideWaistX) * 0.45), $hipY * 0.42),
            Geometry::point($hemX, $length),
            Geometry::point(0, $length),
        ];

        $edges = ['waist', 'side', 'side', 'hem', 'default'];

        $darts = [];

        if ($dartCount > 0 && $dartIntake > 0.6) {
            $each = $dartIntake / $dartCount;
            $apexDepth = $isFront ? $hipY * 0.55 : $hipY * 0.72;

            for ($i = 0; $i < $dartCount; $i++) {
                $centerX = $sideWaistX * (($i + 1) / ($dartCount + 1));
                $darts[] = $this->dart(
                    'waist',
                    $dartCount > 1 ? 'ساسون کمر '.($i + 1) : 'ساسون کمر',
                    0,
                    $centerX,
                    0,
                    $each,
                    $centerX,
                    $apexDepth,
                );
            }
        }

        $markers = [
            $this->marker('hip', 'خط باسن', 0, $hipY, $quarterHip),
            $this->marker(
                $isFront ? 'cf' : 'cb',
                $isFront ? 'خط مرکز جلو' : 'خط مرکز پشت',
                0,
                0,
                0,
                $length,
            ),
        ];

        $meta = [
            'part' => $isFront ? 'skirt_front' : 'skirt_back',
            'edges' => $edges,
            'fold_edges' => [4],
            'side' => $isFront ? 'front' : 'back',
            'hip_y' => round($hipY, 2),
            'quarter_hip' => round($quarterHip, 2),
            'quarter_waist' => round($quarterWaist, 2),
        ];

        if ($vent > 0) {
            $markers[] = $this->marker('vent', 'چاک پشت', 0, $length - $vent, 0, $length);
            $meta['vent'] = round($vent, 2);
        }

        return $this->piece([
            'code' => $o['code'] ?? ($isFront ? 'skirt-front' : 'skirt-back'),
            'name' => $o['name'] ?? ($isFront ? 'دامن جلو' : 'دامن پشت'),
            'cut_quantity' => 1,
            'on_fold' => true,
            'outline' => $outline,
            'grainline' => $this->grainline($sideWaistX * 0.5, 3, $length - 3),
            'darts' => $darts,
            'notches' => [
                $this->notch($quarterHip, $hipY, 1, 'نشانه باسن روی پهلو', 'side'),
            ],
            'markers' => $markers,
            'meta' => $meta,
        ]);
    }
}
