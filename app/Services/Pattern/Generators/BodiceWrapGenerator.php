<?php

namespace App\Services\Pattern\Generators;

/**
 * بالاتنه راپ (کراس).
 *
 * دو نیمه جلو از سرشانه به کمرِ سمت مقابل می‌روند و روی هم می‌افتند؛ کاهش کمر
 * به‌جای ساسون با چین ریز روی خط کمر گرفته می‌شود، برای همین این بالاتنه روی
 * اندام‌های گوناگون خوب می‌نشیند. دو بند از دو پهلو بیرون می‌آید و کمر را گره
 * می‌زند: بند سمت راست از چاک پهلوی چپ بیرون می‌آید.
 */
class BodiceWrapGenerator extends BodiceBaseGenerator
{
    public static function key(): string
    {
        return 'bodice_wrap';
    }

    public function label(): string
    {
        return 'بالاتنه راپ (کراس)';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->baseSchema(
                ['neck_width_extra' => 1],
                ['shoulder_slope', 'neck_width_extra', 'back_neck_depth', 'armhole_depth_extra', 'bodice_length_extra'],
            ),
            [
                'overlap' => [
                    'label' => 'هم‌پوشانی جلو', 'min' => 6, 'max' => 26, 'step' => 0.5,
                    'default' => 14, 'unit' => 'سانتی‌متر',
                    'hint' => 'هرچه بیشتر باشد یقه بسته‌تر و پوشش سینه بیشتر می‌شود.',
                ],
                'gather' => [
                    'label' => 'پُری چین کمر جلو', 'min' => 0, 'max' => 12, 'step' => 0.5,
                    'default' => 4, 'unit' => 'سانتی‌متر',
                ],
                'body_length' => [
                    'label' => 'بلندی از خط کمر', 'min' => 0, 'max' => 40, 'step' => 1,
                    'default' => 0, 'unit' => 'سانتی‌متر',
                ],
                'tie_length' => [
                    'label' => 'بلندی هر بند', 'min' => 25, 'max' => 120, 'step' => 5,
                    'default' => 70, 'unit' => 'سانتی‌متر',
                ],
                'tie_width' => [
                    'label' => 'پهنای بند', 'min' => 2, 'max' => 8, 'step' => 0.5,
                    'default' => 3.5, 'unit' => 'سانتی‌متر',
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $length = (float) $this->param($params, 'body_length', 0);
        $shape = $length > 0.5 ? 'flare' : 'waist';

        $front = $this->wrapFrontPanel($g, [
            'overlap' => (float) $this->param($params, 'overlap', 14),
            'gather' => (float) $this->param($params, 'gather', 4),
            'length' => $length,
            'neck_width_extra' => (float) $this->param($params, 'neck_width_extra', 1),
        ]);

        $back = $this->bodyPanel($g, [
            'side' => 'back',
            'shape' => $shape,
            'length' => $length,
            'waist_dart' => false,
            'bottom_tag' => $length > 0.5 ? 'hem' : 'waist',
            'code' => 'wrap-back',
            'name' => 'پشت راپ',
        ]);

        [$front, $back] = $this->walkSideSeamPair($front, $back);

        $tie = (float) $this->param($params, 'tie_length', 70);
        $tieWidth = (float) $this->param($params, 'tie_width', 3.5);

        $pieces = [
            $front,
            $back,
            $this->bandPiece('wrap-tie', 'بند کمر', $tie, $tieWidth * 2, [
                'cut' => 2, 'part' => 'belt', 'fold_line' => true,
                'meta' => [
                    'notes' => [
                        'دو بند یکسان بریده می‌شود؛ بند سمت راست از چاک درز پهلوی چپ بیرون می‌آید و پشت کمر گره می‌خورد.',
                    ],
                ],
            ]),
            $this->bandPiece('wrap-neck-binding', 'نوار یقه و لبه جلو', ($g['neck_width'] * 2) + $g['front_waist_y'] + 12, 5, [
                'cut' => 2, 'part' => 'facing',
                'meta' => ['bias' => true, 'notes' => ['نوار اریب که لبه یقه و لبه جلو را می‌پوشاند.']],
            ]),
        ];

        return $this->finishBlock($pieces, $g);
    }

    /**
     * درز پهلوی جلو و پشت را می‌سنجد و اختلاف را ثبت می‌کند.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function walkSideSeamPair(array $front, array $back): array
    {
        $delta = round((float) ($front['meta']['side_seam_length'] ?? 0) - (float) ($back['meta']['side_seam_length'] ?? 0), 2);
        $front['meta']['side_seam_match'] = $delta;
        $back['meta']['side_seam_match'] = -$delta;

        return [$front, $back];
    }
}
