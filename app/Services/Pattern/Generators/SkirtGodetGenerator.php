<?php

namespace App\Services\Pattern\Generators;

/**
 * دامن گودت‌دار.
 *
 * تنه دامن ترک‌دارِ باریک است و موجِ دم دامن از قاچ‌های گودت می‌آید که در چاک
 * درزها دوخته می‌شوند. هر گودت یک قاچ دایره است به شعاعِ بلندی چاک و زاویه
 * θ = پهنای گودت ÷ بلندی گودت، پس:
 *   دور دم دامن = دور دم ترک‌ها + تعداد گودت × پهنای هر گودت
 */
class SkirtGodetGenerator extends SkirtBaseGenerator
{
    public static function key(): string
    {
        return 'skirt_godet';
    }

    public function label(): string
    {
        return 'دامن گودت‌دار';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->lengthParam(80, 45, 130),
            [
                'panels' => [
                    'label' => 'تعداد ترک', 'type' => 'select', 'default' => 6,
                    'options' => [4 => '۴ ترک', 6 => '۶ ترک', 8 => '۸ ترک'],
                ],
                'godet_count' => [
                    'label' => 'تعداد گودت', 'min' => 2, 'max' => 12, 'step' => 1, 'default' => 6,
                    'hint' => 'بیشتر از تعداد ترک نمی‌شود؛ هر گودت در یک درز می‌نشیند.',
                ],
                'godet_length' => [
                    'label' => 'بلندی گودت (چاک درز)', 'min' => 12, 'max' => 70, 'step' => 1, 'default' => 32,
                    'unit' => 'سانتی‌متر',
                ],
                'godet_width' => [
                    'label' => 'پهنای دم هر گودت', 'min' => 8, 'max' => 60, 'step' => 1, 'default' => 22,
                    'unit' => 'سانتی‌متر',
                ],
                'flare' => [
                    'label' => 'گشادی هر ترک', 'min' => 0, 'max' => 15, 'step' => 0.5, 'default' => 1,
                    'unit' => 'سانتی‌متر',
                ],
            ],
            $this->waistParams(0.5, 4),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $mx = $this->skirtMetrics($measurements, $ease, $params);
        $panels = in_array((int) $this->param($params, 'panels', 6), [4, 6, 8], true)
            ? (int) $this->param($params, 'panels', 6)
            : 6;
        $length = (float) $this->param($params, 'length', 80);
        $flare = (float) $this->param($params, 'flare', 1);
        $godets = max(1, min($panels, (int) $this->param($params, 'godet_count', 6)));
        $godetLength = min((float) $this->param($params, 'godet_length', 32), $length - $mx['hip_y'] - 4);
        $godetWidth = (float) $this->param($params, 'godet_width', 22);

        $waistW = $mx['waist_target'] / $panels;
        $hipW = $mx['hip_target'] / $panels;
        $hemW = $hipW + $flare;
        $panelHem = $panels * $hemW;

        $note = 'دور دم دامن '.$this->fa(round($panelHem + ($godets * $godetWidth), 1)).' سانتی‌متر است: '
            .$this->fa(round($panelHem, 1)).' سانتی‌متر دم ترک‌ها + '
            .$this->fa($godets).' گودت × '.$this->fa(round($godetWidth, 1)).' سانتی‌متر.';

        $base = [
            'waist_w' => $waistW,
            'hip_w' => $hipW,
            'hem_w' => $hemW,
            'length' => $length,
            'hip_y' => $mx['hip_y'],
            'slit' => $godetLength,
        ];

        $pieces = [
            $this->gorePanel(array_merge($base, [
                'half' => true, 'code' => 'gore-front', 'name' => 'ترک مرکز جلو',
                'part' => 'skirt_front', 'side' => 'front',
            ])),
            $this->gorePanel(array_merge($base, [
                'half' => true, 'code' => 'gore-back', 'name' => 'ترک مرکز پشت',
                'part' => 'skirt_back', 'side' => 'back',
                'meta' => ['notes' => [$note]],
            ])),
        ];

        if ($panels > 2) {
            $pieces[] = $this->gorePanel(array_merge($base, [
                'half' => false, 'cut_quantity' => $panels - 2, 'mirror' => true,
                'code' => 'gore-side', 'name' => 'ترک پهلو',
            ]));
        }

        $pieces[] = $this->godetPiece($godetLength, $godetWidth, [
            'cut_quantity' => $godets,
            'name' => 'گودت دم دامن',
        ]);

        return $this->finish(array_merge($pieces, $this->bandPieces($mx, $params)));
    }
}
