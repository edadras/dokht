<?php

namespace App\Services\Pattern;

use App\Models\Pattern;
use App\Models\PatternPiece;
use Illuminate\Support\Collection;

/**
 * «دوخت مجازی»: کدام لبه به کدام لبه دوخته می‌شود.
 *
 * خروجی روی Pattern::sewing_relations ذخیره می‌شود و ماژول نمای سه‌بعدی از همین
 * فهرست برای چسباندن قطعه‌ها به هم استفاده می‌کند. هر رابطه به شکل
 * [['from' => ['piece' => code, 'edge' => i], 'to' => [...], 'label' => '...']] است.
 */
class SewingRelationBuilder
{
    /** برچسب‌هایی که لبه‌شان دوخته می‌شود. دم و لبهٔ آزاد در این فهرست نیستند. */
    public const SEWABLE_TAGS = ['side', 'shoulder', 'armhole', 'neck', 'waist', 'strap', 'default'];

    /** آیا این الگو تنها یک قطعهٔ آستین دارد؟ ببینید complete() و pairScore(). */
    protected static bool $loneSleeve = false;

    /**
     * پیشنهاد جفت‌های دوخت بر پایه برچسب لبه‌ها و نقش هر قطعه.
     *
     * @return array<int, array{from: array{piece: string, edge: int}, to: array{piece: string, edge: int}, label: string}>
     */
    public static function suggest(Pattern $pattern): array
    {
        $service = new SeamAllowanceService;
        $pieces = $pattern->pieces;

        $tagged = $pieces->mapWithKeys(fn (PatternPiece $piece) => [
            $piece->code => [
                'piece' => $piece,
                'tags' => $service->edgeTags($piece),
                'part' => $piece->meta['part'] ?? null,
                'side' => $piece->meta['side'] ?? null,
            ],
        ]);

        $relations = [];

        $front = static::pick($tagged, ['front_bodice', 'front_leg', 'skirt_front'], 'front');
        $back = static::pick($tagged, ['back_bodice', 'back_leg', 'skirt_back'], 'back');
        $yoke = static::pick($tagged, ['yoke'], null);
        $shoulderSource = $yoke ?? $back;

        if ($front && $shoulderSource) {
            $relations = array_merge($relations, static::pairTag($front, $shoulderSource, 'shoulder', 'درز سرشانه'));
        }

        if ($front && $back) {
            $label = ($front['part'] === 'front_leg') ? 'درز پهلو و داخل پا' : 'درز پهلو';
            $relations = array_merge($relations, static::pairTag($front, $back, 'side', $label));
        }

        // آستین به حلقه آستین
        foreach ($tagged as $entry) {
            if (($entry['part'] ?? null) !== 'sleeve') {
                continue;
            }

            $capEdges = static::edgesWithTag($entry, 'armhole');

            if ($capEdges === []) {
                continue;
            }

            /*
             * سرآستین یک کمان است، نه دو لبه.
             *
             * پیش‌تر فقط نخستین و آخرین لبهٔ سرآستین به حلقه بسته می‌شد و هرچه
             * میانشان بود بی‌دوخت می‌ماند؛ روی پیراهن کلاسیک یعنی کمانِ ۹٫۶
             * سانتی‌متری به حلقهٔ ۱۷ سانتی‌متری، و آستینی که در نمای سه‌بعدی
             * روی سرشانه مچاله می‌شد. حالا کل سرآستین به نسبتِ طولِ حلقهٔ جلو و
             * پشت بین آن دو تقسیم می‌شود — همان کاری که با نشانهٔ سرشانه
             * می‌کنند.
             */
            $frontArmhole = $front ? static::edgesWithTag($front, 'armhole') : [];
            $backArmhole = $back ? static::edgesWithTag($back, 'armhole') : [];

            $frontLength = static::runLength($front, $frontArmhole);
            $backLength = static::runLength($back, $backArmhole);
            $share = ($frontLength + $backLength) > 0.01 ? $frontLength / ($frontLength + $backLength) : 0.5;

            [$capFront, $capBack] = static::splitRun($entry, $capEdges, $share);

            if ($frontArmhole !== [] && $capFront !== []) {
                $relations[] = static::run($entry, $capFront, $front, $frontArmhole, 'دوخت آستین به حلقه جلو');
            }

            if ($backArmhole !== [] && $capBack !== []) {
                $relations[] = static::run($entry, $capBack, $back, $backArmhole, 'دوخت آستین به حلقه پشت');
            }
        }

        // یقه یا نوار یقه به خط یقه
        foreach ($tagged as $entry) {
            if (! in_array($entry['part'] ?? null, ['collar'], true)) {
                continue;
            }

            $collarEdges = static::edgesWithTag($entry, 'neck');

            if ($collarEdges === []) {
                continue;
            }

            /*
             * خط یقهٔ یقه هم یک کمان است.
             *
             * با بستن یک لبه به یک لبه، کمانِ ۵۴ سانتی‌متری یقه به لبهٔ ۱۴
             * سانتی‌متریِ گردنِ جلو می‌رفت و یقه در نمای سه‌بعدی سر جایش
             * نمی‌نشست. کمانِ یقه به نسبت طول خط یقهٔ جلو و پشت تقسیم می‌شود.
             */
            $targets = [];

            foreach ([[$front, 'یقه به خط یقه جلو'], [$shoulderSource, 'یقه به خط یقه پشت']] as [$target, $label]) {
                if (! $target) {
                    continue;
                }

                $neckEdges = static::edgesWithTag($target, 'neck');

                if ($neckEdges !== []) {
                    $targets[] = ['entry' => $target, 'edges' => $neckEdges, 'label' => $label];
                }
            }

            if (count($targets) === 2) {
                $frontNeck = static::runLength($targets[0]['entry'], $targets[0]['edges']);
                $backNeck = static::runLength($targets[1]['entry'], $targets[1]['edges']);
                $share = ($frontNeck + $backNeck) > 0.01 ? $frontNeck / ($frontNeck + $backNeck) : 0.5;
                [$collarFront, $collarBack] = static::splitRun($entry, $collarEdges, $share);

                foreach ([[$collarFront, $targets[0]], [$collarBack, $targets[1]]] as [$run, $target]) {
                    if ($run !== []) {
                        $relations[] = static::run($entry, $run, $target['entry'], $target['edges'], $target['label']);
                    }
                }

                continue;
            }

            foreach ($targets as $target) {
                $relations[] = static::run($entry, $collarEdges, $target['entry'], $target['edges'], $target['label']);
            }
        }

        // یوک به تنه پشت
        if ($yoke && $back) {
            $yokeEdges = static::edgesWithTag($yoke, 'shoulder');
            $backTop = static::edgesWithTag($back, 'shoulder');

            if ($yokeEdges !== [] && $backTop !== []) {
                $relations[] = static::relation(
                    $yoke,
                    $yokeEdges[count($yokeEdges) - 1],
                    $back,
                    $backTop[0],
                    'یوک به تنه پشت',
                );
            }
        }

        // مچ‌بند به دم آستین
        $cuff = static::pick($tagged, ['cuff'], null);

        if ($cuff) {
            foreach ($tagged as $entry) {
                if (($entry['part'] ?? null) !== 'sleeve') {
                    continue;
                }

                $hem = static::edgesWithTag($entry, 'hem');
                $cuffEdges = static::edgesWithTag($cuff, 'hem') ?: static::edgesWithTag($cuff, 'waist');

                if ($hem !== [] && $cuffEdges !== []) {
                    $relations[] = static::relation($cuff, $cuffEdges[0], $entry, $hem[0], 'مچ‌بند به دم آستین');
                }
            }
        }

        // کمربند به خط کمر
        $waistband = static::pick($tagged, ['waistband'], null);

        if ($waistband) {
            foreach ([$front, $back] as $target) {
                if (! $target) {
                    continue;
                }

                $waistEdges = static::edgesWithTag($target, 'waist');
                $bandEdges = static::edgesWithTag($waistband, 'waist');

                // خط کمر بعضی دامن‌ها (کلوش، ترک، دستمالی) یک کمان شکسته است و از
                // چند لبه ساخته می‌شود؛ اگر فقط لبه اول به کمربند وصل شود، بقیه
                // خط کمر بی‌دوخت می‌ماند و نمای سه‌بعدی هم لباس را باز نشان می‌دهد.
                $side = $target['side'] === 'back' ? 'پشت' : 'جلو';

                foreach ($waistEdges as $index => $waistEdge) {
                    if ($bandEdges === []) {
                        break;
                    }

                    $relations[] = static::relation(
                        $waistband,
                        $bandEdges[0],
                        $target,
                        $waistEdge,
                        'کمربند به خط کمر '.$side.(count($waistEdges) > 1 ? ' '.($index + 1) : ''),
                    );
                }
            }
        }

        return array_values($relations);
    }

