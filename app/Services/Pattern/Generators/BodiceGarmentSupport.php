<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Generators\Concerns\BuildsSleeve;
use App\Services\Pattern\Geometry;

/**
 * قطعه‌های کمکی لباس کامل: آستین دوتکه و رگلان، آستین یک‌سره (کیمونو)، یقه،
 * سجاف، کلاه، دامن، جیب و آستر.
 *
 * هر قطعه‌ای که دور بدن را می‌سازد نقش خودش را در meta.girth_role ثبت می‌کند:
 * shell (بالاتنه رو)، skirt (پایین‌تنه رو)، lining / lining_skirt (آستر) و
 * trim (نوار و یقه و جیب که در دور بدن حساب نمی‌شوند).
 */
trait BodiceGarmentSupport
{
    use BuildsSleeve;

    /**
     * آستین دوتکه خیاطی (آستین رو و آستین زیر) برای پالتو و کت.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function tailoredSleevePieces(array $m, array $ease, array $params, float $armhole, array $o = []): array
    {
        $bicep = $this->m($m, 'bicep', 28.5) + $this->ease($ease, 'bicep', 8);
        $wrist = $this->m($m, 'wrist', 16.5) + (float) ($o['wrist_ease'] ?? 8);
        $length = max(24.0, $this->m($m, 'arm_length', 58) + (float) ($o['length_extra'] ?? 0));
        $capEase = (float) $this->param($params, 'cap_ease', 3);

        $upperWidth = $bicep * 0.72;
        $underWidth = $bicep * 0.36;
        $upperCap = $this->fitCapHeight($upperWidth, ($armhole + $capEase) * 0.72);
        $underCap = max(2.0, $upperCap * 0.3);
        $upperHem = $wrist * 0.62;
        $underHem = $wrist * 0.38;

        $upperOutline = $this->capOutline($upperWidth, $upperCap);
        $upperOutline[] = Geometry::curve($upperWidth - 1.5, $length, $upperWidth + 1.4, $upperCap + (($length - $upperCap) * 0.55));
        $upperOutline[] = Geometry::point($upperWidth - 1.5 - $upperHem, $length);
        $upperOutline[0] = Geometry::curve(0, $upperCap, ($upperWidth - $upperHem) * 0.22, $upperCap + (($length - $upperCap) * 0.5));

        $upper = $this->piece([
            'code' => ($o['prefix'] ?? '').'upper-sleeve',
            'name' => 'آستین رو',
            'cut_quantity' => 2,
            'mirror' => true,
            'outline' => $upperOutline,
            'grainline' => $this->grainline($upperWidth * 0.5, $upperCap * 0.4, $length - 4),
            'notches' => [
                $this->notch($upperWidth * 0.5, 0, 2, 'نوک آستین (سرشانه)', 'sleeve_top'),
                $this->notch(0, $upperCap, 4, 'نشانه آستین زیر', 'under_sleeve'),
            ],
            'markers' => [
                $this->marker('bicep', 'خط بازو', 0, $upperCap, $upperWidth),
                $this->marker('elbow', 'خط آرنج', 0, $upperCap + (($length - $upperCap) * 0.55), $upperWidth - 1),
            ],
            'meta' => [
                'part' => 'sleeve',
                'edges' => ['armhole', 'armhole', 'armhole', 'armhole', 'side', 'hem', 'side'],
                'fold_edges' => [],
                'cap_height' => round($upperCap, 2),
                'cap_length' => Geometry::edgesLength($upperOutline, [0, 1, 2, 3]),
                'target_armhole' => round($armhole, 2),
                'two_piece' => 'upper',
                'girth_role' => 'sleeve',
            ],
        ]);

        $underOutline = [
            Geometry::curve(0, $underCap, ($underWidth - $underHem) * 0.2, $underCap + (($length - $underCap) * 0.5)),
            Geometry::curve($underWidth, $underCap, $underWidth * 0.5, -$underCap * 0.9),
            Geometry::curve($underWidth - 1, $length, $underWidth + 1, $underCap + (($length - $underCap) * 0.55)),
            Geometry::point($underWidth - 1 - $underHem, $length),
        ];

        $under = $this->piece([
            'code' => ($o['prefix'] ?? '').'under-sleeve',
            'name' => 'آستین زیر',
            'cut_quantity' => 2,
            'mirror' => true,
            'outline' => $underOutline,
            'grainline' => $this->grainline($underWidth * 0.5, $underCap + 2, $length - 4),
            'notches' => [
                $this->notch(0, $underCap, 4, 'نشانه آستین رو', 'under_sleeve'),
            ],
            'meta' => [
                'part' => 'sleeve',
                'edges' => ['armhole', 'side', 'hem', 'side'],
                'fold_edges' => [],
                'cap_height' => round($underCap, 2),
                'two_piece' => 'under',
                'girth_role' => 'sleeve',
            ],
        ]);

        return [$upper, $under];
    }

    /**
     * آستین رگلان: سرشانه هم بخشی از آستین است.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function raglanSleevePieces(array $m, array $ease, array $params, array $g, array $o = []): array
    {
        $bicep = $this->m($m, 'bicep', 28.5) + $this->ease($ease, 'bicep', 6);
        $length = (float) ($o['length'] ?? max(20.0, $this->m($m, 'arm_length', 58)));
        $width = max($bicep * 0.85, $bicep);
        $capHeight = max(8.0, $g['bust_y'] * 0.55);
        $neckRise = (float) ($o['neck_rise'] ?? 8);
        $hemWidth = (float) ($o['hem_width'] ?? max($width * 0.55, $this->m($m, 'wrist', 16.5) + 6));

        $center = $width / 2;
        $half = $hemWidth / 2;

        // نیم‌آستین رگلان: از یقه به بالا، دو خط رگلان به پایین و دم آستین
        $outline = [
            Geometry::point($center - 2.2, 0),
            Geometry::point($center + 2.2, 0),
            Geometry::curve($width, $capHeight + $neckRise, $center + ($width * 0.32), $capHeight * 0.42),
            Geometry::curve($center + $half, $length + $neckRise, $width - (($width - ($center + $half)) * 0.35), $capHeight + $neckRise + (($length - $capHeight) * 0.5)),
            Geometry::point($center - $half, $length + $neckRise),
            Geometry::curve(0, $capHeight + $neckRise, ($center - $half) * 0.65, $capHeight + $neckRise + (($length - $capHeight) * 0.5)),
        ];

        $sleeve = $this->piece([
            'code' => ($o['prefix'] ?? '').'raglan-sleeve',
            'name' => $o['name'] ?? 'آستین رگلان',
            'cut_quantity' => 2,
            'mirror' => true,
            'outline' => $outline,
            'grainline' => $this->grainline($center, $capHeight * 0.6, $length + $neckRise - 3),
            'notches' => [
                $this->notch($center, 0, 0, 'نشانه سرشانه', 'shoulder'),
                $this->notch($width, $capHeight + $neckRise, 2, 'زیر بغل جلو', 'underarm'),
                $this->notch(0, $capHeight + $neckRise, 5, 'زیر بغل پشت', 'underarm'),
            ],
            'markers' => [
                $this->marker('bicep', 'خط بازو', 0, $capHeight + $neckRise, $width),
            ],
            'meta' => [
                'part' => 'sleeve',
                'edges' => ['neck', 'armhole', 'side', 'hem', 'side', 'default'],
                'fold_edges' => [],
                'raglan' => true,
                'bicep_width' => round($width, 2),
                'sleeve_length' => round($length, 2),
                'girth_role' => 'sleeve',
            ],
        ]);

        return [$sleeve];
    }

    /**
     * پنل تنه با آستین یک‌سره (کیمونو، کافتان، عبا).
     *
     * @param  array<string, float>  $g
     * @param  array<string, mixed>  $o
     * @return array<string, mixed>
     */
    protected function kimonoPanel(array $g, array $o = []): array
    {
        $front = ($o['side'] ?? 'front') === 'front';
        $ext = (float) ($o['extension'] ?? 0);
        $cf = $ext;
        $grow = (float) ($o['grow'] ?? 0);
        $qb = $g['quarter_bust'] + $grow;
        $qh = $g['quarter_hip'] + $grow;

        $drop = (float) ($o['underarm_drop'] ?? 6);
        $underarmY = $g['bust_y'] + $drop;
        $sleeveLength = (float) ($o['sleeve_length'] ?? 30);
        $sleeveEnd = $cf + $qb + $sleeveLength;
        $sleeveSlope = (float) ($o['sleeve_slope'] ?? 6);
        $cuffWidth = (float) ($o['cuff_width'] ?? 17);
        $neckW = $g['neck_width'] + (float) ($o['neck_width_extra'] ?? 0) + ($front ? 0.0 : 0.3);
        $neckD = ($front ? $g['front_neck_depth'] : $g['back_neck_depth']) + (float) ($o['neck_depth_extra'] ?? 0);

        $length = (float) ($o['length'] ?? 60);
        $bottomSideY = $g['side_waist_y'] + $length;
        $bottomCenterY = ($front ? $g['front_waist_y'] : $g['back_waist_y']) + $length;
        $flare = (float) ($o['hem_flare'] ?? 0);
        $hemX = $cf + max($qb, $qh) + $flare;

        $outline = [];
        $edges = [];

        if ($ext > 0) {
            $outline[] = Geometry::point(0, $neckD);
            $outline[] = Geometry::point($cf, $neckD);
            $edges[] = 'default';
        } else {
            $outline[] = Geometry::point($cf, $neckD);
        }

        $outline[] = Geometry::curve($cf + $neckW, 0, $cf + ($neckW * ($front ? 0.10 : 0.34)), $neckD * ($front ? 0.10 : 0.28));
        $edges[] = 'neck';

        $outline[] = Geometry::point($sleeveEnd, $sleeveSlope);
        $edges[] = 'shoulder';

        $outline[] = Geometry::point($sleeveEnd, $sleeveSlope + $cuffWidth);
        $edges[] = 'default'; // دم آستین

        $outline[] = Geometry::curve($cf + $qb, $underarmY, $cf + $qb + (($sleeveEnd - $cf - $qb) * 0.35), $underarmY - 1.5);
        $edges[] = 'armhole';

        $outline[] = Geometry::point($hemX, $bottomSideY);
        $edges[] = 'side';

        $outline[] = abs($bottomCenterY - $bottomSideY) > 0.4
            ? Geometry::curve(0, $bottomCenterY, $hemX * 0.42, $bottomSideY + (($bottomCenterY - $bottomSideY) * 0.22))
            : Geometry::point(0, $bottomCenterY);
        $edges[] = 'hem';
        $edges[] = 'default';

        $onFold = (bool) ($o['on_fold'] ?? ($ext <= 0));

        return $this->finishPanel([
            'code' => $o['code'] ?? ($front ? 'kimono-front' : 'kimono-back'),
            'name' => $o['name'] ?? ($front ? 'تنه جلو با آستین یک‌سره' : 'تنه پشت با آستین یک‌سره'),
            'cut' => (int) ($o['cut'] ?? ($onFold ? 1 : 2)),
            'mirror' => ! $onFold,
            'layer' => $o['layer'] ?? 'outer',
        ], $outline, $edges, [
            'lines' => ['bust' => $underarmY],
            'center_x' => $cf,
            'on_fold' => $onFold,
            'fold_edges' => $onFold ? [count($outline) - 1] : [],
            'grainline' => $this->grainline($cf + ($qb * 0.4), $underarmY - 4, $bottomCenterY - 3),
            'notches' => [
                $this->notch($cf + $qb, $underarmY, 3 + ($ext > 0 ? 1 : 0), 'زیر بغل', 'underarm'),
            ],
            'markers' => [
                $this->marker('bust', 'خط زیر بغل', $cf, $underarmY, $cf + $qb),
                $this->marker($front ? 'cf' : 'cb', $front ? 'خط مرکز جلو' : 'خط مرکز پشت', $cf, $neckD, $cf, $bottomCenterY),
            ],
            'meta' => [
                'part' => $front ? 'front_bodice' : 'back_bodice',
                'side' => $front ? 'front' : 'back',
                'shape' => 'kimono',
                'one_piece_sleeve' => true,
                'underarm_drop' => round($drop, 2),
                'bust_y' => round($underarmY, 2),
            ],
        ]);
    }

