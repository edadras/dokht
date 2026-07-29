<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * پیراهن یقه‌ایستاده (گِرِدداد / مائو).
 *
 * همان پیراهن کلاسیک است بدون سرِ یقه؛ فقط پایهٔ یقه می‌ماند. برای همین
 * ساده‌تر به نظر می‌رسد ولی سخت‌گیرتر است: در پیراهن معمولی سرِ یقه هر ناجوری
 * پایه را می‌پوشاند، این‌جا چیزی برای پوشاندن نیست.
 *
 * دو چیز که این‌جا حساس‌ترند: بلندی یقه (بالای سه‌ونیم سانتی‌متر روی گردن
 * فشار می‌آورد) و اینکه لبهٔ بالای یقه کمی کوتاه‌تر از لبهٔ پایین بریده شود تا
 * دور گردن جمع شود.
 */
class ShirtBandCollarGenerator extends ShirtBaseGenerator
{
    public static function key(): string
    {
        return 'shirt_band_collar';
    }

    public function label(): string
    {
        return 'پیراهن یقه‌ایستاده';
    }

    public function paramsSchema(): array
    {
        return $this->shirtSchema(
            ['sleeve_length' => 58],
            array_merge([
                'button_stand' => [
                    'label' => 'اضافه جای دکمه', 'min' => 1, 'max' => 4, 'step' => 0.25,
                    'default' => 1.75, 'unit' => 'سانتی‌متر',
                ],
                'collar_height' => [
                    'label' => 'بلندی یقه ایستاده', 'min' => 2, 'max' => 6, 'step' => 0.25,
                    'default' => 3.5, 'unit' => 'سانتی‌متر',
                    'hint' => 'بالای سه‌ونیم سانتی‌متر روی گردن فشار می‌آورد.',
                ],
                'yoke_depth' => [
                    'label' => 'بلندی یوک پشت', 'min' => 0, 'max' => 18, 'step' => 0.5,
                    'default' => 10, 'unit' => 'سانتی‌متر',
                    'hint' => 'صفر یعنی بدون یوک.',
                ],
                'cuff' => ['label' => 'مچ‌بند داشته باشد', 'type' => 'toggle', 'default' => true],
                'cuff_height' => [
                    'label' => 'بلندی مچ‌بند', 'min' => 4, 'max' => 12, 'step' => 0.5,
                    'default' => 6, 'unit' => 'سانتی‌متر',
                ],
            ], $this->pocketParam(false)),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $params = $this->withDropShoulder($params);
        $g = $this->bodiceMetrics($measurements, $this->shirtEase($ease, $params), $params);
        $stand = (float) $this->param($params, 'button_stand', 1.75);

        [$front, $back, $extras] = $this->shirtBody($g, $params, [
            'extension' => $stand,
            'prefix' => 'band',
            'yoke' => (float) $this->param($params, 'yoke_depth', 10),
        ]);

        $sleeves = $this->shirtSleeves($measurements, $ease, $params, array_merge([$front, $back], $extras));

        $neckHalf = ($front['meta']['neck_length'] ?? 12)
            + ($back['meta']['neck_length'] ?? 9)
            + ($extras[0]['meta']['neck_length'] ?? 0);

        $pieces = array_merge([$front, $back], $extras, $sleeves, [
            $this->bandCollar($neckHalf, (float) $this->param($params, 'collar_height', 3.5), $stand),
        ]);

        if ($this->flag($params, 'chest_pocket', false)) {
            $pieces[] = $this->patchPocket(11.5, 13, ['name' => 'جیب سینه']);
        }

        $pieces[] = $this->placket($stand, Geometry::height($front['outline']));

        return $this->finish($this->noteOn($pieces, [
            'یقهٔ ایستاده سرِ یقه ندارد، پس هیچ ناجوری‌ای پوشیده نمی‌شود؛ دور گردن را دقیق اندازه بگیرید.',
        ]));
    }
}