    /* ---------------------------------------------------------------------
     |  کامل کردن با هندسه
     * ------------------------------------------------------------------- */

    /**
     * جفت‌های دوختی که فهرست دستیِ بالا نمی‌بیند.
     *
     * `suggest()` روی نقش قطعه‌ها کار می‌کند: جلو، پشت، آستین، یقه، یوک، مچ‌بند،
     * کمربند. این برای کاربری که رابطه‌ها را مرور می‌کند بس است، اما برای دوختِ
     * سه‌بعدی نیست: اندازه‌گیری روی کل کاتالوگ نشان داد به‌طور میانگین تنها حدود
     * یک‌سوم لبه‌های درزی جفت می‌شوند و بیست مدل — از جمله کرست و بوستیه — هیچ
     * رابطه‌ای نمی‌گیرند. لباسی که درزهایش جفت نشده باشد روی مانکن از هم می‌پاشد.
     *
     * جای خالی همیشه یک شکل دارد: **درز پنلی**. درز پرنسسی یا کرست روی مسیرِ
     * خط‌شکسته یک لبه نیست، ده‌ها لبهٔ پشت‌سرهم است که همه برچسب `default`
     * دارند. پس این‌جا به‌جای لبه، «کمان» جفت می‌شود: هر بازهٔ پیوسته از
     * لبه‌های هم‌برچسبِ جفت‌نشده یک نامزد است و نامزدها با طول، برچسب و
     * خویشاوندی قطعه‌ها به هم می‌رسند.
     *
     * خروجی عمداً شکل دیگری دارد (`edges` به‌جای `edge`) تا از رابطه‌های دستی
     * قابل تشخیص باشد و مصرف‌کننده بداند با یک کمان طرف است.
     *
     * @param  array<int, array<string, mixed>>  $relations  خروجی suggest()
     * @return array<int, array{from: array{piece: string, edges: array<int, int>}, to: array{piece: string, edges: array<int, int>}, label: string, reverse: bool, length: float}>
     */
    public static function complete(Pattern $pattern, array $relations = []): array
    {
        /*
         * آستینِ یک‌تکه به خودش دوخته می‌شود؛ آستینِ دوتکه به جفتش.
         *
         * ترنچ‌کت رویه و زیرهٔ آستین دارد و طول درزشان ۵۶ و ۴۷ سانتی‌متر است
         * (اختلاف ۱۶٪، چون یکی را با پُری می‌دوزند). آن اختلاف از دروازهٔ
         * جفت‌شدن رد نمی‌شد، پس هر تکه به خودش دوخته می‌شد و آستین دو لولهٔ
         * باریک می‌شد. شرطِ درست شمردنی است: خوددوخت فقط وقتی که در کل الگو
         * یک قطعهٔ آستین باشد.
         */
        static::$loneSleeve = $pattern->pieces
            ->filter(fn (PatternPiece $piece) => ($piece->meta['part'] ?? null) === 'sleeve')
            ->count() === 1;

        $lining = static::mirrorLining($pattern, $relations);
        $relations = array_merge($relations, $lining);
        $service = new SeamAllowanceService;
        $used = static::usedEdges($relations);
        $runs = [];

        foreach ($pattern->pieces as $piece) {
            foreach (static::runsOf($piece, $service->edgeTags($piece), $used) as $run) {
                $runs[] = $run;
            }
        }

        $pairs = [];

        foreach ($runs as $i => $a) {
            foreach ($runs as $j => $b) {
                if ($j <= $i) {
                    continue;
                }

                $score = static::pairScore($a, $b);

                if ($score !== null) {
                    $pairs[] = ['score' => $score, 'a' => $i, 'b' => $j];
                }
            }
        }

        usort($pairs, fn (array $x, array $y) => $x['score'] <=> $y['score']);

        $taken = [];
        $out = [];

        foreach ($pairs as $pair) {
            if (isset($taken[$pair['a']]) || isset($taken[$pair['b']])) {
                continue;
            }

            $taken[$pair['a']] = true;
            $taken[$pair['b']] = true;

            $a = $runs[$pair['a']];
            $b = $runs[$pair['b']];

            $out[] = [
                'from' => ['piece' => $a['piece'], 'edges' => $a['edges']],
                'to' => ['piece' => $b['piece'], 'edges' => $b['edges']],
                'label' => static::runLabel($a, $b),
                'reverse' => static::runsRunOpposite($a, $b),
                'length' => round(min($a['length'], $b['length']), 2),
            ];
        }

        // درزهایی که همین تابع ساخت (خط کمر، پنل‌ها) هم برای آستر تکرار می‌شوند
        $out = array_merge($lining, $out);

        return array_merge($out, static::mirrorLining($pattern, $out, array_merge($relations, $out)));
    }

