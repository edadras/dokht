<?php

namespace App\Services\Pattern\Generators;

/**
 * برالت.
 *
 * سوتینی که نه فنر دارد و نه کاپِ قالبی: دو پنل پارچه که با نوار زیر سینه روی
 * تن می‌ایستند و از سر پوشیده می‌شوند.
 *
 * چون هیچ قطعهٔ قالب‌داری در کار نیست، فرم را فقط دو چیز می‌سازد و هر دو در الگو
 * هستند:
 *
 *   - **نوار زیر سینه.** محسوس‌تر از هر لبهٔ دیگری کوتاه بریده می‌شود؛ همهٔ وزن
 *     روی همین است. برای همین کشِ آن نسبت جدا دارد.
 *   - **آزادی منفی روی خودِ پنل.** برالت باید از خودِ دور سینه کوچک‌تر باشد، وگرنه
 *     بدون کاپ هیچ چیزی نگهش نمی‌دارد.
 *
 * آستر جلو هست ولی «کاپ» نیست: یک لایهٔ دوم هم‌شکل پنل جلو، برای اینکه پارچه در
 * نور نازک نشود. جیب کاپ عمداً گذاشته نشده؛ برالت با کاپِ جداشدنی دیگر برالت
 * نیست و باید نوار زیر سینهٔ محکم‌تری داشته باشد.
 */
class BraletteGenerator extends UnderwearBaseGenerator
{
    public static function key(): string
    {
        return 'bralette';
    }

    public function label(): string
    {
        return 'برالت';
    }

