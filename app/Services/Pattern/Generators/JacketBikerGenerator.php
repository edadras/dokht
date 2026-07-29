<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * کاپشن بایکر.
 *
 * کاپشن چرمِ موتورسواری. سه نشانه دارد و هر سه از کارِ اصلی‌اش می‌آیند:
 *
 *   ۱. زیپ اریب. زیپِ وسط روی موتور، درست روی استخوان سینه می‌افتد؛ زیپ اریب
 *      از سینه کنار می‌رود. اریب بودنش یعنی هم‌پوشانی جلو باید پهن باشد تا لبهٔ
 *      زیپ روی آن جا شود.
 *   ۲. برش پنلی. کاپشن چرم کش نمی‌آید، پس فرمش را باید از درز بگیرد نه از پارچه.
 *      یوکِ سینه و یوکِ پشت همان درزهایی‌اند که جای بازوی جلوآمدهٔ راکب را
 *      می‌سازند.
 *   ۳. یقهٔ برگردان کوچک با دکمهٔ فشاری. یقهٔ بزرگ روی سرعت بالا بال می‌زند؛
 *      دکمهٔ فشاری نوک یقه را به تنه می‌چسباند.
 *
 * تنه کوتاه و جذب است و کمرش با دو تسمهٔ سگک‌دار تنگ می‌شود.
 */
class JacketBikerGenerator extends OuterwearBaseGenerator
{
    public static function key(): string
    {
        return 'jacket_biker';
    }

