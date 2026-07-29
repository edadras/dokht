<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * چیپائو (قباد چینی).
 *
 * سه چیز چیپائو را چیپائو می‌کند و هر سه در این الگو هست:
 *
 *   ۱. یقه ایستاده چینی که دور گردن می‌ایستد و جلوی آن به هم می‌رسد.
 *   ۲. بستِ اریب (大襟): خطی که از گودی یقه مرکز جلو کج می‌شود و زیر بغل راست
 *      تمام می‌گردد. تنه روی تای پارچه بریده می‌شود و بعد فقط لایه رو از همین
 *      خط بریده می‌شود؛ تکه‌ای که جدا می‌شود همان «زیرلبه» است که زیر لبه رو
 *      دوخته می‌گردد. (الگوی زیرلبه جدا هم داده شده تا اگر پارچه کم بود جداگانه
 *      بریده شود.)
 *   ۳. فرم چسبان با ساسون سینه روی درز پهلو و ساسون کمر، و چاک پهلو که بدون آن
 *      اصلاً نمی‌شود راه رفت.
 *
 * بست با دکمه‌های گره‌ای (قزاقی) بسته می‌شود و برای پوشیدن، زیپ مخفی روی درز
 * پهلوی چپ گذاشته می‌شود؛ بدون آن لباس چسبان از باسن رد نمی‌شود.
 */
class TradQipaoGenerator extends TraditionalBaseGenerator
{
    public static function key(): string
    {
        return 'trad_qipao';
    }

    public function label(): string
    {
        return 'چیپائو (قباد چینی)';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerSchema([
                'shoulder_slope' => 4.5,
                'neck_width_extra' => 0.5,
                'front_neck_depth_extra' => 1,
                'back_neck_depth' => 2,
                'armhole_depth_extra' => 1,
                'waist_dart_share' => 0.55,
            ]),
            $this->fitParam('fitted'),
            $this->garmentLengthParam(72, 20, 105),
            $this->sleeveParam('set_in', 16, [
                'none' => 'بدون آستین (نوار حلقه)',
                'set_in' => 'آستین حلقه‌ای',
            ]),
            [
                'collar_height' => [
                    'label' => 'بلندی یقه ایستاده', 'min' => 2, 'max' => 9, 'step' => 0.5,
                    'default' => 4.5, 'unit' => 'سانتی‌متر',
                    'hint' => 'یقه چیپائو دور گردن می‌ایستد؛ بلندتر از پنج سانتی‌متر گردن را می‌آزارد.',
                ],
                'closure_start' => [
                    'label' => 'شروع بستِ اریب زیر خط یقه', 'min' => 0, 'max' => 12, 'step' => 0.5,
                    'default' => 3, 'unit' => 'سانتی‌متر',
                ],
                'overlap' => [
                    'label' => 'هم‌پوشانی لبه بست', 'min' => 1.5, 'max' => 8, 'step' => 0.5,
                    'default' => 3, 'unit' => 'سانتی‌متر',
                ],
                'side_vent' => [
                    'label' => 'بلندی چاک پهلو', 'min' => 0, 'max' => 60, 'step' => 1,
                    'default' => 34, 'unit' => 'سانتی‌متر',
                ],
                'frogs' => [
                    'label' => 'تعداد دکمه گره‌ای روی بست', 'min' => 2, 'max' => 8, 'step' => 1,
                    'default' => 4,
                ],
                'side_zip' => [
                    'label' => 'زیپ مخفی درز پهلوی چپ', 'type' => 'toggle', 'default' => true,
                    'hint' => 'چیپائوی چسبان بدون زیپ از باسن رد نمی‌شود.',
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = $this->fitGrow($params, ['fitted' => 0.0, 'regular' => 1.0, 'loose' => 2.5]);
        $length = (float) $this->param($params, 'length', 72);
        $overlap = (float) $this->param($params, 'overlap', 3);
        $start = (float) $this->param($params, 'closure_start', 3);
        $vent = (float) $this->param($params, 'side_vent', 34);

        $shared = [
            'shape' => 'fitted',
            'length' => $length,
            'grow' => $grow,
            'waist_dart' => true,
            'bottom_tag' => 'hem',
        ];

        $front = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'front',
            'bust_dart' => true,
            'code' => 'qipao-front',
            'name' => 'تنه جلو (با خط بستِ اریب)',
        ]));

