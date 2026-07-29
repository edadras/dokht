<?php

namespace App\Services\Pattern\Generators;

/**
 * دامن پرنسسی (بال‌گان) با زیردامن حجم‌دهنده.
 *
 * حجم این دامن از پارچه نمی‌آید، از زیرش می‌آید. دامن رو فقط باید آن‌قدر پارچه
 * داشته باشد که روی زیردامن بیفتد؛ اگر خودِ دامن رو را پُر کنیم، سنگین می‌شود و
 * به‌جای ایستادن، می‌افتد.
 *
 * پس این بلوک دو چیز می‌سازد:
 *
 *   دامن رو    ترک‌های گشاد که دور دم‌شان با دور دم زیردامن جور است.
 *   زیردامن    دو یا سه طبقهٔ چین‌دار که هرچه پایین‌تر پُرتر می‌شوند؛ همین
 *              پلکان است که شکل زنگوله می‌سازد.
 *
 * دور دم زیردامن از خودِ قد دامن حساب می‌شود، نه از عددی ثابت: زنگولهٔ کوتاه
 * که به اندازهٔ زنگولهٔ بلند باز شود، شکلش می‌شود چتر.
 */
class SkirtBallGownGenerator extends SkirtBaseGenerator
{
    public static function key(): string
    {
        return 'skirt_ball_gown';
    }

    public function label(): string
    {
        return 'دامن پرنسسی (بال‌گان)';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->lengthParam(100, 60, 140),
            [
                'panels' => [
                    'label' => 'تعداد ترک دامن رو', 'min' => 4, 'max' => 12, 'step' => 2, 'default' => 8,
                ],
                'volume' => [
                    'label' => 'حجم زنگوله', 'type' => 'select', 'default' => 'medium',
                    'options' => [
                        'soft' => 'ملایم (دو برابر دور باسن)',
                        'medium' => 'متوسط (سه برابر)',
                        'grand' => 'پرحجم (چهار برابر)',
                    ],
                ],
                'petticoat' => [
                    'label' => 'زیردامن حجم‌دهنده ساخته شود', 'type' => 'toggle', 'default' => true,
                ],
                'petticoat_tiers' => [
                    'label' => 'تعداد طبقهٔ زیردامن', 'min' => 2, 'max' => 4, 'step' => 1, 'default' => 3,
                ],
                'horsehair' => [
                    'label' => 'نوار مو (کرینولین) در دم', 'type' => 'toggle', 'default' => true,
                ],
            ],
            $this->waistParams(0.6, 4, true, 'back'),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $mx = $this->skirtMetrics($measurements, $ease, $params);
        $length = (float) $this->param($params, 'length', 100);
        $panels = max(4, (int) $this->param($params, 'panels', 8));

        $multiplier = match ((string) $this->param($params, 'volume', 'medium')) {
            'soft' => 2.0,
            'grand' => 4.0,
            default => 3.0,
        };

        // زنگولهٔ کوتاه نباید به اندازهٔ زنگولهٔ بلند باز شود
        $reach = min(1.0, $length / 100);
        $hemGirth = $mx['hip_target'] * $multiplier * (0.65 + (0.35 * $reach));

        $pieces = [];
        $goreWaist = $mx['waist_target'] / $panels;
        $goreHip = $mx['hip_target'] / $panels;
        $goreHem = $hemGirth / $panels;

        $pieces[] = $this->gorePanel([
            'waist_w' => $goreWaist,
            'hip_w' => $goreHip,
            'hem_w' => $goreHem,
            'length' => $length,
            'hip_y' => $mx['hip_y'],
            'cut_quantity' => $panels,
            'code' => 'ballgown-gore',
            'name' => 'ترک دامن',
        ]);

        if ($this->flag($params, 'petticoat', true)) {
            $tiers = max(2, min(4, (int) $this->param($params, 'petticoat_tiers', 3)));
            $tierLength = ($length - 6) / $tiers;
            $previous = $mx['hip_target'] + 6;

            for ($i = 0; $i < $tiers; $i++) {
                // هر طبقه پُرتر از طبقهٔ بالای خودش؛ آخری به دور دم زنگوله می‌رسد
                $target = $mx['hip_target'] + (($hemGirth - $mx['hip_target']) * (($i + 1) / $tiers));
                $width = max($previous * 1.4, $target);

                $pieces[] = $this->rectPanel([
                    'side' => 'front',
                    'part' => 'lining',
                    'code' => 'petticoat-'.($i + 1),
                    'name' => 'زیردامن — طبقه '.$this->fa($i + 1),
                    'width' => $width / 2,
                    'length' => $tierLength,
                    'cut_quantity' => 2,
                    'on_fold' => false,
                    'top_edge' => $i === 0 ? 'waist' : 'default',
                    'fullness' => [
                        $this->fullness('gather', 0, $width / 2, $previous / 2, [
                            'label' => 'چین طبقهٔ زیردامن',
                        ]),
                    ],
                    'notes' => [
                        'طبقه '.$this->fa($i + 1).' زیردامن: '.$this->fa(round($width))
                            .' سانتی‌متر پارچه روی '.$this->fa(round($previous)).' سانتی‌متر.',
                    ],
                ]);

                $previous = $width;
            }
        }

        $pieces = array_merge($pieces, $this->bandPieces($mx, $params));

        $notes = [
            'حجم از زیردامن می‌آید نه از دامن رو؛ دامن رو فقط باید روی آن بیفتد.',
        ];

        if ($this->flag($params, 'horsehair', true)) {
            $notes[] = 'نوار مو (کرینولین) به بلندی '.$this->fa(round($hemGirth))
                .' سانتی‌متر در دم زیردامن دوخته می‌شود؛ همین است که دم را باز نگه می‌دارد.';

            $pieces[0]['meta']['notions'] = [[
                'type' => 'other',
                'label' => 'نوار مو (کرینولین) پنج سانتی‌متری',
                'length' => round($hemGirth, 1),
            ]];
        }

        $pieces[0]['meta']['notes'] = array_merge($pieces[0]['meta']['notes'] ?? [], $notes);

        return $this->finishSkirt($pieces, $params);
    }
}
