<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * پایه مشترک مایوها.
 *
 * مایو از هر لباس دیگری در این کاتالوگ فاصله دارد، و فاصله‌اش فنی است نه
 * سلیقه‌ای. سه قاعده کل این خانواده را می‌سازد:
 *
 *   ۱. آزادی منفی، و زیاد. پارچهٔ مایو ۵۰ تا ۲۰۰ درصد کش می‌آید و اگر الگو به
 *      اندازهٔ بدن بریده شود، مایو در آب — که پارچه سنگین و لیز می‌شود —
 *      می‌افتد. برای همین «ضریب کشسانی» این‌جا یک پارامتر اصلی است، نه یک
 *      تنظیم پیشرفته.
 *   ۲. هر لبهٔ باز کش می‌خورد. یقه، حلقه، خط پا و کمر هیچ‌کدام درز ندارند که
 *      نگهشان دارد؛ فقط کش نگهشان می‌دارد. پس هر لبه یک عدد دارد: کش چقدر
 *      کوتاه‌تر از خودِ لبه بریده شود.
 *   ۳. فاق آستر جدا می‌خواهد. لایهٔ دوم هم بهداشتی است و هم جلوی نازک شدن
 *      پارچه در آب خیس را می‌گیرد.
 *
 * ابزار مشترک این‌جاست: تنه (از همان bodyPanel آزموده)، شورت مایو با خط پای
 * منحنی و نوار فاق، فنجان سینه، و حساب کش هر لبه.
 */
abstract class SwimBaseGenerator extends TopBaseGenerator
{
    public static function group(): string
    {
        return 'swim';
    }

    /* ---------------------------------------------------------------------
     |  پارامترهای مشترک
     * ------------------------------------------------------------------- */

    /**
     * پارامترهای مشترک هر مایو.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function swimSchema(array $extra = [], float $stretch = 0.85): array
    {
        return array_merge([
            'stretch' => [
                'label' => 'ضریب کشسانی پارچه', 'min' => 0.6, 'max' => 1, 'step' => 0.01,
                'default' => $stretch, 'unit' => 'نسبت',
                'hint' => 'الگو به این نسبت از دور بدن کوچک‌تر بریده می‌شود. ۰٫۸۵ برای پارچهٔ معمولی مایو،'
                    .' ۰٫۷۵ برای پارچهٔ پرکشش مسابقه‌ای.',
            ],
            'elastic_ratio' => [
                'label' => 'کوتاهی کش نسبت به لبه', 'min' => 0.7, 'max' => 1, 'step' => 0.01,
                'default' => 0.9,
                'hint' => 'کش هر لبه این‌قدر کوتاه‌تر بریده می‌شود؛ همین کوتاهی است که لبه را روی تن نگه می‌دارد.',
            ],
            'lining' => [
                'label' => 'آستر', 'type' => 'select', 'default' => 'front',
                'options' => [
                    'full' => 'کامل (همهٔ قطعه‌ها)',
                    'front' => 'فقط جلو و فاق',
                    'gusset' => 'فقط نوار فاق',
                ],
            ],
        ], $extra);
    }

    /** آخرین ضریب کشسانی خوانده‌شده؛ برای مهر زدن روی قطعه‌ها. */
    protected ?float $stretchRatio = null;

    /**
     * آیا این مدل کوچک‌تر از بدن بریده می‌شود؟
     *
     * تقریباً همهٔ مایوها بله، ولی نه همه: بورکینی پارچهٔ کشی دارد و لباس گشاد
     * است. مدلی که این را false کند، مهرِ «آزادی منفی» نمی‌گیرد.
     */
    protected bool $negativeEase = true;

    /** نسبت کوچک‌شدن الگو نسبت به بدن. */
    protected function stretch(array $params): float
    {
        return $this->stretchRatio = min(1.0, max(0.6, (float) $this->param($params, 'stretch', 0.85)));
    }

