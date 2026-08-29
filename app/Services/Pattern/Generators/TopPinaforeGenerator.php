<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;
use App\Services\Pattern\Transform\PieceOps;
use App\Services\Pattern\Transform\StyleLineCutter;

/**
 * تاپ پیش‌بندی (پینافور).
 *
 * جلویش یک «سینه‌بند» باریک است که از خط سینه بالاتر نمی‌رود و پهنایش کمتر از
 * تن؛ دو بند از دو گوشهٔ سینه‌بند بالا می‌روند و پشت ضربدری می‌شوند.
 *
 * چون بند ضربدری است، فاصلهٔ دو بند روی سینه‌بند باید از فاصله‌شان روی پشت
 * کمتر باشد، وگرنه بند از روی شانه می‌افتد. همین است که پهنای سینه‌بند را
 * تعیین می‌کند و در الگو از پهنای خودِ تن خوانده می‌شود، نه از عددی ثابت.
 *
 * این مدل معمولاً روی لباس دیگری پوشیده می‌شود، پس آزادی‌اش بیشتر از بقیهٔ
 * تاپ‌هاست.
 */
class TopPinaforeGenerator extends TopBaseGenerator
{
    public static function key(): string
    {
        return 'top_pinafore';
    }

    public function label(): string
    {
        return 'تاپ پیش‌بندی (پینافور)';
    }

