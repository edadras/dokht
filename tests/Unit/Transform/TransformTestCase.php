<?php

namespace Tests\Unit\Transform;

use App\Services\Pattern\Geometry;
use App\Services\Pattern\Transform\DartTool;
use Tests\TestCase;

/**
 * پایه آزمون‌های ابزار تغییرشکل.
 *
 * قطعه‌های نمونه اینجا عمداً لبه صاف دارند و مختصات‌شان عدد گرد است، تا هر
 * اندازه‌ای که آزمون می‌گیرد را بشود روی کاغذ هم حساب کرد و رواداری‌ها به
 * تقریب خط شکسته کردنِ منحنی آلوده نشود. هرجا منحنی لازم باشد، جداگانه ساخته
 * می‌شود و رواداری آن در همان آزمون توضیح داده شده است.
 */
abstract class TransformTestCase extends TestCase
{
    /** رواداری هندسی عمومی (سانتی‌متر یا سانتی‌متر مربع). */
    public const TOLERANCE = 0.01;

    /**
     * مساحت با دقت بالا: منحنی‌ها با ۸۰۰ پاره‌خط باز می‌شوند تا خطای گسسته‌سازی
     * زیر ۱۰⁻⁴ بماند و بشود روی مساحت رواداری تنگ گذاشت.
     */
    protected function preciseArea(array $outline): float
    {
        return abs(Geometry::signedArea(Geometry::flatten($outline, 800)));
    }

    /** مساحت دوخته‌شده با دقت بالا: مساحت مسیر منهای مثلث ساسون‌ها. */
    protected function preciseNetArea(array $piece): float
    {
        $area = $this->preciseArea($piece['outline']);

        foreach ($piece['darts'] ?? [] as $dart) {
            $area -= DartTool::wedgeArea($dart);
        }

        return $area;
    }

    /**
     * «دور سینه» تمام‌شده حول یک نوک ساسون.
     *
     * روی الگوی تخت، اندازه واقعی بدن در خط سینه از نوک برجستگی تا خط مرکز و از
     * نوک تا درز پهلو گرفته می‌شود؛ چرخاندن ساسون یک چرخش صُلب حول همان نوک است،
     * پس هر دو شعاع باید دست‌نخورده بمانند. برای یک‌چهارم بالاتنه، دور کامل چهار
     * برابر مجموع این دو شعاع است.
     */
    protected function bustGirthAround(array $piece, array $apex): float
    {
        return 4 * ($this->distanceToTag($piece, 'default', $apex) + $this->seamRadius($piece, 'side', $apex));
    }

    /**
     * فاصله تمام‌شده نوک ساسون تا یک درز.
     *
     * اگر دهانه ساسونی روی همان درز نشسته باشد، اندازه تا پاهای ساسون گرفته
     * می‌شود، نه تا وسط دهانه؛ چون با دوخته شدن ساسون همان دو پا روی هم می‌افتند
     * و همان نقطه، درزِ تمام‌شده در خط سینه است. اندازه‌گرفتن تا وسط دهانه به
     * اندازه بلندای وترِ دهانه (اینجا ۱٫۷ میلی‌متر) کم می‌آورد.
     */
    protected function seamRadius(array $piece, string $tag, array $point): float
    {
        $edges = Geometry::edgesWithTag($piece, $tag);

        foreach ($piece['darts'] ?? [] as $dart) {
            $legs = array_values($dart['legs'] ?? []);

            if (count($legs) !== 2) {
                continue;
            }

            $onSeam = true;

            foreach ($legs as $leg) {
                $near = Geometry::nearestEdge($piece['outline'], ['x' => (float) $leg['x'], 'y' => (float) $leg['y']]);

                if ($near['distance'] > 0.15 || ! in_array($near['edge'], $edges, true)) {
                    $onSeam = false;
                    break;
                }
            }

            if ($onSeam) {
                return (Geometry::distance($point, $legs[0]) + Geometry::distance($point, $legs[1])) / 2;
            }
        }

        return $this->distanceToTag($piece, $tag, $point);
    }

