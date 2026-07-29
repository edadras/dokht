<?php

namespace App\Services\Pattern\Generators;

/**
 * ثوب (دشداشه مردانه).
 *
 * پیراهن بلند مردانه خلیج: از سرشانه تا مچ پا یک خط راست می‌آید، آستین بلند و
 * باریک تا مچ دست دارد و یقه‌اش ایستاده و بسته است. تنه و آستین یک‌سره بریده
 * می‌شوند — همان برشی که کم‌درزترین و خنک‌ترین حالت را می‌دهد.
 *
 * تفاوت ثوب با کافتان و عبا در همین سه چیز است: یقه ایستاده با چاک دکمه‌خور روی
 * مرکز جلو (کافتان یقه ندارد، عبا جلوباز است)، آستین باریکِ تا مچ (نه آستین پهنِ
 * کوتاه)، و لبه پایین بی‌گشادی که تا مچ پا می‌آید. جیب روی سینه هم بخشی از خودِ
 * لباس است، نه یک افزوده.
 */
class TradThobeGenerator extends TraditionalBaseGenerator
{
    public static function key(): string
    {
        return 'trad_thobe';
    }

    public function label(): string
    {
        return 'ثوب (دشداشه مردانه)';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerSchema([
                'shoulder_slope' => 4,
                'neck_width_extra' => 1,
                'front_neck_depth_extra' => 1,
                'back_neck_depth' => 2,
                'armhole_depth_extra' => 3,
            ]),
            $this->garmentLengthParam(90, 55, 125, 'بلندی از خط کمر تا مچ پا'),
            [
                'ease_extra' => [
                    'label' => 'آزادی افزوده تنه (هر نیم‌قطعه)', 'min' => 1.5, 'max' => 8, 'step' => 0.5,
                    'default' => 4, 'unit' => 'سانتی‌متر',
                ],
                'underarm_drop' => [
                    'label' => 'پایین آمدن زیر بغل', 'min' => 3, 'max' => 18, 'step' => 0.5,
                    'default' => 8, 'unit' => 'سانتی‌متر',
                ],
                'sleeve_length' => [
                    'label' => 'بلندی آستین از زیر بغل', 'min' => 25, 'max' => 65, 'step' => 1,
                    'default' => 46, 'unit' => 'سانتی‌متر',
                    'hint' => 'از خط زیر بغل تا مچ دست؛ با عرض سرشانه جمع می‌شود و به قد آستین می‌رسد.',
                ],
                'cuff_width' => [
                    'label' => 'پهنای دم آستین', 'min' => 10, 'max' => 26, 'step' => 1,
                    'default' => 15, 'unit' => 'سانتی‌متر',
                    'hint' => 'آستین ثوب باریک است؛ پهن‌تر از هجده سانتی‌متر دیگر آستین کافتان می‌شود.',
                ],
                'collar_height' => [
                    'label' => 'بلندی یقه ایستاده', 'min' => 2, 'max' => 7, 'step' => 0.5,
                    'default' => 4, 'unit' => 'سانتی‌متر',
                ],
                'placket' => [
                    'label' => 'بلندی چاک دکمه‌خور جلو', 'min' => 10, 'max' => 45, 'step' => 1,
                    'default' => 28, 'unit' => 'سانتی‌متر',
                ],
                'buttons' => [
                    'label' => 'تعداد دکمه چاک', 'min' => 0, 'max' => 8, 'step' => 1, 'default' => 5,
                ],
                'chest_pocket' => [
                    'label' => 'جیب سینه', 'type' => 'toggle', 'default' => true,
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = (float) $this->param($params, 'ease_extra', 4);
        $length = (float) $this->param($params, 'length', 90);
        $placket = (float) $this->param($params, 'placket', 28);
        $drop = (float) $this->param($params, 'underarm_drop', 8);

        $shared = [
            'grow' => $grow,
            'length' => $length,
            'underarm_drop' => $drop,
            'sleeve_length' => (float) $this->param($params, 'sleeve_length', 46),
            'cuff_width' => (float) $this->param($params, 'cuff_width', 15),
            'sleeve_slope' => 5.0,
            'hem_flare' => 0.0,
        ];

        $front = $this->kimonoPanel($g, array_merge($shared, [
            'side' => 'front',
            'code' => 'thobe-front',
            'name' => 'تنه و آستین جلو',
        ]));

        $back = $this->kimonoPanel($g, array_merge($shared, [
            'side' => 'back',
            'code' => 'thobe-back',
            'name' => 'تنه و آستین پشت',
        ]));

        $front['markers'][] = $this->marker(
            'placket',
            'چاک دکمه‌خور مرکز جلو',
            0,
            $g['front_neck_depth'],
            0,
            $g['front_neck_depth'] + $placket,
        );
        $front['meta']['placket'] = round($placket, 2);
        $front = $this->markButtons(
            $front,
            0.0,
            $g['front_neck_depth'] + 2,
            $g['front_neck_depth'] + $placket - 1,
            (int) $this->param($params, 'buttons', 5),
            'جای دکمه چاک جلو',
        );

        $pieces = [$front, $back];

        $pieces[] = $this->standCollarPiece(
            $this->neckOf([$front, $back]),
            (float) $this->param($params, 'collar_height', 4),
            ['prefix' => 'thobe-', 'name' => 'یقه ایستاده ثوب'],
        );

        $pieces[] = $this->bandPiece('thobe-placket', 'پاتلت چاک جلو', $placket + 4, 9, [
            'cut' => 2, 'part' => 'placket',
            'meta' => [
                'interfacing' => true,
                'girth_role' => 'trim',
                'notes' => [
                    'یکی روی لبه راست چاک و یکی زیر لبه چپ؛ جادکمه روی نوار رو باز می‌شود.',
                    'ته چاک با دوخت افقی محکم می‌شود.',
                ],
            ],
        ]);

        $pieces[] = $this->backNeckFacingPiece($g, ['prefix' => 'thobe-', 'width' => 7]);

        if ($this->flag($params, 'chest_pocket', true)) {
            $pieces[] = $this->patchPocketPiece(13, 15, [
                'prefix' => 'thobe-chest-',
                'name' => 'جیب سینه',
                'cut' => 1,
                'mirror' => false,
            ]);
        }

        $pieces[0] = $this->markCoverage($pieces[0], [
            'hem' => $this->hemFromShoulder($front),
            'hem_at' => 'مچ پا',
            'sleeve' => 'مچ دست',
            'neck' => 'ایستاده و بسته تا زیر گلو',
        ]);

        $pieces[0]['meta']['notes'] = array_merge($pieces[0]['meta']['notes'] ?? [], $this->modestNotes([
            'تنه و آستین یک‌سره‌اند؛ درز حلقه ندارد و زیر بغل '.$this->fa(round($drop))
                .' سانتی‌متر پایین‌تر از خط سینه می‌نشیند تا دست آزاد باشد.',
            'لبه پایین بی‌گشادی است؛ ثوبِ کلوش‌شده دیگر ثوب نیست.',
        ]));

        return $this->finishBlock($pieces, $g, $grow);
    }
}
