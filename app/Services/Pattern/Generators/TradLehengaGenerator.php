<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * لهنگا.
 *
 * سه‌تکه: چولیِ کوتاه، دامنِ بلندِ ترک‌دارِ بسیار پُر، و دوپَتّه (شالِ بلند).
 *
 * دامن از چند ترک بریده می‌شود نه یک دایره: ترک‌بندی همان پُری را با پارچهٔ
 * کمتر می‌دهد و درزهایش جای نشستنِ گلدوزیِ سنگین است. هرچه شمار ترک بیشتر،
 * لبهٔ پایین نرم‌تر می‌افتد.
 */
class TradLehengaGenerator extends TraditionalBaseGenerator
{
    public static function key(): string
    {
        return 'trad_lehenga';
    }

    public function label(): string
    {
        return 'لهنگا';
    }

    public static function group(): string
    {
        return 'traditional';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerSchema([
                'shoulder_slope' => 4,
                'neck_width_extra' => 1.5,
                'front_neck_depth_extra' => 4,
                'back_neck_depth' => 8,
                'armhole_depth_extra' => 1,
                'waist_dart_share' => 0.6,
            ]),
            [
                'choli_length' => [
                    'label' => 'بلندی چولی از سرشانه', 'min' => 28, 'max' => 48, 'step' => 1,
                    'default' => 34, 'unit' => 'سانتی‌متر',
                ],
                'choli_sleeve' => [
                    'label' => 'بلندی آستین چولی', 'min' => 0, 'max' => 34, 'step' => 1,
                    'default' => 12, 'unit' => 'سانتی‌متر',
                ],
                'skirt_length' => [
                    'label' => 'قد دامن', 'min' => 85, 'max' => 115, 'step' => 1,
                    'default' => 102, 'unit' => 'سانتی‌متر',
                ],
                'gores' => [
                    'label' => 'شمار ترک دامن', 'min' => 6, 'max' => 24, 'step' => 1,
                    'default' => 12,
                    'hint' => 'هرچه بیشتر، لبهٔ پایین نرم‌تر می‌افتد.',
                ],
                'hem_sweep' => [
                    'label' => 'دور لبه پایین دامن', 'min' => 250, 'max' => 700, 'step' => 10,
                    'default' => 420, 'unit' => 'سانتی‌متر',
                ],
                'dupatta_length' => [
                    'label' => 'طول دوپته', 'min' => 200, 'max' => 300, 'step' => 5,
                    'default' => 250, 'unit' => 'سانتی‌متر',
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $length = (float) $this->param($params, 'choli_length', 34);

        $pieces = $this->outerGarment($measurements, $ease, $params, $g, [
            'prefix' => 'lehenga-choli-',
            'grow' => 0.0,
            'shape' => 'fitted',
            'length' => $length,
            'opening' => 'button',
            'stand' => 1.5,
            'facing' => false,
            'bust_dart' => true,
            'front_name' => 'تنه جلوی چولی',
            'back_name' => 'تنه پشت چولی',
            'panel' => ['waist_dart' => true, 'bottom_tag' => 'hem'],
            'sleeve' => [
                'sleeve_name' => 'آستین چولی',
                'length' => (float) $this->param($params, 'choli_sleeve', 12),
            ],
        ]);

        $waist = $this->m($measurements, 'waist', 74);
        $gores = max(4, (int) $this->param($params, 'gores', 12));
        $skirt = (float) $this->param($params, 'skirt_length', 102);
        $sweep = max($waist * 1.5, (float) $this->param($params, 'hem_sweep', 420));

        $topWidth = ($waist + 4) / $gores;
        $hemWidth = $sweep / $gores;

        $pieces[] = $this->piece([
            'code' => 'lehenga-gore',
            'name' => 'ترک دامن',
            'cut_quantity' => $gores,
            'mirror' => false,
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point($topWidth, 0),
                Geometry::point($hemWidth, $skirt),
                Geometry::point(0, $skirt),
            ],
            'grainline' => $this->grainline($topWidth * 0.5, 3, $skirt - 3),
            'meta' => [
                'part' => 'skirt_panel',
                'side' => 'front',
                'edges' => ['waist', 'side', 'hem', 'side'],
                'girth' => ['waist'],
                'girth_factor' => 1 / max(1, $gores),
                'notes' => [
                    $this->fa($gores).' ترکِ هم‌اندازه که کنار هم دوخته می‌شوند.',
                    'درزِ ترک‌ها جای گلدوزیِ سنگین است.',
                    'لبهٔ پایین را پیش از دوختِ آخرین درز اندازه بگیرید؛ پارچهٔ اریب کش می‌آید.',
                ],
            ],
        ]);

        $pieces[] = $this->bandPiece('lehenga-waistband', 'کمربند دامن', $waist + 6, 6, [
            'cut' => 1, 'part' => 'waistband',
            'meta' => [
                'edges' => ['waist', 'side', 'waist', 'side'],
                'interfacing' => true,
                'girth_role' => 'trim',
            ],
        ]);

        $dupatta = (float) $this->param($params, 'dupatta_length', 250);

        $pieces[] = $this->piece([
            'code' => 'lehenga-dupatta',
            'name' => 'دوپته (شال)',
            'cut_quantity' => 1,
            'mirror' => false,
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point($dupatta, 0),
                Geometry::point($dupatta, 110),
                Geometry::point(0, 110),
            ],
            'grainline' => $this->grainline($dupatta * 0.5, 3, 107),
            'meta' => [
                'part' => 'drape',
                'edges' => ['hem', 'default', 'hem', 'default'],
                'girth' => [],
                'girth_factor' => 0,
                'girth_role' => 'cover',
                'notes' => ['بریده نمی‌شود؛ چهار لبه‌اش حاشیه می‌خورد.'],
            ],
        ]);

        $pieces[0] = $this->markCoverage($pieces[0], [
            'hem' => $length,
            'hem_at' => 'زیر سینه',
            'sleeve' => $this->fa(round((float) $this->param($params, 'choli_sleeve', 12))).' سانتی‌متر',
            'neck' => 'گرد و باز',
        ]);

        return $this->finishBlock($pieces, $g, 0.0);
    }
}