    public function paramsSchema(): array
    {
        return $this->underwearSchema([
            'band_height' => [
                'label' => 'بلندی نوار زیر سینه', 'min' => 2, 'max' => 8, 'step' => 0.5,
                'default' => 4, 'unit' => 'سانتی‌متر',
            ],
            'band_ratio' => [
                'label' => 'کوتاهی کش زیر سینه', 'min' => 0.7, 'max' => 0.95, 'step' => 0.01,
                'default' => 0.8,
                'hint' => 'کوتاه‌تر از بقیهٔ لبه‌ها؛ بدون کاپ و فنر، همهٔ وزن روی همین نوار است.',
            ],
            'neck_drop' => [
                'label' => 'گودی یقه جلو', 'min' => 2, 'max' => 20, 'step' => 0.5,
                'default' => 9, 'unit' => 'سانتی‌متر',
            ],
            'back_drop' => [
                'label' => 'گودی پشت', 'min' => 2, 'max' => 24, 'step' => 0.5,
                'default' => 12, 'unit' => 'سانتی‌متر',
            ],
            'strap_width' => [
                'label' => 'پهنای بند شانه', 'min' => 1, 'max' => 5, 'step' => 0.25,
                'default' => 2, 'unit' => 'سانتی‌متر',
            ],
            'front_lining' => [
                'label' => 'آستر جلو', 'type' => 'toggle', 'default' => true,
            ],
        ], stretch: 0.82);
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $ease = $this->negativeEaseFor($ease, $measurements, $params);
        $g = $this->blockMetrics($measurements, $ease, $params);

        $strap = (float) $this->param($params, 'strap_width', 2);
        $bandHeight = (float) $this->param($params, 'band_height', 4);
        $bandRatio = (float) $this->param($params, 'band_ratio', 0.8);

        // لبهٔ پایین برالت روی خط زیر سینه می‌ایستد، ولی هرگز بالاتر از چند
        // سانتی‌متر زیر خط زیر بغل نمی‌رود؛ وگرنه پنل آن‌قدر باریک می‌شود که
        // اصلاً دوخته نمی‌شود.
        $bottomY = max($g['bust_y'] + 6.0, $g['under_bust_y'] - ($bandHeight * 0.5));

        $shared = [
            'shape' => 'straight',
            'length' => $bottomY - $g['side_waist_y'],
            'bottom_tag' => 'hem',
            'waist_dart' => false,
            'shoulder_extra' => ($g['neck_width'] + $strap) - $g['shoulder_half'],
            'across_extra' => -min(4.0, $strap * 0.6),
            'armhole_drop' => 3.0,
        ];

        $front = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'front',
            'code' => 'bralette-front',
            'name' => 'برالت — جلو',
            'neck_depth_extra' => (float) $this->param($params, 'neck_drop', 9),
            'meta' => ['molded_cup' => false, 'underwire' => false],
        ]));

        $back = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'back',
            'code' => 'bralette-back',
            'name' => 'برالت — پشت',
            'neck_depth_extra' => (float) $this->param($params, 'back_drop', 12),
            'meta' => ['molded_cup' => false, 'underwire' => false],
        ]));

        $pieces = [];

        foreach ([$front, $back] as $piece) {
            $piece = $this->elasticOn($piece, 'neck', 'کش یقه — '.($piece['meta']['side'] === 'front' ? 'جلو' : 'پشت'), $params);
            $piece = $this->elasticOn($piece, 'armhole', 'کش حلقه — '.($piece['meta']['side'] === 'front' ? 'جلو' : 'پشت'), $params);
            $pieces[] = $piece;
        }

        $under = $this->underBust($measurements);
        $bandGirth = $under['value'] * $this->stretchOf($params);

        $band = $this->bandPiece(
            'bralette-band',
            'نوار زیر سینه',
            ($bandGirth * $bandRatio) / 2,
            $bandHeight,
            [
                'cut' => 2,
                'fold_line' => true,
                'part' => 'binding',
                'meta' => [
                    'girth_role' => 'trim',
                    'band_girth' => round($bandGirth, 2),
                    'notions' => [[
                        'type' => 'elastic',
                        'label' => 'کش زیر سینه',
                        'length' => round($bandGirth * $bandRatio, 1),
                        'edge_length' => round($bandGirth, 1),
                    ]],
                    'notes' => [
                        'دو تکه بریده می‌شود (جلو و پشت) و روی درز پهلو به هم می‌رسد.',
                        'کش زیر سینه '.$this->fa(round((1 - $bandRatio) * 100)).' درصد کوتاه‌تر از دور زیر سینه است؛'
                            .' برالت نه فنر دارد نه کاپ، پس همهٔ وزن روی همین نوار می‌افتد.',
                    ],
                ],
            ],
        );

        if ($under['estimated']) {
            $band['meta']['notes'][] = 'دور زیر سینه گرفته نشده و از دور سینه تخمین زده شده'
                .' ('.$this->fa(round($under['value'], 1)).' سانتی‌متر)؛ اگر نوار شل یا تنگ شد، همین یک عدد را اندازه بگیرید.';
            $band['meta']['under_bust_estimated'] = true;
        }

        $pieces[] = $band;

        if ($this->flag($params, 'front_lining', true)) {
            $lining = $front;
            $lining['code'] = 'bralette-front-lining';
            $lining['name'] = 'آستر جلو';
            $lining['layer'] = 'lining';
            $lining['meta']['part'] = 'lining';
            $lining['meta']['girth_role'] = 'lining';
            $lining['meta']['notions'] = [];
            $lining['meta']['notes'] = [
                'هم‌شکل و هم‌اندازهٔ پنل جلو؛ دو لایه با هم بریده و با هم دوخته می‌شوند.',
                'این لایه «کاپ» نیست و قالب ندارد؛ فقط نمی‌گذارد پارچه در نور نازک شود.',
            ];
            $pieces[] = $lining;
        }

        return $this->finishUnderwear($pieces, $this->underwearNotes($params, [
            'برالت نه فنر دارد و نه کاپِ قالبی؛ فرمش را نوار زیر سینه و تنگی خودِ پارچه می‌سازند.',
            'از سر پوشیده می‌شود و بستی ندارد که باز شود؛ پس دور زیر سینه باید از دور سینه رد شود.',
            $this->finishNote($params, ['یقه', 'حلقه'])['text'],
        ]));
    }
}
