<?php

namespace App\Services\Pattern\Generators;

/**
 * پیراهن پیراهنی (شرت‌دِرس).
 *
 * پیراهنی که از کمر به بالا دقیقاً یک پیراهن مردانه است و از کمر به پایین دامن.
 * سه چیز آن را «پیراهنی» می‌کند و اگر هر سه نباشند، فقط یک پیراهن جلوباز است:
 *
 *   جادکمهٔ سرتاسری   نواری که از خط مرکز جلو بیرون می‌زند و از یقه تا دم می‌رود.
 *                     مهم این است که همین نوار روی *بالاتنه و دامن هر دو* باشد،
 *                     وگرنه دو لبهٔ جلو در خط کمر روی هم نمی‌افتند.
 *   سجاف              جلوی باز بدون سجاف تا شسته شود لوله می‌شود.
 *   یقهٔ پیراهنی       پایهٔ یقه و برگردانِ یقه، هر دو از دور یقهٔ همین الگو.
 *
 * آستین از حلقهٔ اندازه‌گیری‌شدهٔ همین بالاتنه درفت می‌شود، نه از عددی ثابت؛ اگر
 * یقه یا سرشانه عوض شود، آستین هم با حلقهٔ تازه ساخته می‌گردد.
 */
class DressShirtdressGenerator extends DressBaseGenerator
{
    public static function key(): string
    {
        return 'dress_shirtdress';
    }

    public function label(): string
    {
        return 'پیراهن پیراهنی';
    }

