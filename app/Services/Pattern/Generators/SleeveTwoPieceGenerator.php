<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * آستین دوتکه کتی (آستین بالا و آستین زیر).
 *
 * روش کار همان کاری است که خیاط روی الگوی یک‌تکه می‌کند:
 *
 *   ۱. آستین یک‌تکه با درزهای زیر بغل «راست» درفت می‌شود.
 *   ۲. دو خط برش زده می‌شود: یکی به فاصله چند سانتی‌متر از درز جلو و یکی از درز
 *      پشت. تکه میانی «آستین بالا» می‌شود.
 *   ۳. دو نوار کناری از روی درز زیر بغل به هم می‌چرخند (سوئینگ) و «آستین زیر» را
 *      می‌سازند؛ چون چرخش طول را عوض نمی‌کند، دو درز آستین زیر دقیقاً هم‌اندازه
 *      درزهای آستین بالا در می‌آید و الگو بدون تنظیم دستی دوخته می‌شود.
 *
 * مجموع سرآستین دو قطعه همان «طول حلقه + آزادی سرآستین» است.
 */
class SleeveTwoPieceGenerator extends SleeveCatalogGenerator
{
    public static function key(): string
    {
        return 'sleeve_two_piece';
    }

    public function label(): string
    {
        return 'آستین دوتکه کتی';
    }

    public function paramsSchema(): array
    {
        return $this->commonSleeveSchema(2.5, 100, 4) + [
            'front_seam_shift' => [
                'label' => 'فاصله درز جلو از زیر بغل', 'min' => 2, 'max' => 8, 'step' => 0.5, 'default' => 5,
                'unit' => 'سانتی‌متر', 'hint' => 'درز جلو باید از جلوی آستین دیده نشود.',
            ],
            'back_seam_shift' => [
                'label' => 'فاصله درز پشت از زیر بغل', 'min' => 2, 'max' => 8, 'step' => 0.5, 'default' => 4,
                'unit' => 'سانتی‌متر',
            ],
            'under_hem_width' => [
                'label' => 'پهنای دم آستین زیر', 'min' => 3, 'max' => 12, 'step' => 0.5, 'default' => 6,
                'unit' => 'سانتی‌متر',
            ],
            'seam_curve' => [
                'label' => 'خمیدگی درزها به سمت جلو', 'min' => 0, 'max' => 3, 'step' => 0.1, 'default' => 1.0,
                'unit' => 'سانتی‌متر', 'hint' => 'آستین کتی کمی به جلو خم می‌شود تا با حالت طبیعی بازو بخواند.',
            ],
        ];
    }

    protected function sleeveSpec(array $m, array $ease, array $params): array
    {
        return [
            'code' => 'sleeve-upper',
            'name' => 'آستین بالا',
            'family' => 'two_piece',
            'ease_band' => [2.0, 4.0],
            'bicep_ease' => 4.5,
            'side_bulge' => 0.0,
            'hem_ease' => (float) $this->param($params, 'hem_ease', 4),
        ];
    }

