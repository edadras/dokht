<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * شلوار گرمکن.
 *
 * جفتِ گرمکن است و مثل آن لایهٔ *رویی*: از پارچهٔ بافته یا کشیِ سنگین بریده
 * می‌شود و آزادی‌اش مثبت است، چون روی تایت پوشیده می‌شود. برای همین هیچ ضریب
 * کشسانی اعلام نمی‌کند — درست برعکس تایت ورزشی که کوچک‌تر از بدن بریده می‌شود.
 *
 * سه چیز آن را از یک شلوار راحتی معمولی جدا می‌کند:
 *
 *   • دور ران باز است تا زانو آزادانه بالا بیاید، ولی از زانو به پایین جمع
 *     می‌شود تا هنگام دویدن دور مچ نپیچد.
 *   • کمر کشی با بند کشی است، و جای سوراخ بند روی کمر علامت می‌خورد؛ بدون آن
 *     کمرکش هنگام دویدن پایین می‌رود.
 *   • درز پهلو جای نوار رنگی است؛ عرض نوار و جای دقیقش روی هر دو پا ثبت می‌شود
 *     تا نوارِ جلو و پشت روی هم بیفتد.
 */
class ActiveTrackPantsGenerator extends PantsBaseGenerator
{
    use ActiveFabric, PieceRoles;

    /** این مدل در فهرست، زیر «لباس ورزشی» می‌نشیند نه زیر «پایین‌تنه». */
    public static function group(): string
    {
        return 'active';
    }

    public static function key(): string
    {
        return 'active_track_pants';
    }

