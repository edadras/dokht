<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Generators\Concerns\RecordsGathers;
use App\Services\Pattern\Geometry;

/**
 * پایه مشترک لباس‌های سنتی و پوشیده.
 *
 * این خانواده یک چیز دارد که بقیه کاتالوگ ندارد: **پوشیدگی، ادعا نیست؛ اندازه
 * است**. لباسی که «پوشیده» نامیده می‌شود باید بگوید لبه پایینش چند سانتی‌متر
 * پایین‌تر از سرشانه می‌ایستد، آستینش تا کجا می‌رسد و سر و گردن را می‌پوشاند یا
 * نه. همه این‌ها روی `meta.coverage` می‌نشیند و به فارسی هم در `meta.notes`
 * نوشته می‌شود، تا خریدار الگو بتواند پیش از برش بسنجدش.
 *
 * سه ابزار مشترک این‌جاست که در کاتالوگ نظیر ندارند:
 *
 *   markCoverage()    ثبت پوشیدگی سنجیدنی روی یک قطعه.
 *   headCoverPiece()  سرپوش یک‌تکه با جای صورت (خمار، چادر نماز، سرپوش جلباب):
 *                     نیم‌دایره‌ای روی تای پارچه که جای صورت را از خودِ تا
 *                     می‌بُرد، پس باز که شود یک دایره کامل با سوراخ بیضی صورت
 *                     می‌شود.
 *   shalwarPieces()   شلوار سنتی: چهار پنل راست، لِنگه (گاسِت) فاق و نیفه بند.
 *                     شلوار سنتی منحنی فاق ندارد؛ آزادی‌اش از لِنگه می‌آید.
 *
 * قرارداد هندسه همان قرارداد سراسری است: سانتی‌متر، x به راست، y به پایین،
 * مبدأ گوشه بالا-چپ هر قطعه، مسیر بسته و نقطه کنترل چسبیده به نقطه‌ای که به آن
 * می‌رسد.
 */
abstract class TraditionalBaseGenerator extends BodiceGarmentBase
{
    use RecordsGathers;

    /** گروه فهرست مدل‌ها. */
    public static function group(): string
    {
        return 'traditional';
    }

    /* ---------------------------------------------------------------------
     |  پوشیدگی
     * ------------------------------------------------------------------- */

    /**
     * ثبت «تا کجا را می‌پوشاند» روی یک قطعه.
     *
     * گزینه‌ها: hem (سانتی‌متر از سرشانه تا لبه پایین)، hem_at (نام همان جا روی
     * تن)، sleeve (آستین تا کجا)، neck (وضعیت یقه)، head (سر و گردن).
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function markCoverage(array $piece, array $o = []): array
    {
        $coverage = array_filter([
            'hem_from_shoulder' => isset($o['hem']) ? round((float) $o['hem'], 1) : null,
            'hem_at' => $o['hem_at'] ?? null,
            'sleeve_to' => $o['sleeve'] ?? null,
            'neck' => $o['neck'] ?? null,
            'head' => $o['head'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        if ($coverage === []) {
            return $piece;
        }

        $piece['meta']['coverage'] = $coverage;

        $said = [];

        if (isset($coverage['hem_from_shoulder'])) {
            $said[] = 'لبه پایین '.$this->fa($coverage['hem_from_shoulder']).' سانتی‌متر پایین‌تر از سرشانه'
                .(isset($coverage['hem_at']) ? ' ('.$coverage['hem_at'].')' : '');
        }

        foreach (['sleeve_to' => 'آستین تا ', 'neck' => 'یقه ', 'head' => ''] as $key => $prefix) {
            if (isset($coverage[$key])) {
                $said[] = $prefix.$coverage[$key];
            }
        }

        $piece['meta']['notes'] = array_merge($piece['meta']['notes'] ?? [], [
            'پوشیدگی این الگو: '.implode('؛ ', $said).'.',
        ]);

        return $piece;
    }

    /**
     * بلندی لبه پایین از سرشانه، از روی خودِ قطعه.
     *
     * مبدأ هر پنل گوشه بالا-چپ خودش است و بالاترین نقطه همان خط سرشانه/گردن
     * است، پس ارتفاع کادر قطعه همان بلندی از سرشانه است — نه عددی که ما اعلام
     * می‌کنیم.
     *
     * @param  array<string, mixed>  $piece
     */
    protected function hemFromShoulder(array $piece): float
    {
        return round(Geometry::height($piece['outline'] ?? []), 1);
    }