    public function paramsSchema(): array
    {
        return $this->topSchema(array_merge(
            [
                'bib_width' => [
                    'label' => 'پهنای سینه‌بند', 'min' => 40, 'max' => 95, 'step' => 5,
                    'default' => 60, 'unit' => 'درصد پهنای تن',
                    'hint' => 'درصدی از نیم‌پهنای جلو؛ کمتر یعنی سینه‌بند باریک‌تر.',
                ],
                'bib_height' => [
                    'label' => 'بلندی سینه‌بند از خط سینه', 'min' => 0, 'max' => 16, 'step' => 0.5,
                    'default' => 6, 'unit' => 'سانتی‌متر',
                ],
                'back_drop' => [
                    'label' => 'گودی خط بالای پشت', 'min' => 4, 'max' => 30, 'step' => 1,
                    'default' => 16, 'unit' => 'سانتی‌متر',
                ],
            ],
            $this->strapParam(4, 'پهنای بند'),
            [
                'cross' => [
                    'label' => 'بندها پشت ضربدری شوند', 'type' => 'toggle', 'default' => true,
                ],
            ],
        ), length: 10);
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $grow = $this->fitGrow($params, ['fitted' => 1.0, 'regular' => 3.0, 'loose' => 5.5]);
        $g = $this->blockMetrics($measurements, $ease, $params);

        $strap = (float) $this->param($params, 'strap_width', 4);
        $bibShare = min(0.95, max(0.4, (float) $this->param($params, 'bib_width', 60) / 100));
        $bibHeight = (float) $this->param($params, 'bib_height', 6);
        $backDrop = (float) $this->param($params, 'back_drop', 16);

        $shared = [
            'shape' => 'straight',
            'length' => $this->bodyLength($params, $g, 10),
            'grow' => $grow,
            'bottom_tag' => 'hem',
            'waist_dart' => false,
        ];

        $front = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'front', 'code' => 'pinafore-front', 'name' => 'پینافور جلو',
        ]));

        $back = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'back', 'code' => 'pinafore-back', 'name' => 'پینافور پشت',
        ]));

        // سینه‌بند دو برش دارد: یکی افقی (بلندی) و یکی عمودی (پهنا). برش عمودی
        // بخشی از درز پهلوی جلو را هم می‌بَرد، پس درز پهلوی جلو کوتاه‌تر می‌شود.
        // برای اینکه دو درز باز هم هم‌اندازه بمانند، خط بالای پشت دقیقاً به
        // همان‌قدر پایین‌تر بریده می‌شود.
        $bustY = (float) ($front['meta']['bust_y'] ?? 20);
        $bibTop = max(2.0, $bustY - $bibHeight);
        $bibDrop = max(3.0, $bustY - $bibTop + 2.0);

        $front = $this->carveBib($front, $bibTop, $bibShare, $bibDrop);

        /*
         * بریدنِ پشت از همان ارتفاع کافی نیست.
         *
         * درزِ پهلوی جلو و پشت از یک ارتفاع شروع می‌شوند، ولی *منحنی*شان یکی
         * نیست: جلو گودیِ سینه را دارد و پشت ندارد. پس دو درزی که به هم دوخته
         * می‌شوند چند دهمِ سانتی‌متر اختلاف پیدا می‌کنند — کم، ولی روی میزِ
         * دوخت یعنی یک لبه از آن یکی بلندتر است. با بلندترشدنِ سینه‌بند این
         * اختلاف هم بیشتر می‌شود.
         *
         * پس به‌جای «هم‌ارتفاع»، «هم‌طول» می‌بُریم: خطِ برشِ پشت را آن‌قدر بالا و
         * پایین می‌بریم تا طولِ درزِ پهلویش با جلو یکی شود.
         */
        $back = $this->matchSideSeam($back, $front, $bibTop + $bibDrop, [
            'center' => max(2.0, (float) ($g['shoulder_drop'] ?? 4) + $backDrop),
            'shape' => 'straight',
        ]);

        $strapLength = $this->strapLength($g, $bibHeight + 10, $backDrop + 6, extra: 10);

        $pieces = [$front, $back, $this->strapPiece($strapLength, $strap, [
            'code' => 'pinafore-strap',
            'name' => 'بند پینافور',
            'cut' => 2,
        ])];

        $notes = [
            $this->finishNote($params, ['لبهٔ سینه‌بند', 'حلقه']),
            ['type' => 'info', 'text' => $this->flag($params, 'cross', true)
                ? 'بندها پشت ضربدری می‌شوند؛ فاصلهٔ دو بند روی سینه‌بند عمداً کمتر از فاصلهٔ دو دکمهٔ پشت است، وگرنه بند از شانه می‌افتد.'
                : 'بندها مستقیم از جلو به پشت می‌روند؛ اگر روی شانه لیز خوردند، ضربدری‌شان کنید.'],
            ['type' => 'info', 'text' => 'آزادی این مدل از بقیهٔ تاپ‌ها بیشتر گرفته شده چون معمولاً روی لباس دیگری پوشیده می‌شود.'],
        ];

        return $this->finishBlock($this->noted($pieces, $notes), $g, $grow);
    }

    /**
     * ساختن سینه‌بند: بالای خط سینه هم کوتاه می‌شود هم باریک.
     *
     * برش از لبهٔ بالای قطعه پایین می‌آید و بعد به لبهٔ حلقه می‌رسد؛ تکه‌ای که
     * مرکز جلو در آن است نگه داشته می‌شود.
     */
    protected function carveBib(array $piece, float $topY, float $share, float $drop): array
    {
        $piece = $this->cutTop($piece, ['center' => $topY, 'side' => $topY, 'shape' => 'straight']);

        [$minX, $minY, $maxX] = Geometry::bounds($piece['outline']);
        $bibX = $minX + (($maxX - $minX) * $share);
        $bibBottom = $minY + $drop;

        try {
            $halves = StyleLineCutter::cut($piece, [
                ['x' => $bibX, 'y' => $minY - 1.5],
                ['x' => $bibX, 'y' => $bibBottom],
                ['x' => $maxX + 1.5, 'y' => $bibBottom],
            ], ['tag' => 'default', 'notches' => false, 'normalize' => false]);
        } catch (\InvalidArgumentException) {
            return $piece;
        }

        // تکه‌ای که مرکز جلو (کمترین x) در آن است می‌ماند
        usort($halves, fn ($a, $b) => Geometry::bounds($a['outline'])[0] <=> Geometry::bounds($b['outline'])[0]);
        $bib = $halves[0];

        $bib['code'] = $piece['code'];
        $bib['name'] = $piece['name'];
        $bib['meta']['bib'] = true;

        return Geometry::normalizePiece($this->dropStrayMarks($bib));
    }

    /**
     * بریدنِ خطِ بالای پنلِ پشت، آن‌قدر که درزِ پهلویش هم‌اندازهٔ جلو دربیاید.
     *
     * ارتفاعِ خواسته‌شده نقطهٔ شروع است؛ از آن‌جا با تنصیف بالا و پایین می‌رویم.
     * طولِ درزِ پهلو با پایین‌رفتنِ خطِ برش کم می‌شود و با بالا رفتنش زیاد، پس
     * جست‌وجو یکنواخت است و ده دور برای رسیدن به دهمِ میلی‌متر بس است.
     *
     * @param  array<string, mixed>  $back
     * @param  array<string, mixed>  $front
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    protected function matchSideSeam(array $back, array $front, float $sideY, array $spec): array
    {
        $target = PieceOps::seamLength($front, 'side');

        if ($target <= 0) {
            return $this->cutTop($back, array_merge($spec, ['side' => $sideY]));
        }

        [, $minY, , $maxY] = Geometry::bounds($back['outline']);
        $low = $minY + 0.5;
        $high = $maxY - 4.0;
        $best = $this->cutTop($back, array_merge($spec, ['side' => $sideY]));
        $bestGap = abs(PieceOps::seamLength($best, 'side') - $target);

        for ($step = 0; $step < 10 && $bestGap > 0.02; $step++) {
            $middle = ($low + $high) / 2;
            $candidate = $this->cutTop($back, array_merge($spec, ['side' => $middle]));
            $length = PieceOps::seamLength($candidate, 'side');
            $gap = abs($length - $target);

            if ($gap < $bestGap) {
                $best = $candidate;
                $bestGap = $gap;
            }

            // خطِ برشِ پایین‌تر یعنی درزِ پهلوی کوتاه‌تر
            if ($length > $target) {
                $low = $middle;
            } else {
                $high = $middle;
            }
        }

        return $best;
    }
}