    /**
     * مهر «این الگو با آزادی منفی بریده شده» روی قطعه‌های پوسته.
     *
     * کلیدش عمداً از meta.stretch جداست: آن یکی روی نوار کشباف یعنی «نوار
     * این‌قدر کوتاه‌تر از لبه بریده شده»، و این یکی یعنی «کل لباس این‌قدر
     * کوچک‌تر از بدن بریده شده». یکی گرفتنشان، بررسی اندازه را گمراه می‌کند.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @return array<int, array<string, mixed>>
     */
    protected function finish(array $pieces): array
    {
        if ($this->negativeEase && $this->stretchRatio !== null && $this->stretchRatio < 0.999) {
            foreach ($pieces as $index => $piece) {
                $isShell = ($piece['meta']['girth_role'] ?? '') === 'shell'
                    || ! empty($piece['meta']['swimwear']);

                if (! $isShell) {
                    continue;
                }

                $pieces[$index]['meta']['stretch_ratio'] = round($this->stretchRatio, 3);

                // قطعه‌ای که از کاتالوگ شلوار قرض گرفته شده نقش دور را اعلام
                // نمی‌کند؛ همین‌جا اعلامش می‌کنیم تا بررسی‌ها هم ببینندش
                if (($piece['meta']['girth_role'] ?? null) === null) {
                    $pieces[$index]['meta']['girth_role'] = 'shell';
                }
            }
        }

        return parent::finish($pieces);
    }

    /**
     * آزادی منفی بر پایهٔ ضریب کشسانی.
     *
     * @param  array<string, float>  $ease
     * @return array<string, float>
     */
    protected function swimEase(array $ease, array $measurements, array $params): array
    {
        $stretch = $this->stretch($params);

        $shrink = fn (string $key, float $fallback) => -((float) ($measurements[$key] ?? $fallback)) * (1 - $stretch);

        return array_merge($ease, [
            'bust' => $shrink('bust', 92),
            'waist' => $shrink('waist', 74),
            'hip' => $shrink('hip', 98),
            'bicep' => 0.0,
        ]);
    }

    /* ---------------------------------------------------------------------
     |  شورت مایو
     * ------------------------------------------------------------------- */

