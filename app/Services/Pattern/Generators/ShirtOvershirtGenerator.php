<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * اورشرت (شکت).
 *
 * چیزی میان پیراهن و کاپشن: مثل پیراهن دوخته می‌شود ولی روی لباس دیگر پوشیده
 * می‌شود.
 *
 * همین «روی لباس دیگر» تنها چیزی است که الگو را عوض می‌کند، ولی به‌کلی عوض
 * می‌کند: آزادی باید جای یک لباس دیگر را هم بدهد، نه فقط جای بدن را. پس آزادی
 * سینه از پیراهن معمولی بیشتر گرفته شده و آستین هم پهن‌تر است تا روی آستین
 * پیراهنِ زیر بنشیند.
 *
 * دو جیب بزرگ پایین (نه روی سینه) و دکمهٔ درشت‌تر، بقیهٔ تفاوت‌اند.
 */
class ShirtOvershirtGenerator extends ShirtBaseGenerator
{
    public static function key(): string
    {
        return 'shirt_overshirt';
    }

    public function label(): string
    {
        return 'اورشرت (شکت)';
    }

    public function paramsSchema(): array
    {
        return $this->shirtSchema(
            ['fit' => 'loose', 'sleeve_length' => 60, 'body_length' => 24, 'armhole_depth_extra' => 4, 'drop_shoulder' => 3],
            [
                'button_stand' => [
                    'label' => 'اضافه جای دکمه', 'min' => 1.5, 'max' => 5, 'step' => 0.25,
                    'default' => 2.5, 'unit' => 'سانتی‌متر',
                ],
                'collar_height' => [
                    'label' => 'بلندی یقه', 'min' => 5, 'max' => 13, 'step' => 0.5,
                    'default' => 9, 'unit' => 'سانتی‌متر',
                ],
                'layer_ease' => [
                    'label' => 'آزادی برای لباس زیر', 'min' => 0, 'max' => 14, 'step' => 1,
                    'default' => 6, 'unit' => 'سانتی‌متر',
                    'hint' => 'جای پیراهن یا بافتی که زیرش پوشیده می‌شود.',
                ],
                'hip_pockets' => [
                    'label' => 'دو جیب بزرگ پایین', 'type' => 'toggle', 'default' => true,
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $layer = (float) $this->param($params, 'layer_ease', 6);
        $params = $this->withDropShoulder($params);

        $ease = $this->shirtEase($ease, $params);
        $ease['bust'] += $layer;
        $ease['waist'] += $layer;
        $ease['hip'] += $layer;
        $ease['bicep'] = $this->ease($ease, 'bicep', 4) + ($layer * 0.5);

        $g = $this->bodiceMetrics($measurements, $ease, $params);
        $drop = (float) $this->param($params, 'drop_shoulder', 3);
        $g['shoulder_half'] += $drop;

        $stand = (float) $this->param($params, 'button_stand', 2.5);

        [$front, $back, $extras] = $this->shirtBody($g, $params, [
            'extension' => $stand,
            'prefix' => 'overshirt',
            'yoke' => 10.0,
        ]);

        $sleeves = $this->shirtSleeves($measurements, $ease, $params, array_merge([$front, $back], $extras), [
            'sleeve_name' => 'آستین اورشرت',
        ]);

        $neckHalf = ($front['meta']['neck_length'] ?? 12)
            + ($back['meta']['neck_length'] ?? 9)
            + ($extras[0]['meta']['neck_length'] ?? 0);

        $pieces = array_merge([$front, $back], $extras, $sleeves, [
            $this->shirtCollar($neckHalf + $stand, (float) $this->param($params, 'collar_height', 9)),
        ]);

        if ($this->flag($params, 'hip_pockets', true)) {
            $pieces[] = $this->patchPocket(17, 18, [
                'code' => 'overshirt-pocket', 'name' => 'جیب پایین (جفت)', 'cut' => 2, 'radius' => 2,
                'notes' => ['جیب اورشرت پایین می‌نشیند نه روی سینه؛ برای دست است، نه برای حمل چیز.'],
            ]);
        }

        $placket = $this->placket($stand, Geometry::height($front['outline']), spacing: 10.0);
        $placket['meta']['notes'][] = 'دکمهٔ اورشرت درشت‌تر از پیراهن است؛ حدود دو سانتی‌متر.';
        $pieces[] = $placket;

        return $this->finish($this->noteOn($pieces, [
            'آزادی این الگو '.$this->fa($layer).' سانتی‌متر بیشتر از پیراهن معمولی است تا روی لباس زیر بنشیند.',
        ]));
    }
}