    /**
     * دامن پیوسته به بالاتنه (کمر دامن با کمر بالاتنه جفت می‌شود).
     *
     * @param  array<string, float>  $g
     * @param  array<string, mixed>  $o
     * @return array<string, mixed>
     */
    protected function skirtPanel(array $g, array $o = []): array
    {
        $front = ($o['side'] ?? 'front') === 'front';
        $type = $o['type'] ?? 'a_line';
        $grow = (float) ($o['grow'] ?? 0);
        $qw = $g['quarter_waist'] + $grow;
        $qh = $g['quarter_hip'] + $grow;
        $hipDrop = $g['hip_drop'];
        $length = max(12.0, (float) ($o['length'] ?? 60));
        $flare = (float) ($o['flare'] ?? 8);
        $gather = (float) ($o['gather'] ?? 0);
        $dart = $gather > 0 ? 0.0 : max(0.0, (float) ($o['dart'] ?? min(3.0, max(0.0, $qh - $qw) * 0.55)));
        $topWidth = $qw + $dart + $gather;
        $ext = (float) ($o['extension'] ?? 0);
        $cf = $ext;

        if ($type === 'circle') {
            return $this->circleSkirtPanel($g, $o);
        }

        $outline = [];
        $edges = [];

        if ($ext > 0) {
            $outline[] = Geometry::point(0, 0);
            $outline[] = Geometry::point($cf, 0);
            $edges[] = 'waist';
        } else {
            $outline[] = Geometry::point($cf, 0);
        }

        $outline[] = Geometry::point($cf + $topWidth, 0);
        $edges[] = 'waist';

        $outline[] = Geometry::curve($cf + $qh, $hipDrop, $cf + $topWidth + (($qh - $topWidth) * 0.45), $hipDrop * 0.55);
        $edges[] = 'side';

        if ($type === 'mermaid') {
            $kneeY = $length * 0.62;
            $outline[] = Geometry::point($cf + $qh - 1.0, $kneeY);
            $edges[] = 'side';
            $outline[] = Geometry::curve($cf + $qh + $flare, $length, $cf + $qh - 0.5, $kneeY + (($length - $kneeY) * 0.55));
            $edges[] = 'side';
        } elseif ($type === 'straight') {
            $outline[] = Geometry::point($cf + $qh, $length);
            $edges[] = 'side';
        } else {
            $outline[] = Geometry::point($cf + $qh + $flare, $length);
            $edges[] = 'side';
        }

        $hemDrop = (float) ($o['hem_drop'] ?? 0);
        $outline[] = $hemDrop > 0.3
            ? Geometry::curve(0, $length + $hemDrop, ($cf + $qh) * 0.45, $length + ($hemDrop * 0.25))
            : Geometry::point(0, $length);
        $edges[] = 'hem';
        $edges[] = 'default';

        $onFold = (bool) ($o['on_fold'] ?? ($ext <= 0));
        $darts = [];

        if ($dart > 0.6) {
            $darts[] = $this->dart('waist', 'ساسون دامن', 0, $cf + ($topWidth * 0.5), 0, $dart, $cf + ($topWidth * 0.5), $hipDrop * 0.62);
        }

        $pleats = [];

        if ($gather > 0.5) {
            $pleats[] = [
                'type' => 'gather',
                'label' => 'کولیس کمر دامن',
                'edge' => 0,
                'intake' => round($gather, 2),
                'from' => Geometry::point($cf, 0),
                'to' => Geometry::point($cf + $topWidth, 0),
            ];
        }

        $lines = ['waist' => 0.0];

        if (in_array($type, ['a_line', 'straight', 'mermaid', 'pencil'], true)) {
            $lines['hip'] = $hipDrop;
        }

        return $this->finishPanel([
            'code' => $o['code'] ?? (($o['prefix'] ?? '').($front ? 'skirt-front' : 'skirt-back')),
            'name' => $o['name'] ?? ($front ? 'دامن جلو' : 'دامن پشت'),
            'cut' => (int) ($o['cut'] ?? ($onFold ? 1 : 2)),
            'mirror' => ! $onFold,
            'layer' => $o['layer'] ?? 'outer',
            'girth_role' => $o['girth_role'] ?? (($o['layer'] ?? 'outer') === 'lining' ? 'lining_skirt' : 'skirt'),
        ], $outline, $edges, [
            'lines' => $lines,
            'center_x' => $cf,
            'on_fold' => $onFold,
            'fold_edges' => $onFold ? [count($outline) - 1] : [],
            'grainline' => $this->grainline($cf + ($qh * 0.4), 2, $length - 3),
            'darts' => $darts,
            'pleats' => $pleats,
            'girth_deduct' => $gather > 0 ? ['waist' => $gather] : [],
            'notches' => [
                $this->notch($cf + $qh, $hipDrop, $ext > 0 ? 2 : 1, 'خط باسن روی پهلو', 'hip'),
            ],
            'markers' => [
                $this->marker('waist', 'خط کمر', $cf, 0, $cf + $topWidth),
                $this->marker('hip', 'خط باسن', $cf, $hipDrop, $cf + $qh),
            ],
            'meta' => [
                'part' => $front ? 'skirt_front' : 'skirt_back',
                'side' => $front ? 'front' : 'back',
                'skirt_type' => $type,
                'fullness' => $gather > 0.5 ? ['waist' => round($gather, 2)] : [],
                'hip_y' => round($hipDrop, 2),
            ],
        ]);
    }

