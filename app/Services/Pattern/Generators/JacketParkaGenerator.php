<?php

namespace App\Services\Pattern\Generators;

/**
 * پارکا.
 *
 * کاپشن بلندِ زمستانی با کلاهِ خزدار. سه چیز پارکا را از هر کاپشن دیگری جدا
 * می‌کند و هر سه در الگو ردی دارند:
 *
 *   ۱. بلندی تا ران. پارکا باید نشیمن را بپوشاند، وگرنه باد از زیرش می‌آید.
 *      همین بلندی یعنی روی خط باسن باید جا باشد؛ آزادی باسن این مدل عمداً از
 *      آزادی سینه کمتر نیست.
 *   ۲. کمرِ بندی. پارکای راست و بلند، تنه را مثل چادر نشان می‌دهد و باد را هم
 *      داخل نگه نمی‌دارد. بندِ کمر پارچه را جمع می‌کند — ولی الگو به اندازهٔ
 *      **باز** بریده می‌شود، نه جمع‌شده؛ همین در meta.gathers ثبت شده است.
 *   ۳. کلاه خزدار. خز روی لبهٔ صورتِ کلاه می‌نشیند و باد را از صورت دور می‌کند.
 *      خودِ خز خریدنی است و درفت نمی‌شود؛ نوار پایه‌اش هست.
 */
class JacketParkaGenerator extends OuterwearBaseGenerator
{
    public static function key(): string
    {
        return 'jacket_parka';
    }