    /** کوتاه‌ترین فاصله یک نقطه تا لبه‌های دارای این برچسب. */
    protected function distanceToTag(array $piece, string $tag, array $point): float
    {
        $best = INF;

        foreach (Geometry::edgesWithTag($piece, $tag) as $edge) {
            $t = Geometry::edgeParameterOf($piece['outline'], $edge, $point, 128);
            $best = min($best, Geometry::distance(Geometry::pointOnEdge($piece['outline'], $edge, $t), $point));
        }

        return $best === INF ? 0.0 : $best;
    }

    /**
     * نیم‌بالاتنه جلو با لبه‌های صاف و ساسون سینه روی درز پهلو.
     *
     * مساحت مسیر دقیقاً ۹۴۰ سانتی‌متر مربع و مثلث ساسون دقیقاً ۲۴ است، پس مساحت
     * دوخته‌شده ۹۱۶ می‌شود.
     *
     * @return array<string, mixed>
     */
    protected function block(): array
    {
        return [
            'code' => 'block',
            'name' => 'بالاتنه نمونه',
            'layer' => 'outer',
            'cut_quantity' => 1,
            'on_fold' => true,
            'mirror' => false,
            'outline' => [
                Geometry::point(0, 8),   // ۰ خط مرکز جلو، سر یقه
                Geometry::point(7, 0),   // ۱ گردن روی سرشانه
                Geometry::point(18, 4),  // ۲ سر سرشانه
                Geometry::point(24, 18), // ۳ زیر بغل
                Geometry::point(24, 24), // ۴ پای بالای ساسون سینه
                Geometry::point(24, 28), // ۵ پای پایین ساسون سینه
                Geometry::point(24, 44), // ۶ پهلو روی خط کمر
                Geometry::point(0, 44),  // ۷ کمر روی خط مرکز جلو
            ],
            'grainline' => [
                'from' => Geometry::point(10, 6),
                'to' => Geometry::point(10, 40),
                'label' => 'راستای پارچه',
            ],
            'darts' => [[
                'type' => 'bust',
                'label' => 'ساسون سینه',
                'edge' => 4,
                'axis' => 'y',
                'intake' => 4.0,
                'center' => Geometry::point(24, 26),
                'apex' => Geometry::point(12, 26),
                'legs' => [Geometry::point(24, 24), Geometry::point(24, 28)],
            ]],
            'notches' => [
                ['x' => 12.5, 'y' => 2.0, 'edge' => 1, 'label' => 'نشانه سرشانه', 'pair' => 'shoulder'],
            ],
            'drills' => [],
            'pleats' => [],
            'markers' => [
                ['key' => 'bust', 'label' => 'خط سینه', 'from' => Geometry::point(0, 26), 'to' => Geometry::point(24, 26)],
            ],
            'edge_allowances' => [],
            'meta' => [
                'part' => 'front_bodice',
                'edges' => ['neck', 'shoulder', 'armhole', 'side', 'side', 'side', 'waist', 'default'],
                'fold_edges' => [7],
            ],
            'sort' => 0,
        ];
    }

    /**
     * مستطیل ساده ۱۰×۲۰ با یک لبه منحنی سمت راست.
     *
     * @return array<string, mixed>
     */
    protected function curvedPanel(): array
    {
        return [
            'code' => 'panel',
            'name' => 'پانل نمونه',
            'layer' => 'outer',
            'cut_quantity' => 1,
            'on_fold' => false,
            'mirror' => false,
            'outline' => [
                Geometry::point(0, 0),
                Geometry::curve(10, 4, 6, 0),
                Geometry::point(8, 20),
                Geometry::point(0, 20),
            ],
            'grainline' => [
                'from' => Geometry::point(2, 1),
                'to' => Geometry::point(2, 19),
                'label' => 'راستای پارچه',
            ],
            'darts' => [],
            'notches' => [],
            'drills' => [],
            'pleats' => [],
            'markers' => [],
            'edge_allowances' => [],
            'meta' => [
                'part' => 'panel',
                'edges' => ['neck', 'side', 'hem', 'default'],
                'fold_edges' => [3],
            ],
            'sort' => 0,
        ];
    }
}