    /**
     * دامن کلوش کامل: یک‌چهارم حلقه با کمر روی کمان داخلی.
     *
     * @return array<string, mixed>
     */
    protected function circleSkirtPanel(array $g, array $o = []): array
    {
        $front = ($o['side'] ?? 'front') === 'front';
        $qw = $g['quarter_waist'] + (float) ($o['grow'] ?? 0);
        $fullness = max(0.25, min(1.0, (float) ($o['fullness'] ?? 1.0)));
        $length = max(15.0, (float) ($o['length'] ?? 65));

        // کمان کمر یک‌چهارم = qw ⇒ شعاع از طول کمان درمی‌آید
        $sweep = (M_PI / 2) * $fullness;
        $radius = $qw / $sweep;
        $outer = $radius + $length;
        $steps = 16;

        $outline = [];
        $edges = [];

        for ($i = 0; $i <= $steps; $i++) {
            $angle = $sweep * ($i / $steps);
            $outline[] = Geometry::point($radius * sin($angle), $radius * cos($angle));
            $edges[] = 'waist';
        }

        array_pop($edges);
        $edges[] = 'side';

        for ($i = $steps; $i >= 0; $i--) {
            $angle = $sweep * ($i / $steps);
            $outline[] = Geometry::point($outer * sin($angle), $outer * cos($angle));
            $edges[] = 'hem';
        }

        array_pop($edges);
        $edges[] = 'side';

        $waistArc = 0.0;

        for ($i = 1; $i <= $steps; $i++) {
            $waistArc += Geometry::distance(
                ['x' => $outline[$i - 1]['x'], 'y' => $outline[$i - 1]['y']],
                ['x' => $outline[$i]['x'], 'y' => $outline[$i]['y']],
            );
        }

        return $this->finishPanel([
            'code' => $o['code'] ?? (($o['prefix'] ?? '').($front ? 'skirt-front' : 'skirt-back')),
            'name' => $o['name'] ?? ($front ? 'دامن کلوش جلو' : 'دامن کلوش پشت'),
            'cut' => (int) ($o['cut'] ?? 1),
            'mirror' => false,
            'layer' => $o['layer'] ?? 'outer',
            'girth_role' => $o['girth_role'] ?? 'skirt',
        ], $outline, $edges, [
            'girth' => ['waist' => round($waistArc, 3)],
            'on_fold' => (bool) ($o['on_fold'] ?? true),
            'fold_edges' => [],
            'grainline' => $this->grainline($radius * 0.3, $radius * 0.75, $outer * 0.9),
            'markers' => [
                $this->marker('waist', 'کمان کمر', 0, $radius, $radius, 0),
            ],
            'meta' => [
                'part' => $front ? 'skirt_front' : 'skirt_back',
                'side' => $front ? 'front' : 'back',
                'skirt_type' => 'circle',
                'radius' => round($radius, 2),
                'fullness' => ['hem' => round(($outer * $sweep) - $waistArc, 2)],
            ],
        ]);
    }

