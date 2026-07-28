<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Generators\Concerns\DraftsPants;
use App\Services\Pattern\Geometry;

/**
 * اورال بنددار.
 *
 * شلوار با «لتِ سینه» جلو و لتِ پشت که با دو بند از روی شانه به هم می‌رسند.
 * بالاتنه‌ای در کار نیست، پس دور سینه در الگو نمی‌آید و اندازه‌های تعیین‌کننده
 * کمر، باسن و قد فاق‌اند. پهنای لت از عرض سرشانه و فاصله دو بند حساب می‌شود.
 */
class JumpsuitOverallGenerator extends BodiceGarmentBase
{
    use DraftsPants;

    public static function key(): string
    {
        return 'overall';
    }

    public function label(): string
    {
        return 'اورال بنددار';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->fitParam('loose'),
            [
                'bib_width' => [
                    'label' => 'پهنای لتِ سینه', 'min' => 14, 'max' => 34, 'step' => 0.5,
                    'default' => 22, 'unit' => 'سانتی‌متر',
                ],
                'bib_height' => [
                    'label' => 'بلندی لتِ سینه از کمر', 'min' => 12, 'max' => 34, 'step' => 0.5,
                    'default' => 24, 'unit' => 'سانتی‌متر',
                ],
                'back_bib_height' => [
                    'label' => 'بلندی لتِ پشت از کمر', 'min' => 6, 'max' => 30, 'step' => 0.5,
                    'default' => 16, 'unit' => 'سانتی‌متر',
                ],
                'strap_width' => [
                    'label' => 'پهنای بند', 'min' => 2.5, 'max' => 8, 'step' => 0.5,
                    'default' => 4, 'unit' => 'سانتی‌متر',
                ],
                'strap_length' => [
                    'label' => 'بلندی هر بند', 'min' => 35, 'max' => 90, 'step' => 1,
                    'default' => 60, 'unit' => 'سانتی‌متر',
                ],
                'rise_extra' => [
                    'label' => 'آزادی فاق', 'min' => 0, 'max' => 10, 'step' => 0.5,
                    'default' => 3, 'unit' => 'سانتی‌متر',
                ],
                'length_extra' => [
                    'label' => 'تغییر قد پا', 'min' => -45, 'max' => 12, 'step' => 1,
                    'default' => 0, 'unit' => 'سانتی‌متر',
                ],
                'knee_ease' => [
                    'label' => 'آزادی دور زانو', 'min' => 2, 'max' => 30, 'step' => 1,
                    'default' => 12, 'unit' => 'سانتی‌متر',
                ],
                'hem_ease' => [
                    'label' => 'آزادی دم پا', 'min' => 2, 'max' => 45, 'step' => 1,
                    'default' => 18, 'unit' => 'سانتی‌متر',
                ],
                'front_pleat' => [
                    'label' => 'پیلی جلوی شلوار', 'min' => 0, 'max' => 6, 'step' => 0.5,
                    'default' => 0, 'unit' => 'سانتی‌متر',
                ],
                'back_darts' => [
                    'label' => 'تعداد ساسون پشت شلوار', 'min' => 0, 'max' => 2, 'step' => 1, 'default' => 1,
                ],
                'waistband' => [
                    'label' => 'کمربند داشته باشد', 'type' => 'toggle', 'default' => false,
                ],
                'waistband_height' => [
                    'label' => 'بلندی کمربند', 'min' => 2, 'max' => 8, 'step' => 0.5,
                    'default' => 4, 'unit' => 'سانتی‌متر',
                ],
            ],
            $this->pocketParam(true, 15, 16),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $bibWidth = (float) $this->param($params, 'bib_width', 22);
        $bibHeight = (float) $this->param($params, 'bib_height', 24);
        $backHeight = (float) $this->param($params, 'back_bib_height', 16);
        $strapWidth = (float) $this->param($params, 'strap_width', 4);

        $pieces = [];

        foreach (['front', 'back'] as $side) {
            $leg = $this->legPanel($measurements, $ease, $params, [
                'side' => $side,
                'code' => 'overall-leg-'.$side,
                'name' => $side === 'front' ? 'پای جلو' : 'پای پشت',
            ]);

            $leg['meta']['girth_role'] = 'bottom';
            $pieces[] = $leg;
        }

        $pieces[] = $this->bibPiece('overall-bib-front', 'لتِ سینه جلو', $bibWidth, $bibHeight, $strapWidth);
        $pieces[] = $this->bibPiece('overall-bib-back', 'لتِ پشت', $bibWidth - 2, $backHeight, $strapWidth);

        $pieces[] = $this->bandPiece('overall-strap', 'بند شانه', (float) $this->param($params, 'strap_length', 60), $strapWidth * 2, [
            'cut' => 2, 'part' => 'belt', 'fold_line' => true,
            'meta' => ['notes' => ['سرِ بند روی لتِ پشت دوخته و سر دیگر با سگک به لتِ جلو بسته می‌شود.']],
        ]);

        if ($this->flag($params, 'waistband', false)) {
            $pieces[] = $this->waistbandPiece($measurements, $ease, $params, [
                'code' => 'overall-waistband',
                'name' => 'کمربند',
            ]);
        }

        $pieces = array_merge($pieces, $this->pocketSet($params, ['prefix' => 'overall-']));

        return $this->finishBlock($pieces, $g, 0.0, ['bottom']);
    }

    /**
     * لتِ سینه یا لتِ پشت: نیم‌قطعه روی تای پارچه با گوشه بالا کمی تو رفته.
     *
     * @return array<string, mixed>
     */
    protected function bibPiece(string $code, string $name, float $width, float $height, float $strapWidth): array
    {
        $half = max(6.0, $width / 2);
        $topHalf = max(3.0, $half - 2.5);

        $outline = [
            Geometry::point(0, 0),
            Geometry::point($topHalf, 0),
            Geometry::curve($half, $height, $topHalf + 1.2, $height * 0.55),
            Geometry::point(0, $height),
        ];

        return $this->piece([
            'code' => $code,
            'name' => $name,
            'cut_quantity' => 2,
            'on_fold' => true,
            'outline' => $outline,
            'grainline' => $this->grainline($topHalf * 0.5, 1.5, $height - 1.5),
            'notches' => [
                $this->notch($topHalf - ($strapWidth / 2), 0, 0, 'محل بند', 'strap'),
            ],
            'markers' => [
                $this->marker('cf', 'خط مرکز', 0, 0, 0, $height),
            ],
            'meta' => [
                'part' => 'front_panel',
                'edges' => ['default', 'side', 'waist', 'default'],
                'fold_edges' => [3],
                'girth_role' => 'trim',
                'interfacing' => true,
                'notes' => ['دو لایه بریده می‌شود (رو و آستر) و لبه پایین آن به لبه کمر شلوار دوخته می‌گردد.'],
            ],
        ]);
    }
}
