<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Generators\Concerns\BuildsSleeve;
use App\Services\Pattern\Generators\Concerns\DraftsBodice;

/**
 * پیراهن یک‌تکه: بالاتنه در خط کمر به دامن وصل می‌شود.
 *
 * درز پهلو یک‌سره از زیر بغل تا لبه دامن می‌آید و ساسون کمر «بادامی» می‌شود؛ یعنی
 * هم به سمت سینه و هم به سمت باسن بسته می‌شود.
 */
class DressGenerator extends BaseGenerator
{
    use BuildsSleeve, DraftsBodice;

    public function label(): string
    {
        return 'پیراهن یک‌تکه';
    }

    public function paramsSchema(): array
    {
        return [
            'shoulder_slope' => [
                'label' => 'شیب سرشانه', 'min' => 2, 'max' => 8, 'step' => 0.5, 'default' => 4.5, 'unit' => 'سانتی‌متر',
            ],
            'neck_width_extra' => [
                'label' => 'اضافه عرض یقه', 'min' => -1, 'max' => 8, 'step' => 0.5, 'default' => 0.5, 'unit' => 'سانتی‌متر',
            ],
            'front_neck_depth_extra' => [
                'label' => 'گودی بیشتر یقه جلو', 'min' => -2, 'max' => 20, 'step' => 0.5, 'default' => 2, 'unit' => 'سانتی‌متر',
            ],
            'back_neck_depth' => [
                'label' => 'گودی یقه پشت', 'min' => 1, 'max' => 12, 'step' => 0.5, 'default' => 2, 'unit' => 'سانتی‌متر',
            ],
            'armhole_depth_extra' => [
                'label' => 'گودی بیشتر حلقه آستین', 'min' => -1, 'max' => 8, 'step' => 0.5, 'default' => 1, 'unit' => 'سانتی‌متر',
            ],
            'waist_dart_share' => [
                'label' => 'سهم ساسون از کاهش کمر', 'min' => 0, 'max' => 0.9, 'step' => 0.05, 'default' => 0.6,
            ],
            'skirt_length' => [
                'label' => 'بلندی دامن از کمر', 'min' => 25, 'max' => 120, 'step' => 1, 'default' => 62, 'unit' => 'سانتی‌متر',
            ],
            'hem_flare' => [
                'label' => 'گشادی دم دامن', 'min' => 0, 'max' => 40, 'step' => 1, 'default' => 10, 'unit' => 'سانتی‌متر',
            ],
            'sleeve' => [
                'label' => 'آستین داشته باشد', 'type' => 'toggle', 'default' => true,
            ],
            'sleeve_length' => [
                'label' => 'بلندی آستین', 'min' => 8, 'max' => 70, 'step' => 1, 'default' => 30, 'unit' => 'سانتی‌متر',
            ],
            'back_zip' => [
                'label' => 'زیپ پشت', 'type' => 'toggle', 'default' => true,
                'hint' => 'با زیپ، پشت در دو تکه بریده می‌شود و مرکز پشت جای دوخت می‌گیرد.',
            ],
        ];
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->bodiceMetrics($measurements, $ease, $params);
        $bottom = max($g['front_waist_y'], $g['back_waist_y']) + (float) $this->param($params, 'skirt_length', 62);
        $flare = (float) $this->param($params, 'hem_flare', 10);
        $zip = $this->flag($params, 'back_zip', true);

        $front = $this->bodicePiece($g, [
            'side' => 'front',
            'shape' => 'dress',
            'bottom_y' => $bottom,
            'bottom_tag' => 'hem',
            'hem_flare' => $flare,
            'code' => 'dress-front',
            'name' => 'جلوی پیراهن',
        ]);

        $back = $this->bodicePiece($g, [
            'side' => 'back',
            'shape' => 'dress',
            'bottom_y' => $bottom,
            'bottom_tag' => 'hem',
            'hem_flare' => $flare,
            'on_fold' => ! $zip,
            'cut' => $zip ? 2 : 1,
            'mirror' => $zip,
            'code' => 'dress-back',
            'name' => 'پشت پیراهن',
            'meta' => ['back_zip' => $zip],
        ]);

        $pieces = [$front, $back];

        if ($this->flag($params, 'sleeve', true)) {
            $armhole = ($front['meta']['armhole_length'] ?? 22) + ($back['meta']['armhole_length'] ?? 22);

            $pieces = array_merge($pieces, $this->sleevePieces($measurements, $ease, $params, [
                'armhole_length' => $armhole,
                'length' => (float) $this->param($params, 'sleeve_length', 30),
                'no_cuff' => true,
            ]));
        }

        return $this->finish($pieces);
    }
}