    /**
     * یقه ایستاده / نوار یقه.
     *
     * @return array<string, mixed>
     */
    protected function standCollarPiece(float $halfNeck, float $height, array $o = []): array
    {
        return $this->bandPiece(
            ($o['prefix'] ?? '').'collar-stand',
            $o['name'] ?? 'یقه ایستاده',
            max(8.0, $halfNeck + (float) ($o['extra'] ?? 1.5)),
            max(2.0, $height),
            ['cut' => 2, 'on_fold' => true, 'part' => 'collar', 'fold_line' => true, 'meta' => ['interfacing' => true, 'neck_length' => round($halfNeck, 2)]],
        );
    }

    /**
     * یقه برگردان (کلاسیک پیراهن): یقه رو روی تای مرکز پشت.
     *
     * @return array<string, mixed>
     */
    protected function turnCollarPiece(float $halfNeck, float $height, array $o = []): array
    {
        $length = max(10.0, $halfNeck);
        $point = (float) ($o['point'] ?? 2.0);

        $outline = [
            Geometry::point(0, 0),
            Geometry::point($length, 0.8),
            Geometry::point($length + $point, $height),
            Geometry::curve(0, $height, $length * 0.5, $height - 1.2),
        ];

        return $this->piece([
            'code' => ($o['prefix'] ?? '').'collar',
            'name' => $o['name'] ?? 'یقه',
            'cut_quantity' => 2,
            'on_fold' => true,
            'outline' => $outline,
            'grainline' => $this->grainline($length * 0.5, 1.5, $height - 1),
            'notches' => [$this->notch(0, $height, 2, 'مرکز پشت یقه', 'collar_center')],
            'markers' => [$this->marker('cb', 'خط مرکز پشت', 0, 0, 0, $height)],
            'meta' => [
                'part' => 'collar',
                'edges' => ['default', 'side', 'neck', 'default'],
                'fold_edges' => [3],
                'neck_length' => Geometry::edgeLength($outline, 2),
                'interfacing' => true,
                'girth_role' => 'trim',
            ],
        ]);
    }