    /**
     * آستر همان درزهای رو را دارد.
     *
     * لباس غلافی این را نشان داد: بالاتنهٔ رو درز پهلو و خط کمر داشت و بالاتنهٔ
     * آستر تنها درز سرشانه. علتش هم روشن بود — پهلوی این بالاتنه ۲۳٫۵ در برابر
     * ۲۰ سانتی‌متر است (۱۵٪ اختلاف، چون پشت کوتاه‌تر بریده می‌شود) و دروازهٔ
     * ۱۲٪ِ جفت‌کردنِ خودکار ردش می‌کند؛ درزِ رو ولی از رابطهٔ خودِ سازنده می‌آید
     * و از آن دروازه نمی‌گذرد. نتیجه روی مانکن: دو پنلِ آستر از سرشانه آویزان و
     * مچاله روی سینه. در عکس همان مچالگی دیده می‌شد.
     *
     * پس حدس نمی‌زنیم: هر رابطه‌ای که میان دو قطعهٔ رو هست، اگر هر دو قطعه
     * همتای آستر داشته باشند، برای آستر هم نوشته می‌شود — با همان شماره‌های لبه،
     * چون آستر از همان مسیر بریده می‌شود.
     *
     * @param  array<int, array<string, mixed>>  $relations
     * @return array<int, array<string, mixed>>
     */
    protected static function mirrorLining(Pattern $pattern, array $relations, array $existing = []): array
    {
        $codes = [];
        $lengths = [];

        foreach ($pattern->pieces as $piece) {
            $codes[(string) $piece->code] = true;
            $lengths[(string) $piece->code] = ['piece' => $piece];
        }

        $out = [];
        $seen = [];

        foreach ($existing as $relation) {
            $from = (string) ($relation['from']['piece'] ?? '');
            $to = (string) ($relation['to']['piece'] ?? '');
            $seen[$from.'|'.implode(',', (array) ($relation['from']['edges'] ?? []))
                .'~'.$to.'|'.implode(',', (array) ($relation['to']['edges'] ?? []))] = true;
        }

        foreach ($relations as $relation) {
            $pair = [];

            foreach (['from', 'to'] as $end) {
                $code = (string) ($relation[$end]['piece'] ?? '');
                $twin = $code.'-lining';

                if ($code === '' || str_ends_with($code, '-lining') || ! isset($codes[$twin])) {
                    continue 2;
                }

                $pair[$end] = $twin;
            }

            $edges = [
                'from' => (array) ($relation['from']['edges'] ?? [$relation['from']['edge'] ?? null]),
                'to' => (array) ($relation['to']['edges'] ?? [$relation['to']['edge'] ?? null]),
            ];

            if (in_array(null, $edges['from'], true) || in_array(null, $edges['to'], true)) {
                continue;
            }

            /*
             * آستر همیشه از همان مسیرِ رو بریده نمی‌شود. لباس مجلسیِ خطیِ A
             * آسترِ کوتاه‌تری دارد: پهلوی رو ۲۶٫۳ و پهلوی آستر ۲۲٫۸ سانتی‌متر.
             * شماره‌های لبه یکی است ولی طول‌ها یکی نیست، پس تکرارِ کورکورانه
             * درزی می‌سازد که دو سرش هم‌اندازه نیستند. آن‌جا حدس نمی‌زنیم.
             */
            $twinsMatch = true;

            foreach (['from', 'to'] as $end) {
                $outer = static::runLength($lengths[$relation[$end]['piece']] ?? null, $edges[$end]);
                $inner = static::runLength($lengths[$pair[$end]] ?? null, $edges[$end]);

                if ($outer < 0.01 || abs($outer - $inner) / $outer > 0.05) {
                    $twinsMatch = false;

                    break;
                }
            }

            if (! $twinsMatch) {
                continue;
            }

            $key = $pair['from'].'|'.implode(',', $edges['from']).'~'.$pair['to'].'|'.implode(',', $edges['to']);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            $out[] = [
                'from' => ['piece' => $pair['from'], 'edges' => array_map('intval', $edges['from'])],
                'to' => ['piece' => $pair['to'], 'edges' => array_map('intval', $edges['to'])],
                'label' => (string) ($relation['label'] ?? 'درز'),
                'reverse' => (bool) ($relation['reverse'] ?? true),
                'length' => $relation['length'] ?? null,
                // نشانهٔ «این درز کپیِ درزِ رو است، نه حدسِ تازه»
                'mirrors' => $relation['from']['piece'].'|'.$relation['to']['piece'],
            ];
        }

        return $out;
    }

