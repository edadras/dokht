<?php

namespace App\Services\Pattern\Generators;

/**
 * بندو / تاپ استرپلس (تیوب).
 *
 * تاپی که هیچ بندی ندارد و فقط با چسبیدن به تن سر جایش می‌ماند. برای همین
 * تنها مدلی از این گروه است که آزادی‌اش پیش‌فرض منفی است: اگر گشاد باشد
 * می‌افتد.
 *
 * دو راه نگه‌داشتن دارد و هر دو در الگو هست:
 *
 *   کشی    پارچهٔ کشباف با آزادی منفی و یک نوار کش در لبهٔ بالا
 *   بافته  پارچهٔ بافته با ساسون سینه و لایی در لبهٔ بالا؛ این‌جا لبهٔ بالا
 *          باید کوتاه‌تر از دور بالای سینه باشد وگرنه پایین می‌سُرد
 *
 * خط بالا می‌تواند صاف یا قلبی باشد. قلبی فقط تزیین نیست: برجستگی روی سینه
 * جا می‌دهد و همان چیزی است که نگذاشتن ساسون را ممکن می‌کند.
 */
class TopBandeauGenerator extends TopBaseGenerator
{
    public static function key(): string
    {
        return 'top_bandeau';
    }

    public function label(): string
    {
        return 'بندو (تاپ استرپلس)';
    }

    public function paramsSchema(): array
    {
        return $this->topSchema(array_merge(
            [
                'fabric' => [
                    'label' => 'جنس پارچه', 'type' => 'select', 'default' => 'knit',
                    'options' => ['knit' => 'کشی (کشباف)', 'woven' => 'بافته (غیرکشی)'],
                ],
                'negative_ease' => [
                    'label' => 'آزادی منفی', 'min' => 0, 'max' => 12, 'step' => 0.5,
                    'default' => 4, 'unit' => 'سانتی‌متر',
                    'hint' => 'بندو باید تنگ باشد؛ روی پارچهٔ پرکشش عدد بزرگ‌تر بگذارید.',
                ],
            ],
            $this->topLineParam(2, 'گودی خط بالا از زیر بغل'),
            [
                'top_shape' => [
                    'label' => 'شکل خط بالا', 'type' => 'select', 'default' => 'straight',
                    'options' => ['straight' => 'صاف', 'sweetheart' => 'قلبی'],
                ],
                'top_elastic' => [
                    'label' => 'نوار کش لبهٔ بالا', 'type' => 'toggle', 'default' => true,
                ],
            ],
        ), length: 0);
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $knit = $this->param($params, 'fabric', 'knit') === 'knit';
        $negative = $knit ? max(0.0, (float) $this->param($params, 'negative_ease', 4)) : 0.0;
        $ease = $this->knitEase($ease, $negative);

        $g = $this->blockMetrics($measurements, $ease, $params);
        /*
         * بندو هم مثل آف‌شولدر از خطِ سینه شروع می‌شود؛ قدِ کراپ رویش یعنی
         * نوارِ چندسانتی‌متری. کفِ قد از خودِ اندازه‌ها می‌آید.
         */
        $length = $this->bodyLength($params, $g, 0, clearance: 18.0);
        $drop = (float) $this->param($params, 'top_drop', 2);

        $shared = [
            'shape' => $knit ? 'straight' : 'fitted',
            'length' => $length,
            'bottom_tag' => 'hem',
            'waist_dart' => false,
        ];

        $front = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'front',
            'code' => 'bandeau-front',
            'name' => 'بندو جلو',
            'bust_dart' => ! $knit,
        ]));

        $back = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'back',
            'code' => 'bandeau-back',
            'name' => 'بندو پشت',
        ]));

        $frontTop = (float) ($front['meta']['bust_y'] ?? 20) - $drop;
        $backTop = (float) ($back['meta']['bust_y'] ?? 20) - $drop;
        $sideTop = min($frontTop, $backTop) + 1.0;

        $front = $this->cutTop($front, [
            'center' => $frontTop,
            'side' => $sideTop,
            'shape' => (string) $this->param($params, 'top_shape', 'straight'),
            'apex' => 0.62,
        ]);

        $back = $this->cutTop($back, [
            'center' => $backTop,
            'side' => $sideTop,
            'shape' => 'straight',
        ]);

        $pieces = [$front, $back];
        $notes = [$this->finishNote($params, ['خط بالا', 'دم تاپ'])];

        if ($this->flag($params, 'top_elastic', true)) {
            $notes[] = ['type' => 'info', 'text' => 'کش لبهٔ بالا حدود ۱۰ تا ۱۵ درصد کوتاه‌تر از خودِ لبه بریده می‌شود؛ همین کوتاهی است که تاپ را بالا نگه می‌دارد.'];
        }

        $notes[] = $knit
            ? ['type' => 'info', 'text' => 'الگو '.$this->fa($negative).' سانتی‌متر از دور بدن کوچک‌تر بریده شده و پارچه با کش آمدن روی تن می‌نشیند.']
            : ['type' => 'warning', 'text' => 'روی پارچهٔ بافته، لبهٔ بالا حتماً لایی بخورد و بهتر است دو تیغهٔ فنر در درز پهلو گذاشته شود؛ وگرنه بندو پایین می‌سُرد.'];

        return $this->finishBlock($this->noted($pieces, $notes), $g);
    }
}
