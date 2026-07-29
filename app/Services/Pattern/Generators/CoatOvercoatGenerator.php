<?php

namespace App\Services\Pattern\Generators;

/**
 * پالتو بلند.
 *
 * پالتویی که تا زیر زانو می‌آید. بلندی، دو مسئله می‌سازد که پالتوی کوتاه ندارد:
 *
 *   الف) راه رفتن. پارچه‌ای که تا زیر زانو می‌آید اگر راسته بریده شود، قدم را
 *        قفل می‌کند. راه‌حل همان چیزی است که خیاط همیشه می‌کند: لبهٔ پایین در هر
 *        پهلو باز می‌شود و مرکز پشت چاک بلند می‌خورد.
 *   ب) وزن. یک پالتوی بلند از پارچهٔ ضخیم روی سرشانه آویزان است؛ اگر آستر لبهٔ
 *        پایین را با خود بکشد، پالتو کج می‌شود. پس آستر کوتاه‌تر از رو بریده
 *        می‌شود و مرکز پشتش پیلی راحتی دارد.
 *
 * آستین دوتکهٔ خیاطی است و یقهٔ برگردان پهن روی سجاف یک‌سره می‌خوابد.
 */
class CoatOvercoatGenerator extends OuterwearBaseGenerator
{
    public static function key(): string
    {
        return 'coat_overcoat';
    }

    public function label(): string
    {
        return 'پالتو بلند';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerwearSchema([
                'armhole_depth_extra' => 5,
                'neck_width_extra' => 2.5,
                'front_neck_depth_extra' => 3,
                'shoulder_slope' => 4,
                'waist_dart_share' => 0.45,
            ]),
            $this->garmentLengthParam(85, 55, 130),
            $this->openingParam('button', 4),
            $this->collarParam('turn', [
                'turn' => 'یقه برگردان',
                'shawl' => 'یقه شالی با سجاف یک‌سره',
                'stand' => 'یقه ایستاده',
            ], 9),
            $this->sleeveParam('two_piece', 62),
            [
                'hem_flare' => [
                    'label' => 'باز شدن لبه پایین در هر پهلو', 'min' => 0, 'max' => 25, 'step' => 0.5,
                    'default' => 8, 'unit' => 'سانتی‌متر',
                ],
                'back_vent' => [
                    'label' => 'بلندی چاک مرکز پشت', 'min' => 0, 'max' => 60, 'step' => 1,
                    'default' => 38, 'unit' => 'سانتی‌متر',
                ],
                'collar_break' => [
                    'label' => 'محل شکست یقه از خط سینه', 'min' => 0, 'max' => 25, 'step' => 1,
                    'default' => 8, 'unit' => 'سانتی‌متر',
                ],
            ],
            $this->pocketParam(true, 17, 18),
            $this->liningParam(true),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $ease = $this->outerwearEase($ease, $params, 15.0, ['hip' => 1.0]);
        $g = $this->blockMetrics($measurements, $ease, $params);

        $length = (float) $this->param($params, 'length', 85);
        $vent = (float) $this->param($params, 'back_vent', 38);
        $buttons = (int) $this->param($params, 'buttons', 5);

        $pieces = $this->outerGarment($measurements, $ease, $params, $g, [
            'prefix' => 'overcoat-',
            'grow' => 0.0,
            'shape' => 'fitted',
            'back_seam' => true,
            'bust_dart' => true,
            'buttons' => $buttons,
            'front_name' => 'تنه جلوی پالتو',
            'back_name' => 'تنه پشت پالتو (درز مرکزی)',
            'facing_width' => 13,
            'collar_break' => (float) $this->param($params, 'collar_break', 8),
            'lining' => true,
            // آستر سه سانتی‌متر کوتاه‌تر است تا وزنش لبهٔ پایین رو را نکشد
            'lining_options' => ['length' => max(0.0, $length - 3), 'back_pleat' => 3.0],
        ]);

        $notes = [
            'آستر سه سانتی‌متر کوتاه‌تر از رو بریده شده و مرکز پشتش پیلی راحتی دارد؛ '
                .'آستر هم‌قد رو، لبهٔ پایین پالتو را به داخل می‌کشد.',
        ];

        if ($vent > 1) {
            $pieces[1]['meta']['back_vent'] = round($vent, 2);
            $pieces[1]['meta']['notes'] = array_merge($pieces[1]['meta']['notes'] ?? [], [
                'چاک مرکز پشت به بلندی '.$this->fa(round($vent, 1)).' سانتی‌متر باز می‌ماند؛ بدون آن، قدم برداشتن در پالتوی بلند سخت است.',
            ]);
        }

        if ($this->flag($params, 'pocket', true)) {
            $pieces = array_merge($pieces, $this->weltPocketSet(
                (float) $this->param($params, 'pocket_width', 17),
                (float) $this->param($params, 'pocket_height', 18),
                ['prefix' => 'overcoat-', 'welt' => 3.5, 'name' => 'مغزی جیب اریب'],
            ));
        }

        return $this->finishOuterwear($pieces, $g, $ease, $params, $notes);
    }
}