    /**
     * لبه‌هایی که رابطه‌های دستی از قبل مصرف کرده‌اند.
     *
     * @return array<string, true>
     */
    protected static function usedEdges(array $relations): array
    {
        $used = [];

        foreach ($relations as $relation) {
            foreach (['from', 'to'] as $side) {
                $piece = (string) ($relation[$side]['piece'] ?? '');

                foreach ((array) ($relation[$side]['edges'] ?? [$relation[$side]['edge'] ?? null]) as $edge) {
                    if ($edge !== null) {
                        $used[$piece.'|'.(int) $edge] = true;
                    }
                }
            }
        }

        return $used;
    }

    /**
     * بازه‌های پیوستهٔ لبه‌های هم‌برچسبِ جفت‌نشدهٔ یک قطعه.
     *
     * لبهٔ دم، لبهٔ تای پارچه و لبه‌های مصرف‌شده کنار گذاشته می‌شوند: دم دوخته
     * نمی‌شود، تا دوخته نمی‌شود، و لبهٔ مصرف‌شده جایش را دارد.
     *
     * @param  array<int, string>  $tags
     * @param  array<string, true>  $used
     * @return array<int, array<string, mixed>>
     */
    protected static function runsOf(PatternPiece $piece, array $tags, array $used): array
    {
        $count = count($tags);

        if ($count < 3) {
            return [];
        }

        $points = $piece->points();
        $bounds = $piece->bounds();
        $height = (float) ($bounds[3] - $bounds[1]);
        $folds = array_map('intval', $piece->meta['fold_edges'] ?? []);
        $skip = fn (int $edge) => in_array($edge, $folds, true)
            || isset($used[$piece->code.'|'.$edge])
            || ! in_array($tags[$edge], static::SEWABLE_TAGS, true);

        // از یک مرز شروع کن تا بازه‌ای که دور مسیر می‌پیچد دو تکه گزارش نشود
        $start = 0;

        for ($i = 0; $i < $count; $i++) {
            if ($skip($i) || $tags[$i] !== $tags[($i - 1 + $count) % $count]) {
                $start = $i;

                break;
            }
        }

        $runs = [];
        $current = null;

        for ($k = 0; $k <= $count; $k++) {
            $edge = ($start + $k) % $count;
            // یک بازه با عوض شدن برچسب تمام می‌شود، و با «گوشه». پنل‌های کرست
            // همهٔ لبه‌هایشان default است؛ بدون گوشه، لبهٔ بالای کرست و درز پنلی
            // یک بازهٔ بی‌معنا می‌شوند و طولشان با هیچ درزی نمی‌خواند.
            $close = $k === $count || $skip($edge)
                || ($current !== null && $tags[$edge] !== $current['tag'])
                || ($current !== null && static::turnsCorner($points, $edge, $count));

            if ($close && $current !== null) {
                $runs[] = $current;
                $current = null;
            }

            if ($k === $count || $skip($edge)) {
                continue;
            }

            if ($current === null) {
                $current = [
                    'piece' => $piece->code,
                    'part' => (string) ($piece->meta['part'] ?? ''),
                    'side' => (string) ($piece->meta['side'] ?? ''),
                    'layer' => (string) ($piece->layer ?? 'outer'),
                    'tag' => $tags[$edge],
                    'height' => $height,
                    'edges' => [],
                    'length' => 0.0,
                    'before' => $tags[($edge - 1 + $count) % $count],
                ];
            }

            $current['edges'][] = $edge;
            $current['length'] += Geometry::edgeLength($points, $edge);
            $current['after'] = $tags[($edge + 1) % $count];
        }

        // جهت عمودیِ هر کمان (نسبت به بلندی خودش) برای تشخیص وارونگی درز
        foreach ($runs as $index => $run) {
            $first = $run['edges'][0];
            $last = $run['edges'][count($run['edges']) - 1];
            $span = ($points[($last + 1) % $count]['y'] ?? 0) - ($points[$first]['y'] ?? 0);
            $runs[$index]['rise'] = $run['length'] > 0.01 ? round($span / $run['length'], 3) : 0.0;
        }

        return array_values(array_filter($runs, fn (array $run) => $run['length'] > 1.0));
    }