    /* ---------------------------------------------------------------------
     |  سرپوش با جای صورت
     * ------------------------------------------------------------------- */

    /**
     * سرپوش یک‌تکه با جای صورت.
     *
     * پارچه یک نیم‌دایره است که روی تای پارچه بریده می‌شود؛ جای صورت هم روی
     * همان تا می‌نشیند، پس وقتی پارچه باز شود یک دایره کامل با یک سوراخ بیضی
     * برای صورت به دست می‌آید. مرکز سوراخ کمی به سمت جلو جابه‌جا شده تا پشت
     * بلندتر از جلو بیفتد — همان چیزی که خمار و چادر نماز را از یک پانچوی ساده
     * جدا می‌کند.
     *
     * گزینه‌ها: radius (شعاع از فرق سر تا لبه)، face_width و face_height (پهنا
     * و بلندی کامل جای صورت)، face_offset (جابه‌جایی جای صورت به جلو)، head
     * (دور سر، برای اینکه سوراخ از سر کوچک‌تر نشود)، code، name، prefix.
     *
     * @return array<string, mixed>
     */
    protected function headCoverPiece(array $o = []): array
    {
        $radius = max(26.0, (float) ($o['radius'] ?? 70));
        $head = max(38.0, (float) ($o['head'] ?? 56));

        $halfWidth = max(6.0, ((float) ($o['face_width'] ?? 17)) / 2);
        $halfHeight = max(8.0, ((float) ($o['face_height'] ?? 24)) / 2);

        // سوراخ صورت باید از دور سر بزرگ‌تر باشد وگرنه سر از آن رد نمی‌شود.
        // دو نیم‌محور با هم بزرگ می‌شوند تا شکل بیضی به هم نریزد.
        $opening = $this->ellipsePerimeter($halfWidth, $halfHeight);

        if ($opening < $head * 1.02) {
            $scale = ($head * 1.02) / max(1.0, $opening);
            $halfWidth *= $scale;
            $halfHeight *= $scale;
            $opening = $this->ellipsePerimeter($halfWidth, $halfHeight);
        }

        $halfWidth = min($halfWidth, $radius * 0.5);
        $halfHeight = min($halfHeight, $radius * 0.42);

        // مرکز سوراخ روی خط تا، کمی جلوتر از مرکز دایره
        $offset = max(0.0, min((float) ($o['face_offset'] ?? $radius * 0.16), $radius - $halfHeight - 6));
        $centerY = $radius - $offset;

        $outline = [Geometry::point(0, 0)];
        $edges = [];

        foreach (array_slice($this->arcPoints(0, $radius, $radius, -$radius, 0, M_PI, 20), 1) as $point) {
            $outline[] = Geometry::point($point['x'], $point['y']);
            $edges[] = 'hem';
        }

        $outline[] = Geometry::point(0, $centerY + $halfHeight);
        $edges[] = 'default'; // خط تای پارچه، پشتِ سرپوش

        foreach (array_slice($this->arcPoints(0, $centerY, $halfWidth, $halfHeight, 0, M_PI, 12), 1) as $point) {
            $outline[] = Geometry::point($point['x'], $point['y']);
            $edges[] = 'neck'; // لبه صورت
        }

        $edges[] = 'default'; // خط تای پارچه، جلوی سرپوش

        $foldEdges = [count($edges) - 1];
        $foldEdges[] = (int) array_search('default', $edges, true);

        $frontDrop = round($centerY - $halfHeight, 1);
        $backDrop = round((2 * $radius) - ($centerY + $halfHeight), 1);

        $piece = $this->piece([
            'code' => ($o['prefix'] ?? '').($o['code'] ?? 'head-cover'),
            'name' => $o['name'] ?? 'سرپوش',
            'cut_quantity' => 1,
            'on_fold' => true,
            'outline' => $outline,
            'grainline' => $this->grainline($halfWidth * 0.5, $centerY + $halfHeight + 2, (2 * $radius) - 3),
            'notches' => [
                $this->notch(0, $centerY - $halfHeight, count($edges) - 1, 'وسط جلوی لبه صورت', 'face_front'),
                $this->notch(0, $centerY + $halfHeight, $foldEdges[1], 'وسط پشتِ لبه صورت', 'face_back'),
            ],
            'markers' => [
                $this->marker('fold', 'خط تای پارچه', 0, 0, 0, 2 * $radius),
            ],
            'meta' => [
                'part' => $o['part'] ?? 'head_cover',
                'edges' => $edges,
                'fold_edges' => array_values(array_unique($foldEdges)),
                'girth_role' => 'trim',
                'face_opening' => round($opening, 1),
                'front_drop' => $frontDrop,
                'back_drop' => $backDrop,
                'radius' => round($radius, 1),
                'notions' => $o['notions'] ?? [],
                'notes' => array_merge([
                    'روی تای پارچه بریده می‌شود؛ جای صورت هم از همان تا بریده می‌شود، پس باز که شود یک دایره با سوراخ صورت است.',
                    'دور جای صورت '.$this->fa(round($opening)).' سانتی‌متر است و برای سری به دور '
                        .$this->fa(round($head)).' سانتی‌متر باز می‌شود.',
                    'از لبه صورت تا لبه پایین: جلو '.$this->fa($frontDrop).' و پشت '
                        .$this->fa($backDrop).' سانتی‌متر؛ پشت عمداً بلندتر است.',
                ], $o['notes'] ?? []),
            ],
        ]);

        return $piece;
    }

