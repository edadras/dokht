<?php

namespace App\Services\Pattern\Generators;

/**
 * پالتو راپ (بندی).
 *
 * پالتویی که هیچ دکمه‌ای ندارد و با کمربند بسته می‌شود. نبودِ دکمه یک تصمیم
 * ساده نیست؛ سه چیز را عوض می‌کند:
 *
 *   ۱. هم‌پوشانی جلو باید زیاد باشد. وقتی چیزی دو لبه را نگه نمی‌دارد، تنها
 *      چیزی که جلوی باز شدن پالتو را می‌گیرد همان پارچهٔ روی هم افتاده است.
 *      پس هم‌پوشانی اینجا پانزده تا هجده سانتی‌متر است، نه دو و نیم.
 *   ۲. یک بند داخلی لازم است. کمربند بیرونی لبهٔ زیرین را نگه نمی‌دارد؛ اگر
 *      بند داخلی نباشد، لبهٔ زیرین با هر قدم بیرون می‌زند.
 *   ۳. کمربند باید جایی برای عبور داشته باشد، وگرنه از جای خودش بالا و پایین
 *      می‌رود؛ دو حلقه روی درز پهلو، هم‌تراز خط کمر.
 *
 * یقه شالی است و با سجاف جلو یک‌سره می‌شود.
 */
class CoatWrapGenerator extends OuterwearBaseGenerator
{
    public static function key(): string
    {
        return 'coat_wrap';
    }

    public function label(): string
    {
        return 'پالتو راپ (بندی)';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerwearSchema([
                'armhole_depth_extra' => 4.5,
                'neck_width_extra' => 2.5,
                'front_neck_depth_extra' => 5,
                'shoulder_slope' => 4,
                'waist_dart_share' => 0.45,
            ]),
            $this->garmentLengthParam(70, 30, 120),
            $this->collarParam('shawl', [
                'shawl' => 'یقه شالی با سجاف یک‌سره',
                'turn' => 'یقه برگردان',
            ], 9),
            $this->sleeveParam('two_piece', 60),
            [
                'wrap_overlap' => [
                    'label' => 'هم‌پوشانی جلو', 'min' => 8, 'max' => 26, 'step' => 0.5,
                    'default' => 16, 'unit' => 'سانتی‌متر',
                    'hint' => 'این پالتو دکمه ندارد؛ تنها چیزی که جلو را بسته نگه می‌دارد همین پارچهٔ روی هم است.',
                ],
                'belt_width' => [
                    'label' => 'پهنای کمربند', 'min' => 3, 'max' => 9, 'step' => 0.5,
                    'default' => 6, 'unit' => 'سانتی‌متر',
                ],
                'inner_tie' => [
                    'label' => 'بند داخلی لبهٔ زیرین', 'type' => 'toggle', 'default' => true,
                ],
                'hem_flare' => [
                    'label' => 'باز شدن لبه پایین در هر پهلو', 'min' => 0, 'max' => 22, 'step' => 0.5,
                    'default' => 7, 'unit' => 'سانتی‌متر',
                ],
            ],
            $this->pocketParam(true, 17, 18),
            $this->liningParam(true),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $ease = $this->outerwearEase($ease, $params, 13.0, ['hip' => 1.0]);
        $g = $this->blockMetrics($measurements, $ease, $params);

        $overlap = (float) $this->param($params, 'wrap_overlap', 16);
        $length = (float) $this->param($params, 'length', 70);
        $beltWidth = (float) $this->param($params, 'belt_width', 6);

        $pieces = $this->outerGarment($measurements, $ease, $params, $g, [
            'prefix' => 'wrapcoat-',
            'grow' => 0.0,
            'shape' => 'fitted',
            'opening' => 'button',
            'stand' => $overlap,
            'buttons' => 0, // هیچ دکمه‌ای در کار نیست
            'back_seam' => true,
            'bust_dart' => true,
            'front_name' => 'تنه جلوی پالتو راپ (با هم‌پوشانی)',
            'back_name' => 'تنه پشت پالتو راپ (درز مرکزی)',
            'facing_width' => max(12.0, $overlap * 0.7),
            'collar_break' => 12,
            'lining' => true,
            'lining_options' => ['length' => max(0.0, $length - 3), 'back_pleat' => 3.0],
            'front_meta' => ['front_opening' => 'wrap', 'wrap_overlap' => round($overlap, 2)],
        ]);

        $pieces[] = $this->beltPiece($measurements, $params, [
            'prefix' => 'wrapcoat-', 'width' => $beltWidth, 'tie' => 50,
        ]);
        $pieces[] = $this->beltLoopPiece($beltWidth, ['prefix' => 'wrapcoat-', 'cut' => 2]);

        $notes = [
            'این پالتو دکمه ندارد و با کمربند بسته می‌شود؛ هم‌پوشانی جلو '
                .$this->fa(round($overlap, 1)).' سانتی‌متر است تا با هر قدم باز نشود.',
            'دو حلقهٔ کمربند روی درز پهلو و هم‌تراز خط کمر دوخته می‌شود، وگرنه کمربند از جای خودش بالا می‌رود.',
        ];

        if ($this->flag($params, 'inner_tie', true)) {
            $pieces[] = $this->bandPiece('wrapcoat-inner-tie', 'بند داخلی', 45, 3, [
                'cut' => 2, 'part' => 'belt',
                'meta' => ['notes' => ['یک سرش روی لبهٔ زیرین جلو و سر دیگرش روی درز پهلوی مقابل دوخته می‌شود.']],
            ]);
            $notes[] = 'بند داخلی لبهٔ زیرین را سر جایش نگه می‌دارد؛ بدون آن، لبهٔ زیرین از زیر لبهٔ رویی بیرون می‌زند.';
        }

        if ($this->flag($params, 'pocket', true)) {
            $pieces = array_merge($pieces, $this->weltPocketSet(
                (float) $this->param($params, 'pocket_width', 17),
                (float) $this->param($params, 'pocket_height', 18),
                ['prefix' => 'wrapcoat-', 'welt' => 3.0],
            ));
        }

        return $this->finishOuterwear($pieces, $g, $ease, $params, $notes);
    }
}
