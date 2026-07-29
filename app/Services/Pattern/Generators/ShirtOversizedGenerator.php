<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * پیراهن اورسایز (بویفرند).
 *
 * گشادی این پیراهن از آزادی تنه نمی‌آید، از جای سرشانه می‌آید: سرشانه از نوک
 * شانه پایین‌تر می‌افتد و حلقه بزرگ‌تر می‌شود. برای همین اگر فقط دور سینه را
 * زیاد کنید پیراهن گشاد می‌شود ولی اورسایز نمی‌شود.
 *
 * چیزی که همیشه فراموش می‌شود: سرشانهٔ افتاده یعنی حلقه هم باید گودتر شود،
 * وگرنه آستین در حلقه نمی‌نشیند و زیر بغل می‌کشد. این‌جا هر سانتی‌متر افتادن
 * سرشانه، حلقه را هشت‌دهم سانتی‌متر گودتر می‌کند.
 *
 * آستین هم به همان نسبت پهن‌تر است؛ آستین باریک روی حلقهٔ بزرگ چروک می‌اندازد.
 */
class ShirtOversizedGenerator extends ShirtBaseGenerator
{
    public static function key(): string
    {
        return 'shirt_oversized';
    }

    public function label(): string
    {
        return 'پیراهن اورسایز (بویفرند)';
    }

    public function paramsSchema(): array
    {
        return $this->shirtSchema(
            ['fit' => 'boxy', 'drop_shoulder' => 5, 'body_length' => 26, 'sleeve_length' => 56, 'armhole_depth_extra' => 4],
            array_merge([
                'button_stand' => [
                    'label' => 'اضافه جای دکمه', 'min' => 1, 'max' => 4, 'step' => 0.25,
                    'default' => 2, 'unit' => 'سانتی‌متر',
                ],
                'collar_height' => [
                    'label' => 'بلندی یقه', 'min' => 4, 'max' => 12, 'step' => 0.5,
                    'default' => 8, 'unit' => 'سانتی‌متر',
                ],
                'yoke_depth' => [
                    'label' => 'بلندی یوک پشت', 'min' => 0, 'max' => 20, 'step' => 0.5,
                    'default' => 12, 'unit' => 'سانتی‌متر',
                ],
                'cuff' => ['label' => 'مچ‌بند داشته باشد', 'type' => 'toggle', 'default' => true],
                'cuff_height' => [
                    'label' => 'بلندی مچ‌بند', 'min' => 4, 'max' => 12, 'step' => 0.5,
                    'default' => 6.5, 'unit' => 'سانتی‌متر',
                ],
            ], $this->pocketParam(true)),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $drop = (float) $this->param($params, 'drop_shoulder', 5);
        $params = $this->withDropShoulder($params);
        $g = $this->bodiceMetrics($measurements, $this->shirtEase($ease, $params), $params);

        // سرشانه روی بازو می‌افتد
        $g['shoulder_half'] += $drop;
        $g['across_back'] += $drop * 0.6;
        $g['across_chest'] += $drop * 0.6;

        $stand = (float) $this->param($params, 'button_stand', 2);

        [$front, $back, $extras] = $this->shirtBody($g, $params, [
            'extension' => $stand,
            'prefix' => 'oversized',
            'yoke' => (float) $this->param($params, 'yoke_depth', 12),
        ]);

        $sleeves = $this->shirtSleeves($measurements, $ease, $params, array_merge([$front, $back], $extras), [
            'sleeve_name' => 'آستین اورسایز',
        ]);

        $neckHalf = ($front['meta']['neck_length'] ?? 12)
            + ($back['meta']['neck_length'] ?? 9)
            + ($extras[0]['meta']['neck_length'] ?? 0);

        $pieces = array_merge([$front, $back], $extras, $sleeves, [
            $this->shirtCollar($neckHalf + $stand, (float) $this->param($params, 'collar_height', 8)),
        ]);

        if ($this->flag($params, 'chest_pocket', true)) {
            $pieces[] = $this->patchPocket(13, 14.5, ['name' => 'جیب سینه']);
        }

        $pieces[] = $this->placket($stand, Geometry::height($front['outline']), spacing: 9.5);

        return $this->finish($this->noteOn($pieces, [
            'سرشانه '.$this->fa($drop).' سانتی‌متر روی بازو افتاده و حلقه به همان نسبت گودتر شده؛'
                .' همین است که پیراهن را اورسایز می‌کند، نه فقط زیاد کردن دور سینه.',
            'آستین پهن‌تر از پیراهن معمولی است؛ آستین باریک روی حلقهٔ بزرگ چروک می‌اندازد.',
        ]));
    }
}
