<?php

namespace App\Services\Pattern\Generators;

/**
 * عبا.
 *
 * پوششی بلند و آزاد با آستین یک‌سره؛ از سرشانه تا مچ پا یک خط می‌آید و هیچ
 * کمرگیری ندارد. جلو یا کاملاً باز است یا با چند بند بسته می‌شود. چون درز حلقه
 * ندارد، با پارچه‌های نرم و پهن (کرپ، ابریشم، ژرسه) کم‌ترین دورریز را می‌دهد.
 */
class AbayaGenerator extends BodiceGarmentBase
{
    public static function key(): string
    {
        return 'abaya';
    }

    public function label(): string
    {
        return 'عبا';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerSchema(['neck_width_extra' => 2.5, 'front_neck_depth_extra' => 3, 'armhole_depth_extra' => 5]),
            $this->garmentLengthParam(100, 55, 135),
            $this->openingParam('open', 0, [
                'open' => 'جلوباز بدون بست',
                'button' => 'جلوباز با دکمه',
                'closed' => 'جلو بسته با چاک یقه',
            ]),
            [
                'ease_extra' => [
                    'label' => 'آزادی افزوده تنه (هر نیم‌قطعه)', 'min' => 2, 'max' => 18, 'step' => 0.5,
                    'default' => 8, 'unit' => 'سانتی‌متر',
                ],
                'underarm_drop' => [
                    'label' => 'پایین آمدن زیر بغل', 'min' => 4, 'max' => 26, 'step' => 0.5,
                    'default' => 12, 'unit' => 'سانتی‌متر',
                ],
                'sleeve_length' => [
                    'label' => 'بلندی آستین از زیر بغل', 'min' => 15, 'max' => 65, 'step' => 1,
                    'default' => 40, 'unit' => 'سانتی‌متر',
                ],
                'cuff_width' => [
                    'label' => 'پهنای دم آستین', 'min' => 12, 'max' => 40, 'step' => 1,
                    'default' => 24, 'unit' => 'سانتی‌متر',
                ],
                'hem_flare' => [
                    'label' => 'باز شدن لبه پایین در هر پهلو', 'min' => 0, 'max' => 40, 'step' => 1,
                    'default' => 14, 'unit' => 'سانتی‌متر',
                ],
                'tie' => [
                    'label' => 'بند جلو داشته باشد', 'type' => 'toggle', 'default' => true,
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = (float) $this->param($params, 'ease_extra', 8);
        $length = (float) $this->param($params, 'length', 100);
        $opening = (string) $this->param($params, 'front_opening', 'open');
        $stand = $opening === 'button' ? (float) $this->param($params, 'button_stand', 2.5) : 0.0;

        $shared = [
            'grow' => $grow,
            'length' => $length,
            'underarm_drop' => (float) $this->param($params, 'underarm_drop', 12),
            'sleeve_length' => (float) $this->param($params, 'sleeve_length', 40),
            'cuff_width' => (float) $this->param($params, 'cuff_width', 24),
            'hem_flare' => (float) $this->param($params, 'hem_flare', 14),
        ];

        $front = $this->kimonoPanel($g, array_merge($shared, [
            'side' => 'front',
            'extension' => $stand,
            'on_fold' => $opening === 'closed',
            'cut' => $opening === 'closed' ? 1 : 2,
            'neck_depth_extra' => $opening === 'closed' ? 6 : 0,
            'code' => 'abaya-front',
            'name' => 'تنه و آستین جلو',
        ]));

        $back = $this->kimonoPanel($g, array_merge($shared, [
            'side' => 'back',
            'code' => 'abaya-back',
            'name' => 'تنه و آستین پشت',
        ]));

        $pieces = [$front, $back];

        if ($opening !== 'closed') {
            $pieces = array_merge($pieces, $this->facingSet($g, $stand, $g['front_waist_y'] + $length, [
                'prefix' => 'abaya-', 'width' => 9,
            ]));
        } else {
            $pieces[] = $this->backNeckFacingPiece($g, ['prefix' => 'abaya-', 'width' => 7]);
        }

        if ($this->flag($params, 'tie', true)) {
            $pieces[] = $this->bandPiece('abaya-tie', 'بند جلو', 45, 5, [
                'cut' => 2, 'part' => 'belt', 'fold_line' => true,
                'meta' => ['notes' => ['دو بند هم‌تراز خط سینه روی لبه جلو دوخته می‌شود.']],
            ]);
        }

        return $this->finishBlock($pieces, $g, $grow);
    }
}
