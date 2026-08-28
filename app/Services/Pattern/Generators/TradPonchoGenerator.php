<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * پانچو.
 *
 * یک تختهٔ پارچه با سوراخِ سر در وسط. نه آستین دارد نه درزِ پهلو، پس تنها سه
 * تصمیم می‌ماند: اندازهٔ تخته، شکلِ سوراخِ سر، و اینکه لبه‌ها راست باشند یا
 * گوشه‌ها گرد شوند.
 *
 * سوراخِ سر باید از دور سر بزرگ‌تر باشد وگرنه سر رد نمی‌شود؛ ولی هرچه بزرگ‌تر
 * باشد پانچو بیشتر از شانه می‌سُرد. چاکِ کوتاهِ جلو همین را حل می‌کند: سوراخ
 * کوچک می‌ماند و برای پوشیدن باز می‌شود.
 */
class TradPonchoGenerator extends TraditionalBaseGenerator
{
    public static function key(): string
    {
        return 'trad_poncho';
    }

    public function label(): string
    {
        return 'پانچو';
    }

    public static function group(): string
    {
        return 'traditional';
    }

    public function paramsSchema(): array
    {
        return [
            'width' => [
                'label' => 'پهنای تخته', 'min' => 80, 'max' => 160, 'step' => 2,
                'default' => 120, 'unit' => 'سانتی‌متر',
                'hint' => 'از سرِ یک آستین تا سرِ دیگری؛ همین بلندیِ روی بازو را می‌سازد.',
            ],
            'length' => [
                'label' => 'بلندی تخته', 'min' => 70, 'max' => 150, 'step' => 2,
                'default' => 100, 'unit' => 'سانتی‌متر',
            ],
            'neck_width' => [
                'label' => 'پهنای سوراخ سر', 'min' => 16, 'max' => 30, 'step' => 0.5,
                'default' => 21, 'unit' => 'سانتی‌متر',
            ],
            'neck_depth' => [
                'label' => 'گودی سوراخ سر', 'min' => 8, 'max' => 22, 'step' => 0.5,
                'default' => 13, 'unit' => 'سانتی‌متر',
            ],
            'front_slit' => [
                'label' => 'بلندی چاک جلو', 'min' => 0, 'max' => 25, 'step' => 1,
                'default' => 10, 'unit' => 'سانتی‌متر',
            ],
            'fringe' => [
                'label' => 'ریشه لبه پایین', 'type' => 'toggle', 'default' => true,
            ],
        ];
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $width = max(60.0, (float) $this->param($params, 'width', 120));
        $length = max(50.0, (float) $this->param($params, 'length', 100));
        $neckWide = max(12.0, (float) $this->param($params, 'neck_width', 21));
        $neckDeep = max(6.0, (float) $this->param($params, 'neck_depth', 13));
        $slit = max(0.0, (float) $this->param($params, 'front_slit', 10));
        $fringe = (bool) $this->param($params, 'fringe', true);

        $half = $width / 2;
        $head = $this->m($measurements, 'head', 56);

        /*
         * روی تای سرشانه بریده می‌شود: نصفِ بلندی، و نصفِ پهنا. لبهٔ بالا همان
         * خط تاست و سوراخِ سر روی آن نصف می‌شود.
         */
        $outline = [
            Geometry::point(0, 0),
            Geometry::point($half, 0),
            Geometry::point($half, $length / 2),
            Geometry::point(0, $length / 2),
        ];

        $piece = $this->piece([
            'code' => 'poncho-body',
            'name' => 'تخته پانچو',
            'cut_quantity' => 1,
            'on_fold' => true,
            'mirror' => false,
            'outline' => $outline,
            'grainline' => $this->grainline($half * 0.5, 3, $length / 2 - 3),
            'markers' => [
                $this->marker('neck', 'سوراخ سر', 0, 0, $neckWide / 2, 0),
                $this->marker('slit', 'چاک جلو', 0, 0, 0, $slit),
            ],
            'meta' => [
                'part' => 'cape',
                'edges' => ['default', 'hem', 'hem', 'default'],
                'fold_edges' => [0, 3],
                'girth' => [],
                'girth_factor' => 0,
                'girth_role' => 'cover',
                'notes' => [
                    'روی دو تا بریده می‌شود: تای سرشانه و تای مرکز.',
                    'سوراخِ سر ('.$this->fa(round($neckWide)).'×'.$this->fa(round($neckDeep))
                        .') باید از دور سر ('.$this->fa(round($head)).') راحت‌تر رد شود؛'
                        .' چاکِ جلو همین را ممکن می‌کند بی آنکه سوراخ گشاد شود.',
                    $fringe ? 'لبهٔ پایین ریشه می‌خورد؛ برای آن سه سانتی‌متر پارچهٔ اضافه بگذارید.' : 'لبهٔ پایین تو گذاشته و دوخته می‌شود.',
                ],
            ],
        ]);

        $pieces = [$piece];

        $pieces[] = $this->bandPiece(
            'poncho-neck-binding',
            'نوار اریب دور یقه',
            ($neckWide + $neckDeep) * 2 + $slit * 2 + 8,
            3,
            [
                'cut' => 1, 'part' => 'facing',
                'meta' => [
                    'bias' => true,
                    'girth_role' => 'trim',
                    'notes' => ['هم دور سوراخِ سر و هم دو لبهٔ چاک را می‌پوشاند.'],
                ],
            ],
        );

        return $this->finish($pieces);
    }
}