    protected function sleevePieces(array $frame, array $m, array $ease, array $params, array $spec): array
    {
        [$base] = $this->sleeveBody($frame, $spec);

        $width = (float) $frame['width'];
        $length = (float) $frame['length'];
        $capHeight = (float) $frame['cap_height'];
        $center = (float) $frame['center'];
        $hemHalf = ((float) $frame['hem_width']) / 2;

        $front = min($width * 0.22, max(2.0, (float) $this->param($params, 'front_seam_shift', 5)));
        $back = min($width * 0.22, max(2.0, (float) $this->param($params, 'back_seam_shift', 4)));
        $underHem = max(3.0, min((float) $frame['hem_width'] - 4, (float) $this->param($params, 'under_hem_width', 6)));
        $bow = max(0.0, (float) $this->param($params, 'seam_curve', 1.0));

        // خط‌های برش روی منحنی سرآستین
        [$frontLower, $frontUpper, $sf] = $this->splitCurve($base[0], $base[1], $this->tAtX($base, 0, $front));
        [$backUpper, $backLower, $sb] = $this->splitCurve($base[3], $base[4], $this->tAtX($base, 3, $width - $back));

        $hemLeft = ['x' => $center - $hemHalf, 'y' => $length];
        $hemRight = ['x' => $center + $hemHalf, 'y' => $length];
        $hf = ['x' => $hemLeft['x'] + ($underHem / 2), 'y' => $length];
        $hb = ['x' => $hemRight['x'] - ($underHem / 2), 'y' => $length];

        // درزهای مشترک دو قطعه، با کمی خمیدگی به سمت جلو
        $frontSeamControl = $this->seamControl($sf, $hf, $bow, 0.5, -1);
        $backSeamControl = $this->seamControl($sb, $hb, $bow, 0.5, -1);

        $upper = [
            Geometry::point($sf['x'], $sf['y']),
            $frontUpper,
            $base[2],
            $base[3],
            $backUpper,
            Geometry::curve($hb['x'], $hb['y'], $backSeamControl['x'], $backSeamControl['y']),
            Geometry::point($hf['x'], $hf['y']),
        ];
        $upper[0] = Geometry::curve($sf['x'], $sf['y'], $frontSeamControl['x'], $frontSeamControl['y']);
        $upperEdges = ['armhole', 'armhole', 'armhole', 'armhole', 'side', 'hem', 'side'];

        $under = $this->underSleeve($base, $frame, [
            'front_lower' => $frontLower,
            'back_lower' => $backLower,
            'sf' => $sf,
            'sb' => $sb,
            'hf' => $hf,
            'hb' => $hb,
            'hem_left' => $hemLeft,
            'hem_right' => $hemRight,
            'front_control' => $frontSeamControl,
            'back_control' => $backSeamControl,
        ]);

        $upperCap = Geometry::edgesLength($upper, [0, 1, 2, 3]);
        $underCap = Geometry::edgesLength($under['outline'], [0, 1]);
        $total = $upperCap + $underCap;

        $elbowY = (float) $frame['elbow_y'];
        $upperElbowFront = Geometry::pointOnEdge($upper, 6, $this->tAtY($upper, 6, $elbowY));

        $upperPiece = $this->finishSleeve($upper, $upperEdges, $frame, array_merge($spec, [
            'code' => 'sleeve-upper',
            'name' => 'آستین بالا (دوتکه)',
            'armhole_share' => $total > 0 ? $upperCap / $total : 1,
            'notches' => array_merge(
                $this->capNotches($upper, $frame, ['cap_edges' => [0, 1, 2, 3], 'underarm_notches' => false]),
                [
                    $this->notch($sf['x'], $sf['y'], 6, 'سر درز جلو', 'two_piece_front'),
                    $this->notch($sb['x'], $sb['y'], 4, 'سر درز پشت', 'two_piece_back'),
                    $this->notch($upperElbowFront['x'], $upperElbowFront['y'], 6, 'نشانه آرنج روی درز جلو', 'two_piece_elbow'),
                ],
            ),
            'meta' => [
                'two_piece_role' => 'upper',
                'front_seam_length' => Geometry::edgeLength($upper, 6),
                'back_seam_length' => Geometry::edgeLength($upper, 4),
            ],
            'notes' => [
                'آستین بالا و آستین زیر با هم روی حلقه می‌نشینند؛ سرآستین آستین بالا '
                    .round($upperCap, 1).' و آستین زیر '.round($underCap, 1).' سانتی‌متر است.',
            ],
        ]));

        $underPiece = $this->finishSleeve($under['outline'], $under['edges'], $frame, array_merge($spec, [
            'code' => 'sleeve-under',
            'name' => 'آستین زیر (دوتکه)',
            'cap_edges' => [0, 1],
            'armhole_share' => $total > 0 ? $underCap / $total : 0,
            'notches' => [
                $this->notch($under['sf']['x'], $under['sf']['y'], 2, 'سر درز جلو', 'two_piece_front'),
                $this->notch($under['sb']['x'], $under['sb']['y'], 5, 'سر درز پشت', 'two_piece_back'),
                $this->notch($under['underarm']['x'], $under['underarm']['y'], 1, 'زیر بغل', 'underarm'),
            ],
            'replace_markers' => true,
            'markers' => [
                $this->marker('bicep', 'خط بازو', $under['sb']['x'], $under['sb']['y'], $under['sf']['x'], $under['sf']['y']),
                $this->marker('sleeve_center', 'خط میانی آستین زیر', $under['underarm']['x'], $under['underarm']['y'], $under['underarm']['x'], $length),
            ],
            'meta' => [
                'two_piece_role' => 'under',
                'bicep_width' => round(abs($under['sf']['x'] - $under['sb']['x']), 2),
                'hem_width' => round($underHem, 2),
                'front_seam_length' => Geometry::edgeLength($under['outline'], 2),
                'back_seam_length' => Geometry::edgeLength($under['outline'], 5),
                'swing_angle' => $under['angle'],
            ],
            'notes' => [
                'آستین زیر با چرخاندن دو نوار کناری روی درز زیر بغل ساخته شد، پس درزهایش '
                    .'دقیقاً هم‌اندازه درزهای آستین بالاست.',
            ],
        ]));

        return [$upperPiece, $underPiece];
    }