    /**
     * یقه شالی (یک‌سره با سجاف جلو).
     *
     * @return array<string, mixed>
     */
    protected function shawlFacingPiece(array $g, float $stand, float $bottom, array $o = []): array
    {
        $width = (float) ($o['width'] ?? 8);
        $neckW = $g['neck_width'] + $stand;
        $breakY = (float) ($o['break_y'] ?? $g['bust_y'] + 8);

        $outline = [
            Geometry::point(0, 0),
            Geometry::curve($neckW + $width, -$width * 0.5, $neckW * 0.6, -$width * 0.35),
            Geometry::curve($width * 1.2, $breakY, $neckW + $width + 1.5, $breakY * 0.45),
            Geometry::curve($width * 0.9, $bottom, $width * 1.6, $breakY + (($bottom - $breakY) * 0.5)),
            Geometry::point(0, $bottom),
        ];

        return $this->piece([
            'code' => ($o['prefix'] ?? '').'shawl-facing',
            'name' => $o['name'] ?? 'سجاف یقه شالی',
            'cut_quantity' => 2,
            'mirror' => true,
            'outline' => $outline,
            'grainline' => $this->grainline($width * 0.6, $breakY * 0.4, $bottom - 3),
            'markers' => [$this->marker('roll_line', 'خط برگردان', 0, 0, $width * 0.9, $bottom)],
            'meta' => [
                'part' => 'facing',
                'edges' => ['neck', 'default', 'default', 'hem', 'default'],
                'fold_edges' => [],
                'interfacing' => true,
                'girth_role' => 'trim',
            ],
        ]);
    }

