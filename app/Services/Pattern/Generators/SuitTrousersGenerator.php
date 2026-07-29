<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * شلوار کت‌وشلوار.
 *
 * شلوار رسمی روی خط اتو زندگی می‌کند، نه روی درز. سه تصمیم از همین می‌آید:
 *
 *   ۱. یک پیلی رو به مرکز جلو، دقیقاً روی خط اتوی پا. پیلی همان‌قدر پارچه
 *      می‌خورد که ساسون می‌خورد، پس دور کمر تمام‌شده عوض نمی‌شود؛ ولی خطِ اتو از
 *      کمر تا دم پا یک‌سره می‌ماند و همان است که شلوار را «رسمی» نشان می‌دهد.
 *   ۲. باریک‌شدنِ ملایم: زانو تا دم پا فقط چهار سانتی‌متر جمع می‌شود. شلوار رسمیِ
 *      تنگ‌شونده روی کفش می‌شکند.
 *   ۳. کمربندِ دوخته با زبانهٔ رویهم و جادکمه، حلقهٔ کمربند و زیپ جلو — نه کمر
 *      کشی.
 *
 * برگردان دم پا (دوبل) اختیاری است و اگر روشن باشد، پارچهٔ لازمش به قد پا اضافه
 * می‌شود؛ وگرنه شلوار به اندازهٔ همان برگردان کوتاه درمی‌آید.
 */
class SuitTrousersGenerator extends PantsBaseGenerator
{
    use PieceRoles;

    /** این مدل در فهرست، زیر «کت‌وشلوار» می‌نشیند نه زیر «پایین‌تنه». */
    public static function group(): string
    {
        return 'suit';
    }

    public static function key(): string
    {
        return 'suit_trousers';
    }

