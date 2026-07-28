<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Generators\Concerns\DraftsPants;

/**
 * سرهمی (اورال یک‌تکه).
 *
 * بالاتنه و شلوار روی خط کمر به هم دوخته می‌شوند. برای اینکه نشستن و خم شدن
 * راحت باشد، بالاتنه کمی بلندتر از قد واقعی درفت می‌شود (همان «آزادی قد فاق» که
 * خیاط به سرهمی می‌دهد). زیپ روی مرکز پشت می‌آید، پس پشت در دو تکه بریده می‌شود.
 */
class JumpsuitGenerator extends BodiceGarmentBase
{
    use DraftsPants;

    public static function key(): string
    {
        return 'jumpsuit';
    }

    public function label(): string
    {
        return 'سرهمی';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerSchema(['armhole_depth_extra' => 2, 'neck_width_extra' => 1, 'front_neck_depth_extra' => 3, 'waist_dart_share' => 0.6]),
            $this->fitParam('regular'),
            $this->sleeveParam('none', 55, [
                'none' => 'بدون آستین',
                'set_in' => 'آستین معمولی',
            ]),
            [
                'rise_ease' => [
                    'label' => 'آزادی قد بالاتنه', 'min' => 0, 'max' => 8, 'step' => 0.5,
                    'default' => 2.5, 'unit' => 'سانتی‌متر',
                    'hint' => 'بالاتنه این‌قدر بلندتر درفت می‌شود تا نشستن و خم شدن راحت باشد.',
                ],
                'rise_extra' => [
                    'label' => 'آزادی فاق شلوار', 'min' => 0, 'max' => 8, 'step' => 0.5,
                    'default' => 2, 'unit' => 'سانتی‌متر',
                ],
                'length_extra' => [
                    'label' => 'تغییر قد پا', 'min' => -40, 'max' => 12, 'step' => 1,
                    'default' => 0, 'unit' => 'سانتی‌متر',
                ],
                'knee_ease' => [
                    'label' => 'آزادی دور زانو', 'min' => 2, 'max' => 30, 'step' => 1,
                    'default' => 8, 'unit' => 'سانتی‌متر',
                ],
                'hem_ease' => [
                    'label' => 'آزادی دم پا', 'min' => 2, 'max' => 45, 'step' => 1,
                    'default' => 14, 'unit' => 'سانتی‌متر',
                ],
                'front_pleat' => [
                    'label' => 'پیلی جلوی شلوار', 'min' => 0, 'max' => 6, 'step' => 0.5,
                    'default' => 2, 'unit' => 'سانتی‌متر',
                ],
                'back_darts' => [
                    'label' => 'تعداد ساسون پشت شلوار', 'min' => 0, 'max' => 2, 'step' => 1, 'default' => 1,
                ],
                'waistband' => [
                    'label' => 'کمربند داخلی داشته باشد', 'type' => 'toggle', 'default' => false,
                ],
            ],
            $this->pocketParam(false, 14, 15),
        );
    }

    /** قد پای این مدل نسبت به قد داخل پا. */
    protected function legLengthExtra(array $m, array $params): float
    {
        return (float) $this->param($params, 'length_extra', 0);
    }

    /** بلندی بالاتنه از خط کمر (سرهمی روی کمر تمام می‌شود). */
    protected function bodiceLength(array $params): float
    {
        return 0.0;
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $rise = (float) $this->param($params, 'rise_ease', 2.5);
        $params = array_merge($params, [
            'bodice_length_extra' => (float) $this->param($params, 'bodice_length_extra', 0) + $rise,
            'length_extra' => $this->legLengthExtra($measurements, $params),
        ]);

        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = $this->fitGrow($params, ['fitted' => 0.0, 'regular' => 1.5, 'loose' => 3.0]);

        $front = $this->bodyPanel($g, [
            'side' => 'front',
            'shape' => 'waist',
            'grow' => $grow,
            'bust_dart' => true,
            'code' => 'jumpsuit-bodice-front',
            'name' => 'بالاتنه جلو',
        ]);

        $back = $this->bodyPanel($g, [
            'side' => 'back',
            'shape' => 'waist',
            'grow' => $grow,
            'on_fold' => false,
            'cut' => 2,
            'mirror' => true,
            'code' => 'jumpsuit-bodice-back',
            'name' => 'بالاتنه پشت (زیپ مرکزی)',
            'meta' => ['back_zip' => true],
        ]);

        [$front, $back] = $this->walkSideSeams($front, $back);

        $pieces = [$front, $back];

        $pieces = array_merge($pieces, $this->sleeveSet(
            $measurements,
            $ease,
            $params,
            $this->armholeOf([$front, $back]),
            $g,
            ['prefix' => 'jumpsuit-'],
        ));

        foreach (['front', 'back'] as $side) {
            $leg = $this->legPanel($measurements, $ease, $params, [
                'side' => $side,
                'code' => 'jumpsuit-leg-'.$side,
                'name' => $side === 'front' ? 'پای جلو' : 'پای پشت',
            ]);

            $leg['meta']['girth_role'] = 'bottom';
            $leg['meta']['notes'] = array_merge($leg['meta']['notes'] ?? [], [
                'لبه کمر این قطعه به لبه کمر بالاتنه دوخته می‌شود؛ ساسون و پیلی پیش از دوخت بسته می‌شوند.',
            ]);

            $pieces[] = $leg;
        }

        if ($this->flag($params, 'waistband', false)) {
            $pieces[] = $this->waistbandPiece($measurements, $ease, $params, [
                'code' => 'jumpsuit-waistband',
                'name' => 'کمربند داخلی',
            ]);
        }

        $pieces[] = $this->backNeckFacingPiece($g, ['prefix' => 'jumpsuit-', 'width' => 6]);
        $pieces[] = $this->armholeBindingPiece($this->armholeOf([$front, $back]), ['prefix' => 'jumpsuit-']);
        $pieces = array_merge($pieces, $this->pocketSet($params, ['prefix' => 'jumpsuit-']));

        return $this->finishBlock($pieces, $g, $grow);
    }
}