    /**
     * سجاف جلو برای لباس جلوباز.
     *
     * @return array<string, mixed>
     */
    protected function frontFacingPiece(array $g, float $stand, float $bottom, array $o = []): array
    {
        $top = $g['neck_width'] + $stand + (float) ($o['neck_extra'] ?? 0);
        $width = max(5.0, (float) ($o['width'] ?? 8));

        $outline = [
            Geometry::point(0, 0),
            Geometry::point($top, 0),
            Geometry::curve($width, $bottom, $top + 1.0, $g['front_waist_y'] * 0.55),
            Geometry::point(0, $bottom),
        ];

        return $this->piece([
            'code' => ($o['prefix'] ?? '').'front-facing',
            'name' => $o['name'] ?? 'سجاف جلو',
            'cut_quantity' => 2,
            'mirror' => true,
            'outline' => $outline,
            'grainline' => $this->grainline($width * 0.5, 3, $bottom - 3),
            'meta' => [
                'part' => 'facing',
                'edges' => ['neck', 'side', 'hem', 'default'],
                'fold_edges' => [],
                'interfacing' => true,
                'girth_role' => 'trim',
            ],
        ]);
    }

    /**
     * سجاف یقه پشت.
     *
     * @return array<string, mixed>
     */
    protected function backNeckFacingPiece(array $g, array $o = []): array
    {
        $neckW = $g['neck_width'] + 0.3 + (float) ($o['neck_width_extra'] ?? 0);
        $depth = $g['back_neck_depth'];
        $width = max(5.0, (float) ($o['width'] ?? 7));
        $shoulder = $g['shoulder_half'];

        $outline = [
            Geometry::point(0, $depth),
            Geometry::curve($neckW, 0, $neckW * 0.34, $depth * 0.28),
            Geometry::point($shoulder, $g['shoulder_drop'] + 0.5),
            Geometry::point($shoulder - 1.5, $g['shoulder_drop'] + $width),
            Geometry::curve(0, $depth + $width, $neckW * 0.6, $depth + $width - 0.5),
        ];

        return $this->piece([
            'code' => ($o['prefix'] ?? '').'back-neck-facing',
            'name' => 'سجاف یقه پشت',
            'cut_quantity' => 1,
            'on_fold' => true,
            'outline' => $outline,
            'grainline' => $this->grainline($neckW * 0.7, $depth + 1, $depth + $width - 1),
            'meta' => [
                'part' => 'facing',
                'edges' => ['neck', 'shoulder', 'default', 'default', 'default'],
                'fold_edges' => [4],
                'interfacing' => true,
                'girth_role' => 'trim',
            ],
        ]);
    }