    /** محیط بیضی به روش رامانوجان؛ برای اندازه جای صورت. */
    protected function ellipsePerimeter(float $a, float $b): float
    {
        return M_PI * ((3 * ($a + $b)) - sqrt(((3 * $a) + $b) * ($a + (3 * $b))));
    }

    /**
     * نقطه‌های یک کمان بیضی.
     *
     * زاویه صفر روی نقطه (cx، cy+ry) می‌افتد و با بزرگ شدن زاویه به سمت راست
     * می‌چرخد؛ برای منفی دادن ry، کمان به پایین باز می‌شود.
     *
     * @return array<int, array{x: float, y: float}>
     */
    protected function arcPoints(float $cx, float $cy, float $rx, float $ry, float $from, float $to, int $steps): array
    {
        $points = [];

        for ($i = 0; $i <= $steps; $i++) {
            $angle = $from + (($to - $from) * ($i / max(1, $steps)));
            $points[] = [
                'x' => round($cx + ($rx * sin($angle)), 3),
                'y' => round($cy + ($ry * cos($angle)), 3),
            ];
        }

        return $points;
    }

    /* ---------------------------------------------------------------------
     |  شلوار سنتی
     * ------------------------------------------------------------------- */

    /**
     * شلوار سنتی (شلوارِ شلوار کمیز، شلوار زیر جلباب).
     *
     * این شلوار با بلوک شلوار درفت نمی‌شود و نباید بشود: منحنی فاق ندارد. چهار
     * پنل راست است که درز داخل پایشان عمودی است، و همه آزادی نشستن و چمباتمه از
     * یک «لِنگه» مربعی می‌آید که در فاق دوخته می‌شود. کمر با نیفه و بند جمع
     * می‌شود، و مچ پا در یک نوار باریک جمع می‌شود.
     *
     * گزینه‌ها: fullness (نسبت پُری کمر به دور باسن)، length (قد از کمر تا مچ)،
     * ankle (دور تمام‌شده مچ پا)، gusset (اندازه لِنگه)، casing (بلندی نیفه)،
     * prefix.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function shalwarPieces(array $m, array $o = []): array
    {
        $prefix = (string) ($o['prefix'] ?? 'shalwar-');
        $hip = $this->m($m, 'hip', 98);
        $fullness = max(1.2, min(2.6, (float) ($o['fullness'] ?? 1.75)));
        $length = max(40.0, (float) ($o['length'] ?? $this->m($m, 'outseam', 104)));
        $ankle = max(16.0, (float) ($o['ankle'] ?? $this->m($m, 'ankle', 23.5) + 6));
        $gusset = max(10.0, (float) ($o['gusset'] ?? 16));
        $casing = max(3.0, (float) ($o['casing'] ?? 5));

        $taper = max(1.0, min(2.0, (float) ($o['ankle_gather'] ?? 1.5)));
        $topWidth = ($hip * $fullness) / 4;
        $hemWidth = ($ankle * $taper) / 2;
        $body = max(20.0, $length - $casing);
        $waistGather = max(0.0, $topWidth - ($this->m($m, 'waist', 74) / 4));
        $ankleGather = max(0.0, $hemWidth - ($ankle / 2));

        $pieces = [];

        foreach ([['front', 'front_leg', 'پاچه جلو'], ['back', 'back_leg', 'پاچه پشت']] as [$side, $part, $name]) {
            $outline = [
                Geometry::point(0, 0),
                Geometry::point($topWidth, 0),
                Geometry::point($hemWidth, $body),
                Geometry::point(0, $body),
            ];

            $leg = $this->piece([
                'code' => $prefix.'leg-'.$side,
                'name' => 'شلوار — '.$name,
                'cut_quantity' => 2,
                'mirror' => true,
                'outline' => $outline,
                'grainline' => $this->grainline($topWidth * 0.3, 2, $body - 2),
                'notches' => [
                    $this->notch(0, $gusset, 3, 'سر لِنگه روی درز داخل پا', 'gusset'),
                ],
                'markers' => [
                    $this->marker('waist', 'خط کمر', 0, 0, $topWidth),
                ],
                'meta' => [
                    'part' => $part,
                    'side' => $side,
                    'edges' => ['waist', 'side', 'hem', 'side'],
                    'fold_edges' => [],
                    'girth_role' => 'shell',
                    'girth' => [],
                    'girth_factor' => 0,
                    'notes' => [
                        'درز داخل پا صاف و عمودی است؛ فاق منحنی ندارد و آزادی نشستن از لِنگه می‌آید.',
                        'لِنگه از خط کمر '.$this->fa(round($gusset)).' سانتی‌متر پایین‌تر، روی درز داخل پا دوخته می‌شود.',
                    ],
                ],
            ]);

            $leg = $this->recordGathers($leg, $waistGather, 'چین کمر شلوار زیر نیفه', 'waist');
            $leg = $this->recordGathers($leg, $ankleGather, 'چین پاچه روی نوار مچ', 'hem');

            $pieces[] = $leg;
        }

        $pieces[] = $this->piece([
            'code' => $prefix.'gusset',
            'name' => 'لِنگه فاق',
            'cut_quantity' => 2,
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point($gusset, 0),
                Geometry::point($gusset, $gusset),
                Geometry::point(0, $gusset),
            ],
            'grainline' => [
                'from' => Geometry::point($gusset * 0.2, $gusset * 0.2),
                'to' => Geometry::point($gusset * 0.8, $gusset * 0.8),
                'label' => 'راستای پارچه (اریب)',
            ],
            'meta' => [
                'part' => 'gusset',
                'edges' => ['default', 'side', 'default', 'side'],
                'fold_edges' => [],
                'girth_role' => 'trim',
                'bias' => true,
                'notes' => [
                    'مربعی که در فاق، میان دو پاچه دوخته می‌شود و راه رفتن و نشستن را ممکن می‌کند.',
                    'روی اریب بریده می‌شود تا در فاق کش بیاید و درز پاره نشود.',
                ],
            ],
        ]);

        $waistFabric = $topWidth * 4;

        $pieces[] = $this->bandPiece($prefix.'casing', 'نیفه کمر', ($waistFabric / 2) + 3, $casing * 2, [
            'cut' => 2, 'part' => 'waistband', 'fold_line' => true,
            'meta' => [
                'girth_role' => 'trim',
                'notions' => [[
                    'type' => 'cord',
                    'label' => 'بند نیفه شلوار',
                    'count' => 1,
                    'length' => round($this->m($m, 'waist', 74) + 60, 1),
                ]],
                'notes' => [
                    'دو تکه نیفه که روی دو پهلو به هم می‌رسند؛ بند از یک جادکمه روی جلو بیرون می‌آید.',
                    'دور کمر پارچه '.$this->fa(round($waistFabric)).' سانتی‌متر است و با بند تا دور کمر جمع می‌شود.',
                ],
            ],
        ]);

        $pieces[] = $this->bandPiece($prefix.'ankle-band', 'نوار مچ پا', $ankle + 2, 8, [
            'cut' => 2, 'part' => 'cuff',
            'meta' => [
                'girth_role' => 'trim',
                'notes' => ['پاچه با چین ریز روی این نوار جمع می‌شود؛ دور تمام‌شده مچ '
                    .$this->fa(round($ankle)).' سانتی‌متر است.'],
            ],
        ]);

        return $pieces;
    }

    /* ---------------------------------------------------------------------
     |  کمک‌های کوچک
     * ------------------------------------------------------------------- */

