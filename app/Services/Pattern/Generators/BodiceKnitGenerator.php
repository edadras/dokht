<?php

namespace App\Services\Pattern\Generators;

/**
 * بالاتنه پارچه کشی با آزادی منفی.
 *
 * الگو کوچک‌تر از خود بدن درفت می‌شود و پارچه با کش آمدن روی تن می‌نشیند. مقدار
 * کوچک‌شدن از «درصد کشسانی پارچه» می‌آید؛ همان عددی که روی برگه پارچه نوشته
 * می‌شود. ساسون ندارد، یقه با نوار کشباف بسته می‌شود و حلقه آستین کمی تنگ‌تر از
 * بلوک بافته است تا آستین کشی روی آن بخوابد.
 */
class BodiceKnitGenerator extends BodiceBaseGenerator
{
    public static function key(): string
    {
        return 'bodice_knit';
    }

    public function label(): string
    {
        return 'بالاتنه کشی با آزادی منفی';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->baseSchema(
                ['neck_width_extra' => 1.5, 'front_neck_depth_extra' => 2, 'armhole_depth_extra' => 1, 'shoulder_slope' => 4],
                ['shoulder_slope', 'neck_width_extra', 'front_neck_depth_extra', 'back_neck_depth', 'armhole_depth_extra', 'bodice_length_extra'],
            ),
            [
                'negative_ease' => [
                    'label' => 'آزادی منفی', 'min' => 0, 'max' => 14, 'step' => 0.5,
                    'default' => 5, 'unit' => 'سانتی‌متر',
                    'hint' => 'الگو این‌قدر از دور بدن کوچک‌تر بریده می‌شود؛ برای پارچه‌های پرکشش عدد بزرگ‌تر بگذارید.',
                ],
                'body_length' => [
                    'label' => 'بلندی از خط کمر', 'min' => -6, 'max' => 35, 'step' => 1,
                    'default' => 8, 'unit' => 'سانتی‌متر',
                ],
                'neck_band' => [
                    'label' => 'نوار یقه کشباف', 'type' => 'toggle', 'default' => true,
                ],
                'band_ratio' => [
                    'label' => 'نسبت کوتاهی نوار یقه', 'min' => 0.7, 'max' => 1, 'step' => 0.05,
                    'default' => 0.85,
                    'hint' => 'هرچه کمتر باشد نوار بیشتر کشیده می‌شود و یقه بهتر می‌خوابد.',
                ],
                'hem_band' => [
                    'label' => 'نوار کشباف دم لباس', 'type' => 'toggle', 'default' => false,
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $negative = max(0.0, (float) $this->param($params, 'negative_ease', 5));

        $ease = array_merge($ease, [
            'bust' => $this->ease($ease, 'bust', 0) - $negative,
            'waist' => $this->ease($ease, 'waist', 0) - $negative,
            'hip' => $this->ease($ease, 'hip', 0) - ($negative * 0.6),
        ]);

        $g = $this->blockMetrics($measurements, $ease, $params);
        $length = (float) $this->param($params, 'body_length', 8);

        $shared = [
            'shape' => 'straight',
            'length' => $length,
            'waist_dart' => false,
            'bust_dart' => false,
            'bottom_tag' => 'hem',
            'meta' => ['knit' => true, 'negative_ease' => round($negative, 2)],
        ];

        $front = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'front',
            'code' => 'knit-front',
            'name' => 'بالاتنه جلو',
        ]));

        $back = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'back',
            'code' => 'knit-back',
            'name' => 'بالاتنه پشت',
        ]));

        $pieces = [$front, $back];

        if ($this->flag($params, 'neck_band', true)) {
            $neck = (float) ($front['meta']['neck_length'] ?? 0) + (float) ($back['meta']['neck_length'] ?? 0);
            $ratio = (float) $this->param($params, 'band_ratio', 0.85);

            $pieces[] = $this->bandPiece('knit-neck-band', 'نوار یقه کشباف', max(8.0, $neck * $ratio), 5, [
                'cut' => 1, 'on_fold' => true, 'part' => 'collar', 'fold_line' => true,
                'meta' => [
                    'rib' => true,
                    'stretch_ratio' => $ratio,
                    'target_neck' => round($neck, 2),
                    'notes' => ['نوار یقه '.$this->fa(round((1 - $ratio) * 100)).' درصد کوتاه‌تر از دور یقه است و کشیده دوخته می‌شود.'],
                ],
            ]);
        }

        if ($this->flag($params, 'hem_band', false)) {
            $width = $this->panelWidthAt($front, max(1.0, $g['side_waist_y'] + $length - 0.5));

            $pieces[] = $this->bandPiece('knit-hem-band', 'نوار کشباف دم لباس', max(8.0, $width * 2 * 0.85), 6, [
                'cut' => 1, 'on_fold' => true, 'part' => 'waistband', 'fold_line' => true,
                'meta' => ['rib' => true, 'stretch_ratio' => 0.85, 'target_width' => round($width * 2, 2)],
            ]);
        }

        return $this->finishBlock($pieces, $g);
    }
}