        $back = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'back',
            'code' => 'qipao-back',
            'name' => 'تنه پشت',
        ]));

        [$front, $back] = $this->walkSideSeams($front, $back);

        $flap = $this->underFlapPiece($g, $grow, $start, $overlap);
        $closure = (float) ($flap['meta']['closure_length'] ?? 30);

        $front = $this->markClosure($front, $g, $grow, $start, $closure, (int) $this->param($params, 'frogs', 4));
        $front = $this->markSideVent($front, $vent);
        $back = $this->markSideVent($back, $vent);

        if ($this->flag($params, 'side_zip', true)) {
            $front = $this->markSideZip($front, $g);
        }

        $armhole = $this->armholeOf([$front, $back]);
        $halfNeck = $this->neckOf([$front, $back]);
        $style = (string) $this->param($params, 'sleeve_style', 'set_in');
        $sleeveLength = (float) $this->param($params, 'sleeve_length', 16);

        $pieces = [$front, $back, $flap];

        $pieces = array_merge($pieces, $this->sleeveSet($measurements, $ease, $params, $armhole, $g, [
            'prefix' => 'qipao-',
            'sleeve_name' => 'آستین چیپائو',
        ]));

        if ($style === 'none' || $sleeveLength < 4) {
            $pieces[] = $this->armholeBindingPiece($armhole, ['prefix' => 'qipao-', 'height' => 3.5]);
        }

        $pieces[] = $this->standCollarPiece($halfNeck, (float) $this->param($params, 'collar_height', 4.5), [
            'prefix' => 'qipao-',
            'name' => 'یقه ایستاده چینی',
        ]);

        $pieces[] = $this->bandPiece('qipao-closure-facing', 'نوار اریب لبه بست', $closure + $g['neck_width'] + 6, 4, [
            'cut' => 2, 'part' => 'facing',
            'meta' => [
                'bias' => true,
                'girth_role' => 'trim',
                'notes' => [
                    'روی اریب بریده می‌شود تا روی منحنی بست بخوابد؛ یکی برای لبه رو و یکی برای زیرلبه.',
                ],
            ],
        ]);

        $pieces[0] = $this->markCoverage($pieces[0], [
            'hem' => $this->hemFromShoulder($front),
            'hem_at' => $length >= 60 ? 'زیر زانو' : 'بالای زانو',
            'sleeve' => $style === 'none' || $sleeveLength < 4
                ? 'ندارد؛ حلقه با نوار اریب تمام می‌شود'
                : $this->fa(round($sleeveLength)).' سانتی‌متر از سرشانه',
            'neck' => 'ایستاده و بسته تا زیر گلو',
        ]);

        $pieces[0]['meta']['notes'] = array_merge($pieces[0]['meta']['notes'] ?? [], $this->modestNotes([
            'چاک پهلو به بلندی '.$this->fa(round($vent)).' سانتی‌متر باز می‌ماند؛ چیپائوی بدون چاک پوشیدنی نیست.',
        ]));

        return $this->finishBlock($pieces, $g, $grow);
    }

    /**
     * خط بستِ اریب روی تنه جلو، با نشانه دو سر و جای دکمه‌های گره‌ای.
     *
     * @param  array<string, mixed>  $piece
     * @param  array<string, float>  $g
     * @return array<string, mixed>
     */
    protected function markClosure(array $piece, array $g, float $grow, float $start, float $closure, int $frogs): array
    {
        $topY = $g['front_neck_depth'] + $start;
        $side = $this->sidePointAt($piece, $g['bust_y']);

        $piece['markers'][] = $this->marker('closure', 'خط بستِ اریب', 0, $topY, $side['x'], $side['y']);
        $piece['notches'][] = $this->notchAt(
            $piece['outline'],
            $piece['meta']['edges'],
            0,
            $topY,
            'سر بالای بست روی مرکز جلو',
            'closure_top',
            'default',
        );
        $piece['notches'][] = $this->notchAt(
            $piece['outline'],
            $piece['meta']['edges'],
            $side['x'],
            $side['y'],
            'سر پایین بست زیر بغل',
            'closure_end',
            'side',
        );

        $piece['meta']['closure_length'] = round($closure, 2);
        $piece['meta']['notions'][] = [
            'type' => 'other',
            'label' => 'دکمه گره‌ای (قزاقی)',
            'count' => max(2, $frogs),
        ];

        $piece['meta']['notes'] = array_merge($piece['meta']['notes'] ?? [], [
            'تنه جلو روی تای پارچه بریده می‌شود؛ بعد فقط لایه رو از همین خط بریده می‌شود و '
                .'تکه جداشده همان زیرلبه است.',
            'خط بست '.$this->fa(round($closure, 1)).' سانتی‌متر است و '
                .$this->fa(max(2, $frogs)).' دکمه گره‌ای رویش می‌نشیند.',
        ]);

        return $piece;
    }

    /** زیپ مخفی روی درز پهلوی چپ، از زیر بغل تا پایین‌تر از باسن. */
    protected function markSideZip(array $piece, array $g): array
    {
        $bottomY = Geometry::bounds($piece['outline'])[3];
        $top = $this->sidePointAt($piece, $g['bust_y'] + 2);
        $bottom = $this->sidePointAt($piece, min($g['hip_y'] + 2, $bottomY - 1));
        $zip = round(Geometry::distance($top, $bottom), 1);

        if ($zip < 12) {
            return $piece;
        }

        $piece['markers'][] = $this->marker('zip', 'زیپ مخفی درز پهلوی چپ', $top['x'], $top['y'], $bottom['x'], $bottom['y']);
        $piece['meta']['zip_length'] = $zip;
        $piece['meta']['notions'][] = [
            'type' => 'zip',
            'label' => 'زیپ مخفی درز پهلوی چپ',
            'count' => 1,
            'length' => $zip,
        ];

        return $piece;
    }

    /**
     * زیرلبه (小襟): تکه بالای راستِ جلو که زیر لبه رو می‌خوابد.
     *
     * مرزهایش همان مرزهای گوشه بالای پنل جلو است — یقه، سرشانه و حلقه — و لبه
     * چهارمش خودِ خط بست، که به اندازه هم‌پوشانی بیرون‌تر کشیده شده تا زیر لبه رو
     * بماند.
     *
     * @param  array<string, float>  $g
     * @return array<string, mixed>
     */
    protected function underFlapPiece(array $g, float $grow, float $start, float $overlap): array
    {
        $qb = $g['quarter_bust'] + $grow;
        $neckW = $g['neck_width'];
        $neckD = $g['front_neck_depth'];
        $topY = $neckD + $start;
        $shoulderX = min($g['shoulder_half'], $qb - 0.6);
        $shoulderY = $g['shoulder_drop'];
        $across = min($qb - 3.0, $g['across_chest'] + ($grow * 0.5));
        $acrossY = $shoulderY + (($g['bust_y'] - $shoulderY) * 0.62);

        // نقطه کنترل خط بست به نقطه‌ای می‌چسبد که به آن می‌رسیم؛ پس روی نقطه اول
        // می‌نشیند، چون لبه بسته‌شدن مسیر همان خط بست است.
        $outline = [
            Geometry::curve(0, $topY + $overlap, $qb * 0.30, $topY + (($g['bust_y'] - $topY) * 0.78) + $overlap),
            Geometry::curve($neckW, 0, $neckW * 0.10, $neckD * 0.10),
            Geometry::point($shoulderX, $shoulderY),
            Geometry::curve($across, $acrossY, $shoulderX + 0.4, $shoulderY + (($acrossY - $shoulderY) * 0.62)),
            Geometry::curve($qb, $g['bust_y'], $across + (($qb - $across) * 0.16), $g['bust_y'] - (($g['bust_y'] - $acrossY) * 0.06)),
        ];

        $edges = ['neck', 'shoulder', 'armhole', 'armhole', 'default'];

        $piece = $this->piece([
            'code' => 'qipao-under-flap',
            'name' => 'زیرلبه بست (小襟)',
            'cut_quantity' => 1,
            'mirror' => false,
            'outline' => $outline,
            'grainline' => $this->grainline($qb * 0.45, 2, $g['bust_y'] - 2),
            'notches' => [
                $this->notchAt($outline, $edges, 0, $topY + $overlap, 'سر بالای بست', 'closure_top', 'default'),
            ],
            'meta' => [
                'part' => 'front_under_flap',
                'side' => 'front',
                'edges' => $edges,
                'fold_edges' => [],
                'girth_role' => 'trim',
                'overlap' => round($overlap, 2),
                'notes' => [
                    'زیر لبه رو می‌خوابد و '.$this->fa(round($overlap, 1))
                        .' سانتی‌متر از خط بست بیرون‌تر بریده شده تا هم‌پوشانی داشته باشد.',
                    'لبه بلندش (لبه بست) با نوار اریب تمیز می‌شود.',
                ],
            ],
        ]);

        $piece['meta']['closure_length'] = round(Geometry::edgeLength($piece['outline'], 4), 2);

        return $piece;
    }
}