    public function label(): string
    {
        return 'شلوار گرمکن';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->riseParams('mid'),
            $this->legParams(14, 16),
            [
                'thigh_ease' => [
                    'label' => 'آزادی دور ران', 'min' => 8, 'max' => 30, 'step' => 0.5,
                    'default' => 16, 'unit' => 'سانتی‌متر',
                    'hint' => 'گرمکن روی تایت پوشیده می‌شود، پس دور ران باید از تایت گشادتر بماند.',
                ],
                'ankle' => [
                    'label' => 'دم پا', 'type' => 'select', 'default' => 'cuff',
                    'options' => [
                        'cuff' => 'مچ کشباف',
                        'open' => 'دم پا باز',
                        'zip' => 'دم پا با زیپ کناری',
                    ],
                ],
                'cuff_ease' => [
                    'label' => 'آزادی مچ دم پا', 'min' => 2, 'max' => 16, 'step' => 0.5,
                    'default' => 7, 'unit' => 'سانتی‌متر',
                ],
                'cuff_height' => [
                    'label' => 'بلندی مچ', 'min' => 3, 'max' => 10, 'step' => 0.5,
                    'default' => 5, 'unit' => 'سانتی‌متر',
                ],
                'band_stretch' => [
                    'label' => 'کشش نوار کمر', 'min' => 0.6, 'max' => 0.95, 'step' => 0.01, 'default' => 0.85,
                ],
                'waistband_height' => [
                    'label' => 'بلندی نوار کمر', 'min' => 3, 'max' => 10, 'step' => 0.5,
                    'default' => 5, 'unit' => 'سانتی‌متر',
                ],
                'side_stripe' => [
                    'label' => 'پهنای نوار پهلو', 'min' => 0, 'max' => 6, 'step' => 0.5,
                    'default' => 2.5, 'unit' => 'سانتی‌متر',
                    'hint' => 'صفر یعنی بدون نوار.',
                ],
            ],
        );
    }

    protected function shape(array $params, array $measurements): array
    {
        $ankle = (string) $this->param($params, 'ankle', 'cuff');
        $cuffHeight = (float) $this->param($params, 'cuff_height', 5);

        $shape = [
            'thigh_ease' => (float) $this->param($params, 'thigh_ease', 16),
            'hem_vs_knee' => -6.0,
            'front_waist' => 'gather',
            'waist_balance' => 0.0,
            'waist_clearance' => 4.0,
            'band' => 'elastic',
            'band_stretch' => (float) $this->param($params, 'band_stretch', 0.85),
        ];

        if ($ankle === 'cuff') {
            $shape['hem_gather'] = $this->m($measurements, 'ankle', 23.5)
                + (float) $this->param($params, 'cuff_ease', 7);
            $shape['hem_band_height'] = $cuffHeight;
            $shape['length_offset'] = -$cuffHeight;
        }

        return $shape;
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $pieces = parent::generate($measurements, $ease, $params);
        $stripe = (float) $this->param($params, 'side_stripe', 2.5);
        $ankle = (string) $this->param($params, 'ankle', 'cuff');

        foreach ($pieces as $index => $piece) {
            $part = (string) ($piece['meta']['part'] ?? '');

            if (! in_array($part, ['front_leg', 'back_leg'], true)) {
                continue;
            }

            $pieces[$index]['meta']['girth_role'] = 'shell';
            $pieces[$index]['meta']['layer_role'] = 'outer_shell';
            $pieces[$index]['meta']['notes'] = array_merge($piece['meta']['notes'] ?? [], $this->shellNotes());

            if ($stripe > 0.1) {
                $pieces[$index]['meta']['side_stripe'] = round($stripe, 2);
                $pieces[$index]['meta']['notes'][] = 'نوار پهلو به پهنای '.$this->fa(round($stripe, 1))
                    .' سانتی‌متر روی درز پهلو دوخته می‌شود؛ روی هر دو پا هم‌تراز خط باسن نشانه بزنید.';
            }

            if ($ankle === 'zip') {
                $pieces[$index] = $this->markAnkleZip($pieces[$index]);
            }
        }

        $extra = [$this->drawcord($measurements)];

        if ($stripe > 0.1) {
            $extra[] = $this->stripePiece($measurements, $stripe);
        }

        return $this->finish($this->withGirthRoles(array_merge($pieces, $extra)));
    }

    /** زیپ کناری دم پا (برای درآوردن شلوار از روی کفش ورزشی). */
    protected function markAnkleZip(array $piece): array
    {
        [, $minY, $maxX, $maxY] = Geometry::bounds($piece['outline']);
        $length = round(min(24.0, max(8.0, ($maxY - $minY) * 0.18)), 1);

        $piece['markers'][] = $this->marker('zip', 'زیپ دم پا', $maxX, $maxY - $length, $maxX, $maxY);
        $piece['meta']['ankle_zip'] = $length;
        $piece['meta']['notions'][] = [
            'type' => 'zip',
            'label' => 'زیپ دم پا',
            'count' => 1,
            'length' => $length,
        ];
        $piece['meta']['notes'][] = 'زیپ دم پا روی درز پهلو می‌نشیند تا شلوار از روی کفش ورزشی درآید.';

        return $piece;
    }

    /** بند کشی کمر. */
    protected function drawcord(array $m): array
    {
        $length = round($this->m($m, 'waist', 74) + 40, 1);

        return $this->piece([
            'code' => 'track-pants-drawcord',
            'name' => 'بند کشی کمر',
            'cut_quantity' => 1,
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point(min(120.0, $length), 0),
                Geometry::point(min(120.0, $length), 3),
                Geometry::point(0, 3),
            ],
            'grainline' => $this->grainline(min(120.0, $length) * 0.5, 0.6, 2.4),
            'meta' => [
                'part' => 'band',
                'edges' => ['default', 'side', 'default', 'side'],
                'fold_edges' => [],
                'girth_role' => 'trim',
                'notions' => [
                    ['type' => 'eyelet', 'label' => 'مغزی سوراخ بند کمر', 'count' => 2],
                ],
                'notes' => [
                    'دو سوراخ بند روی نوار کمر جلو، هرکدام دو سانتی‌متر از خط مرکز جلو، مغزی می‌خورد.',
                    'بدون بند، کمرکشِ شلوار گرمکن هنگام دویدن پایین می‌رود.',
                ],
            ],
        ]);
    }

    /** نوار رنگی پهلو. */
    protected function stripePiece(array $m, float $width): array
    {
        $length = round(max(40.0, $this->m($m, 'outseam', 100)), 1);

        return $this->piece([
            'code' => 'track-pants-stripe',
            'name' => 'نوار پهلو',
            'cut_quantity' => 2,
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point($width, 0),
                Geometry::point($width, $length),
                Geometry::point(0, $length),
            ],
            'grainline' => $this->grainline($width * 0.5, 1, $length - 1),
            'meta' => [
                'part' => 'trim',
                'edges' => ['waist', 'side', 'hem', 'side'],
                'fold_edges' => [],
                'girth_role' => 'trim',
                'notes' => [
                    'روی درز پهلو، از نوار کمر تا دم پا دوخته می‌شود؛ راستای پارچه در طول نوار است.',
                ],
            ],
        ]);
    }
}
