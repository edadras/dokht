<?php

namespace App\Services\Pattern\Generators;

/**
 * پیراهن کلوش.
 *
 * بالاتنه روی خط کمر تمام می‌شود و یک دامن کلوش به آن دوخته می‌گردد. کمان کمرِ
 * دامن دقیقاً به اندازه یک‌چهارم دور کمر درفت می‌شود — همان اندازه‌ای که لبه کمر
 * بالاتنه پس از بستن ساسون‌ها دارد — پس درز کمر بدون کش آمدن بسته می‌شود.
 */
class DressFlaredGenerator extends BodiceGarmentBase
{
    public static function key(): string
    {
        return 'dress_flared';
    }

    public function label(): string
    {
        return 'پیراهن کلوش';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerSchema(['armhole_depth_extra' => 1, 'neck_width_extra' => 0.5, 'front_neck_depth_extra' => 2.5, 'waist_dart_share' => 0.7]),
            $this->sleeveParam('set_in', 30, [
                'none' => 'بدون آستین',
                'set_in' => 'آستین معمولی',
            ]),
            [
                'skirt_style' => [
                    'label' => 'فرم دامن', 'type' => 'select', 'default' => 'circle',
                    'options' => ['circle' => 'کلوش دایره‌ای', 'a_line' => 'اریب (خط A)', 'gather' => 'چین‌دار'],
                ],
                'skirt_length' => [
                    'label' => 'بلندی دامن از کمر', 'min' => 25, 'max' => 115, 'step' => 1,
                    'default' => 62, 'unit' => 'سانتی‌متر',
                ],
                'fullness' => [
                    'label' => 'پُری دامن', 'min' => 0.3, 'max' => 1, 'step' => 0.05,
                    'default' => 0.85,
                    'hint' => 'یک یعنی کلوش کامل؛ روی پارچه‌های سنگین عدد کمتر بهتر می‌افتد.',
                ],
                'back_zip' => [
                    'label' => 'زیپ مرکز پشت', 'type' => 'toggle', 'default' => true,
                ],
                'bust_dart' => [
                    'label' => 'ساسون سینه روی پهلو', 'type' => 'toggle', 'default' => true,
                ],
            ],
            $this->liningParam(false),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $zip = $this->flag($params, 'back_zip', true);
        $style = (string) $this->param($params, 'skirt_style', 'circle');
        $length = (float) $this->param($params, 'skirt_length', 62);
        $fullness = (float) $this->param($params, 'fullness', 0.85);

        $front = $this->bodyPanel($g, [
            'side' => 'front',
            'shape' => 'waist',
            'bust_dart' => $this->flag($params, 'bust_dart', true),
            'code' => 'dress-flared-bodice-front',
            'name' => 'بالاتنه جلو',
        ]);

        $back = $this->bodyPanel($g, [
            'side' => 'back',
            'shape' => 'waist',
            'on_fold' => ! $zip,
            'cut' => $zip ? 2 : 1,
            'mirror' => $zip,
            'code' => 'dress-flared-bodice-back',
            'name' => $zip ? 'بالاتنه پشت (زیپ مرکزی)' : 'بالاتنه پشت',
            'meta' => ['back_zip' => $zip],
        ]);

        [$front, $back] = $this->walkSideSeams($front, $back);
        $pieces = [$front, $back];

        $pieces = array_merge($pieces, $this->sleeveSet(
            $measurements,
            $ease,
            $params,
            $this->armholeOf([$front, $back]),
            $g,
            ['prefix' => 'dress-flared-'],
        ));

        foreach (['front', 'back'] as $side) {
            $isFront = $side === 'front';

            $pieces[] = $style === 'circle'
                ? $this->circleSkirtPanel($g, [
                    'side' => $side,
                    'length' => $length,
                    'fullness' => $fullness,
                    'code' => 'dress-flared-skirt-'.$side,
                    'name' => $isFront ? 'دامن کلوش جلو' : 'دامن کلوش پشت',
                    'cut' => $isFront || ! $zip ? 1 : 2,
                    'on_fold' => $isFront || ! $zip,
                ])
                : $this->skirtPanel($g, [
                    'side' => $side,
                    'type' => $style === 'gather' ? 'a_line' : 'a_line',
                    'length' => $length,
                    'flare' => round($length * $fullness * 0.5, 2),
                    'gather' => $style === 'gather' ? round($g['quarter_waist'] * $fullness, 2) : 0.0,
                    'code' => 'dress-flared-skirt-'.$side,
                    'name' => $isFront ? 'دامن جلو' : 'دامن پشت',
                    'on_fold' => $isFront || ! $zip,
                    'cut' => $isFront || ! $zip ? 1 : 2,
                ]);
        }

        $pieces[] = $this->backNeckFacingPiece($g, ['prefix' => 'dress-flared-', 'width' => 6]);
        $pieces = array_merge($pieces, $this->liningSet($g, $params, [
            'prefix' => 'dress-flared-',
            'shape' => 'waist',
            'length' => 0.0,
            'default' => false,
        ]));

        return $this->finishBlock($pieces, $g);
    }
}
