<?php

namespace App\Services\Pattern\Generators;

/**
 * تونیک.
 *
 * بلوز بلندی که تا میان ران می‌آید و جلوی آن بسته است؛ از یقه یک چاک کوتاه باز
 * می‌شود تا از سر رد شود. کاهش کمر ملایم است و چاک پهلو راه رفتن را راحت
 * می‌کند. با شلوار و لگ پوشیده می‌شود و پرکاربردترین بلوزِ روزمره است.
 */
class TunicGenerator extends BodiceGarmentBase
{
    public static function key(): string
    {
        return 'tunic';
    }

    public function label(): string
    {
        return 'تونیک';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerSchema(['armhole_depth_extra' => 2, 'neck_width_extra' => 1, 'front_neck_depth_extra' => 2.5]),
            $this->fitParam('regular'),
            $this->garmentLengthParam(34, 10, 70),
            $this->collarParam('none', [
                'none' => 'بدون یقه (نوار دور یقه)',
                'stand' => 'یقه ایستاده',
            ], 4),
            $this->sleeveParam('set_in', 45),
            [
                'placket' => [
                    'label' => 'بلندی چاک جلو', 'min' => 0, 'max' => 30, 'step' => 1,
                    'default' => 16, 'unit' => 'سانتی‌متر',
                    'hint' => 'چاک از خط یقه به پایین باز می‌شود و با پاتلت تمیز می‌شود.',
                ],
                'hem_flare' => [
                    'label' => 'باز شدن لبه پایین در هر پهلو', 'min' => 0, 'max' => 20, 'step' => 0.5,
                    'default' => 4, 'unit' => 'سانتی‌متر',
                ],
                'side_vent' => [
                    'label' => 'بلندی چاک پهلو', 'min' => 0, 'max' => 30, 'step' => 1,
                    'default' => 12, 'unit' => 'سانتی‌متر',
                ],
                'waist_shape' => [
                    'label' => 'کمرگیری ملایم', 'type' => 'toggle', 'default' => true,
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = $this->fitGrow($params, ['fitted' => 0.0, 'regular' => 1.5, 'loose' => 3.5]);
        $placket = (float) $this->param($params, 'placket', 16);
        $vent = (float) $this->param($params, 'side_vent', 12);

        $pieces = $this->outerGarment($measurements, $ease, $params, $g, [
            'prefix' => 'tunic-',
            'grow' => $grow,
            'shape' => $this->flag($params, 'waist_shape', true) ? 'flare' : 'straight',
            'opening' => 'closed',
            'facing' => false,
            'front_name' => 'تنه جلوی تونیک',
            'back_name' => 'تنه پشت تونیک',
            'panel' => ['waist_dart' => false],
        ]);

        $pieces[0] = $this->markSideVent($pieces[0], $vent);
        $pieces[1] = $this->markSideVent($pieces[1], $vent);

        if ($placket > 2) {
            $pieces[0]['markers'][] = $this->marker('placket', 'چاک جلو', 0, $g['front_neck_depth'], 0, $g['front_neck_depth'] + $placket);
            $pieces[0]['meta']['placket'] = round($placket, 2);

            $pieces[] = $this->bandPiece('tunic-placket', 'پاتلت چاک جلو', $placket + 3, 7, [
                'cut' => 2, 'part' => 'placket',
                'meta' => ['interfacing' => true, 'notes' => ['روی چاک جلو دوخته و برگردانده می‌شود.']],
            ]);
        }

        $pieces[] = $this->backNeckFacingPiece($g, ['prefix' => 'tunic-', 'width' => 6]);

        return $this->finishBlock($pieces, $g, $grow);
    }
}