    public function paramsSchema(): array
    {
        return $this->dressSchema(
            array_merge(
                $this->skirtLengthParam(62, 35, 110),
                $this->sleeveParam('set_in', 24, [
                    'none' => 'بدون آستین',
                    'set_in' => 'آستین حلقه‌ای',
                ]),
                [
                    'button_stand' => [
                        'label' => 'اضافه جای دکمه', 'min' => 1.5, 'max' => 5, 'step' => 0.5,
                        'default' => 2.5, 'unit' => 'سانتی‌متر',
                        'hint' => 'پهنای نواری که از خط مرکز جلو بیرون می‌زند؛ روی بالاتنه و دامن یکی است.',
                    ],
                    'buttons' => [
                        'label' => 'تعداد دکمهٔ بالاتنه', 'min' => 3, 'max' => 10, 'step' => 1, 'default' => 5,
                    ],
                    'skirt_buttons' => [
                        'label' => 'تعداد دکمهٔ دامن', 'min' => 0, 'max' => 10, 'step' => 1, 'default' => 4,
                    ],
                    'collar' => [
                        'label' => 'یقه', 'type' => 'select', 'default' => 'shirt',
                        'options' => [
                            'shirt' => 'یقهٔ پیراهنی (پایه و برگردان)',
                            'stand' => 'فقط پایهٔ یقه (یقه دیپلمات)',
                            'none' => 'بدون یقه (لبهٔ تمیزدوزی‌شده)',
                        ],
                    ],
                    'collar_height' => [
                        'label' => 'بلندی برگردان یقه', 'min' => 4, 'max' => 10, 'step' => 0.5,
                        'default' => 6.5, 'unit' => 'سانتی‌متر',
                    ],
                    'skirt_style' => [
                        'label' => 'فرم دامن', 'type' => 'select', 'default' => 'a_line',
                        'options' => ['a_line' => 'خط A', 'straight' => 'راسته', 'gather' => 'چین‌دار'],
                    ],
                    'hem_flare' => [
                        'label' => 'باز شدن دم در هر پهلو', 'min' => 0, 'max' => 25, 'step' => 1,
                        'default' => 9, 'unit' => 'سانتی‌متر',
                    ],
                    'belt' => [
                        'label' => 'کمربند پارچه‌ای', 'type' => 'toggle', 'default' => true,
                    ],
                ],
            ),
            // جلوی این پیراهن باز است، پس بست پشت لازم ندارد
            ['fit' => 'regular', 'back_closure' => 'none', 'lining' => 'none'],
            ['neck_width_extra' => 1, 'front_neck_depth_extra' => 1, 'back_neck_depth' => 2.5, 'waist_dart_share' => 0.55],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $ease = $this->dressEase($ease, $params, ['bust' => 7.0, 'waist' => 5.0, 'hip' => 6.0]);
        $g = $this->blockMetrics($measurements, $ease, $params);

        $stand = (float) $this->param($params, 'button_stand', 2.5);
        $length = (float) $this->param($params, 'skirt_length', 62);
        $style = (string) $this->param($params, 'skirt_style', 'a_line');

        [$bodice, $waist] = $this->dressBodice($g, $params, [
            'prefix' => 'shirtdress',
            'extension' => $stand,
            'bust_dart' => true,
            'waist_dart' => true,
            'back_seam' => false,
            'front_name' => 'بالاتنه جلو (با جای دکمه)',
            'back_name' => 'بالاتنه پشت',
        ]);

        // دکمه‌های بالاتنه روی خط مرکز جلو، از یقه تا خط کمر
        $bodice[0] = $this->markButtons(
            $bodice[0],
            $stand,
            $g['front_neck_depth'] + 2,
            max($g['front_neck_depth'] + 8, $g['front_waist_y'] - 2),
            (int) $this->param($params, 'buttons', 5),
            'جای دکمهٔ بالاتنه',
        );

        $skirt = $this->attachedSkirt($g, [
            'bodice_waist' => $waist,
            'prefix' => 'shirtdress',
            'type' => $style === 'straight' ? 'straight' : 'a_line',
            'length' => $length,
            'flare' => (float) $this->param($params, 'hem_flare', 9),
            'gather' => $style === 'gather' ? round($g['quarter_waist'] * 0.5, 2) : 0.0,
            // همان نوار جای دکمه روی دامن هم ادامه پیدا می‌کند، وگرنه دو لبهٔ جلو
            // در خط کمر روی هم نمی‌افتند
            'extension' => $stand,
            'back_seam' => false,
            'front_name' => 'دامن جلو (با جای دکمه)',
            'back_name' => 'دامن پشت',
        ]);

        [$skirt, $waistNotes] = $this->joinWaist($skirt, $waist);

        $skirtButtons = (int) $this->param($params, 'skirt_buttons', 4);

        if ($skirtButtons > 0) {
            $skirt[0] = $this->markButtons($skirt[0], $stand, 4.0, max(8.0, $length * 0.62), $skirtButtons, 'جای دکمهٔ دامن');
        }

        $pieces = array_merge($bodice, $skirt);

        $pieces = array_merge($pieces, $this->dressSleeves($measurements, $ease, $params, $bodice, $g, ['prefix' => 'shirtdress-']));

        // یقه از دور یقهٔ همین الگو بریده می‌شود، نه از جدول
        $halfNeck = $this->neckOf($bodice);
        $collar = (string) $this->param($params, 'collar', 'shirt');
        $height = (float) $this->param($params, 'collar_height', 6.5);

        if ($collar === 'shirt') {
            $pieces[] = $this->standCollarPiece($halfNeck, 3.0, ['prefix' => 'shirtdress-']);
            $pieces[] = $this->turnCollarPiece($halfNeck + 1.0, $height, ['prefix' => 'shirtdress-', 'name' => 'برگردان یقه']);
        } elseif ($collar === 'stand') {
            $pieces[] = $this->standCollarPiece($halfNeck, min(5.0, $height), ['prefix' => 'shirtdress-']);
        }

        // سجاف جلو تا لبهٔ دم می‌رود؛ جلوی باز بدون سجاف پس از شستن لوله می‌شود
        $pieces[] = $this->frontFacingPiece($g, $stand, $g['front_waist_y'] + $length, [
            'prefix' => 'shirtdress-',
            'width' => $stand + 5.5,
        ]);
        $pieces[] = $this->backNeckFacingPiece($g, ['prefix' => 'shirtdress-', 'width' => 6]);

        if ((string) $this->param($params, 'sleeve_style', 'set_in') === 'none') {
            $pieces[] = $this->armholeBindingPiece($this->armholeOf($bodice), ['prefix' => 'shirtdress-']);
        }

        if ($this->flag($params, 'belt', true)) {
            $pieces[] = $this->beltPiece($measurements, $params, ['prefix' => 'shirtdress-', 'width' => 4, 'tie' => 45]);
            $pieces[] = $this->beltLoopPiece(4, ['prefix' => 'shirtdress-', 'cut' => 2]);
        }

        [$pieces, $closureNotes] = $this->dressClosure($pieces, $g, $params);
        $pieces = $this->dressLining($pieces, $params);

        $notes = array_merge($waistNotes, $closureNotes, [
            'نوار جای دکمه '.$this->fa(round($stand, 1)).' سانتی‌متر است و روی بالاتنه و دامن یکسان بریده می‌شود؛'
                .' اگر روی یکی باشد و روی دیگری نه، دو لبهٔ جلو در خط کمر روی هم نمی‌افتند.',
            'جادکمه‌ها روی خط مرکز جلو می‌افتند، نه روی لبهٔ نوار؛ یکی از آن‌ها باید دقیقاً روی خط کمر بنشیند تا لباس در آن نقطه باز نشود.',
            $collar === 'none'
                ? 'یقه ندارد و خط یقه با سجاف تمام می‌شود.'
                : 'پایهٔ یقه به اندازهٔ دور یقهٔ همین الگو ('.$this->fa(round($halfNeck * 2, 1)).' سانتی‌متر) بریده می‌شود، نه از جدول.',
            'اگر بلندی دامن از زانو گذشت و دکمهٔ دامن کم بود، لباس هنگام نشستن باز می‌شود؛ آخرین دکمه را دست‌کم تا میانهٔ ران بیاورید.',
        ]);

        return $this->finish($this->noted($pieces, $notes));
    }
}
