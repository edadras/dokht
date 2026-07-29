<?php

namespace App\Services\Pattern\Generators;

/**
 * جلیقه کارگو.
 *
 * جلیقهٔ کاری با جیب‌های جان‌دار. تفاوتش با جلیقهٔ رسمی فقط جیب نیست؛ حلقهٔ
 * آستینش هم فرق دارد و همان‌جا بیشترین خطا رخ می‌دهد.
 *
 * جلیقهٔ رسمی زیر کت پوشیده می‌شود، پس حلقه‌اش تنگ و بالاست. این جلیقه **رویِ**
 * پیراهن یا بافت پوشیده می‌شود، پس حلقه‌اش باید گشادتر باشد تا آستینِ لباس زیر
 * در آن جا شود — ولی نه آن‌قدر گشاد که زیر بغل باز بماند. تعادل این دو با دو
 * پارامتر جدا کنترل می‌شود: بالا آمدن حلقه و گشاد شدنش.
 *
 * دور تا دور حلقه با نوار اریب تمام می‌شود، چون هیچ آستینی نیست که لبه را
 * بپوشاند.
 */
class VestUtilityGenerator extends OuterwearBaseGenerator
{
    public static function key(): string
    {
        return 'vest_utility';
    }

    public function label(): string
    {
        return 'جلیقه کارگو';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerwearSchema([
                'armhole_depth_extra' => 2,
                'neck_width_extra' => 2.5,
                'front_neck_depth_extra' => 4,
                'shoulder_slope' => 4,
            ]),
            $this->garmentLengthParam(20, 4, 45),
            $this->collarParam('stand', [
                'stand' => 'یقه ایستاده',
                'none' => 'بدون یقه (لبه با نوار تمام می‌شود)',
            ], 5),
            [
                'front_close' => [
                    'label' => 'بست جلو', 'type' => 'select', 'default' => 'zip',
                    'options' => ['zip' => 'زیپ سراسری', 'button' => 'دکمهٔ فشاری'],
                ],
                'armhole_lift' => [
                    'label' => 'بالا آمدن حلقه آستین', 'min' => 0, 'max' => 6, 'step' => 0.5,
                    'default' => 1.5, 'unit' => 'سانتی‌متر',
                    'hint' => 'زیر بغل نباید باز بماند؛ ولی این جلیقه روی لباس دیگر پوشیده می‌شود، پس حلقه از جلیقهٔ رسمی گشادتر است.',
                ],
                'armhole_widen' => [
                    'label' => 'گشادتر شدن حلقه روی سرشانه', 'min' => 0, 'max' => 6, 'step' => 0.5,
                    'default' => 1.5, 'unit' => 'سانتی‌متر',
                ],
                'cargo_pockets' => [
                    'label' => 'تعداد جیب کارگو (هر طرف)', 'min' => 0, 'max' => 3, 'step' => 1, 'default' => 2,
                ],
                'cargo_width' => [
                    'label' => 'پهنای جیب کارگو', 'min' => 10, 'max' => 20, 'step' => 0.5,
                    'default' => 15, 'unit' => 'سانتی‌متر',
                ],
                'cargo_depth' => [
                    'label' => 'عمق جان‌دار جیب', 'min' => 1.5, 'max' => 6, 'step' => 0.5,
                    'default' => 3.5, 'unit' => 'سانتی‌متر',
                ],
            ],
            $this->liningParam(true),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $ease = $this->outerwearEase($ease, $params, 10.0);
        $g = $this->blockMetrics($measurements, $ease, $params);

        $length = (float) $this->param($params, 'length', 20);
        $close = (string) $this->param($params, 'front_close', 'zip');
        $cargo = (int) $this->param($params, 'cargo_pockets', 2);

        $pieces = $this->outerGarment($measurements, $ease, $params, $g, [
            'prefix' => 'utility-vest-',
            'grow' => 0.0,
            'shape' => 'straight',
            'opening' => $close === 'zip' ? 'zip' : 'button',
            'stand' => 2.5,
            'buttons' => $close === 'zip' ? 0 : 5,
            'front_name' => 'تنه جلوی جلیقه',
            'back_name' => 'تنه پشت جلیقه',
            'facing_width' => 7,
            // بی‌آستین، صریح: وگرنه پیش‌فرضِ لباس رویی آستین معمولی می‌سازد
            'sleeve' => ['style' => 'none'],
            'panel' => [
                'waist_dart' => false,
                'armhole_drop' => -(float) $this->param($params, 'armhole_lift', 1.5),
                'shoulder_extra' => -(float) $this->param($params, 'armhole_widen', 1.5),
            ],
            'lining' => true,
            'lining_options' => ['shape' => 'straight', 'length' => max(0.0, $length - 1.5)],
        ]);

        $armhole = $this->armholeOf([$pieces[0], $pieces[1]]);
        $pieces[] = $this->armholeBindingPiece($armhole, ['prefix' => 'utility-vest-', 'height' => 3.5]);

        $notes = [
            'این جلیقه آستین ندارد، پس حلقه یک «لبهٔ تمام‌شده» است نه درز؛ '
                .'دور حلقه '.$this->fa(round($armhole * 2, 1)).' سانتی‌متر است و با نوار اریب تمام می‌شود.',
            'حلقه از جلیقهٔ رسمی گشادتر بریده شده تا آستینِ لباس زیر در آن جا شود؛ '
                .'اگر جلیقه را روی لباس آستین‌کوتاه می‌پوشید، «بالا آمدن حلقه» را زیاد کنید.',
        ];

        if ($close === 'button') {
            $pieces[0]['meta']['notions'][] = ['type' => 'snap', 'label' => 'دکمهٔ فشاری جلو', 'count' => 5];
            $notes[] = 'دکمهٔ فشاری با دستکش هم باز و بسته می‌شود؛ جادکمهٔ معمولی در لباس کار زود پاره می‌شود.';
        }

        for ($i = 0; $i < $cargo; $i++) {
            $pieces = array_merge($pieces, $this->cargoPocketSet(
                (float) $this->param($params, 'cargo_width', 15),
                (float) $this->param($params, 'cargo_width', 15) * 1.1,
                [
                    'prefix' => 'utility-vest-cargo-'.($i + 1).'-',
                    'depth' => (float) $this->param($params, 'cargo_depth', 3.5),
                    'name' => 'جیب کارگو ردیف '.$this->fa($i + 1),
                    'cut' => 2,
                ],
            ));
        }

        if ($cargo > 0) {
            $notes[] = 'هر جیب کارگو سه قطعه دارد: کیسه، نوار جان‌دار و درپوش. '
                .'نوار جان‌دار جیب را از تنه فاصله می‌دهد و همان است که جیب را جادار می‌کند.';
        }

        $pieces = array_merge($pieces, $this->weltPocketSet(12, 14, [
            'prefix' => 'utility-vest-chest-', 'welt' => 2.0, 'name' => 'مغزی جیب سینه', 'bag_cut' => 4,
        ]));

        return $this->finishOuterwear($pieces, $g, $ease, $params, $notes);
    }
}