    /**
     * آستین زیر: نوار جلو و نوار پشتِ چرخانده‌شده که روی درز زیر بغل به هم می‌رسند.
     *
     * @return array<string, mixed>
     */
    protected function underSleeve(array $base, array $frame, array $parts): array
    {
        $p0 = ['x' => (float) $base[0]['x'], 'y' => (float) $base[0]['y']];
        $p4 = ['x' => (float) $base[4]['x'], 'y' => (float) $base[4]['y']];
        $hemLeft = $parts['hem_left'];
        $hemRight = $parts['hem_right'];

        // چرخشی که درز زیر بغل پشت را روی درز زیر بغل جلو می‌خواباند
        $angle = atan2($hemLeft['y'] - $p0['y'], $hemLeft['x'] - $p0['x'])
            - atan2($hemRight['y'] - $p4['y'], $hemRight['x'] - $p4['x']);

        $spin = function (array $point) use ($angle, $p4, $p0) {
            $moved = $this->rotatePoints([$point], $angle, $p4)[0];
            $moved['x'] = round(((float) $moved['x']) + ($p0['x'] - $p4['x']), 4);
            $moved['y'] = round(((float) $moved['y']) + ($p0['y'] - $p4['y']), 4);

            if (isset($moved['cx'], $moved['cy'])) {
                $moved['cx'] = round(((float) $moved['cx']) + ($p0['x'] - $p4['x']), 4);
                $moved['cy'] = round(((float) $moved['cy']) + ($p0['y'] - $p4['y']), 4);
            }

            return $moved;
        };

        $sbTurned = $spin(Geometry::point($parts['sb']['x'], $parts['sb']['y']));
        $p4Turned = $spin($parts['back_lower']);          // منحنی سرآستین پشت که به زیر بغل می‌رسد
        $hbTurned = $spin(Geometry::point($parts['hb']['x'], $parts['hb']['y']));
        $backControlTurned = $spin(Geometry::point($parts['back_control']['x'], $parts['back_control']['y']));

        $outline = [
            // لبه بسته‌شدن (درز پشت) روی همین نقطه می‌نشیند
            Geometry::curve($sbTurned['x'], $sbTurned['y'], $backControlTurned['x'], $backControlTurned['y']),
            $p4Turned,
            $parts['front_lower'],
            Geometry::curve($parts['hf']['x'], $parts['hf']['y'], $parts['front_control']['x'], $parts['front_control']['y']),
            Geometry::point($hemLeft['x'], $hemLeft['y']),
            Geometry::point($hbTurned['x'], $hbTurned['y']),
        ];

        return [
            'outline' => $outline,
            'edges' => ['armhole', 'armhole', 'side', 'hem', 'hem', 'side'],
            'sf' => ['x' => (float) $parts['sf']['x'], 'y' => (float) $parts['sf']['y']],
            'sb' => ['x' => (float) $sbTurned['x'], 'y' => (float) $sbTurned['y']],
            'underarm' => $p0,
            'angle' => round(rad2deg($angle), 2),
        ];
    }
}
