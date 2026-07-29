<?php

namespace App\Services\Pattern\Generators;

/**
 * بویلرسوت.
 *
 * سرهمی کارِ کلاسیک: گشاد، آستین بلند، درز کمر و بستِ سرتاسری از یقه تا فاق.
 * سه چیز آن را از سرهمی مجلسی جدا می‌کند و هر سه کارکردی‌اند:
 *
 *   ۱. بست سرتاسری. چون لباس از سر پوشیده نمی‌شود، هم بالاتنه و هم پاچه درز
 *      مرکز جلو دارند و زیپ روی همان درز می‌نشیند. اگر به‌جای زیپ دکمه بخواهید،
 *      پاتلت جدا دوخته می‌شود؛ اضافه جای دکمه روی خود تنه، لبه کمر بالاتنه را
 *      از لبه کمر پاچه بلندتر می‌کند.
 *   ۲. درز کمر. بویلرسوت روی خط کمر شکسته می‌شود تا هم بالاتنه فرم بگیرد و هم
 *      پاچه گشاد بماند؛ همان درز جای نیم‌کمربند پشت است.
 *   ۳. آزادی رایز بیشتر از لباس معمولی. لباس کار باید بتواند بنشیند، بالا برود
 *      و خم شود؛ برای همین آزادی قد بالاتنه و قد فاق هر دو بالاتر گرفته شده‌اند.
 */
class OnePieceBoilersuitGenerator extends OnePieceBaseGenerator
{
    public static function key(): string
    {
        return 'one_boilersuit';
    }

    public function label(): string
    {
        return 'بویلرسوت';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->onePieceSchema(['armhole_depth_extra' => 4, 'neck_width_extra' => 1.5]),
            $this->fitParam('loose'),
            $this->sleeveParam('set_in', 58, [
                'set_in' => 'آستین بلند',
            ]),
            $this->riseSchema(4, 3.5),
            $this->legSchema(['knee_ease' => 12, 'hem_ease' => 16]),
            $this->collarParam('stand', [
                'stand' => 'یقه ایستاده',
                'turn' => 'یقه برگردان',
                'none' => 'بدون یقه (نوار تمیزدوزی)',
            ], 5),
            [
                'closure' => [
                    'label' => 'بست سرتاسری جلو', 'type' => 'select', 'default' => 'zip',
                    'options' => [
                        'zip' => 'زیپ سرتاسری روی درز مرکز جلو',
                        'button' => 'دکمه روی پاتلت جلو',
                    ],
                ],
                'buttons' => [
                    'label' => 'تعداد دکمه (اگر پاتلت دارید)', 'min' => 4, 'max' => 12, 'step' => 1, 'default' => 7,
                ],
                'button_stand' => [
                    'label' => 'پهنای پاتلت', 'min' => 2, 'max' => 6, 'step' => 0.5,
                    'default' => 3, 'unit' => 'سانتی‌متر',
                ],
                'cuff' => [
                    'label' => 'مچ‌بند آستین', 'type' => 'toggle', 'default' => true,
                ],
                'cuff_height' => [
                    'label' => 'بلندی مچ‌بند', 'min' => 3, 'max' => 9, 'step' => 0.5,
                    'default' => 6, 'unit' => 'سانتی‌متر',
                ],
                'back_belt' => [
                    'label' => 'نیم‌کمربند پشت', 'type' => 'toggle', 'default' => true,
                ],
            ],
            $this->pocketParam(true, 15, 17),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $params = $this->withRise($params);
        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = $this->fitGrow($params, ['fitted' => 1.0, 'regular' => 2.5, 'loose' => 4.0]);

        $pieces = $this->onePieceBody($measurements, $ease, $params, $g, [
            'prefix' => 'boilersuit-',
            'grow' => $grow,
            'front' => [
                'on_fold' => false,
                'cut' => 2,
                'mirror' => true,
                'name' => 'بالاتنه جلو (درز و بست مرکزی)',
            ],
        ]);

        $front = $pieces[0];
        $back = $pieces[1];

        $pieces = $this->frontClosureSet($pieces, $g, $params, [
            'prefix' => 'boilersuit-',
            'leg_drop' => 12.0,
        ]);

        $pieces = array_merge($pieces, $this->collarSet($g, $this->neckOf([$front, $back]), $params, [
            'prefix' => 'boilersuit-',
        ]));

        $pieces[] = $this->backNeckFacingPiece($g, ['prefix' => 'boilersuit-', 'width' => 7]);

        if ($this->flag($params, 'back_belt', true)) {
            $pieces[] = $this->bandPiece(
                'boilersuit-back-belt',
                'نیم‌کمربند پشت',
                ($g['quarter_waist'] + $grow) * 2,
                7,
                [
                    'cut' => 2, 'part' => 'belt', 'fold_line' => true,
                    'meta' => [
                        'notes' => ['روی درز کمرِ پشت دوخته می‌شود و با سگک تنگ می‌شود؛'
                            .' همان چیزی است که به بویلرسوت گشاد فرم می‌دهد.'],
                    ],
                ],
            );
        }

        $pieces = array_merge($pieces, $this->pocketSet($params, ['prefix' => 'boilersuit-']));

        return $this->finishBlock($pieces, $g, $grow);
    }
}