    /**
     * نقطه‌ای روی درز پهلوی یک پنل، روی ارتفاع خواسته‌شده.
     *
     * برای گذاشتن خط زیپ و نشانه چاک لازم است: عدد از روی خودِ مسیر خوانده
     * می‌شود، نه از روی فرمولی که شاید با مسیر جور نباشد.
     *
     * @param  array<string, mixed>  $piece
     * @return array{x: float, y: float}
     */
    protected function sidePointAt(array $piece, float $y): array
    {
        $outline = $piece['outline'] ?? [];
        $edges = $piece['meta']['side_edges'] ?? [];

        foreach ($edges as $edge) {
            $from = Geometry::pointOnEdge($outline, (int) $edge, 0.0);
            $to = Geometry::pointOnEdge($outline, (int) $edge, 1.0);

            if ($y >= min($from['y'], $to['y']) - 0.01 && $y <= max($from['y'], $to['y']) + 0.01) {
                return $this->pointOnEdgeAtY($outline, (int) $edge, $y);
            }
        }

        $edge = (int) ($edges[0] ?? 0);
        $point = Geometry::pointOnEdge($outline, $edge, 0.0);

        return ['x' => (float) $point['x'], 'y' => (float) $point['y']];
    }

    /**
     * افزودن یراق به یک قطعه، با یادداشت فارسی.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function addNotion(array $piece, array $notion, ?string $note = null): array
    {
        $piece['meta']['notions'][] = $notion;

        if ($note !== null) {
            $piece['meta']['notes'] = array_merge($piece['meta']['notes'] ?? [], [$note]);
        }

        return $piece;
    }

    /** یادداشت‌های همیشگی لباس پوشیده. */
    protected function modestNotes(array $extra = []): array
    {
        return array_merge([
            'پارچه نباید بدن‌نما باشد؛ اگر پارچه نازک است آستر یا زیرپوش هم‌رنگ لازم دارد.',
        ], $extra);
    }
}
