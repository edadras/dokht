<?php

namespace App\Services\Pattern\Generators;

/**
 * تانک‌تاپ.
 *
 * فرقش با کمیزول این است که بند سرخود است: سرشانه تا پهنای بند باریک می‌شود و
 * همان بند با تن یک‌تکه بریده می‌شود. برای همین اصلاً قطعهٔ بند ندارد، و برای
 * همین هم بندش کش نمی‌آید و در پرو کوتاه نمی‌شود — پهنا و جای بند باید همین
 * حالا درست باشد.
 *
 * جای بند از خط یقه اندازه می‌شود نه از نوک سرشانه: بندی که بیش از حد به بیرون
 * برود روی سر استخوان شانه می‌افتد و می‌لغزد.
 */
class TopTankGenerator extends TopBaseGenerator
{
    public static function key(): string
    {
        return 'top_tank';
    }

    public function label(): string
    {
        return 'تانک‌تاپ';
    }

    public function paramsSchema(): array
    {
        return $this->topSchema(array_merge(
            $this->strapParam(4, 'پهنای بند سرخود'),
            [
                'neck_drop' => [
                    'label' => 'گودی یقه جلو', 'min' => 0, 'max' => 24, 'step' => 0.5,
                    'default' => 6, 'unit' => 'سانتی‌متر',
                    'hint' => 'از یقهٔ بلوک پایه به پایین.',
                ],
                'back_neck_drop' => [
                    'label' => 'گودی یقه پشت', 'min' => 0, 'max' => 26, 'step' => 0.5,
                    'default' => 3, 'unit' => 'سانتی‌متر',
                ],
                'armhole_drop' => [
                    'label' => 'گودتر شدن حلقه', 'min' => 0, 'max' => 10, 'step' => 0.5,
                    'default' => 2, 'unit' => 'سانتی‌متر',
                    'hint' => 'حلقهٔ تاپ آستین ندارد، پس می‌تواند از بلوک پایه گودتر باشد.',
                ],
                'knit' => [
                    'label' => 'پارچه کشی است', 'type' => 'toggle', 'default' => true,
                ],
            ],
        ), length: 8);
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $knit = $this->flag($params, 'knit', true);
        $grow = $this->fitGrow($params, ['fitted' => 0.0, 'regular' => 1.0, 'loose' => 3.0]);
        $ease = $knit ? $this->knitEase($ease, 2.5) : $ease;

        $g = $this->blockMetrics($measurements, $ease, $params);

        $strap = (float) $this->param($params, 'strap_width', 4);
        $neckDrop = (float) $this->param($params, 'neck_drop', 6);
        $backNeckDrop = (float) $this->param($params, 'back_neck_drop', 3);
        $armholeDrop = (float) $this->param($params, 'armhole_drop', 2);

        // نوک سرشانه تا لبهٔ بیرونی بند عقب می‌آید: عرض یقه + پهنای بند
        $shoulderExtra = ($g['neck_width'] + $strap) - $g['shoulder_half'];

        $shared = [
            'shape' => $knit ? 'straight' : $this->fitShape($params),
            'length' => $this->bodyLength($params, $g, 8),
            'grow' => $grow,
            'bottom_tag' => 'hem',
            'waist_dart' => false,
            'shoulder_extra' => $shoulderExtra,
            'armhole_drop' => $armholeDrop,
            // شانهٔ باریک یعنی منحنی حلقه هم باید تو بیاید، وگرنه بیرون می‌زند
            'across_extra' => -min(4.0, $strap * 0.5),
        ];

        $front = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'front',
            'code' => 'tank-front',
            'name' => 'تانک‌تاپ جلو',
            'neck_depth_extra' => $neckDrop,
            'bust_dart' => ! $knit,
        ]));

        $back = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'back',
            'code' => 'tank-back',
            'name' => 'تانک‌تاپ پشت',
            'neck_depth_extra' => $backNeckDrop,
        ]));

        $notes = [
            $this->finishNote($params, ['یقه', 'حلقه']),
            ['type' => 'info', 'text' => 'بند سرخود '.$this->fa($strap).' سانتی‌متر است و کنار یقه می‌نشیند؛ اگر بیرون‌تر برود روی استخوان شانه می‌افتد و می‌لغزد.'],
        ];

        if ($knit) {
            $notes[] = ['type' => 'info', 'text' => 'الگو برای پارچهٔ کشی با آزادی منفی بریده شده؛ روی پارچهٔ بافته گزینهٔ «پارچه کشی» را خاموش کنید.'];
        }

        return $this->finishBlock($this->noted([$front, $back], $notes), $g, $grow);
    }
}
