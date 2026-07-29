<?php

namespace App\Services\Pattern\Generators;

/**
 * کورتا.
 *
 * پیراهن بلند راستِ شبه‌قاره: از سرشانه تا میان ران یا زانو یک خط راست می‌آید و
 * هیچ کمرگیری ندارد — همین راست بودن است که کورتا را از تونیک جدا می‌کند. یقه
 * یک چاک عمودیِ بلند روی مرکز جلو دارد که با پاتلت تمیز می‌شود و چند دکمه
 * می‌خورد؛ بالای چاک یا لبه ساده است یا یقه ایستاده کوتاه.
 *
 * چاک پهلو در کورتا تزئینی نیست: لباسِ بلندِ راست بدون چاک پهلو، هنگام راه رفتن
 * و نشستن روی زمین کشیده می‌شود. چاک از لبه پایین تا نزدیک خط باسن باز می‌ماند.
 */
class TradKurtaGenerator extends TraditionalBaseGenerator
{
    public static function key(): string
    {
        return 'trad_kurta';
    }

    public function label(): string
    {
        return 'کورتا';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerSchema([
                'shoulder_slope' => 4,
                'neck_width_extra' => 1,
                'front_neck_depth_extra' => 1.5,
                'back_neck_depth' => 2,
                'armhole_depth_extra' => 2.5,
            ]),
            $this->fitParam('regular'),
            $this->garmentLengthParam(40, 16, 70),
            $this->collarParam('none', [
                'none' => 'بدون یقه (نوار اریب دور یقه)',
                'stand' => 'یقه ایستاده کوتاه',
            ], 3.5),
            $this->sleeveParam('set_in', 56, [
                'none' => 'بدون آستین (نوار حلقه)',
                'set_in' => 'آستین حلقه‌ای',
            ]),
            [
                'placket' => [
                    'label' => 'بلندی چاک عمودی جلو', 'min' => 8, 'max' => 40, 'step' => 1,
                    'default' => 24, 'unit' => 'سانتی‌متر',
                    'hint' => 'چاک عمودی روی مرکز جلو، از خط یقه به پایین.',
                ],
                'placket_buttons' => [
                    'label' => 'تعداد دکمه چاک', 'min' => 0, 'max' => 6, 'step' => 1, 'default' => 3,
                ],
                'hem_flare' => [
                    'label' => 'باز شدن لبه پایین در هر پهلو', 'min' => 0, 'max' => 12, 'step' => 0.5,
                    'default' => 3, 'unit' => 'سانتی‌متر',
                ],
                'side_vent' => [
                    'label' => 'بلندی چاک پهلو', 'min' => 0, 'max' => 45, 'step' => 1,
                    'default' => 22, 'unit' => 'سانتی‌متر',
                ],
                'pocket' => [
                    'label' => 'جیب رودوزی پهلو', 'type' => 'toggle', 'default' => false,
                ],
                'pocket_width' => [
                    'label' => 'پهنای جیب', 'min' => 10, 'max' => 20, 'step' => 0.5,
                    'default' => 14, 'unit' => 'سانتی‌متر',
                ],
                'pocket_height' => [
                    'label' => 'بلندی جیب', 'min' => 10, 'max' => 22, 'step' => 0.5,
                    'default' => 16, 'unit' => 'سانتی‌متر',
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = $this->fitGrow($params, ['fitted' => 0.0, 'regular' => 1.5, 'loose' => 3.0]);
        $length = (float) $this->param($params, 'length', 40);
        $placket = (float) $this->param($params, 'placket', 24);
        $vent = (float) $this->param($params, 'side_vent', 22);

        $pieces = $this->outerGarment($measurements, $ease, $params, $g, [
            'prefix' => 'kurta-',
            'grow' => $grow,
            'shape' => 'straight',
            'length' => $length,
            'opening' => 'closed',
            'facing' => false,
            'front_name' => 'تنه جلوی کورتا',
            'back_name' => 'تنه پشت کورتا',
            'panel' => ['waist_dart' => false],
            'sleeve' => ['sleeve_name' => 'آستین کورتا'],
        ]);

        $pieces[0] = $this->markSideVent($pieces[0], $vent);
        $pieces[1] = $this->markSideVent($pieces[1], $vent);

        $pieces[0]['markers'][] = $this->marker(
            'placket',
            'چاک عمودی جلو',
            0,
            $g['front_neck_depth'],
            0,
            $g['front_neck_depth'] + $placket,
        );
        $pieces[0]['meta']['placket'] = round($placket, 2);
        $pieces[0] = $this->markButtons(
            $pieces[0],
            0.0,
            $g['front_neck_depth'] + 2,
            $g['front_neck_depth'] + $placket - 1,
            (int) $this->param($params, 'placket_buttons', 3),
            'جای دکمه چاک جلو',
        );

        $pieces[] = $this->bandPiece('kurta-placket', 'پاتلت چاک جلو', $placket + 4, 8, [
            'cut' => 2, 'part' => 'placket',
            'meta' => [
                'interfacing' => true,
                'girth_role' => 'trim',
                'notes' => [
                    'دو نوار هم‌اندازه چاک: یکی روی لبه راست و یکی زیر لبه چپ دوخته می‌شود.',
                    'پایین چاک با یک دوخت افقی محکم می‌شود، وگرنه پارچه پاره می‌شود.',
                ],
            ],
        ]);

        if ((string) $this->param($params, 'collar', 'none') === 'none') {
            $pieces[] = $this->bandPiece(
                'kurta-neck-binding',
                'نوار اریب دور یقه',
                (2 * $this->neckOf([$pieces[0], $pieces[1]])) + 4,
                3,
                [
                    'cut' => 1, 'part' => 'facing',
                    'meta' => [
                        'bias' => true,
                        'girth_role' => 'trim',
                        'notes' => ['روی اریب بریده می‌شود تا روی منحنی یقه بخوابد.'],
                    ],
                ],
            );
        }

        $pieces[] = $this->backNeckFacingPiece($g, ['prefix' => 'kurta-', 'width' => 6]);

        foreach ($this->pocketSet($params, ['prefix' => 'kurta-', 'cut' => 2]) as $pocket) {
            $pieces[] = $pocket;
        }

        $style = (string) $this->param($params, 'sleeve_style', 'set_in');
        $sleeveLength = (float) $this->param($params, 'sleeve_length', 56);

        $pieces[0] = $this->markCoverage($pieces[0], [
            'hem' => $this->hemFromShoulder($pieces[0]),
            'hem_at' => $length >= 36 ? 'میان ران تا زانو' : 'روی باسن',
            'sleeve' => $style === 'none' || $sleeveLength < 4
                ? 'ندارد؛ حلقه با نوار تمام می‌شود'
                : $this->fa(round($sleeveLength)).' سانتی‌متر از سرشانه (تا مچ)',
            'neck' => 'گرد و بسته، با چاک عمودی '.$this->fa(round($placket)).' سانتی‌متری',
        ]);

        $pieces[0]['meta']['notes'] = array_merge($pieces[0]['meta']['notes'] ?? [], $this->modestNotes([
            'تنه از زیر بغل تا پایین راست است؛ کمرگیری ندارد و همین کورتا را از تونیک جدا می‌کند.',
        ]));

        return $this->finishBlock($pieces, $g, $grow);
    }
}
