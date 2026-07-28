<?php

namespace App\Services\Pattern\Generators;

/**
 * لباس عروس با دامن پرحجم.
 *
 * بالاتنه کرستِ چندتکه با فنر روی درزها، آستر کامل و دامن بسیار پُر که با چین
 * ریز روی خط کمر جمع می‌شود. برای اینکه دامن پُر بایستد یک زیردامن (آستر پفی)
 * هم ساخته می‌شود. لبه کمرِ دامن به اندازه «نسبت پُری» از دور کمر بلندتر است و
 * همین اختلاف در meta.fullness ثبت می‌شود تا برگه فنی و نقشه برش آن را ببینند.
 */
class DressBridalGenerator extends BodiceGarmentBase
{
    public static function key(): string
    {
        return 'bridal_gown';
    }

    public function label(): string
    {
        return 'لباس عروس با دامن پرحجم';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerSchema(['waist_dart_share' => 0.7]),
            [
                'panels' => [
                    'label' => 'تعداد پنل نیم‌تنه', 'min' => 2, 'max' => 6, 'step' => 1, 'default' => 4,
                ],
                'top_line' => [
                    'label' => 'جابه‌جایی لبه بالای کرست', 'min' => -8, 'max' => 10, 'step' => 0.5,
                    'default' => 0, 'unit' => 'سانتی‌متر',
                ],
                'bodice_drop' => [
                    'label' => 'افتادگی نوک جلوی کرست', 'min' => 0, 'max' => 14, 'step' => 0.5,
                    'default' => 4, 'unit' => 'سانتی‌متر',
                ],
                'skirt_length' => [
                    'label' => 'بلندی دامن از کمر', 'min' => 70, 'max' => 140, 'step' => 1,
                    'default' => 108, 'unit' => 'سانتی‌متر',
                ],
                'fullness_ratio' => [
                    'label' => 'نسبت پُری دامن', 'min' => 1.5, 'max' => 4, 'step' => 0.1,
                    'default' => 2.6,
                    'hint' => 'دو و نیم یعنی لبه کمرِ دامن دو و نیم برابر دور کمر بریده و چین می‌شود.',
                ],
                'train' => [
                    'label' => 'بلندی دنباله پشت', 'min' => 0, 'max' => 120, 'step' => 5,
                    'default' => 30, 'unit' => 'سانتی‌متر',
                ],
                'underskirt' => [
                    'label' => 'زیردامن پفی', 'type' => 'toggle', 'default' => true,
                ],
                'lining' => [
                    'label' => 'آستر کرست', 'type' => 'toggle', 'default' => true,
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $panels = (int) $this->param($params, 'panels', 4);
        $top = (float) $this->param($params, 'top_line', 0);
        $drop = (float) $this->param($params, 'bodice_drop', 4);
        $length = (float) $this->param($params, 'skirt_length', 108);
        $ratio = max(1.0, (float) $this->param($params, 'fullness_ratio', 2.6));
        $train = (float) $this->param($params, 'train', 30);

        $shared = ['top_extra' => $top, 'length' => $drop, 'panels' => $panels];

        $pieces = array_merge(
            $this->corsetPanels($g, array_merge($shared, ['side' => 'front', 'center_fold' => true, 'prefix' => 'bridal-'])),
            $this->corsetPanels($g, array_merge($shared, ['side' => 'back', 'center_fold' => false, 'prefix' => 'bridal-'])),
        );

        if ($this->flag($params, 'lining', true)) {
            foreach (['front', 'back'] as $side) {
                foreach ($this->corsetPanels($g, array_merge($shared, [
                    'side' => $side,
                    'center_fold' => $side === 'front',
                    'layer' => 'lining',
                    'prefix' => 'bridal-lining-',
                ])) as $panel) {
                    $panel['name'] = 'آستر '.$panel['name'];
                    $panel['meta']['girth_role'] = 'lining';
                    $panel['meta']['part'] = 'lining';
                    $pieces[] = $panel;
                }
            }
        }

        $gather = round($g['quarter_waist'] * ($ratio - 1), 2);

        foreach (['front', 'back'] as $side) {
            $isFront = $side === 'front';

            $pieces[] = $this->lowerPanel($g, [
                'side' => $side,
                'shape' => 'flare',
                'top_width' => $g['quarter_waist'],
                'top_y' => $g['side_waist_y'],
                'length' => $length,
                'hem_drop' => $isFront ? 0.0 : $train,
                'gather' => $gather,
                'flare' => round($length * 0.55, 2),
                'top_tag' => 'waist',
                'code' => 'bridal-skirt-'.$side,
                'name' => $isFront ? 'دامن جلو' : 'دامن پشت (با دنباله)',
                'cut' => 2,
                'on_fold' => false,
                'meta' => [
                    'gather_ratio' => $ratio,
                    'train' => $isFront ? 0.0 : round($train, 2),
                    'notes' => array_values(array_filter([
                        'لبه کمر این پنل '.$this->fa($ratio).' برابر لبه کمر کرست است و با چین ریز روی آن جمع می‌شود.',
                        $isFront || $train < 1 ? null : 'دنباله فقط روی خط مرکز پشت بلندتر است؛ درز پهلو با جلو هم‌اندازه می‌ماند.',
                    ])),
                ],
            ]);
        }

        if ($this->flag($params, 'underskirt', true)) {
            $pieces[] = $this->lowerPanel($g, [
                'side' => 'front',
                'shape' => 'flare',
                'top_width' => $g['quarter_waist'],
                'top_y' => $g['side_waist_y'],
                'length' => max(20.0, $length - 6),
                'gather' => round($g['quarter_waist'] * 0.8, 2),
                'flare' => round($length * 0.45, 2),
                'top_tag' => 'waist',
                'layer' => 'lining',
                'girth_role' => 'lining_skirt',
                'code' => 'bridal-underskirt',
                'name' => 'زیردامن پفی',
                'cut' => 4,
                'on_fold' => false,
                'part' => 'lining',
                'meta' => ['notes' => ['چهار پنل یکسان که زیر دامن دوخته می‌شود و به آن حجم می‌دهد.']],
            ]);
        }

        $pieces[] = $this->bandPiece('bridal-waist-stay', 'نوار نگه‌دارنده کمر', $g['waist'] / 2, 2.5, [
            'cut' => 1, 'part' => 'waistband',
            'meta' => ['notes' => ['نوار محکمِ داخل کمر که وزن دامن را می‌گیرد و لباس را سر جای خود نگه می‌دارد.']],
        ]);

        return $this->finishBlock($pieces, $g);
    }
}