    /**
     * امتیاز جفت‌شدن دو کمان؛ هرچه کمتر بهتر. null یعنی این دو به هم نمی‌خورند.
     */
    protected static function pairScore(array $a, array $b): ?float
    {
        /*
         * قطعه به خودش دوخته نمی‌شود — به‌جز قطعه‌ای که خودش لوله است.
         *
         * دو کمانِ هم‌طولِ یک پنلِ تنه، دو پهلوی همان پنل‌اند و هرکدام باید به پنل
         * همسایه برسند؛ بی این نگهبان، امتیازِ «هم‌طول و هم‌قطعه» از همه بهتر
         * می‌شود و پنل به خودش دوخته می‌شود.
         *
         * آستینِ یک‌تکه ولی واقعاً به خودش دوخته می‌شود: درز زیربغل دو لبهٔ خودش
         * را به هم می‌رساند و لوله می‌سازد. تا وقتی این استثنا نبود، آستین ورقی
         * باز می‌ماند و دورِ بازو بسته نمی‌شد — اندازه گرفتیم: تنها ۱۵۰ و ۲۵۰
         * درجه از ۳۶۰ درجهٔ دورِ بازو پوشیده بود و بازو از آستین بیرون می‌زد.
         *
         * مچ‌بند هم لوله است و همیشه: نوارِ مچ روی خودش بسته می‌شود و جای دکمه
         * همان‌جاست. بی آن درز، نوارِ ۱۲ سانتی‌متری باز می‌ماند و روی مانکن مثل
         * زبانه‌ای کنارِ مچ آویزان می‌شود.
         */
        $itself = $a['piece'] === $b['piece'];
        $tube = ($a['part'] ?? '') === 'cuff'
            || (static::$loneSleeve && ($a['part'] ?? '') === 'sleeve');

        if ($itself && (! $tube || $a['tag'] !== 'side')) {
            return null;
        }

        if ($a['tag'] !== $b['tag']) {
            return null;
        }

        // حلقه، خط یقه و خط کمر را نمی‌شود فقط با طول جفت کرد: دو پنلِ یک
        // بالاتنه هم حلقه دارند هم کمر، و هم‌طول‌اند، ولی به هم دوخته نمی‌شوند.
        // حلقه به آستین می‌رود، یقه به یقه‌بند، و کمر به دامن یا کمربند. پس
        // این سه فقط وقتی جفت می‌شوند که دو سر، دو نقشِ متفاوت باشند.
        if (! static::rolesMayJoin($a, $b)) {
            return null;
        }

        // آستر به رو دوخته نمی‌شود؛ هر لایه با لایهٔ خودش
        if (($a['layer'] === 'lining') !== ($b['layer'] === 'lining')) {
            return null;
        }

        if (($a['part'] === 'lining') !== ($b['part'] === 'lining')) {
            return null;
        }

        $longer = max($a['length'], $b['length']);
        $gap = abs($a['length'] - $b['length']) / max(0.01, $longer);

        // اختلاف طول بیش از این یعنی این دو لبه اصلاً یک درز نیستند؛ چینِ
        // واقعی هم روی meta.gathers ثبت می‌شود، پس این‌جا لازم نیست جا باز شود
        if ($gap > 0.12) {
            return null;
        }

        $score = $gap;

        // برچسب default هم درز پنلی است هم لبهٔ آزاد (بالای کرست، لبهٔ سجاف).
        // هندسه به‌تنهایی این دو را از هم جدا نمی‌کند، پس این‌جا سخت‌گیر
        // می‌شویم: فقط دو پنلِ هم‌نقش، با کمانِ بلند، و با شمارهٔ کنارِ هم.
        // حدس زدنِ درزی که وجود ندارد بدتر از نبستن آن است؛ درزِ اشتباه لباس را
        // روی مانکن پیچ می‌دهد.
        if ($a['tag'] === 'default') {
            if ($a['part'] === '' || $a['part'] !== $b['part']) {
                return null;
            }

            if (in_array($a['part'], ['facing', 'binding', 'interfacing', 'lining'], true)) {
                return null;
            }

            $tall = 0.4 * max($a['height'], $b['height']);

            if (min($a['length'], $b['length']) < $tall) {
                return null;
            }

            $indexA = static::panelIndex($a['piece']);
            $indexB = static::panelIndex($b['piece']);

            if ($indexA !== null && $indexB !== null && abs($indexA - $indexB) !== 1) {
                return null;
            }
        }

        // درز پهلو و سرشانه بین جلو و پشت است
        if (in_array($a['tag'], ['side', 'shoulder'], true)) {
            $score += ($a['side'] !== '' && $a['side'] === $b['side']) ? 0.3 : 0.0;
        }

        /*
         * خوددوختِ آستین آخرین چاره است، نه انتخابِ اول.
         *
         * آستینِ دوتکهٔ کت دو قطعه دارد و هر قطعه دو لبهٔ کنارِ هم‌طول؛ اگر
         * خوددوخت هم‌ارزِ جفت‌شدنِ دو قطعه امتیاز بگیرد، هر تکه به خودش دوخته
         * می‌شود و آستین دو لولهٔ باریک می‌شود. اندازه گرفتیم: پوستِ لختِ کت
         * اسپرت از ۷۰ نقطه به ۱۹۰ از ۱۹۲ رفت. با این جریمه، جفتِ میان‌قطعه‌ای
         * همیشه برنده است و خوددوخت فقط وقتی می‌ماند که شریکی نباشد — همان
         * آستینِ یک‌تکه.
         *
         * مچ‌بند جریمه نمی‌گیرد: نوارِ مچ همیشه روی خودش بسته می‌شود (جای دکمه
         * همان‌جاست) و شریکِ دیگری ندارد. بی این درز، نوارِ ۱۲ سانتی‌متری باز
         * می‌ماند و روی مانکن مثل زبانه‌ای کنارِ مچ آویزان می‌شود — کاربر همین
         * را دور هر دو مچ دید.
         */
        if ($itself && ($a['part'] ?? '') !== 'cuff') {
            $score += 10.0;
        }

        return round($score, 4);
    }

