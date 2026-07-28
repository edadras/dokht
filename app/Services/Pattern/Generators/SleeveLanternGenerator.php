<?php

namespace App\Services\Pattern\Generators;

/**
 * آستین فانوسی.
 *
 * دو تکه که در میانه آستین به هم دوخته می‌شوند: تکه بالا از بازو به بیرون باز
 * می‌شود و تکه پایین از همان جا دوباره جمع می‌شود، پس آستین شکل فانوس می‌گیرد.
 * لبه پایین تکه بالا و لبه بالای تکه پایین دقیقاً هم‌اندازه بریده می‌شوند.
 */
class SleeveLanternGenerator extends SleeveCatalogGenerator
{
    public static function key(): string
    {
        return 'sleeve_lantern';
    }

    public function label(): string
    {
        return 'آستین فانوسی';
    }

    public function paramsSchema(): array
    {
        return $this->commonSleeveSchema(1.5, 40, 4) + [
            'lantern_width' => [
                'label' => 'گشادی خط فانوس', 'min' => 10, 'max' => 90, 'step' => 5, 'default' => 40,
                'unit' => 'درصد', 'hint' => 'درصد گشادتر شدن آستین در خط دوخت میانی.',
            ],
            'seam_at' => [
                'label' => 'جای درز فانوس', 'min' => 30, 'max' => 80, 'step' => 5, 'default' => 55,
                'unit' => 'درصد بلندی آستین',
            ],
        ];
    }

    protected function sleeveSpec(array $m, array $ease, array $params): array
    {
        $flare = max(0.1, ((float) $this->param($params, 'lantern_width', 40)) / 100);
        $seamAt = max(0.2, min(0.85, ((float) $this->param($params, 'seam_at', 55)) / 100));

        return [
            'code' => 'sleeve-lantern-upper',
            'name' => 'آستین فانوسی (تکه بالا)',
            'family' => 'lantern',
            'ease_band' => [1.5, 4.0],
            'lantern_flare' => $flare,
            'seam_at' => $seamAt,
            'side_bulge' => 0.4,
        ];
    }

    protected function sleevePieces(array $frame, array $m, array $ease, array $params, array $spec): array
    {
        $width = (float) $frame['width'];
        $capHeight = (float) $frame['cap_height'];
        $length = (float) $frame['length'];
        $flare = (float) $spec['lantern_flare'];
        $seamAt = (float) $spec['seam_at'];

        $seamY = $capHeight + (($length - $capHeight) * $seamAt);
        $seamWidth = $width * (1 + $flare);

        // تکه بالا: از خط بازو تا خط فانوس باز می‌شود
        $upperFrame = array_merge($frame, [
            'length' => $seamY,
            'hem_width' => $seamWidth,
            'elbow_y' => min((float) $frame['elbow_y'], $seamY - 1),
        ]);

        $upper = $this->sleevePiece($upperFrame, array_merge($spec, [
            'code' => 'sleeve-lantern-upper',
            'name' => 'آستین فانوسی (تکه بالا)',
            'side_bulge' => 0.8,
            'side_bulge_at' => 0.6,
            'meta' => ['lantern_role' => 'upper', 'lantern_seam' => round($seamWidth, 2)],
            'notes' => [
                'لبه پایین این تکه '.round($seamWidth, 1).' سانتی‌متر است و به تکه پایین دوخته می‌شود.',
            ],
        ]));

        $hemWidth = (float) $frame['hem_width'];
        $lower = $this->sleevePanelPiece($seamWidth, $hemWidth, $length - $seamY, [
            'code' => 'sleeve-lantern-lower',
            'name' => 'آستین فانوسی (تکه پایین)',
            'part' => 'sleeve_lower',
            'top_tag' => 'default',
            'bulge' => 0.8,
            'meta' => [
                'lantern_role' => 'lower',
                'lantern_seam' => round($seamWidth, 2),
                'notes' => [
                    'لبه بالای این تکه هم‌اندازه لبه پایین تکه بالاست ('.round($seamWidth, 1).' سانتی‌متر) '
                        .'و دم آستین به '.round($hemWidth, 1).' سانتی‌متر جمع می‌شود.',
                ],
            ],
        ]);

        return [$upper, $lower];
    }
}