    public function label(): string
    {
        return 'کاپشن بایکر';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerwearSchema([
                'armhole_depth_extra' => 3,
                'neck_width_extra' => 2,
                'front_neck_depth_extra' => 4,
                'shoulder_slope' => 3.5,
                'waist_dart_share' => 0.35,
            ], [], 'fitted', 'shirt'),
            $this->garmentLengthParam(8, 0, 26),
            $this->collarParam('turn', ['turn' => 'یقه برگردان کوچک'], 6),
            $this->sleeveParam('two_piece', 62),
            [
                'zip_overlap' => [
                    'label' => 'هم‌پوشانی جلو زیر زیپ اریب', 'min' => 5, 'max' => 16, 'step' => 0.5,
                    'default' => 9, 'unit' => 'سانتی‌متر',
                ],
                'yoke_depth' => [
                    'label' => 'عمق یوک سینه و پشت از سرشانه', 'min' => 6, 'max' => 22, 'step' => 0.5,
                    'default' => 12, 'unit' => 'سانتی‌متر',
                ],
                'waist_tabs' => [
                    'label' => 'تسمهٔ سگک‌دار کمر', 'type' => 'toggle', 'default' => true,
                ],
                'cuff_zip' => [
                    'label' => 'زیپ مچ آستین', 'type' => 'toggle', 'default' => true,
                ],
            ],
            $this->pocketParam(true, 15, 15),
            $this->liningParam(true),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $ease = $this->outerwearEase($ease, $params, 10.0, ['bicep' => 7.0]);
        $g = $this->blockMetrics($measurements, $ease, $params);

        $stand = max(5.0, (float) $this->param($params, 'zip_overlap', 9));
        $length = (float) $this->param($params, 'length', 8);
        $bottomY = $g['front_waist_y'] + $length;

        $pieces = $this->outerGarment($measurements, $ease, $params, $g, [
            'prefix' => 'biker-',
            'grow' => 0.0,
            'shape' => 'fitted',
            'opening' => 'button',
            'stand' => $stand,
            'buttons' => 0, // بست این کاپشن زیپ است، نه دکمه
            // ساسون سینه ندارد: فرم سینه از یوک و درزهای پنلی می‌آید. ساسونِ
            // روی درز پهلو هم با برشِ یوک، شمارهٔ لبه‌اش را از دست می‌داد و دو
            // درز پهلو دیگر هم‌اندازه پیاده نمی‌شدند.
            'bust_dart' => false,
            'front_name' => 'تنه جلوی بایکر (زیر یوک سینه)',
            'back_name' => 'تنه پشت بایکر (زیر یوک پشت)',
            'facing_width' => max(10.0, $stand + 4),
            'lining' => true,
            'lining_options' => ['length' => max(0.0, $length - 1.5)],
            'front_meta' => ['front_opening' => 'zip_asymmetric', 'zip_overlap' => round($stand, 2)],
        ]);

        $rest = array_slice($pieces, 2);
        $yokeY = max(
            $g['front_neck_depth'] + 3.0,
            min($g['bust_y'] - 3.0, (float) $this->param($params, 'yoke_depth', 12)),
        );

        [$frontYoke, $front] = $this->panelYoke($pieces[0], $yokeY, [
            'yoke_code' => 'biker-front-yoke',
            'yoke_name' => 'یوک سینه',
            'center_x' => $stand,
            'lines' => ['bust' => $g['bust_y'], 'waist' => $g['side_waist_y']],
            'yoke_note' => 'یوک سینه پیش از بستن درز پهلو به تنهٔ جلو دوخته می‌شود.',
        ]);

        [$backYoke, $back] = $this->panelYoke($pieces[1], $yokeY, [
            'yoke_code' => 'biker-back-yoke',
            'yoke_name' => 'یوک پشت',
            'center_x' => 0.0,
            'lines' => ['bust' => $g['bust_y'], 'waist' => $g['side_waist_y']],
            'yoke_note' => 'یوک پشت پیش از بستن درز پهلو به تنهٔ پشت دوخته می‌شود.',
        ]);

        // خط زیپ اریب: از لبهٔ بیرونیِ هم‌پوشانی روی سینه تا خط مرکز جلو روی دم
        $top = $g['front_neck_depth'] + 1.0;
        $span = max(1.0, $bottomY - $top);
        $zipX = fn (float $y): float => 1.0 + (($stand - 1.0) * max(0.0, min(1.0, ($y - $top) / $span)));

        if ($frontYoke !== null) {
            $frontYoke['markers'][] = $this->marker(
                'zip',
                'زیپ اریب — روی یوک سینه',
                $zipX($top),
                $top,
                $zipX($yokeY - 0.3),
                $yokeY - 0.3,
            );
        }

        $front = $this->markDiagonalZip(
            $front,
            $zipX($yokeY + 0.3),
            0.3,
            $zipX($bottomY - 1.0),
            $bottomY - 1.0 - $yokeY,
            'زیپ اریب — روی تنهٔ جلو',
        );

        $front['meta']['notes'] = array_merge($front['meta']['notes'] ?? [], [
            'این الگو دو نیمهٔ قرینه می‌دهد، ولی جلوی بایکر قرینه نیست: '
                .'نیمهٔ رویی پاتلت زیپ می‌گیرد و نیمهٔ زیرین بدون پاتلت بریده می‌شود. '
                .'هنگام برش، نیمهٔ دوم را از همین الگو ولی بدون اضافهٔ پاتلت ببرید.',
        ]);

        $pieces = array_merge(
            array_values(array_filter([$front, $back, $frontYoke, $backYoke])),
            $rest,
        );

        $notes = [
            'زیپ جلو اریب است: از لبهٔ بیرونی هم‌پوشانی روی سینه تا خط مرکز جلو روی دم می‌رود '
                .'و از استخوان سینه کنار می‌ماند.',
            'یوک سینه و یوک پشت هر دو از عمق '.$this->fa(round($yokeY, 1))
                .' سانتی‌متری سرشانه بریده شده‌اند؛ حلقهٔ آستین میان یوک و تنه تقسیم می‌شود و هر دو سهم خود را نگه می‌دارند.',
        ];

        $pieces[] = $this->bandPiece('biker-zip-placket', 'پاتلت زیپ اریب', Geometry::height($front['outline']) + $yokeY, $stand * 0.6, [
            'cut' => 2, 'part' => 'placket',
            'meta' => [
                'interfacing' => true,
                'notes' => ['زیر لبهٔ زیپ دوخته می‌شود تا دندانهٔ زیپ به تن نخورد.'],
            ],
        ]);

        $pieces[] = $this->bandPiece('biker-collar-snap', 'زبانهٔ دکمهٔ فشاری یقه', 7, 3, [
            'cut' => 2, 'part' => 'collar',
            'meta' => [
                'interfacing' => true,
                'notions' => [['type' => 'snap', 'label' => 'دکمهٔ فشاری نوک یقه', 'count' => 1, 'per_cut' => true]],
                'notes' => ['نوک یقه را به تنه می‌چسباند تا روی سرعت بالا بال نزند.'],
            ],
        ]);

        if ($this->flag($params, 'waist_tabs', true)) {
            $pieces[] = $this->bandPiece('biker-waist-tab', 'تسمهٔ کمر', 22, 4, [
                'cut' => 4, 'part' => 'belt',
                'meta' => [
                    'interfacing' => true,
                    'notions' => [['type' => 'other', 'label' => 'سگک تسمهٔ کمر', 'count' => 2]],
                    'notes' => ['دو تسمه، هرکدام از دو لایه؛ روی درز پهلو و هم‌تراز خط کمر دوخته می‌شود.'],
                ],
            ]);
        }

        if ($this->flag($params, 'cuff_zip', true)) {
            $pieces[] = $this->bandPiece('biker-cuff-placket', 'پاتلت زیپ مچ آستین', 14, 4, [
                'cut' => 4, 'part' => 'cuff',
                'meta' => [
                    'notions' => [['type' => 'zip', 'label' => 'زیپ مچ آستین', 'count' => 2, 'length' => 12]],
                    'notes' => ['روی درز پشتِ مچ آستین دوخته می‌شود.'],
                ],
            ]);
        }

        if ($this->flag($params, 'pocket', true)) {
            $welt = $this->weltPocketSet(
                (float) $this->param($params, 'pocket_width', 15),
                (float) $this->param($params, 'pocket_height', 15),
                ['prefix' => 'biker-', 'welt' => 2.5, 'name' => 'مغزی جیب زیپ‌دار'],
            );

            $welt[0]['meta']['notions'][] = [
                'type' => 'zip',
                'label' => 'زیپ جیب',
                'count' => 2,
                'length' => round((float) $this->param($params, 'pocket_width', 15), 1),
            ];

            $pieces = array_merge($pieces, $welt);
        }

        return $this->finishOuterwear($pieces, $g, $ease, $params, $notes);
    }
}
