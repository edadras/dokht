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
            /*
             * و حلقه همیشه روی *یک* قطعهٔ جلو و *یک* قطعهٔ پشت نیست.
             *
             * کت رسمی چهار پنل دارد و حلقه‌اش میان هر چهار پخش است: مرکز جلو
             * ۱۱٫۲، پهلوی جلو ۱۱٫۳، پهلوی پشت ۱۰٫۶ و مرکز پشت ۱۰٫۷ — روی هم
             * ۴۳٫۸ سانتی‌متر. pick() فقط یکی از هر سو را برمی‌گرداند، پس سرآستینِ
             * ۴۶ سانتی‌متری روی ۲۱٫۹ سانتی‌متر چپانده می‌شد و باقی‌اش را
             * splice() سرِخود به هر کمانِ آزادی که پیدا می‌کرد می‌دوخت — روی یک
             * بازو به «پهلوی پشت» و روی آن یکی به «مرکز پشت». آستین از بازو کنده
             * می‌شد: پوشش ۱۲۰ درجه از ۳۶۰ و ۱۳۴ نقطه از ۳۳۶ بازو لخت.
             *
             * پس هر پنلی که حلقه دارد سهم خودش را می‌گیرد، به ترتیبی که دور حلقه
             * می‌نشیند: از زیربغلِ جلو، روی سرشانه، تا زیربغلِ پشت.
             */
            $under = ($entry['piece']->meta['two_piece'] ?? null) === 'under';
            $targets = array_merge(
                static::armholePanels($tagged, $front, 'front', $under),
                array_reverse(static::armholePanels($tagged, $back, 'back', $under)),
            );

            if ($targets === []) {
                continue;
            }

            /*
             * ولی سرآستین فقط روی *سرشانه* بریده می‌شود، نه روی هر مرزِ پنلی.
             *
             * برش این‌جا شمارهٔ لبه است و نصفِ یک لبه در آن بیان نمی‌شود. سرآستینِ
             * کت رسمی دقیقاً چهار لبه دارد و چهار پنل هم روبه‌رویش است، پس هر لبه
             * زورکی به یک پنل می‌رسید — و لبه‌ها آن‌جا نبریده‌اند که پنل‌ها به هم
             * می‌رسند. اندازه گرفته شد: ۹٫۵ به حلقهٔ ۶٫۹ و ۸٫۲ به ۱۱٫۴ و ۷٫۸ به
             * ۱۰٫۸ و ۱۰٫۲ به ۷٫۵ — تا ۳٫۲ سانتی‌متر ناهم‌طولی روی هر درز، یعنی
             * سرآستین زیرِ بغل زیادی می‌آورد و روی سرشانه کم.
             *
             * تنها مرزی که *واقعاً* روی مرزِ لبه است، نوکِ سرآستین است: همان‌جا
             * که به درزِ سرشانه می‌رسد. پس همان یک برش این‌جا زده می‌شود و
             * تقسیمِ ریزترِ هر نیمه میان پنل‌هایش را بستهٔ سه‌بعدی هندسی می‌بُرد
             * (ببینید DrapePayloadService::share) — همان‌جایی که برش روی رأس
             * می‌افتد نه روی لبه. کت اسپرت و ترنچ‌کت که یک پنل در هر سو دارند
             * چیزی عوض نمی‌شود.
             */
            $groups = [];

            foreach ($targets as $target) {
                // پنل‌های یک برشِ پنلی (چهار پنلِ کت رسمی) کنارِ هم دورِ یک حلقه
                // می‌نشینند و *یک* کمانِ سرآستین را میان خود می‌برند. پنلی که نقشِ
                // دیگری دارد ولی همان سو است — مثلِ لَتِ زیرِ قپائو که زیرِ تنه
                // می‌خوابد — لایهٔ دیگری است، نه همسایه؛ سهمِ جدا می‌گیرد.
                $groups[$target['side'].'|'.($target['entry']['part'] ?? '')][] = $target;
            }

            $reach = array_map(
                fn (array $panels) => max(0.01, array_sum(array_map(
                    fn (array $target) => static::runLength($target['entry'], $target['edges']),
                    $panels,
                ))),
                $groups,
            );
            $total = array_sum($reach);
            $slices = static::splitRuns($entry, $capEdges, array_map(
                fn (float $length) => $length / $total,
                $reach,
            ));

            foreach (array_values($groups) as $index => $panels) {
                if (($slices[$index] ?? []) === []) {
                    continue;
                }

                foreach ($panels as $target) {
                    $relations[] = static::run(
                        $entry,
                        $slices[$index],
                        $target['entry'],
                        $target['edges'],
                        $target['side'] === 'front' ? 'دوخت آستین به حلقه جلو' : 'دوخت آستین به حلقه پشت',
                    );
                }
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

        /*
         * دو تکهٔ آستینِ خیاطی، لولهٔ یک بازو — و ضربدری دوخته می‌شوند.
         *
         * لبهٔ راستِ آستین رو به لبهٔ *چپِ* آستین زیر می‌رسد و برعکس؛ همان کاری
         * که با لوله کردن دو ورق می‌کنیم. تا امروز این جفت را complete() از روی
         * طول حدس می‌زد و هر چهار حالت تقریباً هم‌امتیاز درمی‌آمدند: روی کت رسمی
         * ۰٫۰۰۰۲ در برابر ۰٫۰۰۰۲ و روی کت اسپرت ۰٫۰۶۱۸ در برابر ۰٫۰۶۲۳. یعنی
         * انتخاب سکه‌انداختن بود، و نیمی از مدل‌ها راست‌به‌راست دوخته می‌شدند:
         * آن وقت آستین زیر روی آستین رو تا می‌خورد به‌جای اینکه لوله را ببندد،
         * و لوله می‌خوابید. اندازه گرفته شد — پوششِ دورِ بازو در ۳۵ سانتی‌متر
         * پایینِ شانه از ۱۶ جهت از ۱۶ به ۶ می‌افتاد و بازو لخت می‌ماند.
         *
         * پس این‌جا نوشته می‌شود، نه حدس زده.
         */
        $upperSleeve = static::pickTwoPiece($tagged, 'upper');
        $underSleeve = static::pickTwoPiece($tagged, 'under');

        if ($upperSleeve && $underSleeve) {
            $upperSides = static::sideEdgesOf($upperSleeve);
            $underSides = static::sideEdgesOf($underSleeve);

            foreach ([['left', 'right'], ['right', 'left']] as [$here, $there]) {
                if ($upperSides[$here] === null || $underSides[$there] === null) {
                    continue;
                }

                $relations[] = static::relation(
                    $upperSleeve,
                    $upperSides[$here],
                    $underSleeve,
                    $underSides[$there],
                    'درز آستین دوتکه',
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
            /*
             * کمربند به *هر* قطعهٔ پایین‌تنه‌ای که خط کمر دارد، نه فقط جلو و پشت.
             *
             * pick() تنها front_bodice و skirt_front و front_leg را برمی‌گرداند.
             * ولی دامنِ ترک‌دار (لهنگا) از چند پنلِ هم‌نام ساخته می‌شود و
             * زیردامنیِ ساری هم زیرِ بالاتنه‌ای می‌نشیند که خودش خط کمر ندارد؛
             * هر دو بی‌دوخت می‌ماندند.
             */
            $lowers = [];

            foreach ([$front, $back] as $target) {
                if ($target) {
                    $lowers[(string) $target['piece']->code] = $target;
                }
            }

            foreach ($tagged as $entry) {
                $part = (string) ($entry['part'] ?? '');

                if ($part === 'waistband' || ! in_array($part, static::LOWER_PARTS, true)) {
                    continue;
                }

                if (static::edgesWithTag($entry, 'waist') !== []) {
                    $lowers[(string) $entry['piece']->code] = $entry;
                }
            }

            foreach ($lowers as $target) {
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
    /**
     * چینِ اعلام‌شدهٔ یک لبه، سانتی‌متر.
     *
     * `meta.gathers` می‌گوید چقدر از طولِ این لبه قرار است جمع شود. بی خواندنِ
     * آن، دروازهٔ «اختلافِ طول بیش از ۱۲٪ یعنی این دو یک درز نیستند» هر درزِ
     * چین‌دار را رد می‌کرد: دامنِ چین‌دارِ لباس محلی دو برابرِ لبهٔ تنه است و
     * *باید* باشد. پیلی هم همین‌طور.
     */
    protected static function gathersOn(PatternPiece $piece, int $edge): float
    {
        $total = 0.0;

        foreach (['gathers', 'pleats'] as $key) {
            foreach ((array) ($piece->meta[$key] ?? []) as $entry) {
                if ((int) ($entry['edge'] ?? -1) !== $edge) {
                    continue;
                }

                $total += abs((float) ($entry['amount'] ?? ($entry['depth'] ?? 0)));
            }
        }

        return $total;
    }

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
                    // چینِ اعلام‌شدهٔ روی همین کمان؛ ببینید pairScore()
                    'gathers' => 0.0,
                    'side' => (string) ($piece->meta['side'] ?? ''),
                    // همسایه‌های اعلام‌شدهٔ خودِ قطعه؛ ببینید pairScore()
                    'neighbours' => array_map('strval', (array) ($piece->meta['seam_neighbours'] ?? [])),
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
            $current['gathers'] += static::gathersOn($piece, $edge);
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
            || (static::$loneSleeve && ($a['part'] ?? '') === 'sleeve')
            // قطعه‌ای که خودش را همسایهٔ خودش اعلام کرده: چند بار بریده می‌شود
            // و کپی‌هایش کنار هم می‌نشینند (ترکِ پهلوی دامنِ ترک‌دار)
            || in_array($a['piece'], $a['neighbours'], true);

        if ($itself && (! $tube || $a['tag'] !== 'side')) {
            return null;
        }

        if ($a['tag'] !== $b['tag']) {
            return null;
        }

        /*
         * همسایهٔ اعلام‌شده، حرفِ آخر است.
         *
         * دامنِ ترک‌دار این را لازم کرد. هشت ترک دارد: مرکزِ جلو، مرکزِ پشت و
         * چهار ترکِ پهلو. طولِ درزِ همه دقیقاً یکی است (۶۸ سانتی‌متر)، پس از دیدِ
         * هندسه «جلو به پشت» بهترین جفت است و همان اول برداشته می‌شود؛ آن‌وقت
         * ترک‌های پهلو شریکی ندارند و دامن روی مانکن چهار تکهٔ آویزان می‌شود.
         *
         * هندسه نمی‌تواند این را حدس بزند، چون هیچ چیزی در خودِ مسیرِ قطعه
         * نمی‌گوید میانِ جلو و پشت چیزی هست. پس خودِ سازنده می‌گوید هر ترک به
         * کدام ترک می‌رسد، و این‌جا فقط خوانده می‌شود.
         *
         * قطعه‌ای که خودش را در فهرستِ همسایه‌ها آورده باشد، به کپیِ خودش دوخته
         * می‌شود (ترکِ پهلو چهار بار بریده می‌شود و چهارتا کنارِ هم می‌نشینند).
         */
        foreach ([[$a, $b], [$b, $a]] as [$one, $other]) {
            if ($one['neighbours'] === []) {
                continue;
            }

            if (! in_array($other['piece'], $one['neighbours'], true)) {
                return null;
            }
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

        /*
         * طولِ *تمام‌شده* مقایسه می‌شود، نه طولِ بریده.
         *
         * لبه‌ای که چین یا پیلی اعلام کرده، پس از جمع شدن کوتاه‌تر است و همان
         * اندازهٔ کوتاه‌شده به شریکش دوخته می‌شود. بی این حساب، دامنِ چین‌دارِ
         * لباس محلی — که دو برابرِ لبهٔ تنه بریده می‌شود و باید هم بشود — از
         * دروازهٔ اختلافِ طول رد نمی‌شد و بی‌دوخت می‌ماند.
         */
        $doneA = max(0.5, $a['length'] - (float) ($a['gathers'] ?? 0));
        $doneB = max(0.5, $b['length'] - (float) ($b['gathers'] ?? 0));
        $longer = max($doneA, $doneB);
        $gap = abs($doneA - $doneB) / max(0.01, $longer);

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
            /*
             * درز پهلو و سرشانه میان دو قطعهٔ هم‌نقش است.
             *
             * آستین به آستین، تنه به تنه. تا وقتی این شرط نبود، فقط طول ملاک
             * بود: درز زیربغلِ آستینِ قپائو ۴ سانتی‌متر است و لبهٔ کنارِ
             * یقه‌بندش ۴٫۵ — اختلاف ۱۱٪، زیر حدِ ۱۲٪ — پس آستین *به یقه* دوخته
             * می‌شد و از بازو کنده می‌شد و دور گردن کشیده می‌شد. پوششِ آستین
             * روی بازو ۳۰ درجه از ۳۶۰ اندازه گرفته شد.
             *
             * خوددوختِ لوله از این شرط می‌گذرد چون هر دو سرش یک قطعه است.
             */
            'side', 'shoulder' => $sleeve($a) === $sleeve($b) && $collar($a) === $collar($b),
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
        $share = min(0.95, max(0.05, $share));

        return static::splitRuns($entry, $edges, [$share, 1 - $share]);
    }

    /**
     * همان، ولی به چند تکه: سرآستینی که به چند پنلِ حلقه می‌رسد.
     *
     * @param  array<int, int>  $edges
     * @param  array<int, float>  $shares  سهمِ هر تکه از طولِ کمان، به ترتیب
     * @return array<int, array<int, int>>
     */
    protected static function splitRuns(array $entry, array $edges, array $shares): array
    {
        $edges = array_values($edges);
        $shares = array_values($shares);
        $parts = count($shares);

        if ($parts < 2) {
            return [$edges];
        }

        /*
         * کمانی که به تعدادِ سهم‌ها لبه ندارد، کامل به همهٔ سهم‌ها می‌رسد.
         *
         * سرآستینِ آستین زیر یک لبهٔ تنهاست و باید میان دو پنلِ زیربغل پخش شود؛
         * این‌جا نمی‌شود بریدش، ولی بستهٔ دوختِ سه‌بعدی همان کمان را هندسی
         * می‌بُرد (ببینید DrapePayloadService::share). قرارداد قدیمی هم همین بود.
         */
        if (count($edges) < $parts) {
            return array_fill(0, $parts, $edges);
        }

        $points = $entry['piece']->points();
        $lengths = array_map(fn (int $edge) => Geometry::edgeLength($points, $edge), $edges);
        $total = array_sum($lengths);

        if ($total < 0.01) {
            return array_fill(0, $parts, $edges);
        }

        $cuts = [0];
        $walked = 0.0;
        $target = 0.0;
        $at = 0;

        for ($part = 0; $part < $parts - 1; $part++) {
            $target += $total * $shares[$part];

            while ($at < count($edges) && $walked + ($lengths[$at] / 2) <= $target) {
                $walked += $lengths[$at];
                $at++;
            }

            // هر تکه دست‌کم یک لبه، و برای تکه‌های بعدی هم دست‌کم یکی می‌ماند
            $at = max($cuts[$part] + 1, min($at, count($edges) - ($parts - 1 - $part)));
            $cuts[] = $at;
        }

        $cuts[] = count($edges);

        $out = [];

        for ($part = 0; $part < $parts; $part++) {
            $out[] = array_slice($edges, $cuts[$part], $cuts[$part + 1] - $cuts[$part]);
        }

        return $out;
    }

    /**
     * پنل‌هایی که حلقهٔ آستینِ یک سو رویشان است، به ترتیبِ دورِ حلقه.
     *
     * ترتیب از زیربغل به سرشانه است، چون سرآستین هم از زیربغلِ جلو راه می‌افتد،
     * از روی سرشانه می‌گذرد و به زیربغلِ پشت می‌رسد. پنلی که درز سرشانه ندارد
     * پنلِ زیربغل است، پس اول می‌آید.
     *
     * آستین زیرِ آستینِ دوتکه فقط به پنلِ زیربغل می‌رسد: کمانِ گودش ته حلقه است،
     * نه روی سرشانه. اگر آن سو تنها یک پنل داشته باشد (پیراهن، کت اسپرت،
     * ترنچ‌کت) همان یکی همه‌کاره است و چیزی عوض نمی‌شود.
     *
     * @param  array<string, mixed>|null  $anchor  قطعه‌ای که pick() برگردانده
     * @return array<int, array{entry: array<string, mixed>, edges: array<int, int>, side: string, shoulder: bool}>
     */
    protected static function armholePanels(Collection $tagged, ?array $anchor, string $side, bool $underarmOnly): array
    {
        if ($anchor === null) {
            return [];
        }

        $layer = (string) ($anchor['piece']->layer ?? 'outer');
        $panels = [];

        foreach ($tagged as $entry) {
            $isAnchor = (string) $entry['piece']->code === (string) $anchor['piece']->code;

            // جز خودِ لنگرِ pick()، تنها هم‌سو و هم‌لایه و هم‌نقش پذیرفته می‌شود:
            // آستر و سجاف و یقه حلقه دارند ولی آستین به آن‌ها دوخته نمی‌شود
            if (! $isAnchor && (
                ($entry['side'] ?? null) !== $side
                || ($entry['part'] ?? null) === 'sleeve'
                || (string) ($entry['piece']->layer ?? 'outer') !== $layer
                || in_array($entry['part'] ?? null, ['facing', 'collar', 'binding', 'interfacing', 'lining'], true)
            )) {
                continue;
            }

            $edges = static::edgesWithTag($entry, 'armhole');

            if ($edges === []) {
                continue;
            }

            $panels[] = [
                'entry' => $entry,
                'edges' => $edges,
                'side' => $side,
                'shoulder' => static::edgesWithTag($entry, 'shoulder') !== [],
            ];
        }

        usort($panels, fn (array $a, array $b) => ($a['shoulder'] ? 1 : 0) <=> ($b['shoulder'] ? 1 : 0));

        if (! $underarmOnly) {
            return $panels;
        }

        $lower = array_values(array_filter($panels, fn (array $panel) => ! $panel['shoulder']));

        return $lower === [] ? $panels : $lower;
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

    /** تکهٔ «رو» یا «زیر»ِ آستین دوتکه، اگر در الگو باشد. */
    protected static function pickTwoPiece(Collection $tagged, string $role): ?array
    {
        foreach ($tagged as $entry) {
            if (($entry['part'] ?? null) === 'sleeve' && ($entry['piece']->meta['two_piece'] ?? null) === $role) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * لبهٔ پهلوی چپ و راستِ یک قطعه — بر پایهٔ جای خودِ لبه روی قطعه، نه شماره‌اش.
     *
     * ملاک، وسطِ لبه نسبت به وسطِ کادرِ قطعه است. شماره‌ی لبه به کار نمی‌آید:
     * آستین رو لبه‌های ۴ و ۶ را دارد و آستین زیر ۱ و ۳، و ترتیبشان روی مسیر
     * یکی نیست.
     *
     * @return array{left: int|null, right: int|null}
     */
    protected static function sideEdgesOf(array $entry): array
    {
        $points = $entry['piece']->points();
        $count = count($points);
        $bounds = $entry['piece']->bounds();
        $middle = ($bounds[0] + $bounds[2]) / 2;
        $best = ['left' => null, 'right' => null];
        $reach = ['left' => INF, 'right' => -INF];

        foreach (static::edgesWithTag($entry, 'side') as $edge) {
            $at = (($points[$edge]['x'] ?? 0) + ($points[($edge + 1) % $count]['x'] ?? 0)) / 2;

            if ($at <= $middle && $at < $reach['left']) {
                $best['left'] = $edge;
                $reach['left'] = $at;
            }

            if ($at > $middle && $at > $reach['right']) {
                $best['right'] = $edge;
                $reach['right'] = $at;
            }
        }

        return $best;
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
