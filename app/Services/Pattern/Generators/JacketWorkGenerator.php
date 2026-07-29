<?php

namespace App\Services\Pattern\Generators;

/**
 * کت کار.
 *
 * کتِ کارگاه و مزرعه — همان چیزی که در فرانسه «کتِ آبی» می‌گفتند. هیچ چیزش
 * تزیینی نیست و همین آن را از کتِ رسمی جدا می‌کند:
 *
 *   ۱. آستر ندارد. آسترِ یک لباس کار پاره می‌شود و شسته نمی‌شود؛ به‌جایش همهٔ
 *      درزها از داخل تمیزدوزی می‌شوند. پس درز پهلو باید تخت بخوابد و نمی‌تواند
 *      کمرگیریِ تند داشته باشد — فرم این کت راسته است.
 *   ۲. چهار جیب رودوزی، بزرگ. جیبِ مغزی‌دار در لباس کار پاره می‌شود؛ جیب
 *      رودوزی روی پارچه دوخته می‌شود و همان پارچه وزنش را می‌گیرد.
 *   ۳. یقهٔ ایستاده یا باز، بدون برگردان. برگردانِ یقه لایی می‌خواهد و لایی
 *      اولین چیزی است که در شست‌وشوی مکرر می‌شکند.
 */
class JacketWorkGenerator extends OuterwearBaseGenerator
{
    public static function key(): string
    {
        return 'jacket_work';
    }

    public function label(): string
    {
        return 'کت کار';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerwearSchema([
                'armhole_depth_extra' => 4.5,
                'neck_width_extra' => 2.5,
                'front_neck_depth_extra' => 2,
                'shoulder_slope' => 3.5,
            ]),
            $this->garmentLengthParam(18, 4, 45),
            $this->openingParam('button', 3),
            $this->collarParam('stand', [
                'stand' => 'یقه ایستاده',
                'turn' => 'یقه برگردان تخت',
                'none' => 'بدون یقه',
            ], 5),
            $this->sleeveParam('set_in', 60, ['set_in' => 'آستین معمولی']),
            [
                'chest_pockets' => [
                    'label' => 'جیب سینه', 'type' => 'toggle', 'default' => true,
                ],
                'chest_pocket_width' => [
                    'label' => 'پهنای جیب سینه', 'min' => 8, 'max' => 18, 'step' => 0.5,
                    'default' => 12, 'unit' => 'سانتی‌متر',
                ],
                'cuff' => [
                    'label' => 'مچ‌بند دکمه‌دار روی آستین', 'type' => 'toggle', 'default' => true,
                ],
                'cuff_height' => [
                    'label' => 'بلندی مچ‌بند', 'min' => 4, 'max' => 9, 'step' => 0.5,
                    'default' => 6, 'unit' => 'سانتی‌متر',
                ],
            ],
            $this->pocketParam(true, 17, 18),
            $this->liningParam(false),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $ease = $this->outerwearEase($ease, $params, 13.0, ['bicep' => 10.0]);
        $g = $this->blockMetrics($measurements, $ease, $params);

        $length = (float) $this->param($params, 'length', 18);
        $stand = (float) $this->param($params, 'button_stand', 3);

        $pieces = $this->outerGarment($measurements, $ease, $params, $g, [
            'prefix' => 'workjacket-',
            'grow' => 0.0,
            // راسته، چون بی‌آستر است و درزهایش باید تخت بخوابند
            'shape' => 'straight',
            'stand' => $stand,
            'buttons' => (int) $this->param($params, 'buttons', 5),
            'front_name' => 'تنه جلوی کت کار',
            'back_name' => 'تنه پشت کت کار',
            'facing_width' => 8,
            'panel' => ['waist_dart' => false],
            'lining_options' => ['shape' => 'straight', 'length' => max(0.0, $length - 1.5)],
        ]);

        $notes = [
            'این کت آستر ندارد: همهٔ درزها از داخل تمیزدوزی می‌شوند (درز فرانسوی یا نوارِ اریب) '
                .'و برای همین درز پهلو تخت و بی‌کمرگیری بریده شده است.',
        ];

        if ($this->flag($params, 'pocket', true)) {
            $pieces = array_merge($pieces, [$this->patchPocketPiece(
                (float) $this->param($params, 'pocket_width', 17),
                (float) $this->param($params, 'pocket_height', 18),
                ['prefix' => 'workjacket-hip-', 'name' => 'جیب پایین', 'cut' => 2],
            )]);
        }

        if ($this->flag($params, 'chest_pockets', true)) {
            $chest = (float) $this->param($params, 'chest_pocket_width', 12);
            $pieces[] = $this->patchPocketPiece($chest, $chest * 1.15, [
                'prefix' => 'workjacket-chest-', 'name' => 'جیب سینه', 'cut' => 2,
            ]);
            $notes[] = 'دو جیب سینه و دو جیب پایین، همه رودوزی؛ جیب مغزی‌دار در لباس کار زودتر پاره می‌شود.';
        }

        if ($this->flag($params, 'cuff', true)) {
            // مچ‌بند را خودِ درفت آستین می‌سازد؛ اگر اینجا دوباره ساخته شود، دو
            // قطعه با یک کد در الگو می‌ماند و صورت مواد هم دو برابر می‌شمارد.
            $notes[] = 'مچ‌بند دکمه‌دار، آستین را روی مچ جمع می‌کند تا هنگام کار بالا نرود.';
        }

        return $this->finishOuterwear($pieces, $g, $ease, $params, $notes);
    }
}
