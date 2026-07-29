<?php

namespace App\Services\Pattern\Generators;

/**
 * پالتو دافل.
 *
 * پالتوی پشمی با کلاه و بند و تاگل. دو نشانه‌اش هر دو کاربردی‌اند، نه تزیینی:
 *
 *   ۱. تاگل به‌جای دکمه. تاگل چوبی را با دستکش هم می‌شود بست؛ دکمه را نه. تاگل
 *      روی یک نوار می‌نشیند و حلقه‌اش از سمت مقابل می‌آید، پس هم‌پوشانی جلو
 *      کم است — تاگل خودش فاصله را پر می‌کند و ده سانتی‌متر پارچه لازم نیست.
 *   ۲. کلاه به‌جای یقه. کلاهِ دافل روی پارچهٔ ضخیم می‌نشیند، پس لبهٔ گردنی‌اش
 *      باید دقیقاً به اندازهٔ یقهٔ همین لباس دربیاید، نه به عددی ثابت؛ برای همین
 *      اندازهٔ کلاه از خودِ یقهٔ درفت‌شده گرفته می‌شود.
 *
 * تنه راسته است و تا میان ران می‌آید، جیب‌ها بزرگ و رودوزی‌اند.
 */
class CoatDuffleGenerator extends OuterwearBaseGenerator
{
    public static function key(): string
    {
        return 'coat_duffle';
    }

    public function label(): string
    {
        return 'پالتو دافل';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerwearSchema([
                'armhole_depth_extra' => 5,
                'neck_width_extra' => 3,
                'front_neck_depth_extra' => 3,
                'shoulder_slope' => 3.5,
            ], [], 'regular', 'knit'),
            $this->garmentLengthParam(45, 20, 90),
            $this->sleeveParam('set_in', 60),
            [
                'button_stand' => [
                    'label' => 'اضافه جای بست جلو', 'min' => 2, 'max' => 8, 'step' => 0.5,
                    'default' => 5, 'unit' => 'سانتی‌متر',
                ],
                'toggles' => [
                    'label' => 'تعداد تاگل', 'min' => 2, 'max' => 6, 'step' => 1, 'default' => 4,
                ],
                'hood' => [
                    'label' => 'کلاه', 'type' => 'toggle', 'default' => true,
                ],
                'hem_flare' => [
                    'label' => 'باز شدن لبه پایین در هر پهلو', 'min' => 0, 'max' => 16, 'step' => 0.5,
                    'default' => 3, 'unit' => 'سانتی‌متر',
                ],
            ],
            $this->pocketParam(true, 19, 20),
            $this->liningParam(true),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $ease = $this->outerwearEase($ease, $params, 14.0);
        $g = $this->blockMetrics($measurements, $ease, $params);

        $stand = (float) $this->param($params, 'button_stand', 5);
        $length = (float) $this->param($params, 'length', 45);
        $toggles = (int) $this->param($params, 'toggles', 4);
        $wantsHood = $this->flag($params, 'hood', true);

        $pieces = $this->outerGarment($measurements, $ease, $params, $g, [
            'prefix' => 'duffle-',
            'grow' => 0.0,
            'shape' => 'straight',
            'stand' => $stand,
            'opening' => 'button',
            'buttons' => 0, // بست این پالتو تاگل است، نه دکمهٔ جادکمه‌ای
            'collar' => 'none',
            'front_name' => 'تنه جلوی دافل',
            'back_name' => 'تنه پشت دافل',
            'facing_width' => max(9.0, $stand + 5),
            'lining' => true,
            'lining_options' => ['shape' => 'straight', 'length' => max(0.0, $length - 3), 'back_pleat' => 3.0],
        ]);

        $halfNeck = $this->neckOf([$pieces[0], $pieces[1]]);

        // جای تاگل‌ها روی لبهٔ جلو علامت می‌خورد؛ فاصله‌شان از خط سینه تا کمر
        $top = $g['bust_y'] + 1;
        $bottom = max($top + 6, $g['front_waist_y'] + min(8.0, $length - 6));
        $step = $toggles > 1 ? ($bottom - $top) / ($toggles - 1) : 0.0;

        for ($i = 0; $i < $toggles; $i++) {
            $pieces[0]['drills'][] = [
                'key' => 'toggle_'.($i + 1),
                'label' => 'جای تاگل '.$this->fa($i + 1),
                'x' => round($stand * 0.5, 2),
                'y' => round($top + ($step * $i), 2),
            ];
        }

        $pieces[0]['meta']['toggles'] = $toggles;

        if ($wantsHood) {
            $pieces = array_merge($pieces, $this->hoodSet($g, $halfNeck, [
                'prefix' => 'duffle-',
                'width_extra' => 7,
                'height_ratio' => 2.1,
                'name' => 'کلاه دافل',
            ]));
        } else {
            $pieces[] = $this->standCollarPiece($halfNeck, 6.0, ['prefix' => 'duffle-']);
        }

        $pieces = array_merge($pieces, $this->toggleSet($toggles, ['prefix' => 'duffle-']));

        $notes = [
            'بست این پالتو تاگل است: خودِ تاگل چوبی خریدنی است و در الگو کشیده نمی‌شود، '
                .'ولی نوار حلقه و نوار پایه‌اش هر دو در قطعه‌ها هستند.',
            'هم‌پوشانی جلو تنها '.$this->fa(round($stand, 1)).' سانتی‌متر است؛ تاگل خودش فاصلهٔ دو لبه را پر می‌کند '
                .'و هم‌پوشانی بیشتر، جلو را کلفت و سنگین می‌کند.',
        ];

        if ($this->flag($params, 'pocket', true)) {
            $pieces = array_merge($pieces, $this->pocketSet($params, ['prefix' => 'duffle-']));
            $notes[] = 'جیب‌ها بزرگ و رودوزی‌اند تا با دستکش هم بشود دست را داخلشان کرد.';
        }

        return $this->finishOuterwear($pieces, $g, $ease, $params, $notes);
    }
}