    /**
     * کلاه (هودی و بارانی): دو پنل قرینه.
     *
     * @return array<string, mixed>
     */
    protected function hoodPiece(array $g, float $halfNeck, array $o = []): array
    {
        $height = (float) ($o['height'] ?? 36);
        $width = (float) ($o['width'] ?? 26);
        $faceGap = (float) ($o['face'] ?? 4);

        $outline = [
            Geometry::point($faceGap, 0),
            Geometry::curve($width, $height * 0.42, $width * 0.9, $height * 0.05),
            Geometry::point($width, $height - 4),
            Geometry::curve($faceGap + 3, $height, $width * 0.5, $height + 1.5),
            Geometry::curve($faceGap, $height * 0.5, 0, $height * 0.78),
        ];

        return $this->piece([
            'code' => ($o['prefix'] ?? '').'hood',
            'name' => $o['name'] ?? 'کلاه',
            'cut_quantity' => 2,
            'mirror' => true,
            'outline' => $outline,
            'grainline' => $this->grainline($width * 0.5, 4, $height - 4),
            'notches' => [$this->notch($faceGap + 3, $height, 3, 'مرکز پشت یقه', 'collar_center')],
            'meta' => [
                'part' => 'hood',
                'edges' => ['default', 'default', 'default', 'neck', 'default'],
                'fold_edges' => [],
                'neck_length' => round(Geometry::edgeLength($outline, 3), 2),
                'target_neck' => round($halfNeck, 2),
                'girth_role' => 'trim',
            ],
        ]);
    }

