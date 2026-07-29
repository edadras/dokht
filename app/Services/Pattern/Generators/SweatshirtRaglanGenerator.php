<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * سویشرت رگلان.
 *
 * همان اسکلت تی‌شرت رگلان است با سه تفاوت که از جنس پارچه می‌آید، نه از سلیقه:
 *
 *   • پارچه‌ی سویشرت (فلیس/تودری) ضخیم است، پس آزادی بیشتری می‌خواهد و حلقه‌ی
 *     گودتری لازم دارد تا زیر بغل نکشد.
 *   • دم لباس و مچ آستین با نوار کشباف بسته می‌شوند، پس خودِ لبه‌ها عمداً از
 *     نوارشان پهن‌تر بریده می‌شوند و اختلاف، جمعِ نوار است.
 *   • جلوی یقه یک «کاچاله»ی مثلثی کشباف دارد — همان تکه‌ای که در سویشرت‌های
 *     قدیمی کشیده‌شدنِ یقه هنگام پوشیدن را می‌گرفت.
 *
 * درز رگلان مثل تی‌شرت رگلان از خط یقه تا زیر بغل روی تنه بریده و آستین از روی
 * همان خط درفت می‌شود.
 */
class SweatshirtRaglanGenerator extends RaglanBaseGenerator
{
    public static function key(): string
    {
        return 'sweatshirt_raglan';
    }

    public function label(): string
    {
        return 'سویشرت رگلان';
    }

    public function paramsSchema(): array
    {
        return $this->raglanSchema([
            'fit' => 'loose',
            'body_length' => 20,
            'neck_width_extra' => 2.5,
            'front_neck_depth_extra' => 1.5,
            'back_neck_depth' => 3,
            'armhole_depth_extra' => 4,
            'sleeve_length' => 58,
            'raglan_neck' => 5.5,
            'raglan_armhole' => 10,
            'cap_softness' => 0.6,
        ], [
            'rib_height' => [
                'label' => 'بلندی نوار کشباف دم و مچ', 'min' => 3, 'max' => 10, 'step' => 0.5,
                'default' => 6, 'unit' => 'سانتی‌متر',
            ],
            'neck_rib' => [
                'label' => 'بلندی نوار یقه', 'min' => 1.5, 'max' => 6, 'step' => 0.25,
                'default' => 2.5, 'unit' => 'سانتی‌متر',
            ],
            'rib_stretch' => [
                'label' => 'کوتاهی نوار کشباف', 'min' => 0.65, 'max' => 0.95, 'step' => 0.01,
                'default' => 0.8,
            ],
            'neck_gusset' => [
                'label' => 'کاچاله‌ی یقه', 'type' => 'toggle', 'default' => true,
            ],
        ]);
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $ease = $this->shirtEase($ease, $params);
        $g = $this->bodiceMetrics($measurements, $ease, $params);
        $ribHeight = (float) $this->param($params, 'rib_height', 6);
        $stretch = (float) $this->param($params, 'rib_stretch', 0.8);

        // لبه‌ی پایین تا جایی که نوار کشباف روی آن می‌نشیند کوتاه می‌شود، وگرنه
        // سویشرت به اندازه‌ی بلندی نوار بلندتر از خواسته‌ی کاربر درمی‌آید.
        $params['body_length'] = (float) $this->param($params, 'body_length', 20) - $ribHeight;

        [$front, $back] = $this->shirtBody($g, $params, [
            'prefix' => 'raglan-sweat',
            'front_name' => 'تنه جلو',
            'back_name' => 'تنه پشت',
        ]);

        $cut = $this->raglanCut($measurements, $ease, $params, [$front, $back], [
            'sleeve_length' => max(20.0, (float) $this->param($params, 'sleeve_length', 58) - $ribHeight),
            'hem_ease' => 4.0,
        ]);

        $pieces = $cut['pieces'];

        $pieces[] = $this->ribBand(
            $this->raglanNeckline($pieces),
            (float) $this->param($params, 'neck_rib', 2.5),
            'raglan-sweat-neck-rib',
            'نوار کشباف یقه',
            $stretch,
        );

        $pieces[] = $this->ribBand(
            $this->hemWidthOf($pieces),
            $ribHeight,
            'raglan-sweat-hem-rib',
            'نوار کشباف دم لباس',
            $stretch,
        );

        $pieces[] = $this->ribBand(
            max(14.0, $this->sleeveHemOf($pieces)),
            $ribHeight,
            'raglan-sweat-cuff-rib',
            'مچ کشباف آستین',
            $stretch,
            2,
        );

        if ($this->flag($params, 'neck_gusset', true)) {
            $pieces[] = $this->neckGusset($g);
        }

        return $this->finish($this->noteOn($this->withGirthRoles($pieces), array_merge($cut['notes'], [
            'دم لباس و مچ آستین پیش از دوختن نوار، به اندازه‌ی بلندی نوار کوتاه شده‌اند؛'
                .' پس بلندی تمام‌شده همان است که خواسته‌اید.',
            'نوار کشباف '.$this->fa(round((1 - $stretch) * 100)).' درصد کوتاه‌تر از لبه بریده و کشیده دوخته می‌شود.',
        ])));
    }

    /**
     * کاچاله‌ی یقه: مثلث کشبافی که زیر خط یقه‌ی جلو دوخته می‌شود.
     *
     * @param  array<string, float>  $g
     * @return array<string, mixed>
     */
    protected function neckGusset(array $g): array
    {
        $width = max(6.0, min(12.0, (float) $g['neck_width']));
        $depth = max(4.0, $width * 0.7);

        return $this->piece([
            'code' => 'raglan-sweat-neck-gusset',
            'name' => 'کاچاله یقه',
            'cut_quantity' => 1,
            'on_fold' => true,
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point($width, 0),
                Geometry::curve(0, $depth, $width * 0.35, $depth * 0.85),
            ],
            'grainline' => $this->grainline($width * 0.35, 0.6, $depth * 0.5),
            'meta' => [
                'part' => 'collar',
                'edges' => ['neck', 'default', 'default'],
                'fold_edges' => [2],
                'girth_role' => 'trim',
                'rib' => true,
                'notes' => [
                    'روی تای پارچه بریده می‌شود و زیر خط یقه‌ی جلو، درست پایین نوار یقه می‌نشیند.',
                    'همین تکه است که نمی‌گذارد یقه‌ی سویشرت هنگام پوشیدن کشیده و گشاد بماند.',
                ],
            ],
        ]);
    }
}
