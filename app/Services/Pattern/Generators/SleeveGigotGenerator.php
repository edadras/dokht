<?php

namespace App\Services\Pattern\Generators;

/**
 * آستین ژیگو (ران بره) و ژولیت.
 *
 * بالای آستین خیلی پف دارد و از آرنج به پایین به بازو می‌چسبد. اگر «درز زیر پف»
 * روشن باشد پف در یک قطعه جدا بریده می‌شود و روی آستین تنگ دوخته می‌شود — همان
 * آستین ژولیت؛ وگرنه همه‌چیز در یک قطعه است و پف با چین سرآستین بسته می‌شود.
 */
class SleeveGigotGenerator extends SleeveCatalogGenerator
{
    public static function key(): string
    {
        return 'sleeve_gigot';
    }

    public function label(): string
    {
        return 'آستین ژیگو (ژولیت)';
    }

    public function paramsSchema(): array
    {
        return $this->commonSleeveSchema(1.0, 100, 3) + [
            'puff' => [
                'label' => 'مقدار پف بالای آستین', 'min' => 30, 'max' => 160, 'step' => 5, 'default' => 90,
                'unit' => 'درصد',
            ],
            'cap_lift' => [
                'label' => 'بلندتر کردن سرآستین', 'min' => 0, 'max' => 10, 'step' => 0.5, 'default' => 3,
                'unit' => 'سانتی‌متر',
            ],
            'puff_end' => [
                'label' => 'پایان پف', 'min' => 10, 'max' => 60, 'step' => 5, 'default' => 30,
                'unit' => 'درصد بلندی آستین',
            ],
            'seam_under_puff' => [
                'label' => 'درز زیر پف (ژولیت)', 'type' => 'toggle', 'default' => false,
            ],
        ];
    }

    protected function sleeveSpec(array $m, array $ease, array $params): array
    {
        $puff = max(0.1, ((float) $this->param($params, 'puff', 90)) / 100);
        $juliet = $this->flag($params, 'seam_under_puff', false);
        $puffEnd = max(0.1, min(0.6, ((float) $this->param($params, 'puff_end', 30)) / 100));

        return [
            'code' => $juliet ? 'sleeve-juliet-puff' : 'sleeve-gigot',
            'name' => $juliet ? 'پف آستین ژولیت' : 'آستین ژیگو',
            'family' => 'gigot',
            'ease_band' => [3.0, 30.0],
            'cap_fullness' => $puff,
            'cap_lift' => (float) $this->param($params, 'cap_lift', 3),
            'gather_cap' => true,
            'bicep_ease' => 3.0,
            'side_bulge' => $juliet ? 0.4 : 1.8,
            'side_bulge_at' => 0.3,
            'juliet' => $juliet,
            'puff_end' => $puffEnd,
        ];
    }

    protected function sleevePieces(array $frame, array $m, array $ease, array $params, array $spec): array
    {
        if (! $spec['juliet']) {
            // پف تا خط پایان پف باز است و از آنجا آستین یک‌باره به اندازه بازو جمع می‌شود
            return [$this->sleevePiece($frame, array_merge($spec, [
                'flare_start' => (float) $spec['puff_end'],
                'flare_start_width' => (float) $frame['base_width'],
                'notes' => ['پف از سرآستین تا '.round($spec['puff_end'] * 100)
                    .' درصد بلندی آستین باز است و از آنجا به بعد آستین به بازو می‌چسبد.'],
            ]))];
        }

        $capHeight = (float) $frame['cap_height'];
        $length = (float) $frame['length'];
        $puffEnd = $capHeight + (($length - $capHeight) * (float) $spec['puff_end']);

        $armWidth = (float) $frame['base_width'];
        $puffHem = $armWidth * (1 + ((float) $spec['cap_fullness'] * 0.5));

        $puffFrame = array_merge($frame, [
            'length' => $puffEnd,
            'hem_width' => $puffHem,
            'elbow_y' => $puffEnd + 1,
        ]);

        $puff = $this->sleevePiece($puffFrame, array_merge($spec, [
            'hem_gather' => $puffHem - $armWidth,
            'hem_drop' => 1.0,
            'meta' => ['gigot_role' => 'puff'],
            'notes' => [
                'لبه پایین پف روی آستین تنگ به '.round($armWidth, 1).' سانتی‌متر چین می‌خورد.',
            ],
        ]));

        $lower = $this->sleevePanelPiece($armWidth, (float) $frame['hem_width'], $length - $puffEnd, [
            'code' => 'sleeve-juliet-lower',
            'name' => 'آستین تنگ ژولیت',
            'part' => 'sleeve_lower',
            'bulge' => 0.5,
            'meta' => [
                'gigot_role' => 'lower',
                'notes' => ['لبه بالای این قطعه به لبه پایین پف دوخته می‌شود.'],
            ],
        ]);

        return [$puff, $lower];
    }
}
