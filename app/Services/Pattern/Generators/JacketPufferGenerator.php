<?php

namespace App\Services\Pattern\Generators;

/**
 * کاپشن پافر.
 *
 * کاپشنی که گرمایش را نه از پارچه که از هوای میان پُر می‌گیرد. دو چیز این مدل
 * را می‌سازد و هر دو در الگو دیده می‌شوند:
 *
 *   ۱. خطوط دوخت کاناله (بافل). اگر پُر آزاد بماند، ته لباس جمع می‌شود و بالا
 *      خالی. پس تنه با خط‌های افقی به کانال‌های بسته تقسیم می‌شود و هر کانال
 *      سهم خودش را نگه می‌دارد. این خط‌ها روی الگو نشانه دارند، نه در ذهن خیاط.
 *   ۲. آزادی زیاد. حجم پُر از پهنای الگو کم می‌کند: پارچه‌ای که پُر می‌گیرد،
 *      به دور بدن نمی‌رسد. پس آزادی این مدل بیشترین آزادی این خانواده است.
 *
 * صادقانه بگوییم چه چیزی درفت نشده: «جمع‌شدگیِ» هر کانال — یعنی پارچه‌ای که
 * برجسته شدنِ کانال می‌خورد — به‌صورت آزادیِ کلی حساب شده است، نه به‌صورت اضافهٔ
 * جداگانه روی هر کانال. برای پُرِ خیلی پرحجم باید به هر کانال یک تا یک و نیم
 * سانتی‌متر اضافه کرد.
 */
class JacketPufferGenerator extends OuterwearBaseGenerator
{
    public static function key(): string
    {
        return 'jacket_puffer';
    }

