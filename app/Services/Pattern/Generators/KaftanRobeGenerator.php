<?php

namespace App\Services\Pattern\Generators;

/**
 * کیمونو و روب خانگی.
 *
 * جلوباز با آستین یک‌سره؛ دو لبه جلو روی هم می‌افتند و با بند کمر بسته می‌شوند.
 * نوارِ یک‌سره‌ای از لبه پایینِ جلو تا پشت گردن و تا لبه پایینِ سمت دیگر می‌رود و
 * همان چیزی است که به روب حالت می‌دهد. بند از دو حلقه روی درز پهلو رد می‌شود تا
 * جا نماند.
 */
class KaftanRobeGenerator extends BodiceGarmentBase
{
    public static function key(): string
    {
        return 'kimono_robe';
    }

    public function label(): string
    {
        return 'کیمونو و روب';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerSchema(['neck_width_extra' => 2, 'front_neck_depth_extra' => 4, 'armhole_depth_extra' => 4]),
            $this->garmentLengthParam(62, 20, 120),
            [
                'ease_extra' => [
                    'label' => 'آزادی افزوده تنه (هر نیم‌قطعه)', 'min' => 1, 'max' => 16, 'step' => 0.5,
                    'default' => 5, 'unit' => 'سانتی‌متر',
                ],
                'overlap' => [
                    'label' => 'هم‌پوشانی جلو', 'min' => 4, 'max' => 22, 'step' => 0.5,
                    'default' => 10, 'unit' => 'سانتی‌متر',
                ],
                'underarm_drop' => [
                    'label' => 'پایین آمدن زیر بغل', 'min' => 3, 'max' => 22, 'step' => 0.5,
                    'default' => 10, 'unit' => 'سانتی‌متر',
                ],
                'sleeve_length' => [
                    'label' => 'بلندی آستین از زیر بغل', 'min' => 8, 'max' => 60, 'step' => 1,
                    'default' => 30, 'unit' => 'سانتی‌متر',
                ],
                'cuff_width' => [
                    'label' => 'پهنای دم آستین', 'min' => 12, 'max' => 40, 'step' => 1,
                    'default' => 24, 'unit' => 'سانتی‌متر',
                ],
                'band_width' => [
                    'label' => 'پهنای نوار لبه جلو و یقه', 'min' => 3, 'max' => 12, 'step' => 0.5,
                    'default' => 6, 'unit' => 'سانتی‌متر',
                ],
                'belt_width' => [
                    'label' => 'پهنای بند کمر', 'min' => 3, 'max' => 10, 'step' => 0.5,
                    'default' => 5, 'unit' => 'سانتی‌متر',
                ],
            ],
            $this->pocketParam(true, 15, 16),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = (float) $this->param($params, 'ease_extra', 5);
        $length = (float) $this->param($params, 'length', 62);
        $overlap = (float) $this->param($params, 'overlap', 10);
        $bandWidth = (float) $this->param($params, 'band_width', 6);
        $beltWidth = (float) $this->param($params, 'belt_width', 5);

        $shared = [
            'grow' => $grow,
            'length' => $length,
            'underarm_drop' => (float) $this->param($params, 'underarm_drop', 10),
            'sleeve_length' => (float) $this->param($params, 'sleeve_length', 30),
            'cuff_width' => (float) $this->param($params, 'cuff_width', 24),
        ];

        $front = $this->kimonoPanel($g, array_merge($shared, [
            'side' => 'front',
            'extension' => $overlap,
            'on_fold' => false,
            'cut' => 2,
            'code' => 'robe-front',
            'name' => 'تنه و آستین جلو',
            'meta' => ['wrap_overlap' => round($overlap, 2)],
        ]));

        $back = $this->kimonoPanel($g, array_merge($shared, [
            'side' => 'back',
            'code' => 'robe-back',
            'name' => 'تنه و آستین پشت',
        ]));

        $path = ($g['front_waist_y'] + $length) + (float) ($back['meta']['neck_length'] ?? 0);

        $pieces = [
            $front,
            $back,
            $this->bandPiece('robe-band', 'نوار یک‌سره لبه جلو و یقه', max(25.0, $path), $bandWidth * 2, [
                'cut' => 2, 'part' => 'placket', 'fold_line' => true,
                'meta' => ['notes' => ['دو نوار در مرکز پشت گردن به هم دوخته می‌شوند.']],
            ]),
            $this->beltPiece($measurements, $params, ['prefix' => 'robe-', 'width' => $beltWidth, 'tie' => 60]),
            $this->beltLoopPiece($beltWidth, ['prefix' => 'robe-', 'cut' => 2]),
        ];

        $pieces = array_merge($pieces, $this->pocketSet($params, ['prefix' => 'robe-']));

        return $this->finishBlock($pieces, $g, $grow);
    }
}
