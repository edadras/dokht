<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;
use App\Services\Pattern\Transform\FullnessRecorder;

/**
 * لباس گل‌دختر.
 *
 * تنها لباس این خانواده که روی بدن کودک ساخته می‌شود، و همین یک نکته تقریباً همهٔ
 * پیش‌فرض‌های خانواده را برعکس می‌کند:
 *
 *   بی‌تیغه       تیغهٔ فنر روی بدن کودک نه لازم است نه درست؛ بالاتنه بنددار است
 *                 و خودش روی شانه می‌ایستد.
 *   بی‌فنجان      کودک سینه ندارد؛ جای فنجان روی این لباس بی‌معناست.
 *   دکمهٔ پشت     بچه لباس را خودش نمی‌پوشد. بستِ پشت باید بلند و ساده باشد و
 *                 پیش از دوخت مطمئن شوید سرِ کودک از یقه رد می‌شود.
 *   دامن پُر      همان چیزی که این لباس را «گل‌دختر» می‌کند: دامن چین‌دار پُر روی
 *                 یک کمرِ بندی. زیرش تور می‌خورد تا بایستد.
 *
 * و یک نکتهٔ فنی که این لباس را می‌شکند: **خط کمرِ کودک بالاتر از خط کمرِ بزرگسال
 * است و اختلاف کمر و باسنش تقریباً هیچ است.** پس ساسون کمر عملاً درنمی‌آید و کمرِ
 * بالاتنه چند میلی‌متر پهن‌تر از حساب می‌شود؛ دامن با همان عددِ اندازه‌گیری‌شده
 * درفت می‌شود، نه با دور کمرِ بدن.
 */
class DressFlowerGirlGenerator extends EveningBaseGenerator
{
    public static function key(): string
    {
        return 'dress_flower_girl';
    }

