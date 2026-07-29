<?php

namespace App\Services\Pattern\Generators;

/**
 * رکابی ورزشی.
 *
 * لایهٔ چسبانِ رویی: روی سوتین ورزشی پوشیده می‌شود، پس آزادی‌اش منفی است ولی نه
 * به اندازهٔ سوتین — ضریب کشسانی پیش‌فرض ۰٫۹۳ یعنی هفت درصد کوچک‌تر از بدن، که
 * روی تن می‌نشیند بی‌آنکه فشار بیاورد.
 *
 * سه چیز آن را از یک تاپ معمولی جدا می‌کند:
 *
 *   • حلقه بالاتر و تنگ‌تر است تا هنگام بالا بردن دست، زیر بغل باز نماند؛ برای
 *     همین «تنگ‌تر شدن حلقه» و «بالا آمدن حلقه» دو پارامتر جدا هستند.
 *   • دم لباس پشت بلندتر از جلوست تا هنگام خم شدن کمر بیرون نماند.
 *   • هر دو لبهٔ باز (خط بالا و حلقه) با نوار کشباف تمام می‌شوند، و طول نوار از
 *     روی خودِ لبه‌ها اندازه گرفته می‌شود نه از عددی حدسی.
 */
class ActiveTankGenerator extends ActiveBaseGenerator
{
    public static function key(): string
    {
        return 'active_tank';
    }

    public function label(): string
    {
        return 'رکابی ورزشی';
    }

    public function paramsSchema(): array
    {
        return $this->activeSchema(
            ['neck_width_extra' => 2, 'front_neck_depth_extra' => 2],
            array_merge(
                $this->stretchParam(0.93),
                [
                    'body_length' => [
                        'label' => 'بلندی از خط کمر', 'min' => -14, 'max' => 24, 'step' => 1,
                        'default' => 12, 'unit' => 'سانتی‌متر',
                        'hint' => 'عدد منفی یعنی کراپ.',
                    ],
                    'back_drop' => [
                        'label' => 'بلندتر بودن پشت', 'min' => 0, 'max' => 10, 'step' => 0.5,
                        'default' => 4, 'unit' => 'سانتی‌متر',
                    ],
                    'strap_width' => [
                        'label' => 'پهنای بند سرشانه', 'min' => 2, 'max' => 10, 'step' => 0.5,
                        'default' => 5, 'unit' => 'سانتی‌متر',
                    ],
                    'armhole_lift' => [
                        'label' => 'بالا آمدن حلقه', 'min' => 0, 'max' => 6, 'step' => 0.5,
                        'default' => 2, 'unit' => 'سانتی‌متر',
                    ],
                    'armhole_narrow' => [
                        'label' => 'تنگ‌تر شدن حلقه', 'min' => 0, 'max' => 6, 'step' => 0.5,
                        'default' => 2.5, 'unit' => 'سانتی‌متر',
                    ],
                    'binding_height' => [
                        'label' => 'بلندی نوار لبه', 'min' => 1, 'max' => 4, 'step' => 0.25,
                        'default' => 1.75, 'unit' => 'سانتی‌متر',
                    ],
                    'binding_ratio' => [
                        'label' => 'کوتاهی نوار لبه', 'min' => 0.7, 'max' => 0.95, 'step' => 0.01,
                        'default' => 0.88,
                    ],
                    'mesh_back' => [
                        'label' => 'پشت توری', 'type' => 'toggle', 'default' => false,
                    ],
                ],
            ),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $stretch = $this->readStretch($params, 0.93);
        $ease = $this->activeEase($ease, $measurements, $stretch);
        $g = $this->blockMetrics($measurements, $ease, $params);

        $length = (float) $this->param($params, 'body_length', 12);
        $backDrop = (float) $this->param($params, 'back_drop', 4);
        $strap = (float) $this->param($params, 'strap_width', 5);

        $shared = [
            'shape' => 'straight',
            'bottom_tag' => 'hem',
            'waist_dart' => false,
            'shoulder_extra' => $this->strapShoulder($g, $strap),
            'across_extra' => -(float) $this->param($params, 'armhole_narrow', 2.5),
            'armhole_drop' => -(float) $this->param($params, 'armhole_lift', 2),
        ];

        $front = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'front',
            'length' => $length,
            'code' => 'active-tank-front',
            'name' => 'تنه جلو',
        ]));

        $back = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'back',
            'length' => $length,
            'code' => 'active-tank-back',
            'name' => 'تنه پشت',
            'neck_depth_extra' => 1.5,
            'meta' => $this->flag($params, 'mesh_back', false)
                ? ['fabric' => 'mesh', 'notes' => ['از پارچهٔ توری بریده می‌شود؛ درزها را نواردوزی کنید تا لبهٔ توری نساید.']]
                : [],
        ]));

        // «پشت بلندتر» فقط روی مرکز پشت می‌نشیند، نه روی درز پهلو؛ وگرنه دو درزی
        // که به هم دوخته می‌شوند هم‌اندازه نمی‌مانند.
        $back = $this->lengthenCenterHem($back, $backDrop);

        $pieces = [$front, $back];

        $pieces[] = $this->edgeBinding(
            'active-tank-neck-binding',
            'نوار خط بالا',
            $this->edgeTotal($pieces, 'neck'),
            (float) $this->param($params, 'binding_height', 1.75),
            (float) $this->param($params, 'binding_ratio', 0.88),
        );

        $pieces[] = $this->edgeBinding(
            'active-tank-armhole-binding',
            'نوار حلقه',
            $this->edgeTotal($pieces, 'armhole') / 2,
            (float) $this->param($params, 'binding_height', 1.75),
            (float) $this->param($params, 'binding_ratio', 0.88),
            2,
        );

        $pieces[0]['meta']['notes'] = array_merge(
            $pieces[0]['meta']['notes'] ?? [],
            $this->compressionNotes($stretch),
            [
                'دم پشت '.$this->fa(round($backDrop, 1)).' سانتی‌متر بلندتر از جلوست؛'
                    .' هنگام خم شدن کمر بیرون نمی‌ماند.',
                'حلقه '.$this->fa(round((float) $this->param($params, 'armhole_lift', 2), 1))
                    .' سانتی‌متر بالاتر و '.$this->fa(round((float) $this->param($params, 'armhole_narrow', 2.5), 1))
                    .' سانتی‌متر تنگ‌تر از حلقهٔ لباس آستین‌دار است تا با بالا بردن دست باز نماند.',
            ],
        );

        return $this->finishBlock($pieces, $g);
    }
}
