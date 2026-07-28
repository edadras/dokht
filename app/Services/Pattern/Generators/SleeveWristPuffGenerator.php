<?php

namespace App\Services\Pattern\Generators;

/**
 * آستین پفی مچ.
 *
 * برش و بازکردن با لولا روی سرآستین: سرآستین دست‌نخورده روی حلقه می‌نشیند و همه
 * گشادی به دم آستین می‌رود و آنجا روی یک نوار باریک چین می‌خورد.
 */
class SleeveWristPuffGenerator extends SleeveCatalogGenerator
{
    public static function key(): string
    {
        return 'sleeve_puff_wrist';
    }

    public function label(): string
    {
        return 'آستین پفی مچ';
    }

    public function paramsSchema(): array
    {
        return $this->commonSleeveSchema(1.5, 100, 4) + [
            'wrist_puff' => [
                'label' => 'مقدار پف مچ', 'min' => 20, 'max' => 150, 'step' => 5, 'default' => 60,
                'unit' => 'درصد', 'hint' => 'درصد بازشدن دم آستین نسبت به دور مچ.',
            ],
            'band_height' => [
                'label' => 'بلندی نوار مچ', 'min' => 1.5, 'max' => 8, 'step' => 0.5, 'default' => 2.5,
                'unit' => 'سانتی‌متر',
            ],
        ];
    }

    protected function sleeveSpec(array $m, array $ease, array $params): array
    {
        $puff = max(0.1, ((float) $this->param($params, 'wrist_puff', 60)) / 100);
        $band = $this->m($m, 'wrist', 16.5) + (float) $this->param($params, 'hem_ease', 4);
        $hem = $band * (1 + $puff);

        return [
            'code' => 'sleeve-wrist-puff',
            'name' => 'آستین پفی مچ',
            'family' => 'puff',
            'ease_band' => [1.5, 4.0],
            'hem_width' => $hem,
            'hem_gather' => $hem - $band,
            'side_bulge' => 1.6,
            'side_bulge_at' => 0.65,
            'hem_drop' => 1.2,
            'band_width' => $band,
            'notes' => [
                'دم آستین روی نوار مچ به اندازه '.round($band, 1).' سانتی‌متر چین می‌خورد.',
            ],
        ];
    }

    protected function sleevePieces(array $frame, array $m, array $ease, array $params, array $spec): array
    {
        return [
            $this->sleevePiece($frame, $spec),
            $this->sleeveCuffPiece(
                (float) $spec['band_width'],
                (float) $this->param($params, 'band_height', 2.5),
                ['code' => 'sleeve-wrist-band', 'name' => 'نوار مچ', 'overlap' => 3],
            ),
        ];
    }
}