    /**
     * جیب رو دوخت ساده.
     *
     * @return array<string, mixed>
     */
    protected function patchPocketPiece(float $width, float $height, array $o = []): array
    {
        $outline = [
            Geometry::point(0, 0),
            Geometry::point($width, 0),
            Geometry::point($width, $height - 2),
            Geometry::curve($width - 2, $height, $width - 0.5, $height - 0.5),
            Geometry::point(2, $height),
            Geometry::curve(0, $height - 2, 0.5, $height - 0.5),
        ];

        return $this->piece([
            'code' => ($o['prefix'] ?? '').'pocket',
            'name' => $o['name'] ?? 'جیب رودوزی',
            'cut_quantity' => (int) ($o['cut'] ?? 2),
            'mirror' => (bool) ($o['mirror'] ?? true),
            'outline' => $outline,
            'grainline' => $this->grainline($width * 0.5, 2, $height - 2),
            'markers' => [$this->marker('fold', 'تای دهانه جیب', 0, 2.5, $width)],
            'meta' => [
                'part' => 'pocket',
                'edges' => ['default', 'side', 'hem', 'hem', 'hem', 'side'],
                'fold_edges' => [],
                'girth_role' => 'trim',
            ],
        ]);
    }

    /**
     * آستر بالاتنه: کمی گشادتر از رو، با پیلی راحتی روی مرکز پشت.
     *
     * @param  array<string, float>  $g
     * @param  array<string, mixed>  $o
     * @return array<int, array<string, mixed>>
     */
    protected function bodiceLining(array $g, array $o = []): array
    {
        $grow = (float) ($o['grow'] ?? 0.6);
        $shape = $o['shape'] ?? 'fitted';
        $length = (float) ($o['length'] ?? 0);
        $pleat = (float) ($o['back_pleat'] ?? 1.5);

        $front = $this->bodyPanel($g, array_merge([
            'side' => 'front',
            'shape' => $shape,
            'length' => $length,
            'grow' => $grow,
            'layer' => 'lining',
            'girth_role' => 'lining',
            'on_fold' => false,
            'cut' => 2,
            'code' => ($o['prefix'] ?? '').'lining-front',
            'name' => 'آستر جلو',
            'part' => 'lining',
            'bottom_tag' => 'hem',
        ], $o['front'] ?? []));

        $back = $this->bodyPanel($g, array_merge([
            'side' => 'back',
            'shape' => $shape,
            'length' => $length,
            'grow' => $grow,
            'layer' => 'lining',
            'girth_role' => 'lining',
            'on_fold' => true,
            'cut' => 1,
            'code' => ($o['prefix'] ?? '').'lining-back',
            'name' => 'آستر پشت',
            'part' => 'lining',
            'bottom_tag' => 'hem',
        ], $o['back'] ?? []));

        $back['pleats'][] = [
            'type' => 'pleat',
            'label' => 'پیلی راحتی مرکز پشت',
            'edge' => null,
            'intake' => round($pleat, 2),
            'from' => Geometry::point(0, 1),
            'to' => Geometry::point(0, max(6.0, $g['bust_y'])),
        ];

        $back['meta']['ease_pleat'] = round($pleat, 2);

        return [$front, $back];
    }
}
