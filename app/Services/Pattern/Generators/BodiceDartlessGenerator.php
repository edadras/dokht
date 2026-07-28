<?php

namespace App\Services\Pattern\Generators;

/**
 * بالاتنه بدون ساسون.
 *
 * همه کاهش کمر روی درز پهلو گرفته می‌شود؛ بلوکی که برای پارچه‌های نرم، بلوز
 * راحت و مدل‌هایی که ساسون در آن‌ها دیده نمی‌شود به کار می‌رود.
 */
class BodiceDartlessGenerator extends BaseGenerator
{
    use BodiceCatalogSupport;

    public static function key(): string
    {
        return 'bodice_dartless';
    }

    public static function group(): string
    {
        return 'bodice';
    }

    public function label(): string
    {
        return 'بالاتنه بدون ساسون';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->baseSchema(
                ['neck_width_extra' => 0.5, 'front_neck_depth_extra' => 1, 'armhole_depth_extra' => 1],
                ['shoulder_slope', 'neck_width_extra', 'front_neck_depth_extra', 'back_neck_depth', 'armhole_depth_extra', 'bodice_length_extra'],
            ),
            [
                'body_length' => [
                    'label' => 'بلندی از خط کمر', 'min' => 0, 'max' => 30, 'step' => 1, 'default' => 0, 'unit' => 'سانتی‌متر',
                    'hint' => 'صفر یعنی بلوک دقیقاً روی خط کمر تمام می‌شود.',
                ],
                'shoulder_extra' => [
                    'label' => 'اضافه سرشانه', 'min' => 0, 'max' => 6, 'step' => 0.5, 'default' => 0, 'unit' => 'سانتی‌متر',
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $length = (float) $this->param($params, 'body_length', 0);
        $shape = $length > 0.5 ? 'flare' : 'waist';

        $shared = [
            'shape' => $shape,
            'length' => $length,
            'waist_dart' => false,
            'shoulder_extra' => (float) $this->param($params, 'shoulder_extra', 0),
            'bottom_tag' => $length > 0.5 ? 'hem' : 'waist',
        ];

        return $this->finish([
            $this->bodyPanel($g, array_merge($shared, [
                'side' => 'front',
                'code' => 'bodice-dartless-front',
                'name' => 'بالاتنه جلو',
            ])),
            $this->bodyPanel($g, array_merge($shared, [
                'side' => 'back',
                'code' => 'bodice-dartless-back',
                'name' => 'بالاتنه پشت',
            ])),
        ]);
    }
}