    public function label(): string
    {
        return 'شلوار کت‌وشلوار';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->riseParams('mid'),
            $this->legParams(12, 16),
            [
                'thigh_ease' => [
                    'label' => 'آزادی دور ران', 'min' => 6, 'max' => 24, 'step' => 0.5,
                    'default' => 12, 'unit' => 'سانتی‌متر',
                ],
                'front_pleats' => [
                    'label' => 'تعداد پیلی جلو', 'min' => 0, 'max' => 2, 'step' => 1, 'default' => 1,
                    'hint' => 'صفر یعنی جلو صاف؛ آن‌وقت کاهش کمر به درز پهلو و شیب فاق می‌رود.',
                ],
                'back_darts' => [
                    'label' => 'تعداد ساسون پشت', 'min' => 1, 'max' => 2, 'step' => 1, 'default' => 2,
                ],
                'cuff' => [
                    'label' => 'برگردان دم پا (دوبل)', 'min' => 0, 'max' => 6, 'step' => 0.5,
                    'default' => 4, 'unit' => 'سانتی‌متر',
                    'hint' => 'صفر یعنی بدون برگردان؛ پارچهٔ لازمش خودکار به قد پا اضافه می‌شود.',
                ],
                'belt_loops' => [
                    'label' => 'تعداد حلقه کمربند', 'min' => 0, 'max' => 8, 'step' => 1, 'default' => 6,
                ],
            ],
            $this->bandParams(4),
        );
    }

    protected function shape(array $params, array $measurements): array
    {
        $pleats = (int) $this->param($params, 'front_pleats', 1);
        $cuff = (float) $this->param($params, 'cuff', 4);

        return [
            'thigh_ease' => (float) $this->param($params, 'thigh_ease', 12),
            // زانو تا دم پا فقط چهار سانتی‌متر: باریک‌شونده ولی نه تنگ
            'hem_vs_knee' => -4.0,
            'front_waist' => $pleats > 0 ? 'pleat' : 'none',
            'pleat_count' => max(1, $pleats),
            'pleat_style' => 'knife',
            'back_waist' => 'dart',
            'dart_count' => (int) $this->param($params, 'back_darts', 2),
            'waist_balance' => 0.4,
            'side_share' => 0.3,
            'lean_share' => 0.15,
            // پارچهٔ برگردان از خودِ پا برداشته می‌شود، پس قد پا همان‌قدر بلندتر بریده می‌شود
            'length_offset' => $cuff * 2,
        ];
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $pieces = parent::generate($measurements, $ease, $params);
        $cuff = (float) $this->param($params, 'cuff', 4);

        foreach ($pieces as $index => $piece) {
            if (! in_array($piece['meta']['part'] ?? '', ['front_leg', 'back_leg'], true)) {
                continue;
            }

            $pieces[$index]['meta']['girth_role'] = 'shell';
            $pieces[$index]['meta']['notes'] = array_merge($piece['meta']['notes'] ?? [], [
                'خط اتوی پا از کمر تا دم پا یک‌سره اتو می‌شود؛ در شلوار رسمی همین خط است که دیده می‌شود، نه درز.',
            ]);

            if ($cuff > 0.1) {
                $pieces[$index] = $this->markCuff($pieces[$index], $cuff);
            }
        }

        $extra = [$this->pocketBag($measurements), $this->weltPiece()];

        $loops = (int) $this->param($params, 'belt_loops', 6);

        if ($loops > 0) {
            $extra[] = $this->beltLoops($params, $loops);
        }

        return $this->finish($this->withGirthRoles(array_merge($pieces, $extra)));
    }

    /**
     * خط برگردان دم پا.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function markCuff(array $piece, float $cuff): array
    {
        [$minX, , $maxX, $maxY] = Geometry::bounds($piece['outline']);

        $piece['markers'][] = $this->marker('cuff_fold', 'خط تای برگردان', $minX, $maxY - $cuff, $maxX);
        $piece['markers'][] = $this->marker('cuff_line', 'خط پایانی برگردان', $minX, $maxY - ($cuff * 2), $maxX);
        $piece['meta']['cuff'] = round($cuff, 2);
        $piece['meta']['notes'][] = 'برگردان دم پا '.$this->fa(round($cuff, 1))
            .' سانتی‌متر است؛ قد پا به اندازهٔ دو برابر آن بلندتر بریده شده تا پس از برگرداندن، قد درست دربیاید.';

        return $piece;
    }

    /** کیسه جیب پهلوی درزی. */
    protected function pocketBag(array $m): array
    {
        $depth = max(22.0, min(32.0, $this->m($m, 'waist_to_hip', 21) + 8));
        $width = 17.0;

        return $this->piece([
            'code' => 'suit-trousers-pocket-bag',
            'name' => 'کیسه جیب پهلو',
            'cut_quantity' => 4,
            'mirror' => true,
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point($width, 0),
                Geometry::point($width, $depth - 5),
                Geometry::curve(0, $depth, $width * 0.55, $depth),
            ],
            'grainline' => $this->grainline($width * 0.5, 1.5, $depth - 2),
            'meta' => [
                'part' => 'pocket',
                'edges' => ['waist', 'side', 'default', 'default'],
                'fold_edges' => [],
                'girth_role' => 'trim',
                'notes' => [
                    'چهار تکه (دو لایه برای هر جیب)؛ دهانهٔ جیب روی خودِ درز پهلو، از خط کمر تا شانزده سانتی‌متر پایین‌تر است.',
                    'لایهٔ رویی نواری از پارچهٔ شلوار می‌گیرد تا از دهانه، آستر دیده نشود.',
                ],
            ],
        ]);
    }

    /** فیلتاب جیب پشت. */
    protected function weltPiece(): array
    {
        return $this->piece([
            'code' => 'suit-trousers-welt',
            'name' => 'فیلتاب جیب پشت',
            'cut_quantity' => 4,
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point(17, 0),
                Geometry::point(17, 5),
                Geometry::point(0, 5),
            ],
            'grainline' => $this->grainline(8.5, 0.8, 4.2),
            'markers' => [$this->marker('fold', 'خط تای فیلتاب', 0, 2.5, 17)],
            'meta' => [
                'part' => 'pocket',
                'edges' => ['default', 'side', 'default', 'side'],
                'fold_edges' => [],
                'interfacing' => true,
                'girth_role' => 'trim',
                'notes' => [
                    'دو فیلتاب برای هر جیب پشت؛ دهانهٔ جیب سیزده سانتی‌متر، هفت سانتی‌متر پایین‌تر از خط کمر.',
                ],
                'notions' => [['type' => 'button', 'label' => 'دکمه جیب پشت', 'count' => 2]],
            ],
        ]);
    }

    /** حلقه‌های کمربند. */
    protected function beltLoops(array $params, int $count): array
    {
        $height = (float) $this->param($params, 'waistband_height', 4);
        $length = $height + 3;

        return $this->piece([
            'code' => 'suit-trousers-belt-loops',
            'name' => 'حلقه کمربند',
            'cut_quantity' => $count,
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point(3.5, 0),
                Geometry::point(3.5, $length),
                Geometry::point(0, $length),
            ],
            'grainline' => $this->grainline(1.75, 0.6, $length - 0.6),
            'meta' => [
                'part' => 'belt',
                'edges' => ['default', 'side', 'default', 'side'],
                'fold_edges' => [],
                'girth_role' => 'trim',
                'notes' => [
                    $this->fa($count).' حلقه: دو تا جلو، دو تا روی درز پهلو و بقیه روی پشت، هم‌فاصله.',
                    'هر حلقه از سه طرف تا می‌خورد و پهنای تمام‌شده‌اش یک سانتی‌متر می‌شود.',
                ],
            ],
        ]);
    }
}
