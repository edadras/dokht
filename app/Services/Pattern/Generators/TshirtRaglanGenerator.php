<?php

namespace App\Services\Pattern\Generators;

/**
 * تی‌شرت رگلان (تی‌شرت بیسبالی).
 *
 * تنه از یک پارچه و آستین‌ها از پارچه‌ی رنگ متضاد؛ همان تی‌شرتی که خط رگلانش
 * عمداً دیده می‌شود. چون خط رگلان دیدنی است، دو چیز این‌جا مهم‌تر از یک تی‌شرت
 * معمولی است و هر دو در الگو دیده می‌شوند:
 *
 *   • خط رگلان روی تنه و روی آستین باید دقیقاً هم‌اندازه باشند، وگرنه رنگ متضاد
 *     هر میلی‌متر ناهم‌خوانی را جار می‌زند. طول هر دو پس از برش اندازه گرفته و
 *     در meta.raglan_seam ثبت می‌شود.
 *   • خط یقه‌ی رگلان از چهار تکه ساخته می‌شود (جلو، پشت و دو آستین)، پس نوار
 *     کشباف یقه باید روی جمع هر چهار تا حساب شود نه فقط روی یقه‌ی تنه.
 */
class TshirtRaglanGenerator extends RaglanBaseGenerator
{
    public static function key(): string
    {
        return 'tshirt_raglan';
    }

    public function label(): string
    {
        return 'تی‌شرت رگلان';
    }

    public function paramsSchema(): array
    {
        return $this->raglanSchema([
            'fit' => 'regular',
            'body_length' => 16,
            'neck_width_extra' => 1.5,
            'front_neck_depth_extra' => 1,
            'back_neck_depth' => 2.5,
            'armhole_depth_extra' => 2,
            'sleeve_length' => 22,
        ], [
            'neck_rib' => [
                'label' => 'بلندی نوار یقه', 'min' => 1.5, 'max' => 5, 'step' => 0.25,
                'default' => 2, 'unit' => 'سانتی‌متر',
            ],
            'rib_stretch' => [
                'label' => 'کوتاهی نوار یقه', 'min' => 0.7, 'max' => 0.95, 'step' => 0.01,
                'default' => 0.85,
                'hint' => 'نوار این‌قدر کوتاه‌تر از خط یقه بریده و کشیده دوخته می‌شود.',
            ],
            'contrast_sleeve' => [
                'label' => 'آستین رنگ متضاد', 'type' => 'toggle', 'default' => true,
            ],
        ]);
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $ease = $this->shirtEase($ease, $params);
        $g = $this->bodiceMetrics($measurements, $ease, $params);

        [$front, $back] = $this->shirtBody($g, $params, [
            'prefix' => 'raglan-tee',
            'front_name' => 'تنه جلو',
            'back_name' => 'تنه پشت',
        ]);

        $cut = $this->raglanCut($measurements, $ease, $params, [$front, $back], [
            'sleeve_length' => (float) $this->param($params, 'sleeve_length', 22),
            'hem_ease' => 8.0,
        ]);

        $pieces = $cut['pieces'];

        if ($this->flag($params, 'contrast_sleeve', true)) {
            foreach ($pieces as $index => $piece) {
                if (($piece['meta']['part'] ?? '') === 'sleeve') {
                    $pieces[$index]['meta']['contrast'] = true;
                    $pieces[$index]['meta']['notes'][] = 'از پارچه‌ی رنگ متضاد بریده می‌شود؛ خط رگلان همان مرز رنگ است.';
                }
            }
        }

        $pieces[] = $this->ribBand(
            $this->raglanNeckline($pieces),
            (float) $this->param($params, 'neck_rib', 2),
            'raglan-tee-neck-rib',
            'نوار کشباف یقه',
            (float) $this->param($params, 'rib_stretch', 0.85),
        );

        return $this->finish($this->noteOn($this->withGirthRoles($this->stampSeams($pieces)), array_merge($cut['notes'], [
            'تنه درز سرشانه ندارد؛ سرشانه به آستین رفته و جایش درز رگلان نشسته است.',
            'با نخ کشی و سوزن جرسی بدوزید؛ خط رگلان روی پارچه‌ی کشباف با درز معمولی می‌شکند.',
        ])));
    }

    /**
     * ثبت طول درز رگلان روی هر قطعه، تا در برگه‌ی فنی و آزمون دیده شود.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @return array<int, array<string, mixed>>
     */
    protected function stampSeams(array $pieces): array
    {
        foreach ($pieces as $index => $piece) {
            $raglan = $piece['meta']['raglan']['seam'] ?? null;

            if ($raglan !== null) {
                $pieces[$index]['meta']['raglan_seam'] = round((float) $raglan, 2);
            }
        }

        return $pieces;
    }
}
