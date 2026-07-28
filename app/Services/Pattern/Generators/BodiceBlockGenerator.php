<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Generators\Concerns\DraftsBodice;

/** بالاتنه پایه: جلو و پشت با ساسون سینه و کمر، یقه، حلقه آستین و سرشانه. */
class BodiceBlockGenerator extends BaseGenerator
{
    use DraftsBodice;

    public function label(): string
    {
        return 'بالاتنه پایه';
    }

    public function paramsSchema(): array
    {
        return [
            'shoulder_slope' => [
                'label' => 'شیب سرشانه', 'min' => 2, 'max' => 8, 'step' => 0.5, 'default' => 4.5, 'unit' => 'سانتی‌متر',
                'hint' => 'هرچه بیشتر باشد سرشانه افتاده‌تر می‌شود.',
            ],
            'neck_width_extra' => [
                'label' => 'اضافه عرض یقه', 'min' => -2, 'max' => 8, 'step' => 0.5, 'default' => 0, 'unit' => 'سانتی‌متر',
            ],
            'front_neck_depth_extra' => [
                'label' => 'گودی بیشتر یقه جلو', 'min' => -2, 'max' => 20, 'step' => 0.5, 'default' => 0, 'unit' => 'سانتی‌متر',
            ],
            'back_neck_depth' => [
                'label' => 'گودی یقه پشت', 'min' => 1, 'max' => 12, 'step' => 0.5, 'default' => 2, 'unit' => 'سانتی‌متر',
            ],
            'armhole_depth_extra' => [
                'label' => 'گودی بیشتر حلقه آستین', 'min' => -2, 'max' => 8, 'step' => 0.5, 'default' => 0, 'unit' => 'سانتی‌متر',
            ],
            'bodice_length_extra' => [
                'label' => 'بلندی بیشتر بالاتنه', 'min' => -6, 'max' => 20, 'step' => 0.5, 'default' => 0, 'unit' => 'سانتی‌متر',
            ],
            'waist_dart_share' => [
                'label' => 'سهم ساسون از کاهش کمر', 'min' => 0, 'max' => 0.9, 'step' => 0.05, 'default' => 0.6,
                'hint' => 'باقی کاهش کمر روی درز پهلو گرفته می‌شود.',
            ],
            'bust_dart' => [
                'label' => 'ساسون سینه روی پهلو', 'type' => 'toggle', 'default' => true,
            ],
        ];
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->bodiceMetrics($measurements, $ease, $params);
        $bustDart = $this->flag($params, 'bust_dart', true);

        return $this->finish([
            $this->bodicePiece($g, [
                'side' => 'front',
                'shape' => 'waist',
                'bust_dart' => $bustDart,
                'code' => 'bodice-front',
            ]),
            $this->bodicePiece($g, [
                'side' => 'back',
                'shape' => 'waist',
                'code' => 'bodice-back',
            ]),
        ]);
    }
}
