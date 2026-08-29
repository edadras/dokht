<?php

namespace App\Services\Pattern\Generators;

/**
 * تاپ آفشولدر (باردو).
 *
 * خط بالا از روی سرشانه پایین‌تر می‌افتد و دور بازو می‌نشیند. از بیرون شبیه
 * بندو است، ولی از نظر الگو نیست: بندو دور سینه را می‌گیرد و آفشولدر دور بازو
 * را، و دور بازو با حرکت دست عوض می‌شود.
 *
 * همین یک تفاوت، دو تصمیم را ناگزیر می‌کند و هر دو در الگو هست:
 *
 *   ۱. خط بالا باید کشسان باشد (کش یا کشباف)، وگرنه بالا رفتن دست لباس را
 *      پایین می‌کشد.
 *   ۲. پهنای خط بالا از دور سینه گرفته نمی‌شود؛ از دور بازو و فاصلهٔ دو بازو
 *      حساب می‌شود، وگرنه لباس یا می‌افتد یا بازو را می‌بندد.
 *
 * نوار چین‌خوردهٔ لبهٔ بالا (والان) اختیاری است و همان چیزی است که این مدل را
 * از یک بندوِ افتاده جدا می‌کند.
 */
class TopOffShoulderGenerator extends TopBaseGenerator
{
    public static function key(): string
    {
        return 'top_off_shoulder';
    }

    public function label(): string
    {
        return 'تاپ آفشولدر (باردو)';
    }

    public function paramsSchema(): array
    {
        return $this->topSchema(array_merge(
            $this->topLineParam(-2, 'جای خط بالا نسبت به زیر بغل'),
            [
                'band_height' => [
                    'label' => 'بلندی نوار لبهٔ بالا', 'min' => 1.5, 'max' => 10, 'step' => 0.5,
                    'default' => 3, 'unit' => 'سانتی‌متر',
                ],
                'band_stretch' => [
                    'label' => 'کوتاهی نوار نسبت به لبه', 'min' => 0.7, 'max' => 1, 'step' => 0.05,
                    'default' => 0.88,
                    'hint' => 'هرچه کمتر، نوار سفت‌تر می‌چسبد. کمتر از ۰٫۸ روی بازو فشار می‌آورد.',
                ],
                'ruffle' => [
                    'label' => 'والان روی خط بالا', 'type' => 'toggle', 'default' => false,
                ],
                'ruffle_depth' => [
                    'label' => 'بلندی والان', 'min' => 4, 'max' => 25, 'step' => 1,
                    'default' => 9, 'unit' => 'سانتی‌متر',
                ],
            ],
        ), length: 4);
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $ease = $this->knitEase($ease, 2.0);
        $g = $this->blockMetrics($measurements, $ease, $params);

        $drop = (float) $this->param($params, 'top_drop', -2);
        $bandHeight = (float) $this->param($params, 'band_height', 3);
        $stretch = (float) $this->param($params, 'band_stretch', 0.88);

        $shared = [
            'shape' => 'straight',
            /*
             * آف‌شولدر از خطِ سینه شروع می‌شود، نه از سرشانه، پس کوتاه‌کردنش
             * زودتر از هر تاپِ دیگری به ته می‌رسد: با قدِ کراپ چیزی که می‌ماند
             * نواری است که نه دوختنی است نه پوشیدنی. کفِ قدش را بالاتر
             * می‌گذاریم تا همیشه یک تنهٔ واقعی بماند.
             */
            'length' => $this->bodyLength($params, $g, 4, clearance: 20.0),
            'bottom_tag' => 'hem',
            'waist_dart' => false,
        ];

        $front = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'front', 'code' => 'bardot-front', 'name' => 'آفشولدر جلو',
        ]));

        $back = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'back', 'code' => 'bardot-back', 'name' => 'آفشولدر پشت',
        ]));

        $frontTop = (float) ($front['meta']['bust_y'] ?? 20) - $drop;
        $backTop = (float) ($back['meta']['bust_y'] ?? 20) - $drop;

        $sideTop = min($frontTop, $backTop);

        $front = $this->cutTop($front, ['center' => $frontTop, 'side' => $sideTop, 'shape' => 'straight']);
        $back = $this->cutTop($back, ['center' => $backTop, 'side' => $sideTop, 'shape' => 'straight']);

        $edge = $this->panelWidthAt($front, $frontTop + 0.6) + $this->panelWidthAt($back, $backTop + 0.6);
        $edge = max(30.0, $edge * 2);

        $pieces = [$front, $back];

        $pieces[] = $this->bandPiece(
            'bardot-band',
            'نوار لبهٔ بالا',
            max(20.0, $edge * $stretch),
            $bandHeight * 2,
            [
                'cut' => 1, 'on_fold' => true, 'fold_line' => true, 'part' => 'binding',
                'meta' => [
                    'rib' => true,
                    'stretch_ratio' => $stretch,
                    'target_edge' => round($edge, 2),
                    'girth_role' => 'trim',
                ],
            ],
        );

        if ($this->flag($params, 'ruffle', false)) {
            $depth = (float) $this->param($params, 'ruffle_depth', 9);

            $pieces[] = $this->bandPiece(
                'bardot-ruffle',
                'والان خط بالا',
                max(30.0, $edge * 1.8),
                $depth,
                [
                    'cut' => 1, 'part' => 'trim',
                    'meta' => [
                        'gather_ratio' => 1.8,
                        'target_edge' => round($edge, 2),
                        'girth_role' => 'trim',
                        'notes' => ['والان یک‌ونیم‌برابر تا دوبرابر لبه بریده می‌شود؛ همین اضافه است که چین می‌خورد.'],
                    ],
                ],
            );
        }

        $notes = [
            $this->finishNote($params, ['خط بالا']),
            ['type' => 'warning', 'text' => 'خط بالای آفشولدر روی بازو می‌نشیند نه روی سینه؛ حتماً کشسان باشد وگرنه با بالا رفتن دست، لباس پایین کشیده می‌شود.'],
            ['type' => 'info', 'text' => 'نوار لبهٔ بالا '.$this->fa(round((1 - $stretch) * 100)).' درصد کوتاه‌تر از خودِ لبه بریده شده و کشیده دوخته می‌شود.'],
        ];

        return $this->finishBlock($this->noted($pieces, $notes), $g);
    }
}