    public function label(): string
    {
        return 'کاپشن پافر';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerwearSchema([
                'armhole_depth_extra' => 6,
                'neck_width_extra' => 3,
                'front_neck_depth_extra' => 2,
                'shoulder_slope' => 3,
            ], [], 'loose', 'knit'),
            $this->garmentLengthParam(22, 4, 60),
            $this->sleeveParam('set_in', 62, ['set_in' => 'آستین معمولی (کاناله‌دوزی‌شده)']),
            [
                'baffle_spacing' => [
                    'label' => 'فاصلهٔ خطوط کاناله', 'min' => 6, 'max' => 20, 'step' => 0.5,
                    'default' => 11, 'unit' => 'سانتی‌متر',
                    'hint' => 'فاصلهٔ کم یعنی کانال‌های باریک و پُرِ ثابت‌تر، ولی دوخت بیشتر و گرمای کمتر.',
                ],
                'fill' => [
                    'label' => 'پُر', 'type' => 'select', 'default' => 'down',
                    'options' => [
                        'down' => 'پَرِ طبیعی',
                        'synthetic' => 'الیاف مصنوعی',
                        'wadding' => 'لایی گرم (وادینگ)',
                    ],
                ],
                'collar_height' => [
                    'label' => 'بلندی یقهٔ ایستاده', 'min' => 4, 'max' => 14, 'step' => 0.5,
                    'default' => 9, 'unit' => 'سانتی‌متر',
                ],
                'cuff_elastic' => [
                    'label' => 'مچ آستین کشی', 'type' => 'toggle', 'default' => true,
                ],
            ],
            $this->pocketParam(true, 16, 16),
            $this->liningParam(true),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $ease = $this->outerwearEase($ease, $params, 17.0, ['bicep' => 12.0]);
        $g = $this->blockMetrics($measurements, $ease, $params);

        $length = (float) $this->param($params, 'length', 22);
        $spacing = (float) $this->param($params, 'baffle_spacing', 11);
        $fill = (string) $this->param($params, 'fill', 'down');

        $pieces = $this->outerGarment($measurements, $ease, $params, $g, [
            'prefix' => 'puffer-',
            'grow' => 0.0,
            'shape' => 'straight',
            'opening' => 'zip',
            'collar' => 'stand',
            'collar_height' => (float) $this->param($params, 'collar_height', 9),
            'front_name' => 'تنه جلوی پافر',
            'back_name' => 'تنه پشت پافر',
            'facing_width' => 7,
            'panel' => ['waist_dart' => false],
            'lining' => true,
            'lining_options' => ['shape' => 'straight', 'length' => $length, 'back_pleat' => 1.0],
        ]);

        // خطوط کاناله روی هر قطعه‌ای که پُر می‌گیرد: تنه، آستین و آستر
        foreach ($pieces as $index => $piece) {
            if (! in_array($piece['meta']['girth_role'] ?? '', ['shell', 'lining', 'sleeve'], true)) {
                continue;
            }

            $pieces[$index] = $this->baffleLines($piece, $spacing, ($piece['meta']['girth_role'] ?? '') === 'sleeve' ? 8.0 : 0.0);
        }

        $notes = [
            'خطوط کاناله با فاصلهٔ '.$this->fa(round($spacing, 1)).' سانتی‌متر روی الگو نشانه خورده‌اند؛ '
                .'هر کانال یک لولهٔ بسته می‌شود و پُر داخلش جابه‌جا نمی‌شود.',
            'صادقانه: جمع‌شدگیِ خودِ کانال (پارچه‌ای که برجسته شدن می‌خورد) در این درفت به‌صورت '
                .'آزادیِ کلی حساب شده، نه اضافهٔ جداگانه روی هر کانال. برای پُرِ خیلی پرحجم، به هر کانال '
                .'یک تا یک و نیم سانتی‌متر اضافه کنید.',
            match ($fill) {
                'synthetic' => 'با الیاف مصنوعی، کانال‌ها را می‌شود مستقیم روی پارچه دوخت.',
                'wadding' => 'با لایی گرم، لایی را پیش از دوختِ کاناله روی پارچه سنجاق کنید تا نلغزد.',
                default => 'با پَرِ طبیعی، پارچه باید پَرگذر نباشد و درزها باید از داخل نواردوزی شوند، وگرنه پَر بیرون می‌زند.',
            },
        ];

        if ($this->flag($params, 'cuff_elastic', true)) {
            $pieces[] = $this->ribBandPiece(
                'puffer-cuff',
                'مچ کشیِ آستین',
                $this->m($measurements, 'wrist', 16.5) + 10,
                ['height' => 5, 'ratio' => 0.8, 'cut' => 2, 'on_fold' => false, 'part' => 'cuff'],
            );

            $pieces[count($pieces) - 1]['meta']['notions'][] = [
                'type' => 'elastic',
                'label' => 'کش مچ آستین',
                'count' => 2,
                'length' => round($this->m($measurements, 'wrist', 16.5) + 3, 1),
            ];
        }

        $hem = ($this->panelWidthAt($pieces[0], max(1.0, $g['side_waist_y'] + $length - 1.0)) * 2)
            + ($this->panelWidthAt($pieces[1], max(1.0, $g['side_waist_y'] + $length - 1.0)) * 2);

        $pieces[] = $this->bandPiece('puffer-hem-casing', 'جای کش لبهٔ پایین', $hem / 2, 4, [
            'cut' => 2, 'part' => 'waistband',
            'meta' => [
                'notions' => [
                    ['type' => 'cord', 'label' => 'بند کشی لبهٔ پایین', 'count' => 1, 'length' => round($hem + 30, 1)],
                    ['type' => 'eyelet', 'label' => 'مغزی بند لبهٔ پایین', 'count' => 2],
                ],
                'notes' => ['دو تکه برای دور کامل؛ بند از دو مغزی روی لبهٔ جلو بیرون می‌آید.'],
            ],
        ]);

        if ($this->flag($params, 'pocket', true)) {
            $pieces = array_merge($pieces, $this->weltPocketSet(
                (float) $this->param($params, 'pocket_width', 16),
                (float) $this->param($params, 'pocket_height', 16),
                ['prefix' => 'puffer-', 'welt' => 2.5, 'name' => 'مغزی جیب زیپ‌دار'],
            ));
        }

        return $this->finishOuterwear($pieces, $g, $ease, $params, $notes);
    }
}
