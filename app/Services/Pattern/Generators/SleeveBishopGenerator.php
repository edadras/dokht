<?php

namespace App\Services\Pattern\Generators;

/**
 * آستین اسقفی (بیشاپ).
 *
 * آستین بلند و آزاد که همه گشادی‌اش پایین جمع می‌شود و روی مچ‌بند دکمه‌دار چین
 * می‌خورد. کمی بلندتر از قد آستین بریده می‌شود تا روی مچ‌بند بخوابد و چین بیفتد.
 */
class SleeveBishopGenerator extends SleeveCatalogGenerator
{
    public static function key(): string
    {
        return 'sleeve_bishop';
    }

    public function label(): string
    {
        return 'آستین اسقفی با مچ‌بند';
    }

    public function paramsSchema(): array
    {
        return $this->commonSleeveSchema(1.5, 105, 4) + [
            'hem_fullness' => [
                'label' => 'گشادی دم آستین', 'min' => 30, 'max' => 180, 'step' => 5, 'default' => 90,
                'unit' => 'درصد', 'hint' => 'نسبت به دور مچ‌بند؛ همین مقدار چین می‌شود.',
            ],
            'cuff_height' => [
                'label' => 'بلندی مچ‌بند', 'min' => 3, 'max' => 12, 'step' => 0.5, 'default' => 6,
                'unit' => 'سانتی‌متر',
            ],
            'blouson' => [
                'label' => 'خوابیدن روی مچ‌بند', 'min' => 0, 'max' => 8, 'step' => 0.5, 'default' => 3,
                'unit' => 'سانتی‌متر', 'hint' => 'بلندی اضافه‌ای که روی مچ‌بند چین می‌خورد.',
            ],
        ];
    }

    protected function sleeveSpec(array $m, array $ease, array $params): array
    {
        $fullness = max(0.2, ((float) $this->param($params, 'hem_fullness', 90)) / 100);
        $cuffHeight = (float) $this->param($params, 'cuff_height', 6);
        $blouson = (float) $this->param($params, 'blouson', 3);
        $band = $this->m($m, 'wrist', 16.5) + (float) $this->param($params, 'hem_ease', 4);
        $hem = $band * (1 + $fullness);
        $armLength = $this->m($m, 'arm_length', 58);

        return [
            'code' => 'sleeve-bishop',
            'name' => 'آستین اسقفی',
            'family' => 'bishop',
            'ease_band' => [1.5, 4.0],
            'length' => ($armLength * ((float) $this->param($params, 'length_percent', 105)) / 100)
                + (float) $this->param($params, 'length_extra', 0) + $blouson - $cuffHeight,
            'hem_width' => $hem,
            'hem_gather' => $hem - $band,
            'side_bulge' => 2.2,
            'side_bulge_at' => 0.7,
            'hem_drop' => 1.5,
            'band_width' => $band,
            'cuff_height' => $cuffHeight,
            'notes' => [
                'دم آستین به اندازه '.round($hem - $band, 1).' سانتی‌متر روی مچ‌بند چین می‌خورد؛ '
                    .'چین‌ها را بیشتر پشت آستین جمع کنید تا روی مچ خوش‌حالت بیفتد.',
                'چاک مچ را روی درز پشت آستین بگذارید.',
            ],
        ];
    }

    protected function sleevePieces(array $frame, array $m, array $ease, array $params, array $spec): array
    {
        return [
            $this->sleevePiece($frame, $spec),
            $this->sleeveCuffPiece(
                (float) $spec['band_width'],
                (float) $spec['cuff_height'],
                ['code' => 'sleeve-bishop-cuff', 'name' => 'مچ‌بند آستین اسقفی'],
            ),
        ];
    }
}
