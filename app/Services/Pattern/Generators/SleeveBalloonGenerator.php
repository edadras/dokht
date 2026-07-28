<?php

namespace App\Services\Pattern\Generators;

/**
 * آستین بالونی.
 *
 * هم سرآستین و هم دم آستین باز می‌شود و درزهای پهلو به بیرون قوس می‌گیرند، پس
 * آستین وسطش باد می‌کند و دو سرش چین می‌خورد: بالا روی حلقه و پایین روی نوار مچ.
 */
class SleeveBalloonGenerator extends SleeveCatalogGenerator
{
    public static function key(): string
    {
        return 'sleeve_balloon';
    }

    public function label(): string
    {
        return 'آستین بالونی';
    }

    public function paramsSchema(): array
    {
        return $this->commonSleeveSchema(1.0, 45, 4) + [
            'puff' => [
                'label' => 'پف سرشانه', 'min' => 0, 'max' => 100, 'step' => 5, 'default' => 25, 'unit' => 'درصد',
            ],
            'hem_fullness' => [
                'label' => 'گشادی دم آستین', 'min' => 30, 'max' => 200, 'step' => 5, 'default' => 100,
                'unit' => 'درصد',
            ],
            'volume' => [
                'label' => 'باد کردن درز پهلو', 'min' => 0, 'max' => 12, 'step' => 0.5, 'default' => 4,
                'unit' => 'سانتی‌متر', 'hint' => 'قوس بیرونی درزها؛ حجم بالون از همین می‌آید.',
            ],
            'band_height' => [
                'label' => 'بلندی نوار دم آستین', 'min' => 1.5, 'max' => 8, 'step' => 0.5, 'default' => 3,
                'unit' => 'سانتی‌متر',
            ],
        ];
    }

    protected function sleeveSpec(array $m, array $ease, array $params): array
    {
        $puff = max(0.0, ((float) $this->param($params, 'puff', 25)) / 100);
        $fullness = max(0.2, ((float) $this->param($params, 'hem_fullness', 100)) / 100);
        $band = $this->m($m, 'elbow', 25.8) + (float) $this->param($params, 'hem_ease', 4);
        $hem = $band * (1 + $fullness);

        return [
            'code' => 'sleeve-balloon',
            'name' => 'آستین بالونی',
            'family' => 'balloon',
            'ease_band' => [2.0, 20.0],
            'cap_fullness' => $puff,
            'gather_cap' => $puff > 0.05,
            'hem_width' => $hem,
            'hem_gather' => $hem - $band,
            'side_bulge' => (float) $this->param($params, 'volume', 4),
            'side_bulge_at' => 0.5,
            'hem_drop' => 2.0,
            'band_width' => $band,
            'notes' => [
                'دم آستین '.round($hem - $band, 1).' سانتی‌متر روی نوار چین می‌خورد و حجم بالون '
                    .'از قوس بیرونی درزهای پهلو می‌آید.',
            ],
        ];
    }

    protected function sleevePieces(array $frame, array $m, array $ease, array $params, array $spec): array
    {
        return [
            $this->sleevePiece($frame, $spec),
            $this->sleeveCuffPiece(
                (float) $spec['band_width'],
                (float) $this->param($params, 'band_height', 3),
                ['code' => 'sleeve-balloon-band', 'name' => 'نوار دم آستین', 'overlap' => 2],
            ),
        ];
    }
}