    public function label(): string
    {
        return 'لباس گل‌دختر';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->fitParam(),
            $this->eveningSchema(
                array_merge($this->gownLengthParam(58, 'بلندی دامن از خط کمر'), [
                    'skirt_fullness' => [
                        'label' => 'نسبت پُری دامن', 'min' => 1.4, 'max' => 3, 'step' => 0.1,
                        'default' => 2.2,
                        'hint' => 'پارچهٔ دامن چند برابر دور کمر بریده شود؛ کمتر از یک و نیم دیگر «پُر» نیست.',
                    ],
                    'sash_width' => [
                        'label' => 'پهنای کمرِ بندی', 'min' => 3, 'max' => 10, 'step' => 0.5,
                        'default' => 6, 'unit' => 'سانتی‌متر',
                    ],
                    'tulle' => [
                        'label' => 'زیردامنی تور', 'type' => 'toggle', 'default' => true,
                    ],
                ]),
                [
                    'neckline' => 'strap', 'strap_width' => 5, 'bodice_length' => 'natural',
                    'boning' => false, 'bust_cups' => false, 'closure' => 'buttons', 'lining' => 'bodice',
                ],
            ),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        // روی بدن کودک نه فنر معنا دارد نه فنجان سینه
        $params['boning'] = false;
        $params['bust_cups'] = false;

        $ease = $this->gownEase($ease, $params);

        [$bodice, $waist] = $this->eveningBodice($measurements, $ease, $params, [
            'prefix' => 'flowergirl',
            'neck_drop' => 4,
            'back_drop' => 5,
        ]);

        // دامن با همان کمرِ اندازه‌گیری‌شدهٔ بالاتنه درفت می‌شود، نه با دور کمرِ بدن.
        // روی بدن کودک اختلاف این دو چند میلی‌متر است و همان چند میلی‌متر کافی است
        // که دو لبه به هم نرسند.
        $skirtEase = array_merge($ease, [
            'waist' => $waist - $this->m($measurements, 'waist', 74),
        ]);

        $skirt = $this->gownSkirt('skirt_gathered', $measurements, $skirtEase, [
            'length' => (float) $this->param($params, 'skirt_length', 58),
            'fullness' => (float) $this->param($params, 'skirt_fullness', 2.2),
            'dart_share' => 0,
        ], 'flowergirl');

        [$skirt, $waistNotes] = $this->joinWaist($skirt, $waist);

        $pieces = array_merge($bodice, $skirt);

        // کمرِ بندی: نوار پهن دور کمر که پشت به پاپیون گره می‌خورد
        $sash = (float) $this->param($params, 'sash_width', 6);

        $pieces[] = $this->bandPiece('flowergirl-sash', 'کمرِ بندی (پاپیون پشت)', ($waist * 0.6) + 55, $sash * 2, [
            'cut' => 2, 'part' => 'belt', 'fold_line' => true,
            'meta' => [
                'girth_role' => 'trim',
                'finished_width' => round($sash, 2),
                'notes' => [
                    'دو تکه از پهلو به پشت می‌رود و پشت به پاپیون گره می‌خورد؛ روی درز کمر دوخته می‌شود نه روی دامن.',
                    'عمداً بلند بریده شده است؛ پاپیون کوچک روی لباس کودک گم می‌شود.',
                ],
            ],
        ]);

        if ($this->flag($params, 'tulle', true)) {
            // زیردامنی تور: مستطیل چین‌خورده‌ای که دامن روی آن می‌ایستد. جزو دور
            // کمرِ تمام‌شده حساب نمی‌شود، پس نقشش آستر است.
            $length = max(20.0, (float) $this->param($params, 'skirt_length', 58) - 4);

            $tulle = $this->bandPiece('flowergirl-tulle', 'زیردامنی تور', max(30.0, $waist * 1.6), $length, [
                'cut' => 2, 'part' => 'lining', 'layer' => 'lining',
                'meta' => [
                    'girth_role' => 'lining',
                    'notes' => [
                        'دو مستطیل تور که روی کمر چین می‌خورند و به آستر دوخته می‌شوند؛ بدون آن، دامن پُر روی تن می‌خوابد.',
                        'چهار سانتی‌متر کوتاه‌تر از دامن است تا از زیر لبه بیرون نزند.',
                    ],
                ],
            ]);

            $tulle['meta']['edges'] = ['waist', 'side', 'hem', 'side'];

            // چین تور هم مثل هر چین دیگری در meta.gathers ثبت می‌شود، وگرنه پهنای
            // خام پارچه‌اش اندازهٔ کمر شمرده می‌شود
            $pieces[] = FullnessRecorder::gathers(
                $tulle,
                0,
                round(max(1.0, ($waist * 1.6) - ($waist / 2)), 2),
                ['label' => 'چین کمر تور'],
            );
        }

        [$pieces, $closureNotes] = $this->gownClosure($pieces, $params, $measurements);
        $pieces = $this->gownLining($pieces, $params);

        $height = 0.0;

        foreach ($bodice as $piece) {
            if (($piece['meta']['part'] ?? '') === 'back_bodice') {
                $height = max($height, Geometry::height($piece['outline']));
            }
        }

        $notes = array_merge($waistNotes, $closureNotes, $this->gownNotes($params), [
            'روی بدن کودک نه تیغهٔ فنر لازم است نه جای فنجان سینه؛ هر دو از این مدل برداشته شده‌اند.',
            'بستِ پشت '.$this->fa(round($height, 1)).' سانتی‌متر بالاتنه را باز می‌کند؛ پیش از دوخت مطمئن شوید سرِ کودک از دهانهٔ یقه رد می‌شود.',
            'دور یقه و حلقه با نوار اریب تمام می‌شود، نه با لبهٔ خام؛ لبهٔ خامِ تور روی پوست کودک می‌خارد.',
            'دامن '.$this->fa(round((float) $this->param($params, 'skirt_fullness', 2.2), 1))
                .' برابر دور کمر بریده و روی کمر چین می‌شود؛ همین است که لباس را «گل‌دختر» می‌کند.',
        ]);

        return $this->finish($this->noted($pieces, array_map(
            fn (string $t) => ['type' => 'info', 'text' => $t],
            $notes,
        )));
    }
}
