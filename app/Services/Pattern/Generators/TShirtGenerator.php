<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Generators\Concerns\BuildsSleeve;
use App\Services\Pattern\Generators\Concerns\DraftsBodice;
use App\Services\Pattern\Geometry;

/**
 * تی‌شرت کشی.
 *
 * بلوک پارچه کشی: بدون ساسون، درز پهلوی راست و پذیرای آزادی منفی (پارچه کشسان
 * می‌تواند کوچک‌تر از دور بدن بریده شود). نوار یقه به اندازه کشش پارچه کوتاه‌تر
 * از دور یقه بریده می‌شود.
 */
class TShirtGenerator extends BaseGenerator
{
    use BuildsSleeve, DraftsBodice;

    public function label(): string
    {
        return 'تی‌شرت کشی';
    }

    public function paramsSchema(): array
    {
        return [
            'shoulder_slope' => [
                'label' => 'شیب سرشانه', 'min' => 2, 'max' => 8, 'step' => 0.5, 'default' => 4, 'unit' => 'سانتی‌متر',
            ],
            'neck_width_extra' => [
                'label' => 'اضافه عرض یقه', 'min' => 0, 'max' => 8, 'step' => 0.5, 'default' => 1.5, 'unit' => 'سانتی‌متر',
            ],
            'front_neck_depth_extra' => [
                'label' => 'گودی بیشتر یقه جلو', 'min' => 0, 'max' => 15, 'step' => 0.5, 'default' => 1.5, 'unit' => 'سانتی‌متر',
            ],
            'back_neck_depth' => [
                'label' => 'گودی یقه پشت', 'min' => 1, 'max' => 6, 'step' => 0.5, 'default' => 2, 'unit' => 'سانتی‌متر',
            ],
            'armhole_depth_extra' => [
                'label' => 'گودی بیشتر حلقه آستین', 'min' => 0, 'max' => 8, 'step' => 0.5, 'default' => 1.5, 'unit' => 'سانتی‌متر',
            ],
            'body_length' => [
                'label' => 'بلندی تنه از کمر', 'min' => -5, 'max' => 40, 'step' => 1, 'default' => 16, 'unit' => 'سانتی‌متر',
            ],
            'sleeve_length' => [
                'label' => 'بلندی آستین', 'min' => 8, 'max' => 70, 'step' => 1, 'default' => 22, 'unit' => 'سانتی‌متر',
            ],
            'neckband_height' => [
                'label' => 'بلندی نوار یقه', 'min' => 1.5, 'max' => 6, 'step' => 0.5, 'default' => 2.5, 'unit' => 'سانتی‌متر',
            ],
            'neckband_stretch' => [
                'label' => 'کشش نوار یقه', 'min' => 0.6, 'max' => 1, 'step' => 0.05, 'default' => 0.85,
                'hint' => 'نوار یقه به این نسبت از دور یقه بریده می‌شود تا کشیده و صاف بنشیند.',
            ],
        ];
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->bodiceMetrics($measurements, $ease, $params);
        $bodyLength = (float) $this->param($params, 'body_length', 16);
        // لبه پایین جلو و پشت هم‌سطح گرفته می‌شود تا درز پهلو دقیقاً هم‌اندازه شود
        $bottom = max($g['front_waist_y'], $g['back_waist_y']) + $bodyLength;

        $front = $this->bodicePiece($g, [
            'side' => 'front',
            'shape' => 'straight',
            'darts' => false,
            'bottom_y' => $bottom,
            'bottom_tag' => 'hem',
            'code' => 'tshirt-front',
            'name' => 'تنه جلو',
        ]);

        $back = $this->bodicePiece($g, [
            'side' => 'back',
            'shape' => 'straight',
            'darts' => false,
            'bottom_y' => $bottom,
            'bottom_tag' => 'hem',
            'code' => 'tshirt-back',
            'name' => 'تنه پشت',
        ]);

        $armhole = ($front['meta']['armhole_length'] ?? 22) + ($back['meta']['armhole_length'] ?? 22);

        $sleeves = $this->sleevePieces($measurements, $ease, $params, [
            'armhole_length' => $armhole,
            'length' => (float) $this->param($params, 'sleeve_length', 22),
            'no_cuff' => true,
            'sleeve_name' => 'آستین تی‌شرت',
        ]);

        $neckLength = (($front['meta']['neck_length'] ?? 12) + ($back['meta']['neck_length'] ?? 9)) * 2;
        $stretch = min(1.0, max(0.6, (float) $this->param($params, 'neckband_stretch', 0.85)));
        $bandHeight = (float) $this->param($params, 'neckband_height', 2.5);

        $neckband = $this->piece([
            'code' => 'neckband',
            'name' => 'نوار یقه',
            'cut_quantity' => 1,
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point(round($neckLength * $stretch, 2), 0),
                Geometry::point(round($neckLength * $stretch, 2), $bandHeight * 2),
                Geometry::point(0, $bandHeight * 2),
            ],
            'grainline' => $this->grainline(($neckLength * $stretch) * 0.5, 0.8, ($bandHeight * 2) - 0.8),
            'markers' => [
                $this->marker('fold', 'خط تای نوار یقه', 0, $bandHeight, round($neckLength * $stretch, 2)),
            ],
            'meta' => [
                'part' => 'collar',
                'edges' => ['neck', 'side', 'default', 'side'],
                'fold_edges' => [],
                'neck_length' => round($neckLength, 2),
                'stretch' => $stretch,
            ],
        ]);

        return $this->finish(array_merge([$front, $back], $sleeves, [$neckband]));
    }
}