    public function label(): string
    {
        return 'پارکا';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerwearSchema([
                'armhole_depth_extra' => 5.5,
                'neck_width_extra' => 3,
                'front_neck_depth_extra' => 2.5,
                'shoulder_slope' => 3.5,
            ], [], 'regular', 'knit'),
            $this->garmentLengthParam(38, 20, 70),
            $this->sleeveParam('set_in', 62),
            [
                'waist_draw' => [
                    'label' => 'جمع‌شدن کمر با بند', 'min' => 0, 'max' => 30, 'step' => 1,
                    'default' => 14, 'unit' => 'سانتی‌متر',
                    'hint' => 'الگو به اندازهٔ باز بریده می‌شود؛ این عدد فقط می‌گوید بند چقدر جمعش می‌کند.',
                ],
                'hem_draw' => [
                    'label' => 'جمع‌شدن لبهٔ پایین با بند', 'min' => 0, 'max' => 30, 'step' => 1,
                    'default' => 10, 'unit' => 'سانتی‌متر',
                ],
                'fur_trim' => [
                    'label' => 'خز لبهٔ کلاه', 'type' => 'toggle', 'default' => true,
                ],
                'storm_flap' => [
                    'label' => 'لتِ روی زیپ', 'type' => 'toggle', 'default' => true,
                ],
            ],
            $this->pocketParam(true, 18, 19),
            $this->liningParam(true),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $ease = $this->outerwearEase($ease, $params, 17.0, ['hip' => 2.0, 'bicep' => 11.0]);
        $g = $this->blockMetrics($measurements, $ease, $params);

        $length = (float) $this->param($params, 'length', 38);
        $waistDraw = (float) $this->param($params, 'waist_draw', 14);
        $hemDraw = (float) $this->param($params, 'hem_draw', 10);

        $pieces = $this->outerGarment($measurements, $ease, $params, $g, [
            'prefix' => 'parka-',
            'grow' => 0.0,
            'shape' => 'straight',
            'opening' => 'zip',
            'collar' => 'none',
            'front_name' => 'تنه جلوی پارکا',
            'back_name' => 'تنه پشت پارکا',
            'facing_width' => 8,
            'panel' => ['waist_dart' => false],
            'lining' => true,
            'lining_options' => ['shape' => 'straight', 'length' => max(0.0, $length - 2), 'back_pleat' => 2.5],
        ]);

        $halfNeck = $this->neckOf([$pieces[0], $pieces[1]]);
        $hipY = $g['side_waist_y'] + $g['hip_drop'];

        $notes = [
            'پارکا تا میان ران می‌آید؛ آزادی باسن این الگو '
                .$this->fa(round($this->ease($ease, 'hip', 0), 1))
                .' سانتی‌متر است تا لبهٔ پایین روی باسن گیر نکند.',
        ];

        // بند کمر: روی هر دو پنل تنه ثبت می‌شود، چون هر دو جمع می‌شوند
        if ($waistDraw > 0.5) {
            $waistPer = $waistDraw / 4;

            foreach ([0, 1] as $index) {
                $pieces[$index]['markers'][] = $this->marker(
                    'drawcord',
                    'جای بند کمر',
                    0,
                    round($g['side_waist_y'], 2),
                    round($this->panelWidthAt($pieces[$index], $g['side_waist_y']), 2),
                );
                // بند کمر روی هیچ لبه‌ای نیست: وسط پنل می‌افتد، پس بی‌شمارهٔ لبه ثبت می‌شود
                $pieces[$index] = $this->markDrawcord(
                    $pieces[$index],
                    '',
                    $waistPer,
                    'بند کمر',
                    $this->m($measurements, 'waist', 74) + 40,
                );
            }

            $notes[] = 'خط بند کمر روی هر دو پنل نشانه دارد؛ جای بند از داخل، روی همان خط دوخته می‌شود.';
        }

        $pieces[0] = $this->markDrawcord(
            $pieces[0],
            'hem',
            $hemDraw / 2,
            'بند لبهٔ پایین',
            $this->m($measurements, 'hip', 98) + 50,
        );

        $pieces = array_merge($pieces, $this->hoodSet($g, $halfNeck, [
            'prefix' => 'parka-',
            'width_extra' => 8,
            'height_ratio' => 2.15,
            'name' => 'کلاه پارکا',
            'facing_width' => 8,
        ]));

        if ($this->flag($params, 'fur_trim', true)) {
            $pieces[] = $this->bandPiece('parka-fur-band', 'نوار پایهٔ خز کلاه', max(24.0, $halfNeck * 2.2), 6, [
                'cut' => 1, 'part' => 'facing',
                'meta' => [
                    'notions' => [['type' => 'snap', 'label' => 'دکمهٔ فشاری خز جداشونده', 'count' => 6]],
                    'notes' => [
                        'خودِ خز خریدنی است و درفت نمی‌شود؛ این نوار پایه‌ای است که خز روی آن دوخته و '
                            .'با دکمهٔ فشاری به لبهٔ صورتِ کلاه بسته می‌شود تا برای شست‌وشو جدا شود.',
                    ],
                ],
            ]);
        }

        if ($this->flag($params, 'storm_flap', true)) {
            $pieces[] = $this->bandPiece('parka-storm-flap', 'لتِ روی زیپ', $g['front_waist_y'] + $length - $g['front_neck_depth'], 5, [
                'cut' => 2, 'part' => 'facing',
                'meta' => [
                    'interfacing' => true,
                    'notions' => [['type' => 'snap', 'label' => 'دکمهٔ فشاری لتِ زیپ', 'count' => 5]],
                    'notes' => ['روی زیپ می‌افتد و باد و آب را از دندانهٔ زیپ دور می‌کند.'],
                ],
            ]);
        }

        $pieces[0]['meta']['notions'][] = [
            'type' => 'zip',
            'label' => 'زیپ دوطرفهٔ جداشونده',
            'count' => 1,
            'length' => round($g['front_waist_y'] + $length - $g['front_neck_depth'], 1),
        ];
        $notes[] = 'زیپ دوطرفه است: از پایین هم باز می‌شود تا نشستن و راه رفتن در پارکای بلند راحت بماند.';

        if ($this->flag($params, 'pocket', true)) {
            $pieces = array_merge($pieces, $this->cargoPocketSet(
                (float) $this->param($params, 'pocket_width', 18),
                (float) $this->param($params, 'pocket_height', 19),
                ['prefix' => 'parka-', 'depth' => 4.0, 'flap' => 6.0, 'name' => 'جیب بزرگ پارکا'],
            ));
            $notes[] = 'جیب‌ها روی خط باسن ('.$this->fa(round($hipY, 1)).' سانتی‌متر از سرشانه) می‌نشینند تا دست به‌راحتی برسد.';
        }

        return $this->finishOuterwear($pieces, $g, $ease, $params, $notes);
    }
}