    /**
     * شورت مایو: جلو، پشت و نوار فاق.
     *
     * $o:
     *   rise_drop   چقدر پایین‌تر از خط کمر بنشیند (کمربلند = صفر)
     *   leg_rise    بالا آمدن خط پا؛ صفر یعنی خط پای افقی، زیاد یعنی فرم فرانسوی
     *   coverage    پوشش نشیمن: 'full' | 'medium' | 'cheeky'
     *   gusset      پهنای نوار فاق
     *   prefix، leg_length (برای شورت پادار مثل جامر و بردشورت)
     *
     * @return array<int, array<string, mixed>>
     */
    protected function swimBottom(array $measurements, array $params, array $o = []): array
    {
        $stretch = $this->stretch($params);

        $waist = ((float) ($measurements['waist'] ?? 74)) * $stretch;
        $hip = ((float) ($measurements['hip'] ?? 98)) * $stretch;
        $rise = (float) ($measurements['rise'] ?? $measurements['crotch_depth'] ?? 26);

        $drop = (float) ($o['rise_drop'] ?? 0);
        $legRise = (float) ($o['leg_rise'] ?? 6);
        $gusset = max(6.0, (float) ($o['gusset'] ?? 9));
        $prefix = (string) ($o['prefix'] ?? 'swim');

        $coverage = (string) ($o['coverage'] ?? 'medium');
        $backWidth = match ($coverage) {
            'cheeky' => 0.62,
            'full' => 1.0,
            default => 0.82,
        };

        // کمر پایین‌تر از خط کمر طبیعی: دور همان‌جا بزرگ‌تر است
        $topGirth = $drop < 0.5
            ? $waist
            : $waist + (($hip - $waist) * min(1.0, $drop / max(1.0, $rise)));

        $bodyRise = max(10.0, $rise - $drop);
        $quarter = max(5.0, $topGirth / 4);

        $pieces = [];

        foreach ([
            // «پاچه» نیست: شورت مایو نه درز داخل پا دارد نه پیش‌آمدگی فاق شلوار،
            // و مساحتش هم یک‌سوم پاچه است. نام جدا یعنی هیچ بررسی‌ای آن را با
            // پاچهٔ شلوار اشتباه نمی‌گیرد.
            ['front', 'شورت مایو — جلو', 'swim_bottom_front', 1.0],
            ['back', 'شورت مایو — پشت', 'swim_bottom_back', $backWidth],
        ] as [$side, $name, $part, $share]) {
            $isFront = $side === 'front';

            // پوشش کمتر یعنی خط پا بالاتر می‌رود و منحنی‌اش به مرکز نزدیک‌تر
            // می‌شود؛ هر دو با هم پارچه را کم می‌کنند. اگر فقط نقطهٔ کنترل
            // جابه‌جا شود، پوشش «کم» ممکن است پارچهٔ بیشتری بخورد تا «کامل».
            $legDrop = ($isFront ? $legRise : $legRise * 0.55) + ((1 - $share) * $bodyRise * 0.45);
            $control = $quarter * (0.3 + (0.35 * $share));
            $sideDrop = min($bodyRise - 3.0, max(3.5, $bodyRise * 0.28));

            $outline = [
                Geometry::point(0, 0),
                Geometry::point($quarter, 0),
                // درز پهلو یک لبهٔ واقعی و کوتاه است. پیش‌تر این رأس وجود
                // نداشت و کل منحنی خط پا با برچسب side به پشت دوخته می‌شد؛
                // دو سوراخ پا بسته و پایین‌تنه به توده‌ای مثلثی تبدیل می‌شد.
                Geometry::point($quarter, $sideDrop),
                Geometry::curve(
                    $gusset / 2,
                    $bodyRise,
                    $control,
                    $bodyRise - ($legDrop * 1.1),
                ),
                Geometry::point(0, $bodyRise),
            ];

            $pieces[] = $this->piece([
                'code' => $prefix.'-bottom-'.$side,
                'name' => $name,
                'cut_quantity' => 1,
                'on_fold' => true,
                'outline' => $outline,
                'grainline' => $this->grainline($quarter * 0.45, 1.5, $bodyRise - 1.5),
                'markers' => [
                    $this->marker($isFront ? 'cf' : 'cb', $isFront ? 'خط مرکز جلو' : 'خط مرکز پشت', 0, 0, 0, $bodyRise),
                ],
                'meta' => [
                    'part' => $part,
                    'edges' => ['waist', 'side', 'hem', 'crotch', 'default'],
                    'fold_edges' => [4],
                    'side' => $side,
                    'stretch' => $stretch,
                    'crotch_depth' => round($bodyRise, 2),
                    'girth_role' => 'shell',
                    'girth' => ['waist' => round($quarter * 2, 2)],
                    'girth_factor' => 2,
                    'leg_edge' => 2,
                    'crotch_edge' => 3,
                    'notes' => [
                        'لبهٔ پا و کمر هر دو کش می‌خورند؛ بدون کش، مایو در آب می‌افتد.',
                    ],
                ],
            ]);
        }

        $pieces[] = $this->piece([
            'code' => $prefix.'-gusset',
            'name' => 'نوار فاق',
            'cut_quantity' => 2,
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point($gusset, 0),
                Geometry::point($gusset, 13),
                Geometry::point(0, 13),
            ],
            'grainline' => $this->grainline($gusset * 0.5, 1, 12),
            'meta' => [
                'part' => 'gusset',
                'edges' => ['default', 'side', 'default', 'side'],
                'fold_edges' => [],
                'girth_role' => 'lining',
                'notes' => [
                    'دو لایه بریده می‌شود: یکی از پارچهٔ رو و یکی از آستر.',
                    'نوار فاق هم بهداشتی است و هم نمی‌گذارد پارچه در آب نازک شود.',
                ],
            ],
        ]);

