<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * تاپ پشت‌باز.
 *
 * جلو پوشیده و پشت تا نزدیک کمر باز است. برخلاف چیزی که به نظر می‌رسد، سختی
 * این مدل در پشت نیست، در جلوست: وقتی پشت باز شد، دیگر هیچ درزی جلو را نگه
 * نمی‌دارد جز بند گردن و درز پهلو. پس جلو باید دقیق به تن بخورد و آزادی‌اش کم
 * باشد، وگرنه از کنار باز می‌ماند.
 *
 * سه چیز که در الگو حساب شده‌اند:
 *
 *   - ساسون سینه پیش‌فرض روشن است؛ بدون آن جلو زیر سینه فاصله می‌گیرد.
 *   - لبهٔ باز پشت روی اریب پارچه می‌افتد و کش می‌آید، پس نوار تقویتی خواسته
 *     می‌شود.
 *   - بند دور کمر (اختیاری) وزن را از گردن برمی‌دارد؛ در تاپ پشت‌باز بلند،
 *     نبودنش یعنی همهٔ وزن روی گردن.
 */
class TopBacklessGenerator extends TopBaseGenerator
{
    public static function key(): string
    {
        return 'top_backless';
    }

    public function label(): string
    {
        return 'تاپ پشت‌باز';
    }

    public function paramsSchema(): array
    {
        return $this->topSchema(array_merge(
            [
                'back_open' => [
                    'label' => 'بازی پشت', 'min' => 6, 'max' => 45, 'step' => 1,
                    'default' => 26, 'unit' => 'سانتی‌متر',
                    'hint' => 'از خط سرشانه به پایین؛ ۲۶ یعنی تقریباً تا زیر تیغهٔ شانه.',
                ],
                'back_shape' => [
                    'label' => 'شکل پشت', 'type' => 'select', 'default' => 'straight',
                    'options' => ['straight' => 'صاف', 'scoop' => 'گرد (U)', 'sweetheart' => 'هفتِ برعکس'],
                ],
            ],
            $this->strapParam(3, 'پهنای بند گردن'),
            [
                'waist_tie' => [
                    'label' => 'بند دور کمر', 'type' => 'toggle', 'default' => true,
                ],
                'neck_drop' => [
                    'label' => 'گودی یقه جلو', 'min' => 0, 'max' => 20, 'step' => 0.5,
                    'default' => 3, 'unit' => 'سانتی‌متر',
                ],
            ],
        ), length: 6);
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $grow = $this->fitGrow($params, ['fitted' => -1.0, 'regular' => 0.0, 'loose' => 1.5]);
        $g = $this->blockMetrics($measurements, $ease, $params);

        $strap = (float) $this->param($params, 'strap_width', 3);
        $open = (float) $this->param($params, 'back_open', 26);

        $shared = [
            'shape' => $this->fitShape($params, ['fitted' => 'fitted', 'regular' => 'fitted', 'loose' => 'fitted']),
            /*
             * تاپِ پشت‌باز کفِ قدِ بلندتری از باقیِ تاپ‌ها می‌خواهد.
             *
             * پشتش از سرشانه تا نزدیکِ کمر بریده می‌شود؛ اگر خودِ لباس کوتاه
             * باشد، آن‌چه از پنلِ پشت می‌ماند نواری چندسانتی‌متری است که خطِ برشِ
             * گرد یا قلبی رویش جا نمی‌شود و مسیرِ قطعه خودش را قطع می‌کند. روی
             * تنِ کودک با قدِ کراپ دقیقاً همین می‌شد.
             */
            'length' => $this->bodyLength($params, $g, 6, clearance: 18.0),
            'grow' => $grow,
            'bottom_tag' => 'hem',
            'waist_dart' => true,
            'armhole_drop' => 1.5,
        ];

        $front = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'front',
            'code' => 'backless-front',
            'name' => 'تاپ پشت‌باز — جلو',
            'bust_dart' => true,
            'neck_depth_extra' => (float) $this->param($params, 'neck_drop', 3),
            'shoulder_extra' => ($g['neck_width'] + $strap) - $g['shoulder_half'],
            'across_extra' => -3.5,
        ]));

        $back = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'back',
            'code' => 'backless-back',
            'name' => 'تاپ پشت‌باز — پشت',
        ]));

        /*
         * پشت از خط سرشانه به اندازهٔ «بازی پشت» پایین بریده می‌شود — ولی نه
         * پایین‌تر از خودِ قطعه.
         *
         * بازیِ پشت عددی ثابت است (پیش‌فرض ۲۶ سانتی‌متر) و قدِ تاپ می‌تواند
         * کراپ باشد. روی تنِ کوچک، بیست‌وشش سانتی‌متر از سرشانه از دمِ لباس هم
         * می‌گذرد: مسیرِ برش از قطعه بیرون می‌زند و پشتِ تاپ خودش را قطع می‌کند.
         * پس بازی همیشه دست‌کم چهار سانتی‌متر بالای دمِ قطعه می‌ایستد.
         */
        $shoulderY = (float) ($g['shoulder_drop'] ?? 4);
        $backHem = Geometry::bounds($back['outline'])[3];
        $backTop = min($shoulderY + $open, $backHem - 4.0);

        $back = $this->cutTop($back, [
            'center' => $backTop,
            // جلو بریده نشده و درز پهلویش از زیر بغل شروع می‌شود؛ پشت هم باید
            // همان‌جا به درز پهلو برسد تا دو درز هم‌اندازه بمانند
            'side' => (float) ($front['meta']['bust_y'] ?? $shoulderY + 4),
            'shape' => (string) $this->param($params, 'back_shape', 'straight'),
            'apex' => 0.5,
        ]);

        $pieces = [$front, $back];

        if ($this->flag($params, 'waist_tie', true)) {
            $waist = (float) ($g['waist'] ?? 70);

            $pieces[] = $this->strapPiece($waist + 60, 2.5, [
                'code' => 'backless-waist-tie',
                'name' => 'بند دور کمر',
                'cut' => 1,
                'meta' => ['notes' => ['بند دور کمر پشت گره می‌خورد و بخشی از وزن لباس را از گردن برمی‌دارد.']],
            ]);
        }

        $notes = [
            $this->finishNote($params, ['لبهٔ باز پشت', 'حلقه']),
            ['type' => 'warning', 'text' => 'لبهٔ باز پشت روی اریب پارچه می‌افتد و کش می‌آید؛ پیش از دوخت یک نوار تقویتی (نوار لایی یا نوار پارچهٔ راستا) رویش بچسبانید.'],
            ['type' => 'info', 'text' => 'جلو عمداً جذب‌تر از بلوک معمولی بریده شده؛ در تاپ پشت‌باز هیچ درزی جز پهلو جلو را نگه نمی‌دارد.'],
        ];

        if (! $this->flag($params, 'waist_tie', true)) {
            $notes[] = ['type' => 'warning', 'text' => 'بدون بند دور کمر، همهٔ وزن لباس روی بند گردن می‌افتد.'];
        }

        return $this->finishBlock($this->noted($pieces, $notes), $g, $grow);
    }
}
