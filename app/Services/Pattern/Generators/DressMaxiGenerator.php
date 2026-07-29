<?php

namespace App\Services\Pattern\Generators;

/**
 * پیراهن ماکسی.
 *
 * یک‌تکه و بلند تا مچ پا. تنه تا باسن قالب می‌گیرد و از باسن به پایین راست یا
 * کمی باز می‌آید تا راه رفتن راحت باشد. برای بلندی‌های بیش از نود سانتی‌متر چاک
 * پهلو گذاشته می‌شود، وگرنه قدم برداشتن سخت است.
 */
class DressMaxiGenerator extends BodiceGarmentBase
{
    public static function key(): string
    {
        return 'dress_maxi';
    }

    public function label(): string
    {
        return 'پیراهن ماکسی';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerSchema(['armhole_depth_extra' => 1.5, 'neck_width_extra' => 1, 'front_neck_depth_extra' => 3, 'waist_dart_share' => 0.6]),
            $this->fitParam('regular'),
            $this->garmentLengthParam(96, 60, 125, 'بلندی دامن از کمر'),
            $this->sleeveParam('none', 30, [
                'none' => 'بدون آستین',
                'set_in' => 'آستین معمولی',
            ]),
            [
                'hem_flare' => [
                    'label' => 'باز شدن دم دامن در هر پهلو', 'min' => 0, 'max' => 40, 'step' => 1,
                    'default' => 10, 'unit' => 'سانتی‌متر',
                ],
                'side_vent' => [
                    'label' => 'بلندی چاک پهلو', 'min' => 0, 'max' => 70, 'step' => 1,
                    'default' => 32, 'unit' => 'سانتی‌متر',
                ],
                'back_zip' => [
                    'label' => 'زیپ مرکز پشت', 'type' => 'toggle', 'default' => true,
                ],
            ],
            $this->liningParam(false),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = $this->fitGrow($params, ['fitted' => 0.0, 'regular' => 1.5, 'loose' => 3.0]);
        $zip = $this->flag($params, 'back_zip', true);
        $vent = (float) $this->param($params, 'side_vent', 32);

        $pieces = $this->outerGarment($measurements, $ease, $params, $g, [
            'prefix' => 'dress-maxi-',
            'grow' => $grow,
            'shape' => 'fitted',
            'opening' => 'closed',
            'facing' => false,
            'bust_dart' => true,
            'back_seam' => $zip,
            'front_name' => 'جلوی پیراهن ماکسی',
            'back_name' => $zip ? 'پشت پیراهن ماکسی (زیپ مرکزی)' : 'پشت پیراهن ماکسی',
            'lining' => false,
            'lining_options' => ['length' => 30, 'shape' => 'fitted'],
        ]);

        $pieces[0] = $this->markSideVent($pieces[0], $vent);
        $pieces[1] = $this->markSideVent($pieces[1], $vent);

        if ($zip) {
            $pieces[1] = $this->markBackZip($pieces[1], $g);
        }

        $pieces[] = $this->backNeckFacingPiece($g, ['prefix' => 'dress-maxi-', 'width' => 6]);
        $pieces[] = $this->armholeBindingPiece($this->armholeOf([$pieces[0], $pieces[1]]), ['prefix' => 'dress-maxi-']);

        return $this->finishBlock($pieces, $g, $grow);
    }
}
