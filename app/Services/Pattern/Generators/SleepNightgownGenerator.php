<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * لباس خواب بلند.
 *
 * پیراهن خوابی از جرسی یا ساتن کشی که از خط بالای سینه تا نزدیک مچ پا می‌رود؛
 * بالا به تن می‌نشیند و از کمر به پایین آزاد می‌ریزد.
 *
 * دو عدد کل این الگو را می‌سازند و هر دو در دسترس کاربرند:
 *
 *   - **ضریب کشسانی.** بالای لباس تکیه‌گاهی جز خودِ پارچه ندارد (نه زیپی، نه
 *     کشی، نه ساسون سینه‌ای که همه‌جا جواب بدهد)، پس باید کوچک‌تر از دور سینه
 *     بریده شود، وگرنه خط بالا از سینه جدا می‌ماند.
 *   - **باز شدن دم لباس.** لباس خوابِ راست، پا را می‌بندد و آدم در خواب پیچش
 *     می‌خورد. درفت خودش دم لباس را دست‌کم به اندازه‌ای باز می‌کند که از باسن رد
 *     شود، حتی اگر کاربر عدد کمی بدهد.
 *
 * بند باریک و جدا است: بند سرخود روی جرسی زیر وزن لباس دراز می‌شود و خط بالا
 * می‌افتد.
 */
class SleepNightgownGenerator extends SleepwearBaseGenerator
{
    public static function key(): string
    {
        return 'sleep_nightgown';
    }

    public function label(): string
    {
        return 'لباس خواب بلند';
    }

    public function paramsSchema(): array
    {
        return $this->knitSchema([
            'length' => [
                'label' => 'بلندی از خط کمر', 'min' => 25, 'max' => 110, 'step' => 1,
                'default' => 78, 'unit' => 'سانتی‌متر',
                'hint' => 'هفتاد و هشت سانتی‌متر روی بدن بزرگسال تا نزدیک مچ پا می‌آید.',
            ],
            'hem_flare' => [
                'label' => 'باز شدن دم لباس', 'min' => 0, 'max' => 40, 'step' => 1,
                'default' => 14, 'unit' => 'سانتی‌متر',
                'hint' => 'اگر کمتر از لازم بدهید، درفت خودش تا جایی که از باسن رد شود بازش می‌کند.',
            ],
            'top_drop' => [
                'label' => 'گودی خط بالای جلو از زیر بغل', 'min' => 2, 'max' => 22, 'step' => 0.5,
                'default' => 9, 'unit' => 'سانتی‌متر',
            ],
            'back_drop' => [
                'label' => 'گودی خط بالای پشت', 'min' => 2, 'max' => 26, 'step' => 0.5,
                'default' => 13, 'unit' => 'سانتی‌متر',
            ],
            'top_shape' => [
                'label' => 'شکل خط بالای جلو', 'type' => 'select', 'default' => 'sweetheart',
                'options' => ['straight' => 'صاف', 'sweetheart' => 'قلبی', 'scoop' => 'گرد'],
            ],
            'strap_width' => [
                'label' => 'پهنای بند', 'min' => 0.8, 'max' => 4, 'step' => 0.2,
                'default' => 1.6, 'unit' => 'سانتی‌متر',
            ],
        ], stretch: 0.92);
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $ease = $this->negativeEaseFor($ease, $measurements, $params);
        $g = $this->blockMetrics($measurements, $ease, $params);

        $length = (float) $this->param($params, 'length', 78);
        $frontDrop = (float) $this->param($params, 'top_drop', 9);
        $backDrop = (float) $this->param($params, 'back_drop', 13);

        $shared = [
            'shape' => 'flare',
            'length' => $length,
            'hem_flare' => (float) $this->param($params, 'hem_flare', 14),
            'bottom_tag' => 'hem',
            'waist_dart' => false,
        ];

        $front = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'front',
            'code' => 'nightgown-front',
            'name' => 'لباس خواب — جلو',
        ]));

        $back = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'back',
            'code' => 'nightgown-back',
            'name' => 'لباس خواب — پشت',
        ]));

        // جای برخورد خط بالا با درز پهلو روی جلو و پشت یکی است، وگرنه دو درز پهلو
        // هم‌اندازه نمی‌شوند و لباس دوخته نمی‌شود.
        $frontTop = (float) ($front['meta']['bust_y'] ?? 18) - $frontDrop;
        $backTop = (float) ($back['meta']['bust_y'] ?? 18) - $backDrop;
        $sideTop = min($frontTop, $backTop) + 1.5;

        $front = $this->cutTop($front, [
            'center' => $frontTop,
            'side' => $sideTop,
            'shape' => (string) $this->param($params, 'top_shape', 'sweetheart'),
            'apex' => 0.58,
        ]);

        $back = $this->cutTop($back, [
            'center' => $backTop,
            'side' => $sideTop,
            'shape' => 'straight',
        ]);

        // خط بالای بریده‌شده و خط مرکز هر دو برچسب default دارند؛ فقط آن یکی که
        // تای پارچه نیست کش می‌خورد.
        $frontTopEdges = $this->openEdges($front, 'default');
        $backTopEdges = $this->openEdges($back, 'default');
        $topLine = Geometry::edgesLength($front['outline'], $frontTopEdges)
            + Geometry::edgesLength($back['outline'], $backTopEdges);

        $front = $this->elasticFor($front, $frontTopEdges, 'کش نازک خط بالای جلو', $params);
        $back = $this->elasticFor($back, $backTopEdges, 'کش نازک خط بالای پشت', $params);

        $strapLength = $this->strapLength($g, $frontDrop + 4, $backDrop + 4, extra: 8);

        $strap = $this->strapPiece($strapLength, (float) $this->param($params, 'strap_width', 1.6), [
            'code' => 'nightgown-strap',
            'name' => 'بند لباس خواب',
            'cut' => 2,
            'meta' => [
                'adjustable' => true,
                'strap_path' => round($strapLength, 1),
            ],
        ]);

        $strap['meta']['notes'] = array_merge($strap['meta']['notes'] ?? [], [
            'بند '.$this->fa($strapLength).' سانتی‌متر بریده شده و عمداً بلندتر است؛ در پرو کوتاه می‌شود.',
            'بند را از نوار کشباف یا از دو لای پارچه با نوار کش داخلش ببرید؛ بند لختِ جرسی دراز می‌شود.',
        ]);

        $binding = $this->bandPiece(
            'nightgown-binding',
            'نوار خط بالا',
            max(28.0, $topLine + 6.0),
            2.2,
            [
                'cut' => 2,
                'fold_line' => true,
                'part' => 'binding',
                'meta' => [
                    'girth_role' => 'trim',
                    'notes' => [
                        'خط بالای جلو و پشت با همین نوار تمام می‌شود.',
                        'نوار را ده درصد کوتاه‌تر از خودِ لبه ببرید و روی آن بکشید؛ همین است که خط بالا را به تن می‌چسباند.',
                    ],
                ],
            ],
        );

        return $this->finishSleepwear([$front, $back, $strap, $binding], $this->sleepNotes($params, [
            'دم لباس دست‌کم تا جایی باز شده که از باسن رد شود؛ لباس خوابِ راست پا را می‌بندد.',
            'خط بالا با نوار کوتاه‌تر از لبه تمام می‌شود؛ بدون آن، خط بالا از سینه جدا می‌ماند.',
        ]));
    }
}
