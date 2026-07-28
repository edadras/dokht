<?php

namespace App\Services\Pattern\Generators\Concerns;

use App\Services\Pattern\Geometry;

/**
 * درفت مشترک بالاتنه.
 *
 * همه لباس‌های بالاتنه (بالاتنه پایه، تی‌شرت، پیراهن، پیراهن یک‌تکه و کت) از همین
 * یک درفت استفاده می‌کنند و تنها «شکل» پایین قطعه و چند گزینه فرق می‌کند:
 *   waist  → در خط کمر تمام می‌شود (بلوک پایه)
 *   straight → درز پهلو راست از زیر بغل تا لبه پایین (تی‌شرت و پیراهن مردانه)
 *   flare  → از کمر به بیرون باز می‌شود (تونیک و پیراهن آزاد)
 *   dress  → کمر گرفته، از باسن رد می‌شود و تا پایین می‌آید (پیراهن یک‌تکه)
 */
trait DraftsBodice
{
    /**
     * یک قطعه بالاتنه کامل (جلو یا پشت).
     *
     * گزینه‌ها: side، shape، bottom_y، bottom_tag، extension (اضافه جلو برای جای دکمه)،
     * neck_extra، hem_flare، hip_ease_extra، darts، code، name، part، on_fold، cut، mirror، top_y.
     *
     * @param  array<string, float>  $g  خروجی bodiceMetrics()
     * @param  array<string, mixed>  $o
     * @return array<string, mixed>
     */
    protected function bodicePiece(array $g, array $o = []): array
    {
        $isFront = ($o['side'] ?? 'front') === 'front';
        $shape = $o['shape'] ?? 'waist';
        $ext = (float) ($o['extension'] ?? 0);
        $cf = $ext;

        $neckWidth = $g['neck_width'] + ($isFront ? 0 : 0.3);
        $neckDepth = ($isFront ? $g['front_neck_depth'] : $g['back_neck_depth']) + (float) ($o['neck_extra'] ?? 0);
        $waistY = $isFront ? $g['front_waist_y'] : $g['back_waist_y'];
        $shoulderDrop = $g['shoulder_drop'] + ($isFront ? 0 : 0.5);
        $quarterBust = $g['quarter_bust'];
        $bustY = $g['bust_y'];
        $across = $isFront ? $g['across_chest'] : $g['across_back'];
        $bottomY = (float) ($o['bottom_y'] ?? $waistY);
        $sideIntake = $shape === 'straight' ? 0.0 : $g['side_intake'];
        $sideX = $cf + $quarterBust - $sideIntake;
        $hemFlare = (float) ($o['hem_flare'] ?? 0);

        $outline = [];
        $edges = [];

        // اضافه جلو (جای دکمه) با یک پله افقی در بالای لبه جلو شروع می‌شود
        if ($ext > 0) {
            $outline[] = Geometry::point(0, $neckDepth);
            $outline[] = Geometry::point($cf, $neckDepth);
            $edges[] = 'default';
        } else {
            $outline[] = Geometry::point($cf, $neckDepth);
        }

        // یقه
        $outline[] = Geometry::curve(
            $cf + $neckWidth,
            0,
            $cf + ($neckWidth * ($isFront ? 0.12 : 0.32)),
            $neckDepth * ($isFront ? 0.12 : 0.24),
        );
        $edges[] = 'neck';

        // سرشانه
        $outline[] = Geometry::point($cf + $g['shoulder_half'], $shoulderDrop);
        $edges[] = 'shoulder';

        // حلقه آستین: منحنی گودافتاده تا زیر بغل
        $outline[] = Geometry::curve(
            $cf + $quarterBust,
            $bustY,
            $cf + $across - ($isFront ? 1.0 : 0.6),
            $bustY * ($isFront ? 0.62 : 0.66),
        );
        $edges[] = 'armhole';

        $waistSideIndex = null;
        $hipY = $waistY + $g['hip_drop'];

        if ($shape === 'straight') {
            $outline[] = Geometry::point($cf + $quarterBust + $hemFlare, $bottomY);
            $edges[] = 'side';
        } elseif ($shape === 'flare') {
            $outline[] = Geometry::curve(
                $sideX,
                $waistY,
                $cf + $quarterBust - ($sideIntake * 0.7),
                $bustY + (($waistY - $bustY) * 0.55),
            );
            $edges[] = 'side';
            $waistSideIndex = count($outline) - 1;
            $outline[] = Geometry::point($cf + $quarterBust + $hemFlare, $bottomY);
            $edges[] = 'side';
        } elseif ($shape === 'dress') {
            $hipX = $cf + max($quarterBust, $g['quarter_hip'] + (float) ($o['hip_ease_extra'] ?? 0));
            $outline[] = Geometry::curve(
                $sideX,
                $waistY,
                $cf + $quarterBust - ($sideIntake * 0.7),
                $bustY + (($waistY - $bustY) * 0.55),
            );
            $edges[] = 'side';
            $waistSideIndex = count($outline) - 1;
            $outline[] = Geometry::curve($hipX, $hipY, $sideX + (($hipX - $sideX) * 0.35), $waistY + ($g['hip_drop'] * 0.55));
            $edges[] = 'side';
            $outline[] = Geometry::point($hipX + $hemFlare, $bottomY);
            $edges[] = 'side';
        } else { // waist
            $outline[] = Geometry::curve(
                $sideX,
                $bottomY,
                $cf + $quarterBust - ($sideIntake * 0.7),
                $bustY + (($bottomY - $bustY) * 0.55),
            );
            $edges[] = 'side';
            $waistSideIndex = count($outline) - 1;
        }

        // لبه پایین و بستن قطعه از خط مرکزی
        $outline[] = Geometry::point(0, $bottomY);
        $edges[] = $o['bottom_tag'] ?? ($shape === 'waist' ? 'waist' : 'hem');
        $edges[] = 'default'; // لبه بسته‌شدن: خط مرکز جلو/پشت

        $closingEdge = count($outline) - 1;
        $onFold = (bool) ($o['on_fold'] ?? ($ext <= 0));

        $part = $o['part'] ?? ($isFront ? 'front_bodice' : 'back_bodice');
        $centerKey = $isFront ? 'cf' : 'cb';
        $centerLabel = $isFront ? 'خط مرکز جلو' : 'خط مرکز پشت';

        $markers = [
            $this->marker('bust', 'خط سینه', $cf, $bustY, $cf + $quarterBust),
            $this->marker($centerKey, $centerLabel, $cf, $neckDepth, $cf, $bottomY),
        ];

        if ($bottomY > $waistY + 2) {
            $markers[] = $this->marker('waist', 'خط کمر', $cf, $waistY, $sideX);
        }

        if ($bottomY > $hipY + 1) {
            $markers[] = $this->marker('hip', 'خط باسن', $cf, $hipY, $cf + $g['quarter_hip']);
        }

        $shoulderMid = Geometry::pointOnEdge($outline, array_search('shoulder', $edges, true), 0.5);
        $armholeIndex = (int) array_search('armhole', $edges, true);
        $armholeNotch = Geometry::pointOnEdge($outline, $armholeIndex, $isFront ? 0.62 : 0.55);

        $notches = [
            $this->notch($shoulderMid['x'], $shoulderMid['y'], (int) array_search('shoulder', $edges, true), 'نشانه سرشانه', 'shoulder'),
            $this->notch($armholeNotch['x'], $armholeNotch['y'], $armholeIndex, 'نشانه حلقه آستین', 'armhole'),
        ];

        if ($waistSideIndex !== null) {
            $notches[] = $this->notch($sideX, $waistY, (int) array_search('side', $edges, true), 'نشانه کمر روی پهلو', 'side');
        }

        $darts = [];
        $wantsDarts = (bool) ($o['darts'] ?? true);

        if ($wantsDarts && $g['dart_intake'] > 0.6) {
            if ($shape === 'waist') {
                $bottomEdge = (int) array_search($o['bottom_tag'] ?? 'waist', $edges, true);
                $darts[] = $this->dart(
                    'waist',
                    'ساسون کمر',
                    $bottomEdge,
                    $isFront ? $cf + $g['bust_apex_x'] : $cf + ($quarterBust * 0.45),
                    $bottomY,
                    $g['dart_intake'],
                    $isFront ? $cf + $g['bust_apex_x'] : $cf + ($quarterBust * 0.45),
                    $isFront ? $g['bust_apex_y'] : $bustY + 3,
                );
            } elseif (in_array($shape, ['dress', 'flare'], true)) {
                // ساسون بادامی: در خط کمر بسته می‌شود و به بالا و پایین باز می‌شود
                $centerX = $isFront ? $cf + $g['bust_apex_x'] : $cf + ($quarterBust * 0.45);
                $darts[] = $this->dart(
                    'waist',
                    'ساسون کمر',
                    null,
                    $centerX,
                    $waistY,
                    $g['dart_intake'],
                    $centerX,
                    $isFront ? $g['bust_apex_y'] : $bustY + 3,
                    'x',
                    ['apex_lower' => Geometry::point($centerX, min($bottomY - 2, $hipY - 1))],
                );
            }
        }

        if ($isFront && $wantsDarts && ($o['bust_dart'] ?? false) && $g['bust_dart_intake'] > 0.8) {
            $sideEdge = (int) array_search('side', $edges, true);
            $apexY = min($g['bust_apex_y'], $bustY + (($waistY - $bustY) * 0.5));
            $t = max(0.12, min(0.6, ($apexY - $bustY) / max(1, $waistY - $bustY)));
            $on = Geometry::pointOnEdge($outline, $sideEdge, $t);

            $darts[] = $this->dart(
                'bust',
                'ساسون سینه',
                $sideEdge,
                $on['x'],
                $on['y'],
                $g['bust_dart_intake'],
                $cf + $g['bust_apex_x'],
                $apexY,
                'y',
            );
        }

        return $this->piece([
            'code' => $o['code'] ?? ($isFront ? 'front' : 'back'),
            'name' => $o['name'] ?? ($isFront ? 'بالاتنه جلو' : 'بالاتنه پشت'),
            'cut_quantity' => (int) ($o['cut'] ?? 1),
            'on_fold' => $onFold,
            'mirror' => (bool) ($o['mirror'] ?? ! $onFold),
            'layer' => $o['layer'] ?? 'outer',
            'outline' => $outline,
            'grainline' => $this->grainline($cf + ($quarterBust * 0.4), $bustY * 0.35, $bottomY - 3),
            'darts' => $darts,
            'notches' => $notches,
            'markers' => $markers,
            'meta' => array_merge([
                'part' => $part,
                'edges' => $edges,
                'fold_edges' => $onFold ? [$closingEdge] : [],
                'side' => $isFront ? 'front' : 'back',
                'shape' => $shape,
                'armhole_edge' => $armholeIndex,
                'armhole_length' => Geometry::edgeLength($outline, $armholeIndex),
                'neck_length' => Geometry::edgeLength($outline, (int) array_search('neck', $edges, true)),
                'bust_y' => $bustY,
                'waist_y' => $waistY,
                'hip_y' => $hipY,
            ], $o['meta'] ?? []),
        ]);
    }
}
