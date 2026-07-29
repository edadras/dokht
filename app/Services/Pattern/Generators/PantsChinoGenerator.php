<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * شلوار چینو.
 *
 * چینو شلوار کتان جلو-صافِ نظامی‌تبار است و سه چیز آن را از شلوار معمولی جدا
 * می‌کند، و هر سه در همین درفت دیده می‌شود:
 *
 *   ۱. جلو ساسون و پیلی ندارد. کاهش کمرِ جلو یک‌سره به درز پهلو و خوابیدن خط
 *      مرکز جلو می‌رود (front_waist = none)، چون روی کتانِ ساده هر ساسونی جلو
 *      خودش را نشان می‌دهد. پشت ساسون می‌پذیرد، ولی فقط اگر کاهش کمرِ باقی‌مانده
 *      از یک سانتی‌متر بیشتر باشد؛ وگرنه همان مقدار با خوابیدنِ خط مرکز پشت
 *      گرفته می‌شود و پشت هم صاف می‌ماند.
 *   ۲. جیب پهلو اریب است، نه در درز. دهانه از خط کمر شروع می‌شود و روی درز پهلو
 *      پایین‌تر می‌نشیند؛ برای همین «کیسه جیب» و «رویه جیب» دو قطعه جدا هستند و
 *      رویه، همان اریب را می‌پوشاند تا آستر از دهانه دیده نشود.
 *   ۳. پا از زانو به پایین کمی باریک می‌شود ولی هرگز تنگ نمی‌شود؛ اختلاف زانو و
 *      دم پا این‌جا پنج سانتی‌متر است، نه بیشتر.
 */
class PantsChinoGenerator extends PantsBaseGenerator
{
    use PieceRoles;

    public static function key(): string
    {
        return 'pants_chino';
    }