    /**
     * آیا مسیر پیش از این لبه گوشه می‌خورد؟
     *
     * منحنیِ باز شده به خط شکسته، لبه‌هایش چند درجه با هم فرق دارند؛ گوشهٔ واقعی
     * (جایی که درز تمام می‌شود و لبهٔ دیگری شروع) تندتر از این می‌پیچد.
     */
    protected static function turnsCorner(array $points, int $edge, int $count): bool
    {
        $previous = ($edge - 1 + $count) % $count;

        $ax = ($points[($previous + 1) % $count]['x'] ?? 0) - ($points[$previous]['x'] ?? 0);
        $ay = ($points[($previous + 1) % $count]['y'] ?? 0) - ($points[$previous]['y'] ?? 0);
        $bx = ($points[($edge + 1) % $count]['x'] ?? 0) - ($points[$edge]['x'] ?? 0);
        $by = ($points[($edge + 1) % $count]['y'] ?? 0) - ($points[$edge]['y'] ?? 0);

        $lengthA = sqrt(($ax * $ax) + ($ay * $ay));
        $lengthB = sqrt(($bx * $bx) + ($by * $by));

        if ($lengthA < 0.01 || $lengthB < 0.01) {
            return false;
        }

        $cos = (($ax * $bx) + ($ay * $by)) / ($lengthA * $lengthB);

        return $cos < cos(deg2rad(40));
    }

