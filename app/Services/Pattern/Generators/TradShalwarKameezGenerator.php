<?php

namespace App\Services\Pattern\Generators;

/**
 * شلوار کمیز.
 *
 * یک دست کامل دوتکه است، نه یک لباس: پیراهن بلند (کمیز) روی شلوار گشاد (شلوار).
 * هیچ‌کدام به تنهایی این مدل نیست.
 *
 *   کمیز — پیراهنی راست و بلند تا زیر زانو، با یقه ایستاده و چاک عمودی جلو، و
 *   چاک پهلوی بلند که هم راه رفتن را ممکن می‌کند و هم شلوار زیرش را نشان می‌دهد.
 *
 *   شلوار — گشاد از بالا و جمع‌شده در مچ پا. با بلوک شلوار درفت نمی‌شود و نباید
 *   بشود: منحنی فاق ندارد. چهار پنل راست است با درز داخل پای عمودی، و همه آزادی
 *   نشستن از یک «لِنگه» مربعی در فاق می‌آید. کمر با نیفه و بند جمع می‌شود.
 *
 * پوشیدگی این دست سنجیدنی است: کمیز تا زیر زانو، آستین تا مچ، و شلوار تا مچ پا.
 */
class TradShalwarKameezGenerator extends TraditionalBaseGenerator
{
    public static function key(): string
    {
        return 'trad_shalwar_kameez';
    }

    public function label(): string
    {
        return 'شلوار کمیز';
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
            $this->fitParam('regular'),
            $this->garmentLengthParam(52, 25, 80, 'بلندی کمیز از خط کمر'),
            $this->sleeveParam('set_in', 56, [
                'none' => 'بدون آستین (نوار حلقه)',
                'set_in' => 'آستین حلقه‌ای',
            ]),
            [
                'collar_height' => [
                    'label' => 'بلندی یقه ایستاده کمیز', 'min' => 2, 'max' => 7, 'step' => 0.5,
                    'default' => 3.5, 'unit' => 'سانتی‌متر',
                ],
                'placket' => [
                    'label' => 'بلندی چاک عمودی جلو', 'min' => 8, 'max' => 40, 'step' => 1,
                    'default' => 26, 'unit' => 'سانتی‌متر',
                ],
                'placket_buttons' => [
                    'label' => 'تعداد دکمه چاک', 'min' => 0, 'max' => 6, 'step' => 1, 'default' => 3,
                ],
                'hem_flare' => [
                    'label' => 'باز شدن لبه پایین کمیز در هر پهلو', 'min' => 0, 'max' => 12, 'step' => 0.5,
                    'default' => 4, 'unit' => 'سانتی‌متر',
                ],
                'side_vent' => [
                    'label' => 'بلندی چاک پهلوی کمیز', 'min' => 0, 'max' => 55, 'step' => 1,
                    'default' => 30, 'unit' => 'سانتی‌متر',
                ],
                'shalwar_fullness' => [
                    'label' => 'نسبت پُری کمر شلوار به دور باسن', 'min' => 1.2, 'max' => 2.6, 'step' => 0.05,
                    'default' => 1.75,
                    'hint' => 'شلوارِ کم‌پُر، دیگر شلوارِ شلوار کمیز نیست؛ نشستن روی زمین را هم سخت می‌کند.',
                ],
                'shalwar_length' => [
                    'label' => 'قد شلوار از کمر تا مچ پا', 'min' => 60, 'max' => 125, 'step' => 1,
                    'default' => 100, 'unit' => 'سانتی‌متر',
                ],
                'shalwar_ankle' => [
                    'label' => 'دور تمام‌شده مچ پا', 'min' => 16, 'max' => 48, 'step' => 1,
                    'default' => 30, 'unit' => 'سانتی‌متر',
                ],
                'shalwar_gusset' => [
                    'label' => 'اندازه لِنگه فاق', 'min' => 10, 'max' => 26, 'step' => 1,
                    'default' => 16, 'unit' => 'سانتی‌متر',
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = $this->fitGrow($params, ['fitted' => 0.5, 'regular' => 2.0, 'loose' => 3.5]);
        $length = (float) $this->param($params, 'length', 52);
        $placket = (float) $this->param($params, 'placket', 26);
        $vent = (float) $this->param($params, 'side_vent', 30);

        $pieces = $this->outerGarment($measurements, $ease, $params, $g, [
            'prefix' => 'kameez-',
            'grow' => $grow,
            'shape' => 'straight',
            'length' => $length,
            'opening' => 'closed',
            'collar' => 'stand',
            'collar_height' => (float) $this->param($params, 'collar_height', 3.5),
            'facing' => false,
            'front_name' => 'کمیز — تنه جلو',
            'back_name' => 'کمیز — تنه پشت',
            'panel' => ['waist_dart' => false],
            'sleeve' => ['sleeve_name' => 'آستین کمیز'],
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

        $pieces[] = $this->bandPiece('kameez-placket', 'پاتلت چاک جلو', $placket + 4, 8, [
            'cut' => 2, 'part' => 'placket',
            'meta' => [
                'interfacing' => true,
                'girth_role' => 'trim',
                'notes' => ['ته چاک با دوخت افقی محکم می‌شود.'],
            ],
        ]);

        $pieces[] = $this->backNeckFacingPiece($g, ['prefix' => 'kameez-', 'width' => 6]);

        foreach ($this->shalwarPieces($measurements, [
            'prefix' => 'shalwar-',
            'fullness' => (float) $this->param($params, 'shalwar_fullness', 1.75),
            'length' => (float) $this->param($params, 'shalwar_length', 100),
            'ankle' => (float) $this->param($params, 'shalwar_ankle', 30),
            'gusset' => (float) $this->param($params, 'shalwar_gusset', 16),
        ]) as $piece) {
            $pieces[] = $piece;
        }

        $style = (string) $this->param($params, 'sleeve_style', 'set_in');
        $sleeveLength = (float) $this->param($params, 'sleeve_length', 56);

        $pieces[0] = $this->markCoverage($pieces[0], [
            'hem' => $this->hemFromShoulder($pieces[0]),
            'hem_at' => $length >= 48 ? 'زیر زانو' : 'بالای زانو',
            'sleeve' => $style === 'none' || $sleeveLength < 4
                ? 'ندارد؛ حلقه با نوار تمام می‌شود'
                : $this->fa(round($sleeveLength)).' سانتی‌متر از سرشانه (تا مچ)',
            'neck' => 'ایستاده و بسته، با چاک عمودی '.$this->fa(round($placket)).' سانتی‌متری',
            'head' => 'شلوار تا مچ پا می‌رسد، پس ساق پا هم پوشیده است',
        ]);

        $pieces[0]['meta']['notes'] = array_merge($pieces[0]['meta']['notes'] ?? [], $this->modestNotes([
            'چاک پهلوی کمیز '.$this->fa(round($vent)).' سانتی‌متر است؛ بلندتر از این، پهلو را باز می‌گذارد.',
        ]));

        return $this->finishBlock($pieces, $g, $grow);
    }
}