    public function label(): string
    {
        return 'شلوار چینو';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->riseParams('mid'),
            $this->legParams(10, 14),
            [
                'thigh_ease' => [
                    'label' => 'آزادی دور ران', 'min' => 5, 'max' => 22, 'step' => 0.5,
                    'default' => 10, 'unit' => 'سانتی‌متر',
                ],
                'pocket_slant' => [
                    'label' => 'اریبی دهانه جیب پهلو', 'min' => 4, 'max' => 16, 'step' => 0.5,
                    'default' => 9, 'unit' => 'سانتی‌متر',
                    'hint' => 'از خط کمر روی درز پهلو پایین می‌آید؛ بیشتر یعنی دهانه اریب‌تر و بازتر.',
                ],
                'back_welt' => [
                    'label' => 'جیب فیلتاب پشت', 'type' => 'toggle', 'default' => true,
                ],
                'back_darts' => [
                    'label' => 'تعداد ساسون پشت', 'min' => 1, 'max' => 2, 'step' => 1, 'default' => 2,
                ],
            ],
            $this->bandParams(4),
        );
    }

    protected function shape(array $params, array $measurements): array
    {
        return [
            'thigh_ease' => (float) $this->param($params, 'thigh_ease', 10),
            // زانو تا دم پا پنج سانتی‌متر جمع می‌شود: باریک‌شونده، ولی نه تنگ
            'hem_vs_knee' => -5.0,
            'front_waist' => 'none',
            'back_waist' => 'dart',
            'waist_balance' => 0.4,
            'side_share' => 0.34,
            'lean_share' => 0.2,
            'dart_count' => (int) $this->param($params, 'back_darts', 2),
            'scoop_front' => 0.58,
        ];
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $pieces = parent::generate($measurements, $ease, $params);
        $slant = (float) $this->param($params, 'pocket_slant', 9);

        foreach ($pieces as $index => $piece) {
            if (($piece['meta']['part'] ?? '') !== 'front_leg') {
                continue;
            }

            $pieces[$index] = $this->markSlantPocket($piece, $slant);
        }

        $extra = [
            $this->pocketBag($measurements, $slant),
            $this->pocketFacing($slant),
        ];

        if ($this->flag($params, 'back_welt', true)) {
            $extra[] = $this->weltPiece();
            $extra[] = $this->backPocketBag();
        }

        return $this->finish($this->withGirthRoles(array_merge($pieces, $extra)));
    }

    /**
     * خط دهانه جیب اریب روی پای جلو.
     *
     * دهانه از خط کمر شروع می‌شود و روی درز پهلو به اندازه «اریبی» پایین می‌آید.
     * قطعه جدا نمی‌شود چون چینو جیبِ برشی ندارد: پارچه بریده و رویه پشتش دوخته
     * می‌شود، پس فقط خط برش و نشانه‌اش لازم است.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function markSlantPocket(array $piece, float $slant): array
    {
        $sideEdges = $piece['meta']['side_edges'] ?? [];

        if ($sideEdges === []) {
            return $piece;
        }

        $topEdge = (int) $sideEdges[0];
        $sideLength = max(0.01, Geometry::edgeLength($piece['outline'], $topEdge));
        $from = Geometry::pointOnEdge($piece['outline'], 0, 0.45);
        $to = Geometry::pointOnEdge($piece['outline'], $topEdge, min(0.95, $slant / $sideLength));

        $piece['markers'][] = $this->marker(
            'pocket',
            'دهانه جیب پهلو',
            (float) $from['x'],
            (float) $from['y'],
            (float) $to['x'],
            (float) $to['y'],
        );

        $piece['notches'][] = $this->notch((float) $from['x'], (float) $from['y'], 0, 'سر دهانه جیب روی کمر', 'pocket_top');
        $piece['notches'][] = $this->notch((float) $to['x'], (float) $to['y'], $topEdge, 'ته دهانه جیب روی پهلو', 'pocket_side');

        $piece['meta']['pocket'] = [
            'type' => 'slant',
            'label' => 'جیب اریب پهلو',
            'opening' => round(Geometry::distance(
                ['x' => (float) $from['x'], 'y' => (float) $from['y']],
                ['x' => (float) $to['x'], 'y' => (float) $to['y']],
            ), 2),
            'slant' => round($slant, 2),
        ];
        $piece['meta']['notes'] = array_merge($piece['meta']['notes'] ?? [], [
            'پارچه بالای خط دهانه جیب بریده می‌شود؛ کیسه و رویه جیب پشت همان خط دوخته می‌شوند.',
        ]);

        return $piece;
    }

    /** کیسه جیب پهلو: یک تکه که از تا برمی‌گردد و هر دو لایه را می‌سازد. */
    protected function pocketBag(array $m, float $slant): array
    {
        $depth = max(20.0, min(30.0, $this->m($m, 'waist_to_hip', 21) + 6));
        $width = 16.0;

        return $this->piece([
            'code' => 'chino-pocket-bag',
            'name' => 'کیسه جیب پهلو',
            'cut_quantity' => 2,
            'mirror' => true,
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point($width, 0),
                Geometry::point($width, $depth - 4),
                Geometry::curve(0, $depth, $width * 0.55, $depth),
            ],
            'grainline' => $this->grainline($width * 0.5, 1.5, $depth - 2),
            'markers' => [$this->marker('fold', 'خط تای کیسه', 0, $depth * 0.5, $width)],
            'meta' => [
                'part' => 'pocket',
                'edges' => ['waist', 'side', 'default', 'default'],
                'fold_edges' => [],
                'girth_role' => 'trim',
                'notions' => [],
                'notes' => [
                    'از آستر جیب بریده می‌شود؛ دهانه اریب '.$this->fa(round($slant, 1)).' سانتی‌متری روی همین کیسه می‌نشیند.',
                ],
            ],
        ]);
    }

    /** رویه جیب: تکه‌ای از پارچه رو که پشت دهانه اریب دوخته می‌شود. */
    protected function pocketFacing(float $slant): array
    {
        $width = 13.0;
        $height = max(14.0, $slant + 8);

        return $this->piece([
            'code' => 'chino-pocket-facing',
            'name' => 'رویه جیب پهلو',
            'cut_quantity' => 2,
            'mirror' => true,
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point($width, 0),
                Geometry::point($width, $height),
                Geometry::point(0, $height * 0.65),
            ],
            'grainline' => $this->grainline($width * 0.5, 1.5, $height * 0.6),
            'meta' => [
                'part' => 'pocket',
                'edges' => ['default', 'side', 'hem', 'default'],
                'fold_edges' => [],
                'girth_role' => 'trim',
                'notes' => ['از پارچه رو بریده می‌شود تا از دهانه جیب، آستر دیده نشود.'],
            ],
        ]);
    }

    /** فیلتاب جیب پشت. */
    protected function weltPiece(): array
    {
        return $this->piece([
            'code' => 'chino-back-welt',
            'name' => 'فیلتاب جیب پشت',
            'cut_quantity' => 2,
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
                'notes' => ['دولا و لایی‌دار؛ دهانه جیب پشت ۱۳ سانتی‌متر است و دو سرش پس‌دوزی می‌شود.'],
                'notions' => [['type' => 'button', 'label' => 'دکمه جیب پشت', 'count' => 1, 'per_cut' => true]],
            ],
        ]);
    }

    /** کیسه جیب پشت. */
    protected function backPocketBag(): array
    {
        return $this->piece([
            'code' => 'chino-back-pocket-bag',
            'name' => 'کیسه جیب پشت',
            'cut_quantity' => 2,
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point(17, 0),
                Geometry::point(17, 32),
                Geometry::point(0, 32),
            ],
            'grainline' => $this->grainline(8.5, 1.5, 30),
            'markers' => [$this->marker('fold', 'خط تای کیسه', 0, 16, 17)],
            'meta' => [
                'part' => 'pocket',
                'edges' => ['default', 'side', 'default', 'side'],
                'fold_edges' => [],
                'girth_role' => 'trim',
                'notes' => ['از خط تا برمی‌گردد و دو لایه کیسه را می‌سازد.'],
            ],
        ]);
    }
}