    /** شمارهٔ پنل از انتهای کدِ قطعه؛ null یعنی قطعه شماره ندارد. */
    protected static function panelIndex(string $code): ?int
    {
        return preg_match('/(\d+)$/', $code, $match) ? (int) $match[1] : null;
    }

    /** قطعه‌هایی که خط کمرشان به بالاتنه می‌رسد، نه به پنل کناری. */
    protected const LOWER_PARTS = ['skirt_front', 'skirt_back', 'skirt_panel', 'skirt_tier', 'front_leg', 'back_leg', 'waistband', 'peplum'];

    /**
     * آیا نقشِ دو سر با هم می‌خواند؟
     *
     * برای درز پنلی و پهلو و سرشانه هر دو سر هم‌نقش‌اند. برای حلقه، یقه و کمر
     * باید دو نقش متفاوت باشند، وگرنه دو پنل هم‌طولِ یک بالاتنه از حلقه به هم
     * دوخته می‌شوند و لباس روی مانکن بسته می‌ماند.
     */
    protected static function rolesMayJoin(array $a, array $b): bool
    {
        $sleeve = fn (array $run) => $run['part'] === 'sleeve';
        $collar = fn (array $run) => in_array($run['part'], ['collar', 'binding', 'facing'], true);
        $lower = fn (array $run) => in_array($run['part'], static::LOWER_PARTS, true);

        return match ($a['tag']) {
            'armhole' => $sleeve($a) !== $sleeve($b),
            'neck' => $collar($a) !== $collar($b),
            'waist' => $lower($a) !== $lower($b),
            default => true,
        };
    }

    /**
     * آیا کمان دوم باید وارونه پیموده شود؟
     *
     * از جهت پیمایش فهمیده می‌شود: درز پهلوی جلو و پشت هر دو از بالا به پایین
     * می‌روند و سر به سر دوخته می‌شوند؛ ولی در درز پرنسسی، کمانِ پنل میانی از
     * حلقه به کمر می‌رود و کمانِ پنل پهلو از کمر به حلقه، پس یکی باید وارونه
     * شود تا دو سرِ هم‌نام به هم برسند. این همان «هم‌ترازکردن نشانه‌ها»ی خیاط است.
     *
     * اگر جهت عمودی دو کمان به هم نزدیک بود (درز افقی مثل خط کمر)، به همسایهٔ
     * دو سرِ کمان تکیه می‌کنیم.
     */
    protected static function runsRunOpposite(array $a, array $b): bool
    {
        $riseA = $a['rise'] ?? 0.0;
        $riseB = $b['rise'] ?? 0.0;

        if (abs($riseA) > 0.2 && abs($riseB) > 0.2) {
            return ($riseA > 0) !== ($riseB > 0);
        }

        return ! (($a['before'] ?? '') === ($b['before'] ?? '') && ($a['after'] ?? '') === ($b['after'] ?? ''));
    }