        return $pieces;
    }

    /* ---------------------------------------------------------------------
     |  فنجان و آستر
     * ------------------------------------------------------------------- */

    /**
     * فنجان مثلثی بیکینی.
     *
     * مثلث بیکینی روی اریب بریده می‌شود، چون فقط اریب است که روی سینه شکل
     * می‌گیرد؛ همان مثلث روی راستای پارچه، تخت می‌ماند و فاصله می‌گیرد.
     */
    protected function trianglePiece(float $width, float $height, array $o = []): array
    {
        // نقطهٔ کنترل به نقطه‌ای می‌چسبد که *به آن* می‌رسیم؛ پس کنترلِ لبهٔ بسته‌شونده
        // روی نقطهٔ اول می‌نشیند. با سه نقطه هر دو پهلوی مثلث منحنی می‌شوند و هیچ
        // نقطه‌ای تکراری نمی‌ماند.
        $outline = [
            Geometry::curve(0, 0, $width * 0.22, $height * 0.72),
            Geometry::point($width, 0),
            Geometry::curve($width / 2, $height, $width * 0.78, $height * 0.72),
        ];

        return $this->piece([
            'code' => $o['code'] ?? 'bikini-cup',
            'name' => $o['name'] ?? 'فنجان مثلثی',
            'cut_quantity' => (int) ($o['cut'] ?? 4),
            'outline' => $outline,
            'grainline' => [
                'from' => Geometry::point($width * 0.25, $height * 0.25),
                'to' => Geometry::point($width * 0.75, $height * 0.75),
                'label' => 'راستای پارچه (اریب)',
            ],
            'meta' => [
                'part' => 'cup',
                'edges' => ['default', 'side', 'side'],
                'fold_edges' => [],
                'bias' => true,
                'girth_role' => 'trim',
                'notes' => array_merge([
                    'چهار تکه بریده می‌شود: دو رو و دو آستر.',
                    'روی اریب بریده می‌شود؛ مثلثِ راستا روی سینه تخت می‌ماند و فاصله می‌گیرد.',
                ], $o['notes'] ?? []),
            ],
        ]);
    }

    /**
     * آستر یک قطعه: همان قطعه با لایهٔ آستر.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function liningOf(array $piece, string $suffix = 'lining'): array
    {
        $lining = $piece;
        $lining['code'] = ($piece['code'] ?? 'piece').'-'.$suffix;
        $lining['name'] = 'آستر '.($piece['name'] ?? '');
        $lining['layer'] = 'lining';
        $lining['meta']['girth_role'] = 'lining';
        $lining['meta']['part'] = 'lining';
        $lining['meta']['notes'] = ['هم‌اندازهٔ قطعهٔ رو؛ دو لایه با هم بریده و با هم دوخته می‌شوند.'];

        return $lining;
    }

    /**
     * آستر خواسته‌شده بر پایهٔ پارامتر.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @return array<int, array<string, mixed>>
     */
    protected function withLining(array $pieces, array $params): array
    {
        $mode = (string) $this->param($params, 'lining', 'front');

        if ($mode === 'gusset') {
            return $pieces;
        }

        $out = $pieces;

        foreach ($pieces as $piece) {
            $part = (string) ($piece['meta']['part'] ?? '');

            if (in_array($part, ['gusset', 'lining', 'cup', 'strap', 'belt'], true)) {
                continue;
            }

            if ($mode === 'front' && ! str_contains($part, 'front')) {
                continue;
            }

            $out[] = $this->liningOf($piece);
        }

        return $out;
    }

    /* ---------------------------------------------------------------------
     |  کش و بند
     * ------------------------------------------------------------------- */

    /**
     * ثبت کش یک لبه روی قطعه.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function elastic(array $piece, string $tag, string $label, array $params): array
    {
        $ratio = min(1.0, max(0.7, (float) $this->param($params, 'elastic_ratio', 0.9)));
        $length = 0.0;

        foreach (Geometry::edgesWithTag($piece, $tag) as $edge) {
            $length += Geometry::edgeLength($piece['outline'], $edge);
        }

        if ($length < 1.0) {
            return $piece;
        }

        $repeats = ! empty($piece['on_fold']) ? 2 : max(1, (int) ($piece['cut_quantity'] ?? 1));
        $total = $length * $repeats;

        $piece['meta']['notions'][] = [
            'type' => 'elastic',
            'label' => $label,
            'length' => round($total * $ratio, 1),
        ];

        $piece['meta']['notes'][] = $label.': '.$this->fa(round($total * $ratio))
            .' سانتی‌متر کش برای لبه‌ای به بلندی '.$this->fa(round($total)).' سانتی‌متر.';

        return $piece;
    }

    /** بند نازک مایو (گردنی، پهلویی یا کمری). */
    protected function tie(float $length, float $width, string $code, string $name, int $cut = 2, array $notes = []): array
    {
        $piece = $this->strapPiece($length, $width, [
            'code' => $code,
            'name' => $name,
            'cut' => $cut,
        ]);

        $piece['meta']['notes'] = array_merge($piece['meta']['notes'] ?? [], $notes);

        return $piece;
    }

    /** یادداشت همیشگی مایو. */
    protected function swimNotes(array $params): array
    {
        $stretch = $this->stretch($params);

        return [
            'الگو '.$this->fa(round((1 - $stretch) * 100)).' درصد کوچک‌تر از دور بدن بریده شده؛'
                .' پارچهٔ مایو با کش آمدن روی تن می‌نشیند و در آب که سنگین می‌شود، همین تنگی نگهش می‌دارد.',
            'همهٔ لبه‌های باز کش می‌خورند؛ بدون کش هیچ درزی نیست که لبه را نگه دارد.',
            'با نخ کشی (استرچ) و سوزن جرسی بدوزید؛ درز معمولی با کشیدن پارچه پاره می‌شود.',
        ];
    }
}
