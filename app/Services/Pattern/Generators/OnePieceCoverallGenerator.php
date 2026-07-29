<?php

namespace App\Services\Pattern\Generators;

/**
 * سرهمی کار (کاورال).
 *
 * کاورال بویلرسوتِ گشادتر نیست؛ لباسی است که *روی* لباس دیگر پوشیده می‌شود و
 * همین یک جمله سه چیز را در الگو عوض می‌کند:
 *
 *   ۱. آزادی «روی لباس». آزادی کاورال باید جای پیراهن و شلوارِ زیرش را هم داشته
 *      باشد، پس یک پارامتر جدا برای همین هست و روی هر سه دور می‌نشیند. بدون آن،
 *      کاورال روی آستین پیراهن گیر می‌کند.
 *   ۲. یقه پیراهنی. کاورال بازِ یقه‌برگردان است تا یقه لباس زیر از آن بیرون
 *      بماند و گردن زیر دو یقه فشرده نشود.
 *   ۳. جیب کار. جیب کاورال با درپوش و با پیلی حجم بریده می‌شود تا ابزار درش جا
 *      شود؛ جیب تخت رودوزی همان کار را نمی‌کند.
 *
 * بست سرتاسری با دکمه فشاری است، چون با دستکش کار باز و بسته می‌شود.
 */
class OnePieceCoverallGenerator extends OnePieceBaseGenerator
{
    public static function key(): string
    {
        return 'one_coverall';
    }

    public function label(): string
    {
        return 'سرهمی کار (کاورال)';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->onePieceSchema([
                'shoulder_slope' => 3.5,
                'armhole_depth_extra' => 5,
                'neck_width_extra' => 2,
                'back_neck_depth' => 3,
            ]),
            $this->sleeveParam('set_in', 60, [
                'set_in' => 'آستین بلند',
            ]),
            $this->riseSchema(5, 4.5),
            $this->legSchema(['knee_ease' => 16, 'hem_ease' => 20]),
            [
                'over_ease' => [
                    'label' => 'آزادی پوشیدن روی لباس', 'min' => 0, 'max' => 10, 'step' => 0.5,
                    'default' => 4, 'unit' => 'سانتی‌متر',
                    'hint' => 'این عدد به دور سینه، کمر و باسن اضافه می‌شود تا لباس زیر در کاورال جا شود.',
                ],
                'closure' => [
                    'label' => 'بست سرتاسری جلو', 'type' => 'select', 'default' => 'button',
                    'options' => [
                        'button' => 'دکمه فشاری روی پاتلت جلو',
                        'zip' => 'زیپ سرتاسری روی درز مرکز جلو',
                    ],
                ],
                'buttons' => [
                    'label' => 'تعداد دکمه فشاری', 'min' => 4, 'max' => 12, 'step' => 1, 'default' => 8,
                ],
                'button_stand' => [
                    'label' => 'پهنای پاتلت', 'min' => 2.5, 'max' => 7, 'step' => 0.5,
                    'default' => 4, 'unit' => 'سانتی‌متر',
                ],
                'collar_height' => [
                    'label' => 'بلندی یقه پیراهنی', 'min' => 4, 'max' => 10, 'step' => 0.5,
                    'default' => 7, 'unit' => 'سانتی‌متر',
                ],
                'cuff' => [
                    'label' => 'مچ‌بند آستین', 'type' => 'toggle', 'default' => true,
                ],
                'cuff_height' => [
                    'label' => 'بلندی مچ‌بند', 'min' => 3, 'max' => 10, 'step' => 0.5,
                    'default' => 7, 'unit' => 'سانتی‌متر',
                ],
                'chest_pockets' => [
                    'label' => 'جیب سینه با درپوش', 'type' => 'toggle', 'default' => true,
                ],
                'thigh_pocket' => [
                    'label' => 'جیب ران (ابزار)', 'type' => 'toggle', 'default' => true,
                ],
                'pocket_gusset' => [
                    'label' => 'پیلی حجم جیب', 'min' => 0, 'max' => 6, 'step' => 0.5,
                    'default' => 3, 'unit' => 'سانتی‌متر',
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $params = $this->withRise($params);
        $g = $this->blockMetrics($measurements, $ease, $params);

        // «گشادتر از بویلرسوت» یک حرف سلیقه‌ای نیست: پایه همان چهار سانتی‌متر
        // رشدِ فرم گشاد است و آزادی پوشیدن روی لباس رویش سوار می‌شود
        $grow = 4.0 + ((float) $this->param($params, 'over_ease', 4) / 4);

        $pieces = $this->onePieceBody($measurements, $ease, $params, $g, [
            'prefix' => 'coverall-',
            'grow' => $grow,
            'panel' => ['shoulder_extra' => 1.5],
            'front' => [
                'on_fold' => false,
                'cut' => 2,
                'mirror' => true,
                'name' => 'بالاتنه جلو (درز و بست مرکزی)',
            ],
            'leg_front_name' => 'پاچه کار جلو',
            'leg_back_name' => 'پاچه کار پشت',
        ]);

        $front = $pieces[0];
        $back = $pieces[1];

        $pieces = $this->frontClosureSet($pieces, $g, $params, [
            'prefix' => 'coverall-',
            'leg_drop' => 14.0,
            'notion' => 'snap',
        ]);

        $pieces[] = $this->turnCollarPiece($this->neckOf([$front, $back]), (float) $this->param($params, 'collar_height', 7), [
            'prefix' => 'coverall-',
            'name' => 'یقه پیراهنی',
        ]);

        $pieces[] = $this->backNeckFacingPiece($g, ['prefix' => 'coverall-', 'width' => 8]);

        $gusset = (float) $this->param($params, 'pocket_gusset', 3);

        if ($this->flag($params, 'chest_pockets', true)) {
            $pieces = array_merge($pieces, $this->utilityPocketSet(13, 14, [
                'prefix' => 'coverall-chest-',
                'name' => 'جیب سینه با درپوش',
                'gusset' => 0.0,
                'flap' => 5,
                'cut' => 2,
            ]));
        }

        if ($this->flag($params, 'thigh_pocket', true)) {
            $pieces = array_merge($pieces, $this->utilityPocketSet(16, 19, [
                'prefix' => 'coverall-thigh-',
                'name' => 'جیب ران (ابزار)',
                'gusset' => $gusset,
                'flap' => 6,
                'cut' => 2,
            ]));
        }

        $pieces[0]['meta']['notes'] = array_merge($pieces[0]['meta']['notes'] ?? [], [
            'این کاورال با '.$this->fa(round((float) $this->param($params, 'over_ease', 4), 1))
                .' سانتی‌متر آزادیِ «روی لباس» درفت شده؛ اگر بی‌واسطه روی تن پوشیده می‌شود این عدد را صفر کنید.',
        ]);

        return $this->finishBlock($pieces, $g, $grow);
    }
}