    /** نام فارسی درز از روی برچسب لبه. */
    protected static function runLabel(array $a, array $b): string
    {
        return match ($a['tag']) {
            'side' => 'درز پهلو',
            'shoulder' => 'درز سرشانه',
            'armhole' => 'دوخت حلقه',
            'neck' => 'دوخت خط یقه',
            'waist' => 'دوخت خط کمر',
            'strap' => 'دوخت بند',
            default => 'درز پنلی',
        };
    }

    /**
     * جفت‌کردن لبه‌های هم‌برچسب دو قطعه به ترتیب ظاهر شدن.
     *
     * @return array<int, array<string, mixed>>
     */
    protected static function pairTag(array $a, array $b, string $tag, string $label): array
    {
        $left = static::edgesWithTag($a, $tag);
        $right = static::edgesWithTag($b, $tag);
        $count = min(count($left), count($right));
        $relations = [];

        for ($i = 0; $i < $count; $i++) {
            $relations[] = static::relation($a, $left[$i], $b, $right[$i], $count > 1 ? $label.' '.($i + 1) : $label);
        }

        return $relations;
    }

    /** @return array<string, mixed> */
    protected static function relation(array $from, int $fromEdge, array $to, int $toEdge, string $label): array
    {
        return [
            'from' => ['piece' => $from['piece']->code, 'edge' => $fromEdge],
            'to' => ['piece' => $to['piece']->code, 'edge' => $toEdge],
            'label' => $label,
        ];
    }

    /**
     * رابطه‌ای که هر دو سرش یک کمان است.
     *
     * `edge` هم نوشته می‌شود تا هر مصرف‌کننده‌ای که فقط یک لبه می‌فهمد
     * (ویرایشگر الگو) از کار نیفتد؛ `edges` کمان کامل را می‌دهد.
     *
     * @param  array<int, int>  $fromEdges
     * @param  array<int, int>  $toEdges
     * @return array<string, mixed>
     */
    protected static function run(array $from, array $fromEdges, array $to, array $toEdges, string $label): array
    {
        return [
            'from' => ['piece' => $from['piece']->code, 'edge' => $fromEdges[0], 'edges' => array_values($fromEdges)],
            'to' => ['piece' => $to['piece']->code, 'edge' => $toEdges[0], 'edges' => array_values($toEdges)],
            'label' => $label,
        ];
    }

    /** طول یک کمان روی یک قطعه. */
    protected static function runLength(?array $entry, array $edges): float
    {
        if ($entry === null || $edges === []) {
            return 0.0;
        }

        $points = $entry['piece']->points();
        $total = 0.0;

        foreach ($edges as $edge) {
            $total += Geometry::edgeLength($points, (int) $edge);
        }

        return $total;
    }

    /**
     * شکستن یک کمان به دو کمان، به نسبت خواسته‌شده از طول.
     *
     * برش روی مرز لبه‌ها می‌افتد، نه وسط یک لبه: شمارهٔ لبه واحدِ این فهرست است
     * و نصفِ یک لبه در آن بیان نمی‌شود.
     *
     * @param  array<int, int>  $edges
     * @return array{0: array<int, int>, 1: array<int, int>}
     */
    protected static function splitRun(array $entry, array $edges, float $share): array
    {
        $edges = array_values($edges);

        if (count($edges) < 2) {
            return [$edges, $edges];
        }

        $points = $entry['piece']->points();
        $lengths = array_map(fn (int $edge) => Geometry::edgeLength($points, $edge), $edges);
        $total = array_sum($lengths);

        if ($total < 0.01) {
            return [$edges, $edges];
        }

        $target = $total * min(0.95, max(0.05, $share));
        $walked = 0.0;
        $cut = 1;

        foreach ($lengths as $index => $length) {
            if ($walked + ($length / 2) > $target) {
                $cut = max(1, $index);

                break;
            }

            $walked += $length;
            $cut = $index + 1;
        }

        $cut = min(count($edges) - 1, $cut);

        return [array_slice($edges, 0, $cut), array_slice($edges, $cut)];
    }

    /** @return array<int, int> */
    protected static function edgesWithTag(array $entry, string $tag): array
    {
        $indexes = [];

        foreach ($entry['tags'] as $index => $value) {
            if ($value === $tag) {
                $indexes[] = (int) $index;
            }
        }

        return $indexes;
    }

    /** اولین قطعه‌ای که نقش یا سمت آن با خواسته ما بخواند. */
    protected static function pick(Collection $tagged, array $parts, ?string $side): ?array
    {
        foreach ($tagged as $entry) {
            if (in_array($entry['part'] ?? null, $parts, true)) {
                return $entry;
            }
        }

        if ($side === null) {
            return null;
        }

        foreach ($tagged as $entry) {
            if (($entry['side'] ?? null) === $side) {
                return $entry;
            }
        }

        return null;
    }
}
