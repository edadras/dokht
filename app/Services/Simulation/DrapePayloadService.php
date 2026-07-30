<?php

namespace App\Services\Simulation;

use App\Models\Pattern;
use App\Models\PatternPiece;
use App\Services\Pattern\Geometry;
use App\Services\Pattern\SewingRelationBuilder;
use App\Services\Pattern\Transform\PieceOps;
use App\Support\Measurements;
use Throwable;

/**
 * بسته «دوخت سه‌بعدی»: از قطعه‌های الگو تا پارچه‌ای که مرورگر روی مانکن می‌نشاند.
 *
 * شکل خروجی در docs/drape-contract.md نوشته شده و همان‌جا قرارداد دو سمت است.
 * کار این کلاس چهار تاست:
 *
 *   ۱. قطعه روی تای پارچه را باز می‌کند (وگرنه نصف لباس روی مانکن می‌رود).
 *   ۲. هر برش را به یک نمونه جدا تبدیل می‌کند و نمونه آینه‌ای را خودش آینه می‌کند.
 *   ۳. لبه‌های اصلی الگو را به بازه رأس روی خط شکسته ترجمه می‌کند — همین پل است
 *      که مرورگر با آن دو لبه درز را جفت می‌کند.
 *   ۴. رابطه‌های دوخت را به کمان‌های همین بسته برمی‌گرداند و هر رابطه‌ای را که
 *      سرش پیدا نشود در meta.unmatched گزارش می‌کند، نه اینکه دور بیندازد.
 *
 * همه طول‌ها سانتی‌متر است و محور y مثل خود الگو به پایین می‌رود؛ تبدیل به متر و
 * چرخاندن محور کار مرورگر است.
 */
class DrapePayloadService
{
    /** بیشترین رأسی که مرورگر برای همه قطعه‌ها با هم می‌پذیرد. */
    public const MAX_VERTICES = 6000;

    /** طول یال هدف مثلث‌بندی، سانتی‌متر. */
    public const TARGET_EDGE = 3.0;

    /** فاصله‌ای که کمتر از آن، یک نقطه «روی» رأس خط شکسته حساب می‌شود. */
    public const SNAP = 0.08;

    /** جریمه جفت‌شدن دو کمان از دو سمت بدن. */
    protected const SIDE_PENALTY = 1000.0;

    /**
     * لبه‌هایی که درزشان میان چند شریک تقسیم می‌شود، پس رأسِ میانی لازم دارند.
     *
     * خط یقه و حلقهٔ آستین و سرشانه، چون یقه میان دو تنه و یوک تقسیم می‌شود و
     * سرآستین میان تنه و یوک. «کمر» و «پهلو» عمداً نیستند: امتحانشان کردیم و
     * دامنِ کلوش بدتر شد — نوارِ کمرش دوازده رابطه دارد که همه‌شان کلِ نوار را
     * نام می‌برند، و ریزتر شدنِ مرز فقط همان اشکالِ قدیمی را بزرگ‌تر نشان داد
     * (مثلث تیغه‌ای ۸ به ۱۷). آن اشکال جای دیگری دارد.
     */
    protected const SPLIT_TAGS = ['neck', 'armhole', 'shoulder'];

    /**
     * سهمِ کمانِ یک پنلِ آستین، حداکثر چند برابرِ سهمِ منصفانه‌اش.
     *
     * پنل‌ها دقیقاً کنار هم چیده نمی‌شوند: روی پارچه جای درز هست و پهنایشان از
     * دور بازو بیشتر است. اگر دقیقاً تقسیم شود، هر پنل فشرده می‌شود و آستین
     * کت‌وشلوار ۸ سانتی‌متر روی بازو سُر می‌خورد و تمام کت را پایین می‌کشد
     * (پوستِ لخت ۲ ← ۴۰). با اجازهٔ هم‌پوشانی، ۲ ← ۵.
     */
    protected const PANEL_OVERLAP = 1.5;

    /** شعاع مرجع برای تبدیل اختلاف زاویه به فاصله (سانتی‌متر). */
    protected const REFERENCE_RADIUS = 15.0;

    /**
     * بیشترین ناهم‌طولیِ دو سرِ یک درز که با آزادیِ پارچه توضیح داده می‌شود.
     *
     * سرآستین را با ۵ تا ۱۰ درصد پُری می‌برند تا سر شانه بخوابد، و کمرِ چین‌دار
     * از این هم بیشتر. بالای این حد ولی توضیحِ دیگری دارد: شریکِ جامانده.
     */
    protected const EASE_SHARE = 0.18;

    /**
     * بسته کامل دوخت سه‌بعدی.
     *
     * @return array{scale: float, pieces: array, seams: array, budget: array, meta: array}
     */
    public function payload(Pattern $pattern): array
    {
        $body = new DrapeBody(Measurements::complete($pattern->measurements ?? []));
        $models = $pattern->relationLoaded('pieces') ? $pattern->pieces : $pattern->pieces()->get();

        $notes = [];
        $instances = [];
        $byCode = [];

        foreach ($models as $model) {
            // یک قطعه خراب نباید کل نمای سه‌بعدی را ببرد؛ گزارش می‌شود و بقیه
            // لباس ساخته می‌شود.
            try {
                $prepared = $this->prepare($model, $notes);

                if ($prepared === null) {
                    continue;
                }

                foreach ($this->instances($prepared, $body) as $instance) {
                    $instances[$instance['id']] = $instance;
                    $byCode[$instance['code']][] = $instance['id'];
                }
            } catch (Throwable $error) {
                $notes[] = "قطعه «{$model->code}» ساخته نشد: ".$error->getMessage();
            }
        }

        $this->arrange($instances);
        $this->arrangeSleeves($instances);
        [$instances, $byCode] = $this->dedupe($instances, $notes);

        $relations = [];
        $unmatched = [];
        $seams = [];

        try {
            $relations = $this->relations($pattern);
            $seams = $this->seams($relations, $instances, $byCode, $unmatched);
        } catch (Throwable $error) {
            $notes[] = 'رابطه‌های دوخت خوانده نشد: '.$error->getMessage();
        }

        foreach ($instances as $instance) {
            foreach ($instance['dart_seams'] as $seam) {
                $seams[] = $seam;
            }
        }

        try {
            $seams = array_merge($seams, $this->closures($instances, $byCode));
        } catch (Throwable $error) {
            $notes[] = 'بستن مرکز جلو و پشت انجام نشد: '.$error->getMessage();
        }

        try {
            $seams = array_merge($seams, $this->adopt($instances, $seams));
        } catch (Throwable $error) {
            $notes[] = 'دوختن قطعه‌های جامانده انجام نشد: '.$error->getMessage();
        }

        try {
            $seams = $this->splice($instances, $seams);
        } catch (Throwable $error) {
            $notes[] = 'شریکِ جاماندهٔ درزها پیدا نشد: '.$error->getMessage();
        }

        return [
            'scale' => 0.01,
            'pieces' => array_values(array_map(fn (array $instance) => $instance['payload'], $instances)),
            'seams' => array_values($seams),
            'budget' => $this->budget($instances),
            'meta' => [
                'unmatched' => array_values($unmatched),
                'relations' => count($relations),
                'notes' => array_values($notes),
            ],
        ];
    }

    /* ---------------------------------------------------------------------
     |  آماده‌سازی قطعه: باز کردن تا و نگه داشتن رد لبه‌های اصلی
     * ------------------------------------------------------------------- */

    /**
     * قطعه دیتابیسی را به قطعه آرایه‌ای «باز‌شده» تبدیل می‌کند.
     *
     * برای اینکه بعد از باز شدن تا بدانیم هر لبه تازه از کدام لبه اصلی آمده،
     * پیش از unfold برچسب هر لبه با یک نشانه یکتا («#۳») عوض می‌شود. خودِ unfold
     * برچسب‌ها را با مسیر جابه‌جا و قرینه می‌کند، پس نشانه‌ها همان نقشه‌ای را
     * می‌سازند که لازم داریم — بدون آنکه لازم باشد درون آن دست ببریم.
     *
     * @param  array<int, string>  $notes
     * @return array{model: PatternPiece, piece: array, origins: array<int, int|null>, tags: array<int, string>, unfolded: bool}|null
     */
    protected function prepare(PatternPiece $model, array &$notes): ?array
    {
        $outline = array_values($model->outline ?? []);
        $count = count($outline);

        if ($count < 3) {
            $notes[] = "قطعه «{$model->code}» مسیر بسته ندارد و در بسته نیامد.";

            return null;
        }

        $tags = Geometry::edgeTags(['outline' => $outline, 'meta' => $model->meta ?? []]);

        $piece = [
            'code' => (string) $model->code,
            'name' => (string) $model->name,
            'outline' => $outline,
            'darts' => $model->darts ?? [],
            'notches' => $model->notches ?? [],
            'drills' => $model->drills ?? [],
            'pleats' => $model->pleats ?? [],
            'markers' => $model->markers ?? [],
            'meta' => $model->meta ?? [],
        ];

        $piece['meta']['edges'] = array_map(fn (int $index) => '#'.$index, range(0, $count - 1));

        $unfolded = false;

        if ($model->on_fold && ($piece['meta']['fold_edges'] ?? []) !== []) {
            $open = PieceOps::unfold($piece);

            if (count($open['outline'] ?? []) >= 3) {
                $piece = $open;
                $unfolded = true;
            } else {
                $notes[] = "قطعه «{$model->code}» روی تای پارچه است ولی باز نشد.";
            }
        } elseif ($model->on_fold) {
            $notes[] = "قطعه «{$model->code}» روی تای پارچه است ولی لبه تا ندارد؛ نیمه بریده‌شده به مرورگر رفت.";
        }

        $piece = $this->standUp($piece, $tags, $model);
        $origins = $this->origins($piece);
        $oriented = DrapeGeometry::orient($piece, $origins);

        return [
            'model' => $model,
            'piece' => Geometry::normalizePiece($oriented['piece']),
            'origins' => $oriented['per_edge'],
            'tags' => $tags,
            'unfolded' => $unfolded,
        ];
    }

    /**
     * یقهٔ ایستاده روی تن سروته است.
     *
     * روی الگو، خط یقهٔ یقه بالای قطعه است و لبهٔ بیرونی پایینش — همان‌طور که
     * روی کاغذ کشیده می‌شود. روی تن ولی برعکس است: خط یقه پایین می‌نشیند (روی
     * خط یقهٔ لباس) و یقه از آن‌جا بالا می‌رود. تا وقتی همان ترتیبِ کاغذ را روی
     * بدن می‌گذاشتیم، لبهٔ بیرونیِ یقه ۷٫۵ سانتی‌متر *زیرِ* خط یقه چیده می‌شد و
     * قید درز باید نوار را از میان سوراخِ گردن بکشد بالا؛ نوار در همان کشیدن
     * سروته می‌شد. اندازه گرفتیم: پس از چیدن ۱۰۰٪ مثلث‌های یقه رو به بیرون بود،
     * پس از دوختن ۶٪. رویِ برگشتهٔ پارچه تیره سایه می‌زند و در عکس مثل شکافِ
     * دور گردن دیده می‌شد.
     *
     * شرطش دقیق است و به مدل گره نخورده: تنها یقه‌ای که خط یقه‌اش در نیمهٔ بالای
     * کادر خودش است برمی‌گردد. یقهٔ تختِ خوابیده (پیتر‌پن) خط یقه‌اش پایین است و
     * دست‌نخورده می‌ماند.
     *
     * @param  array<int, string>  $tags
     * @return array<string, mixed>
     */
    protected function standUp(array $piece, array $tags, PatternPiece $model): array
    {
        if ($this->role($model) !== 'collar') {
            return $piece;
        }

        $outline = $piece['outline'] ?? [];
        [, $minY, , $maxY] = Geometry::bounds($outline);
        $height = $maxY - $minY;

        if ($height < 0.5) {
            return $piece;
        }

        $sum = 0.0;
        $seen = 0;

        foreach ($tags as $edge => $tag) {
            if ($tag !== 'neck' || ! isset($outline[$edge])) {
                continue;
            }

            $sum += (float) ($outline[$edge]['y'] ?? 0) + (float) ($outline[($edge + 1) % count($outline)]['y'] ?? 0);
            $seen += 2;
        }

        if ($seen === 0 || ($sum / $seen) > $minY + ($height / 2)) {
            return $piece; // خط یقه پایین است؛ یقه همان‌جا که هست درست است
        }

        $flip = $minY + $maxY;

        foreach (['outline', 'darts', 'notches', 'markers', 'drills'] as $key) {
            if (! isset($piece[$key]) || ! is_array($piece[$key])) {
                continue;
            }

            $piece[$key] = $this->mirrorY($piece[$key], $flip);
        }

        return $piece;
    }

    /**
     * قرینه کردن y هر نقطه‌ای که در یک ساختار تودرتو هست.
     *
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    protected function mirrorY(array $data, float $flip): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->mirrorY($value, $flip);

                continue;
            }

            if (($key === 'y' || $key === 'cy' || $key === 'y1' || $key === 'y2') && is_numeric($value)) {
                $data[$key] = $flip - (float) $value;
            }
        }

        return $data;
    }

    /**
     * شماره لبه اصلی هر لبه فعلی، از روی نشانه‌هایی که در برچسب لبه‌ها گذاشتیم.
     *
     * @return array<int, int|null>
     */
    protected function origins(array $piece): array
    {
        $origins = [];

        foreach (Geometry::edgeTags($piece) as $index => $tag) {
            $origins[$index] = preg_match('/^#(\d+)$/', (string) $tag, $found) === 1
                ? (int) $found[1]
                : null;
        }

        return $origins;
    }

    /* ---------------------------------------------------------------------
     |  نمونه‌ها
     * ------------------------------------------------------------------- */

    /**
     * هر برش یک نمونه: نمونه‌های آینه‌ای همین‌جا آینه می‌شوند.
     *
     * @param  array{model: PatternPiece, piece: array, origins: array, tags: array, unfolded: bool}  $prepared
     * @return array<int, array<string, mixed>>
     */
    protected function instances(array $prepared, DrapeBody $body): array
    {
        $model = $prepared['model'];
        $quantity = max(1, (int) $model->cut_quantity);
        $mirror = (bool) $model->mirror;
        $out = [];

        for ($index = 0; $index < $quantity; $index++) {
            $piece = $prepared['piece'];
            $origins = $prepared['origins'];
            $mirrored = $mirror && ($index % 2 === 1);

            if ($mirrored) {
                $flipped = DrapeGeometry::mirrorPiece($piece, $origins);
                $piece = Geometry::normalizePiece($flipped['piece']);
                $origins = $flipped['per_edge'];
            }

            $out[] = $this->instance($prepared, $piece, $origins, $index, $mirrored, $quantity, $body);
        }

        return $out;
    }

    /**
     * یک نمونه کامل: خط شکسته، پل لبه‌ها، ساسون‌ها و چیدن اولیه.
     *
     * @return array<string, mixed>
     */
    protected function instance(
        array $prepared,
        array $piece,
        array $origins,
        int $index,
        bool $mirrored,
        int $quantity,
        DrapeBody $body,
    ): array {
        $model = $prepared['model'];
        /*
         * فقط لبه‌های دوختنیِ راست رأسِ میانی می‌گیرند.
         *
         * درز روی رأس بریده می‌شود، پس لبهٔ راستِ بی‌رأسِ میانی هرگز میان دو
         * شریک تقسیم نمی‌شود — خط یقهٔ یقهٔ پیراهن همین بود و همهٔ ۲۵٫۸
         * سانتی‌مترش روی خط یقهٔ ۱۴٫۴ سانتی‌متریِ یک تنه می‌رفت.
         *
         * ولی شکستنِ همهٔ لبه‌های راست را هم اندازه گرفتیم و بدتر بود: روی هشت
         * لباسِ سنجه شمار مثلث خراب از ۲۵ به ۵۱ رفت، چون مرزِ لبهٔ بی‌درز هم
         * ریزتر می‌شد و مثلث‌بندی و جفت‌کردنِ رأس‌ها را به‌هم می‌زد. پس همان‌جا
         * که لازم است: لبه‌ای که دوخته می‌شود.
         */
        $breakable = [];

        foreach ($origins as $edge => $origin) {
            $tag = $origin !== null ? ($prepared['tags'][$origin] ?? 'default') : 'default';
            $breakable[$edge] = in_array($tag, static::SPLIT_TAGS, true);
        }

        $flat = DrapeGeometry::flattenWithSpans(
            $piece['outline'],
            split: $breakable,
        );
        $polygon = $flat['polygon'];
        $spans = $flat['spans'];

        $lengths = [];
        $edges = [];

        foreach ($spans as $edge => [$start, $end]) {
            $length = DrapeGeometry::arcLength($polygon, $start, $end);
            $lengths[$edge] = $length;
            $tag = $origins[$edge] !== null ? ($prepared['tags'][$origins[$edge]] ?? 'default') : 'default';

            $edges[$edge] = [
                'tag' => $tag,
                'start' => $start,
                'end' => $end,
                'length' => round($length, 3),
            ];
        }

        $code = (string) $model->code;
        $id = $code.'#'.$index;
        $role = $this->role($model);
        $side = $this->side($model, $mirrored, $quantity, $index);

        $instance = [
            'id' => $id,
            'code' => $code,
            'role' => $role,
            'side' => $side,
            'instance' => $index,
            'mirrored' => $mirrored,
            'quantity' => $quantity,
            'outline' => $piece['outline'],
            'bounds' => Geometry::bounds($piece['outline']),
            'polygon' => $polygon,
            'spans' => $spans,
            'lengths' => $lengths,
            'edges' => $edges,
            'origins' => $origins,
            'unfolded' => $prepared['unfolded'],
            'meta' => $piece['meta'] ?? [],
        ];

        $placement = $this->placement($instance, $model, $body);
        $darts = $this->darts($piece, $instance, $id);

        $instance['placement'] = $placement;
        $instance['top_cm'] = $placement['y_top'] * $body->height;
        $instance['dart_seams'] = $darts['seams'];
        $instance['payload'] = [
            'id' => $id,
            'code' => $code,
            'name' => (string) $model->name,
            'role' => $role,
            'side' => $side,
            'instance' => $index,
            'mirrored' => $mirrored,
            'layer' => (string) ($model->layer ?: 'outer'),
            'polygon' => array_map(fn (array $point) => [round($point['x'], 3), round($point['y'], 3)], $polygon),
            'edges' => array_values($edges),
            'roll' => $this->rollLine($piece, $polygon, $edges),
            'darts' => $darts['darts'],
            'placement' => array_intersect_key($placement, array_flip([
                'zone', 'u0', 'u1', 'y_top', 'radius_hint', 'radius', 'flip',
            ])),
        ];

        return $instance;
    }

    /**
     * نقش قطعه.
     *
     * نخست meta.part خوانده می‌شود چون همان چیزی است که ژنراتور خودش اعلام کرده؛
     * تنها اگر part چیزی نگوید (مثلاً «lining») سراغ کد و نام قطعه می‌رویم. اگر
     * جای این دو عوض شود، آسترِ دامن «تنه» می‌شود و مچ‌بند «آستین».
     */
    protected function role(PatternPiece $piece): string
    {
        return $this->matchRole((string) ($piece->meta['part'] ?? ''))
            ?? $this->matchRole($piece->code.' '.$piece->name)
            ?? 'torso';
    }

    /** نقشی که یک رشته به آن اشاره می‌کند. */
    protected function matchRole(string $haystack): ?string
    {
        $haystack = mb_strtolower(trim($haystack));

        if ($haystack === '') {
            return null;
        }

        foreach ([
            // مچ‌بند دور مچِ دست می‌پیچد، نه دور بدن. اگر «جزئیات» شمرده شود،
            // روی محور بدن و در ارتفاع مچ می‌نشیند — یعنی یک نوار پارچه دور
            // زانو، همان چیزی که در نمای سه‌بعدی جدا از لباس دیده می‌شد.
            'sleeve' => ['sleeve', 'cuff', 'آستین', 'مچ'],
            'collar' => ['collar', 'hood', 'lapel', 'یقه', 'کلاه'],
            'skirt' => ['skirt', 'peplum', 'godet', 'دامن'],
            'leg' => ['leg', 'pant', 'trouser', 'panty', 'short', 'شلوار', 'پاچه'],
            'detail' => [
                'pocket', 'facing', 'waistband', 'belt', 'placket', 'binding', 'band',
                'strap', 'tie', 'loop', 'trim', 'patch', 'gusset', 'veil',
                'جیب', 'سجاف', 'کمربند', 'نوار', 'بند',
            ],
        ] as $role => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($haystack, $keyword)) {
                    return $role;
                }
            }
        }

        return null;
    }

    /** سمت قطعه: جلو/پشت از خود الگو می‌آید و چپ/راست از جفت آینه‌ای. */
    protected function side(PatternPiece $piece, bool $mirrored, int $quantity, int $index = 0): ?string
    {
        $side = $piece->meta['side'] ?? null;

        if (in_array($side, ['front', 'back'], true)) {
            return $side;
        }

        // یوک همیشه روی تنه پشت می‌نشیند و ژنراتورها side آن را نمی‌نویسند؛ اگر
        // اینجا جبران نشود، یوک روی سینه چیده می‌شود و درز سرشانه‌اش از میان تن
        // رد می‌شود.
        if (($piece->meta['part'] ?? null) === 'yoke') {
            return 'back';
        }

        if ($quantity > 1 && $piece->mirror) {
            return $mirrored ? 'right' : 'left';
        }

        // قطعه‌ی اندام قرینه است و ژنراتور آینه‌اش نمی‌کند، ولی دو تا که بریده
        // شد یکی روی دست (یا پای) چپ می‌رود و یکی روی راست. ملاک شماره‌ی نمونه
        // است نه آینه بودن؛ وگرنه هر دو مچ‌بند روی یک دست می‌نشینند.
        if ($quantity > 1 && in_array($piece->meta['part'] ?? null, ['cuff', 'sleeve'], true)) {
            return $index % 2 === 1 ? 'right' : 'left';
        }

        return null;
    }

    /* ---------------------------------------------------------------------
     |  ساسون
     * ------------------------------------------------------------------- */

    /**
     * ساسون‌های یک نمونه.
     *
     * ساسونی که روی مسیر بریده شده باشد دو ساقش دو کمان روی همان خط شکسته است و
     * به‌صورت درز از نوع «dart» هم می‌آید؛ ساسونی که فقط سه نقطه دارد در darts
     * می‌ماند تا مرورگر خودش دهانه‌اش را ببندد.
     *
     * @return array{darts: array<int, array<string, mixed>>, seams: array<int, array<string, mixed>>}
     */
    protected function darts(array $piece, array $instance, string $id): array
    {
        $darts = [];
        $seams = [];
        $polygon = $instance['polygon'];

        foreach ($piece['darts'] ?? [] as $dart) {
            $legs = array_values($dart['legs'] ?? []);

            if (count($legs) !== 2 || ! isset($dart['apex']['x'], $dart['apex']['y'])) {
                continue;
            }

            $first = ['x' => (float) $legs[0]['x'], 'y' => (float) $legs[0]['y']];
            $second = ['x' => (float) $legs[1]['x'], 'y' => (float) $legs[1]['y']];
            $apex = ['x' => (float) $dart['apex']['x'], 'y' => (float) $dart['apex']['y']];
            $mouth = Geometry::lerp($first, $second, 0.5);

            $startAt = $this->vertexAt($polygon, $first);
            $endAt = $this->vertexAt($polygon, $second);

            // دهانه ساسون باید در جهت مسیر خوانده شود، وگرنه کمانِ «ساق» از سمت
            // اشتباه دور قطعه می‌چرخد و به‌جای یک وجب، تقریباً کل محیط را می‌گیرد.
            if ($startAt !== null && $endAt !== null) {
                $apexAt = $this->vertexAt($polygon, $apex);

                $wrongWay = $apexAt !== null
                    ? ! $this->between($polygon, $startAt, $apexAt, $endAt)
                    : DrapeGeometry::arcLength($polygon, $startAt, $endAt)
                        > DrapeGeometry::arcLength($polygon, $endAt, $startAt);

                if ($wrongWay) {
                    [$startAt, $endAt] = [$endAt, $startAt];
                }
            }

            $record = [
                'legs' => [
                    [round($first['x'], 3), round($first['y'], 3)],
                    [round($second['x'], 3), round($second['y'], 3)],
                ],
                'apex' => [round($apex['x'], 3), round($apex['y'], 3)],
                'intake' => round((float) ($dart['intake'] ?? Geometry::distance($first, $second)), 3),
                'on_edge' => Geometry::nearestEdge($instance['outline'], $mouth)['edge'],
                'start' => $startAt,
                'end' => $endAt,
                'label' => (string) ($dart['label'] ?? 'ساسون'),
            ];

            $darts[] = $record;

            $apexAt = $this->vertexAt($polygon, $apex);

            if ($startAt === null || $endAt === null || $apexAt === null || $apexAt === $startAt || $apexAt === $endAt) {
                continue;
            }

            if (! $this->between($polygon, $startAt, $apexAt, $endAt)) {
                continue;
            }

            $legOne = DrapeGeometry::arcLength($polygon, $startAt, $apexAt);
            $legTwo = DrapeGeometry::arcLength($polygon, $apexAt, $endAt);

            $seams[] = [
                'a' => ['piece' => $id, 'from' => $startAt, 'to' => $apexAt, 'length' => round($legOne, 3)],
                'b' => ['piece' => $id, 'from' => $apexAt, 'to' => $endAt, 'length' => round($legTwo, 3)],
                'label' => (string) ($dart['label'] ?? 'ساسون'),
                'reverse' => true,
                'ease' => round($legTwo - $legOne, 3),
                'kind' => 'dart',
                'relation' => null,
            ];
        }

        return ['darts' => $darts, 'seams' => $seams];
    }

    /** شماره رأس خط شکسته که این نقطه روی آن نشسته است (اگر نشسته باشد). */
    protected function vertexAt(array $polygon, array $point): ?int
    {
        $best = null;
        $bestDistance = static::SNAP;

        foreach ($polygon as $index => $vertex) {
            $distance = Geometry::distance($vertex, $point);

            if ($distance <= $bestDistance) {
                $bestDistance = $distance;
                $best = $index;
            }
        }

        return $best;
    }

    /** آیا رأس $middle در جهت مسیر، میان $from و $to است؟ */
    protected function between(array $polygon, int $from, int $middle, int $to): bool
    {
        $count = max(1, count($polygon));
        $step = fn (int $a, int $b) => ((($b - $a) % $count) + $count) % $count;

        return $step($from, $middle) < $step($from, $to);
    }

    /* ---------------------------------------------------------------------
     |  چیدن اولیه دور بدن
     * ------------------------------------------------------------------- */

    /**
     * جای اولیه قطعه روی بدن.
     *
     * این جدول ثابتِ نام قطعه‌ها نیست؛ از برچسب لبه بالا و پایین، پهنای خود قطعه
     * و سمت آن درمی‌آید. دقیق بودنش لازم نیست — فقط باید گره نخورد: جلو و پشت
     * روی هم نیفتند و آستین از داخل تنه شروع نشود.
     *
     * @return array{zone: string, u0: float, u1: float, y_top: float, radius_hint: string, flip: bool}
     */
    protected function placement(array $instance, PatternPiece $model, DrapeBody $body): array
    {
        $role = $instance['role'];
        $side = $instance['side'];
        [$minX, $minY, $maxX, $maxY] = $instance['bounds'];
        $width = max(0.5, $maxX - $minX);
        $height = max(0.5, $maxY - $minY);

        $anchors = $this->edgeAnchors($instance);
        $top = $this->levelOf($anchors['top'], $body);
        $bottom = $this->levelOf($anchors['bottom'], $body);
        $part = $this->partLevel($model->meta['part'] ?? null, $body);

        $yTop = match (true) {
            $role === 'collar' => $body->level('neck'),
            // مچ‌بند روی همان محورِ دست است ولی سرِ دیگرش: پای آستین، نه حلقه
            $role === 'sleeve' && $part !== null => $part,
            $role === 'sleeve' => $body->level('armhole'),
            $top !== null => $top,
            $part !== null => $part,
            $bottom !== null => min(0.95, $bottom + ($height / $body->height)),
            default => match ($role) {
                'skirt', 'leg' => $body->level('waist'),
                default => $body->level('shoulder'),
            },
        };

        $middle = max(0.02, $yTop - (($height / $body->height) / 2));
        $hint = $this->radiusHint($role, $middle, $body, $instance);
        $radius = max(2.0, $body->radii[$hint] ?? $body->radii['bust']);

        /*
         * پهنای کادر برای قطعه‌ی خمیده کم است.
         *
         * نوار یقه مثل موز خمیده است: کادرش ۳۵ سانتی‌متر ولی خودِ لبه‌ی گردنش
         * ۵۴. پیچاندن با پهنای کادر یعنی همان لبه‌ی ۵۴ سانتی‌متری روی ۳۵
         * سانتی‌متر جمع شود. برای قطعه‌ای که دور چیزی می‌پیچد، طولِ بلندترین
         * لبه‌اش ملاک است، نه کادرش.
         */
        $wrap = $this->wrapsAround($role, $model) ? max($width, $this->wrapLength($instance)) : $width;
        $span = $wrap / $radius;

        /*
         * قطعه‌ای که از دور بدن بلندتر است، روی دایره‌ی خودش می‌نشیند.
         *
         * نوار یقه‌ی پیراهن ۵۴ سانتی‌متر است و دور گردن ۳۷؛ اگر همان‌جا دور
         * گردن پیچانده شود، یک‌سوم طولش فشرده می‌شود و روی خودش می‌افتد —
         * ناحیه‌ی گردن و سرشانه به‌هم می‌ریزد و درزها هیچ‌وقت جا نمی‌افتند.
         * پس شعاع از خودِ قطعه گرفته می‌شود و درزها آن را روی بدن می‌کشند،
         * نه برعکس. همین برای دامن کلوش و کمربند بلند هم درست است.
         */
        $ownRadius = null;

        if ($span > 2 * M_PI) {
            $ownRadius = round($wrap / (2 * M_PI), 2);
            $span = 2 * M_PI;
        }
        $center = $side === 'back' ? M_PI : 0.0;
        $symmetric = $instance['unfolded'] || ! $model->mirror;

        if ($role === 'sleeve') {
            $span = min($span, 2 * M_PI);
            $u0 = -$span / 2;
            $u1 = $span / 2;
        } elseif ($role === 'collar') {
            $span = min($span, 2 * M_PI);
            $u0 = M_PI - ($span / 2);
            $u1 = M_PI + ($span / 2);
        } elseif ($role === 'leg') {
            // دو نمونهٔ پاچه، دو پای جداگانه‌اند نه دو نیمهٔ یک لوله؛ پس هر کدام
            // روی استوانهٔ خودش وسط‌چین می‌شود.
            $span = min($span, M_PI);
            $u0 = $center - ($span / 2);
            $u1 = $center + ($span / 2);
        } elseif ($symmetric) {
            $u0 = $center - (min($span, M_PI) / 2);
            $u1 = $center + (min($span, M_PI) / 2);
        } else {
            $half = min($span, M_PI / 2);

            if ($this->centerAtLeft($instance)) {
                $u0 = $center;
                $u1 = $center + $half;
            } else {
                $u0 = $center - $half;
                $u1 = $center;
            }
        }

        return [
            'zone' => $this->zone($role, $side),
            'u0' => round($u0, 4),
            'u1' => round($u1, 4),
            'y_top' => round($yTop, 4),
            'radius_hint' => $hint,
            'radius' => $ownRadius,
            'flip' => $instance['mirrored'],
            // برای چیدن گروهی (فقط سمت سرور؛ در بسته نمی‌آید)
            'center' => $center,
            'span' => $span,
            'wrap' => $wrap,
            'radius_body' => $radius,
            'symmetric' => $symmetric,
            'to_right' => $symmetric ? true : $this->centerAtLeft($instance),
            'central' => $symmetric || $this->edgeTagsOf($instance, 'side') === [],
        ];
    }

    /** شماره لبه‌هایی از یک نمونه که این برچسب را دارند. */
    protected function edgeTagsOf(array $instance, string $tag): array
    {
        $found = [];

        foreach ($instance['edges'] as $edge => $data) {
            if ($data['tag'] === $tag) {
                $found[] = $edge;
            }
        }

        return $found;
    }

    /**
     * چیدن قطعه‌های یک ناحیه کنار هم، نه روی هم.
     *
     * قطعه‌ای که روی خط مرکز می‌نشیند (باز‌شده از تا، یا بی‌درزِ پهلو) وسط ناحیه
     * می‌ماند و پنل پهلو بیرون از آن می‌نشیند؛ اگر جا کم بیاید هر دو با هم فشرده
     * می‌شوند تا از نیم‌دور بدن بیرون نزنند. بدون این کار، تنهٔ مرکزی و پنل
     * پرنسسی هر دو روی مرکز جلو می‌افتند و از همان قدم اول در هم فرو می‌روند.
     *
     * قطعه‌های هم‌ترازِ روی هم (تنه پشت و یوک) هر دو مرکزی‌اند و کنار هم چیده
     * نمی‌شوند؛ چون اختلافشان در ارتفاع است نه در زاویه.
     *
     * @param  array<string, array<string, mixed>>  $instances
     */
    protected function arrange(array &$instances): void
    {
        $groups = [];

        foreach ($instances as $id => $instance) {
            $zone = $instance['placement']['zone'];

            if (! in_array($zone, ['torso_front', 'torso_back', 'skirt_front', 'skirt_back'], true)) {
                continue;
            }

            $groups[$zone.'|'.$instance['payload']['layer']][] = $id;
        }

        foreach ($groups as $ids) {
            $centralWidest = 0.0;
            $outerWidest = 0.0;

            foreach ($ids as $id) {
                $place = $instances[$id]['placement'];
                $half = $place['symmetric'] ? $place['span'] / 2 : $place['span'];

                if ($place['central']) {
                    $centralWidest = max($centralWidest, $half);
                } else {
                    $outerWidest = max($outerWidest, $half);
                }
            }

            $central = min($centralWidest, M_PI / 2);
            $outer = min($outerWidest, (M_PI / 2) - $central);

            // اگر قطعه مرکزی همه نیم‌دور را برداشته باشد، پنل پهلو جایی نمی‌ماند؛
            // یک‌چهارم دور را به آن برمی‌گردانیم.
            if ($outerWidest > 0 && $outer < 0.2) {
                $outer = min($outerWidest, M_PI / 4);
                $central = (M_PI / 2) - $outer;
            }

            foreach ($ids as $id) {
                $place = $instances[$id]['placement'];
                $room = $place['central'] ? $central : $outer;
                $start = $place['central'] ? 0.0 : $central;
                $half = min($place['symmetric'] ? $place['span'] / 2 : $place['span'], $room);
                $middle = $place['center'];

                if ($place['symmetric']) {
                    $u0 = $middle - $start - $half;
                    $u1 = $middle + $start + $half;
                } elseif ($place['to_right']) {
                    $u0 = $middle + $start;
                    $u1 = $middle + $start + $half;
                } else {
                    $u0 = $middle - $start - $half;
                    $u1 = $middle - $start;
                }

                $instances[$id]['placement']['u0'] = round($u0, 4);
                $instances[$id]['placement']['u1'] = round($u1, 4);
                $instances[$id]['payload']['placement']['u0'] = round($u0, 4);
                $instances[$id]['payload']['placement']['u1'] = round($u1, 4);
            }
        }
    }

    /**
     * پنل‌های یک آستین، کنار هم دور بازو — نه روی هم.
     *
     * آستین دوتکه (کت، کت‌وشلوار) دو پنل دارد: بالا و زیر. هر دو «آستین»اند، پس
     * هر دو وسط‌چین می‌شدند و u = -π..π می‌گرفتند — یعنی هر دو تمامِ دور بازو را
     * ادعا می‌کردند و از قدم اول در هم فرو می‌رفتند. اندازه گرفته شد: پوششِ آستین
     * روی کت ۳۰ درجه از ۳۶۰ و روی کت‌وشلوار ۴۵ — بازو عملاً لخت.
     *
     * درستش همان کاری است که arrange() برای تنه می‌کند: دور بازو میان پنل‌ها
     * تقسیم شود، به نسبتِ پهنای خودشان. و شعاع هم مشترک است: پنل‌های یک آستین
     * روی یک استوانه‌اند، پس شعاعش از *مجموع* پهنایشان می‌آید نه از پهنای هرکدام
     * جداگانه (وگرنه پنل بالا روی استوانهٔ ۴ سانتی‌متری می‌رفت و پنل زیر روی
     * ۴٫۵ سانتی‌متری، دو لولهٔ تودرتو).
     *
     * @param  array<string, array<string, mixed>>  $instances
     */
    protected function arrangeSleeves(array &$instances): void
    {
        $groups = [];

        foreach ($instances as $id => $instance) {
            $place = $instance['placement'];

            if (($instance['payload']['role'] ?? '') !== 'sleeve') {
                continue;
            }

            // مچ‌بند دور مچ می‌پیچد و پنلِ آستین نیست؛ سهمش تمامِ دور است
            if (($instance['payload']['meta']['part'] ?? '') === 'cuff') {
                continue;
            }

            $key = implode('|', [
                $place['zone'],
                $instance['payload']['layer'],
                (string) ($instance['payload']['side'] ?? ''),
                (string) $place['y_top'],
            ]);

            $groups[$key][] = $id;
        }

        foreach ($groups as $ids) {
            if (count($ids) < 2) {
                continue; // آستین یک‌تکه؛ خودش تمامِ دور را می‌گیرد
            }

            $total = 0.0;

            foreach ($ids as $id) {
                $total += max(0.5, (float) $instances[$id]['placement']['wrap']);
            }

            $radius = 0.0;

            foreach ($ids as $id) {
                $place = $instances[$id]['placement'];
                $radius = max($radius, (float) ($place['radius'] ?? 0), (float) $place['radius_body']);
            }

            $radius = round($radius, 2);

            /*
             * پنلِ پهن وسط‌چین می‌ماند، بقیه از کنارش پُر می‌کنند.
             *
             * سرِ آستین — همان کمانِ خمیده‌ای که به حلقه دوخته می‌شود — بیشترش
             * روی پنلِ بالاست. اگر چیدن از u = -π شروع شود، آن سر جای دلخواهی
             * می‌افتد و آستین از درزی کج آویزان می‌ماند: اندازه گرفته شد که
             * آستین کت‌وشلوار ۸٫۱ سانتی‌متر روی بازو سُر می‌خورد و پوستِ لخت از
             * ۲ به ۴۰ می‌رفت. آستین یک‌تکه وسط‌چین است و کار می‌کند؛ پس پنلِ
             * پهن هم همان‌جا می‌ماند.
             */
            $order = $ids;
            $at = -M_PI;

            foreach ($order as $id) {
                $place = $instances[$id]['placement'];
                $wrap = max(0.5, (float) $place['wrap']);
                $share = (2 * M_PI) * $wrap / $total;
                // پهنای خودِ پنل، فشرده‌نشده: پنل‌ها کمی روی هم می‌آیند، همان‌طور
                // که جای درز روی پارچه هست
                $half = min(M_PI, $wrap / (2 * max(0.5, $radius)), ($share / 2) * static::PANEL_OVERLAP);
                $middle = $at + ($share / 2);
                $u0 = $middle - $half;
                $u1 = $middle + $half;
                $at += $share;

                foreach (['placement', 'payload'] as $where) {
                    $target = $where === 'payload' ? 'placement' : null;
                    $slot = &$instances[$id][$where];

                    if ($target !== null) {
                        $slot = &$slot[$target];
                    }

                    $slot['u0'] = round($u0, 4);
                    $slot['u1'] = round($u1, 4);
                    $slot['radius'] = $radius;
                    unset($slot);
                }
            }
        }
    }

    /** ناحیه بدن که قطعه در آن می‌نشیند. */
    protected function zone(string $role, ?string $side): string
    {
        $back = $side === 'back';

        return match ($role) {
            'sleeve' => 'sleeve',
            'collar' => 'collar',
            'detail' => 'detail',
            'skirt' => $back ? 'skirt_back' : 'skirt_front',
            'leg' => $back ? 'leg_back' : 'leg_front',
            default => $back ? 'torso_back' : 'torso_front',
        };
    }

    /**
     * برچسب لبه بالا و لبه پایین قطعه.
     *
     * لبه‌هایی که میانگین ارتفاعشان در یک‌هشتم بالای (یا پایینِ) قطعه است شمرده
     * می‌شوند و پرتکرارترین برچسب غیرِ «default» برنده است.
     *
     * @return array{top: string|null, bottom: string|null}
     */
    protected function edgeAnchors(array $instance): array
    {
        [, $minY, , $maxY] = $instance['bounds'];
        $height = max(0.5, $maxY - $minY);
        $band = $height / 8;

        $top = [];
        $bottom = [];

        foreach ($instance['edges'] as $edge => $data) {
            if ($data['tag'] === 'default' || $data['length'] < 0.5) {
                continue;
            }

            $middle = DrapeGeometry::arcMidpoint($instance['polygon'], $data['start'], $data['end']);

            if ($middle['y'] <= $minY + $band) {
                $top[$data['tag']] = ($top[$data['tag']] ?? 0) + $data['length'];
            }

            if ($middle['y'] >= $maxY - $band) {
                $bottom[$data['tag']] = ($bottom[$data['tag']] ?? 0) + $data['length'];
            }
        }

        arsort($top);
        arsort($bottom);

        return [
            'top' => array_key_first($top),
            'bottom' => array_key_first($bottom),
        ];
    }

    /**
     * ارتفاع لبه بالای قطعه‌هایی که خودشان برچسب گویا ندارند.
     *
     * سجاف و پیش‌بند و مچ‌بند لبه‌هایشان «default» است و از روی هندسه نمی‌شود
     * فهمید کجای بدن می‌نشینند؛ ولی ژنراتور در meta.part گفته این قطعه چیست و
     * همان کافی است تا نقطه شروع بی‌ربط نباشد.
     */
    protected function partLevel(?string $part, DrapeBody $body): ?float
    {
        return match ($part) {
            'cuff' => $body->wristLevel(),
            'waistband', 'belt' => $body->level('waist'),
            'collar', 'hood' => $body->level('neck'),
            'pocket' => $body->level('highHip'),
            'strap', 'placket', 'facing', 'lapel' => $body->level('shoulder'),
            'panty' => $body->level('hip'),
            default => null,
        };
    }

    /** آیا این قطعه دور چیزی می‌پیچد (یقه، کمربند، نوار، مچ‌بند)؟ */
    protected function wrapsAround(string $role, PatternPiece $model): bool
    {
        return $role === 'collar' || in_array($model->meta['part'] ?? null, [
            'waistband', 'band', 'binding', 'cuff', 'collar',
        ], true);
    }

    /**
     * طولِ پیچیدنِ یک نوار: نصف محیط منهای پهنای نوار.
     *
     * نوار یقه از چند لبه‌ی پشت‌سرهم ساخته شده و هیچ‌کدام به‌تنهایی طولش را
     * نمی‌گویند؛ ولی هر نواری دو ضلع بلند دارد و دو ضلع کوتاه، پس نصف محیط
     * منهای بلندی، همان ضلع بلند است.
     */
    protected function wrapLength(array $instance): float
    {
        $perimeter = 0.0;

        foreach ($instance['edges'] as $info) {
            $perimeter += (float) $info['length'];
        }

        [, $minY, , $maxY] = $instance['bounds'];

        return max(0.0, ($perimeter / 2) - ($maxY - $minY));
    }

    /** ارتفاع ترازی که یک برچسب لبه به آن اشاره می‌کند. */
    protected function levelOf(?string $tag, DrapeBody $body): ?float
    {
        return match ($tag) {
            'neck' => $body->level('neck'),
            'shoulder' => $body->level('shoulder'),
            'armhole' => $body->level('armhole'),
            'waist' => $body->level('waist'),
            default => null,
        };
    }

    /** نزدیک‌ترین تراز بدن به میانه ارتفاع قطعه، از میان ترازهای همان نقش. */
    protected function radiusHint(string $role, float $middle, DrapeBody $body, array $instance): string
    {
        $wrist = $body->wristLevel();

        return match ($role) {
            'collar' => 'neck',
            'sleeve' => $body->nearestRadius($middle, ['bicep', 'wrist'], [
                'bicep' => $body->level('armhole'),
                'wrist' => $wrist,
            ]),
            'leg' => $body->nearestRadius($middle, ['hip', 'thigh', 'knee', 'ankle'], [
                'thigh' => $body->level('crotch'),
            ]),
            'skirt' => $body->nearestRadius($middle, ['waist', 'highHip', 'hip', 'knee']),
            'detail' => $body->nearestRadius($middle, ['neck', 'bust', 'waist', 'hip', 'knee', 'wrist', 'ankle'], [
                'wrist' => $wrist,
            ]),
            default => $body->nearestRadius($middle, ['neck', 'armhole', 'bust', 'underBust', 'waist', 'highHip', 'hip']),
        };
    }

    /**
     * آیا مرکز بدن (خط مرکز جلو یا پشت) سمت چپِ خودِ قطعه است؟
     *
     * درز پهلو دورترین جای قطعه از مرکز بدن است؛ پس اگر لبه‌های «side» سمت راست
     * قطعه باشند، مرکز سمت چپ است. برای نمونه آینه‌شده این حساب خودبه‌خود برعکس
     * می‌شود، چون خود هندسه قرینه شده است.
     */
    protected function centerAtLeft(array $instance): bool
    {
        [$minX, , $maxX] = $instance['bounds'];
        $middle = ($minX + $maxX) / 2;

        $weight = 0.0;
        $sum = 0.0;

        foreach ($instance['edges'] as $data) {
            if ($data['tag'] !== 'side') {
                continue;
            }

            $point = DrapeGeometry::arcMidpoint($instance['polygon'], $data['start'], $data['end']);
            $sum += $point['x'] * $data['length'];
            $weight += $data['length'];
        }

        if ($weight <= 0.0) {
            return ! $instance['mirrored'];
        }

        return ($sum / $weight) > $middle;
    }

    /* ---------------------------------------------------------------------
     |  درزها
     * ------------------------------------------------------------------- */

    /**
     * ترجمه رابطه‌های دوخت به کمان‌های همین بسته.
     *
     * @param  array<int, array<string, mixed>>  $relations
     * @param  array<string, array<string, mixed>>  $instances
     * @param  array<string, array<int, string>>  $byCode
     * @param  array<int, array<string, mixed>>  $unmatched
     * @return array<int, array<string, mixed>>
     */
    protected function seams(array $relations, array $instances, array $byCode, array &$unmatched): array
    {
        $seams = [];
        $resolved = [];

        foreach ($relations as $index => $relation) {
            $from = $this->relationSide($relation['from'] ?? []);
            $to = $this->relationSide($relation['to'] ?? []);

            if ($from === null || $to === null) {
                $unmatched[] = $this->unmatched($relation, $index, 'رابطه دوخت سرِ درستی ندارد.');

                continue;
            }

            $left = $this->arcs($instances, $byCode, $from['piece'], $from['edges']);
            $right = $this->arcs($instances, $byCode, $to['piece'], $to['edges']);

            if ($left === [] || $right === []) {
                $missing = $left === [] ? $from['piece'] : $to['piece'];
                $unmatched[] = $this->unmatched($relation, $index, "لبه‌های خواسته‌شده روی قطعه «{$missing}» در بسته پیدا نشد.");

                continue;
            }

            $resolved[$index] = ['left' => $left, 'right' => $right, 'relation' => $relation];
        }

        $resolved = $this->share($resolved);

        foreach ($resolved as $index => $entry) {
            $relation = $entry['relation'];
            $label = (string) ($relation['label'] ?? 'درز');

            [$left, $right] = $this->balance($entry['left'], $entry['right']);

            $pairs = $this->pairArcs($left, $right);

            foreach ($pairs['matched'] as [$a, $b]) {
                $seams[] = $this->seam($a, $b, $label, $index, $relation);
            }

            if ($pairs['left'] !== [] || $pairs['right'] !== []) {
                $unmatched[] = $this->unmatched($relation, $index, sprintf(
                    'تعداد کمان دو سمت برابر نشد؛ %d کمان بی‌جفت ماند.',
                    count($pairs['left']) + count($pairs['right']),
                ));
            }
        }

        return $seams;
    }

    /**
     * فهرست رابطه‌های دوخت.
     *
     * suggest() جفت‌های نام‌دار را می‌دهد (سرشانه، پهلو، آستین، یقه) و complete()
     * کمان‌های جفت‌نشده — درز پرنسسی و پنل‌های کرست — را روی هم می‌آورد. خروجی
     * دوم فقط «افزوده‌ها» است، پس دو فهرست به هم چسبانده می‌شوند؛ اگر روزی
     * complete() خودش فهرست کامل را برگرداند، تکراری‌ها اینجا کنار می‌روند.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function relations(Pattern $pattern): array
    {
        $relations = SewingRelationBuilder::suggest($pattern);

        if (method_exists(SewingRelationBuilder::class, 'complete')) {
            $relations = array_merge($relations, SewingRelationBuilder::complete($pattern, $relations));
        }

        $seen = [];
        $out = [];

        foreach ($relations as $relation) {
            $from = $this->relationSide($relation['from'] ?? []);
            $to = $this->relationSide($relation['to'] ?? []);
            $key = $from === null || $to === null
                ? null
                : $from['piece'].'|'.implode(',', $from['edges']).'~'.$to['piece'].'|'.implode(',', $to['edges']);

            if ($key !== null && isset($seen[$key])) {
                continue;
            }

            if ($key !== null) {
                $seen[$key] = true;
            }

            $out[] = $relation;
        }

        return $out;
    }

    /**
     * یک سرِ رابطه: کد قطعه و فهرست لبه‌های اصلی.
     *
     * suggest() یک لبه تکی می‌دهد و complete() آرایه‌ای از لبه‌های پشت‌سرهم؛ هر
     * دو شکل پذیرفته می‌شود.
     *
     * @return array{piece: string, edges: array<int, int>}|null
     */
    protected function relationSide(array $side): ?array
    {
        $code = trim((string) ($side['piece'] ?? ''));

        if ($code === '') {
            return null;
        }

        $edges = [];

        if (isset($side['edges']) && is_array($side['edges'])) {
            foreach ($side['edges'] as $edge) {
                if (is_numeric($edge)) {
                    $edges[] = (int) $edge;
                }
            }
        } elseif (is_numeric($side['edge'] ?? null)) {
            $edges[] = (int) $side['edge'];
        }

        return $edges === [] ? null : ['piece' => $code, 'edges' => $edges];
    }

    /**
     * کمان‌های یک سرِ رابطه روی همه نمونه‌های آن قطعه.
     *
     * یک لبه روی قطعه‌ای که تایش باز شده دو بار می‌آید (یکی روی هر نیمه)، پس
     * خروجی فهرست کمان است نه یک کمان.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function arcs(array $instances, array $byCode, string $code, array $edges): array
    {
        $arcs = [];

        foreach ($byCode[$code] ?? [] as $id) {
            $instance = $instances[$id];

            foreach (DrapeGeometry::runs($instance['origins'], $edges) as $run) {
                $from = $instance['spans'][$run[0]][0];
                $to = $instance['spans'][$run[count($run) - 1]][1];
                $length = 0.0;

                foreach ($run as $edge) {
                    $length += $instance['lengths'][$edge] ?? 0.0;
                }

                if ($length < 0.05) {
                    continue;
                }

                $middle = DrapeGeometry::arcMidpoint($instance['polygon'], $from, $to);

                $arcs[] = [
                    'piece' => $id,
                    'from' => $from,
                    'to' => $to,
                    'length' => $length,
                    'instance' => $instance,
                    'at' => $this->onBody($instance, $middle),
                    'frame' => $this->frame($instance['role']),
                    'body_side' => $this->bodySide($instance),
                ];
            }
        }

        return $arcs;
    }

    /**
     * دو نمونه‌ی دقیقاً هم‌جا، یک لایه‌اند نه دو قطعه.
     *
     * یقه و یوک و نوارها دو بار بریده می‌شوند چون دو لایه دارند: رو و زیر.
     * هر دو لایه یک شکل و یک جا دارند، پس در بسته دو نمونه‌ی هم‌جا می‌شوند و
     * روی مانکن دو پارچه‌ی هم‌اندازه روی هم می‌افتند و با هم می‌جنگند — همان
     * توده‌ای که روی سرشانه‌ی پیراهن دیده می‌شد و لباس را نامتقارن نشان می‌داد
     * (یوک دوم فقط به یقه‌ی دوم دوخته بود و آزاد می‌ماند).
     *
     * ملاک هندسه است نه نام: نمونه‌ای که بازه‌ی زاویه‌ای و ارتفاعش دقیقاً با
     * نمونه‌ی پیش از خودش یکی است، لایه‌ی دوم همان است. لنگه‌ی چپ و راست بازه‌ی
     * یکسان ندارند (یا سمتشان فرق دارد)، پس دست‌نخورده می‌مانند.
     *
     * @param  array<string, array<string, mixed>>  $instances
     * @param  array<int, string>  $notes
     * @return array{0: array<string, array<string, mixed>>, 1: array<string, array<int, string>>}
     */
    protected function dedupe(array $instances, array &$notes): array
    {
        $seen = [];
        $kept = [];
        $byCode = [];
        $dropped = 0;

        foreach ($instances as $id => $instance) {
            $placement = $instance['placement'];
            $key = implode('|', [
                $instance['code'],
                $instance['side'] ?? '—',
                round($placement['u0'], 3),
                round($placement['u1'], 3),
                round($placement['y_top'], 4),
            ]);

            if (isset($seen[$key])) {
                $dropped++;

                continue;
            }

            $seen[$key] = true;
            $kept[$id] = $instance;
            $byCode[$instance['code']][] = $id;
        }

        if ($dropped > 0) {
            $notes[] = $dropped.' قطعه‌ی هم‌جا (لایه‌ی دوم یقه، یوک یا نوار) در پیش‌نمایش یک بار نشان داده می‌شود.';
        }

        return [$kept, $byCode];
    }

    /**
     * قطعه‌ای که هیچ رابطه‌ای به آن نرسیده، به همسایه‌اش دوخته می‌شود.
     *
     * سنجش روی کل کاتالوگ: از ۲۳۶ مدل، ۵۲۱ قطعه هیچ درزی نداشتند — جیب و
     * سجاف و نوار، ولی همچنین ۵۴ یقه، ۴۳ تکه دامن، ۳۱ آستین و ۲۸ تنه. قطعه‌ی
     * بی‌درز روی مانکن یا می‌افتد یا باید پنهان شود؛ هر دو یعنی لباس ناقص.
     *
     * فهرست رابطه‌ها این‌ها را نمی‌بیند چون نامشان را نمی‌شناسد. ولی جای همه‌شان
     * روی بدن معلوم است: یقه کنار خط یقه است، جیب روی تنه، نوار روی لبه‌ای که
     * می‌پوشاند. پس همان ملاکی که برای جفت‌کردن کمان‌ها داریم — نزدیکی روی بدن
     * و هم‌طولی — این‌جا هم به کار می‌آید: بلندترین کمانِ قطعه‌ی جامانده به
     * نزدیک‌ترین کمانِ هم‌طولِ آزاد روی یک قطعه‌ی دوخته‌شده می‌رسد.
     *
     * سخت‌گیرانه است و باید باشد: درزی که وجود ندارد لباس را پیچ می‌دهد.
     *
     * @param  array<int, array<string, mixed>>  $seams
     * @return array<int, array<string, mixed>>
     */
    protected function adopt(array $instances, array $seams): array
    {
        $stitched = [];
        $used = [];

        foreach ($seams as $seam) {
            foreach (['a', 'b'] as $end) {
                $stitched[$seam[$end]['piece']] = true;
                $used[$seam[$end]['piece'].'|'.$seam[$end]['from'].'|'.$seam[$end]['to']] = true;
            }
        }

        $free = [];

        foreach ($instances as $id => $instance) {
            if (! isset($stitched[$id])) {
                continue;
            }

            foreach ($this->sewableArcs($instance) as $arc) {
                if (! isset($used[$id.'|'.$arc['from'].'|'.$arc['to']])) {
                    $free[] = $arc;
                }
            }
        }

        $out = [];

        foreach ($instances as $id => $instance) {
            if (isset($stitched[$id]) || $free === []) {
                continue;
            }

            $arcs = $this->sewableArcs($instance);
            $best = null;

            foreach ($arcs as $arc) {
                foreach ($free as $key => $partner) {
                    $longer = max($arc['length'], $partner['length']);

                    if ($longer < 4.0 || abs($arc['length'] - $partner['length']) / $longer > 0.25) {
                        continue;
                    }

                    $cost = $this->cost($arc, $partner);

                    if ($cost > 25.0) {
                        continue; // بیش از یک وجب دورتر، همسایه نیست
                    }

                    if ($best === null || $cost < $best['cost']) {
                        $best = ['cost' => $cost, 'arc' => $arc, 'partner' => $partner, 'key' => $key];
                    }
                }
            }

            if ($best === null) {
                continue;
            }

            $out[] = $this->seam($best['arc'], $best['partner'], 'دوخت به قطعه‌ی همسایه', null, []);
            $stitched[$id] = true;
            unset($free[$best['key']]);
        }

        return $out;
    }

    /**
     * کمان‌های دوختنیِ یک نمونه، از بلند به کوتاه.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function sewableArcs(array $instance): array
    {
        $arcs = [];

        foreach ($instance['edges'] as $edge => $info) {
            if ($info['length'] < 2.0) {
                continue;
            }

            $middle = DrapeGeometry::arcMidpoint($instance['polygon'], $info['start'], $info['end']);

            $arcs[] = [
                'piece' => $instance['id'],
                'from' => $info['start'],
                'to' => $info['end'],
                'length' => $info['length'],
                'tag' => (string) ($info['tag'] ?? 'default'),
                'instance' => $instance,
                'at' => $at = $this->onBody($instance, $middle),
                'frame' => $this->frame($instance['role']),
                'body_side' => $this->arcSide($instance, $at),
            ];
        }

        usort($arcs, fn (array $a, array $b) => $b['length'] <=> $a['length']);

        return $arcs;
    }

    /**
     * سمتِ بدنِ یک کمان — نه سمتِ قطعه‌ای که کمان رویش است.
     *
     * یوکِ پشت روی تای پارچه بریده می‌شود و از شانهٔ چپ تا شانهٔ راست می‌رسد؛
     * پس دو حلقهٔ آستین دارد، یکی چپ و یکی راست. تا وقتی سمت را از خودِ قطعه
     * می‌گرفتیم، هر دو «چپ» بودند و جریمهٔ سمتِ مخالف (هزار) آستینِ راست را از
     * یوک دور می‌کرد؛ اندازه‌گیری هزینهٔ ۱۰۰۹ را نشان داد. کمان جای خودش را
     * روی بدن دارد، پس سمتش را هم خودش دارد.
     *
     * آستین و پا استثناءاند: زاویهٔ آنها دور بازو و ران می‌چرخد، نه دور تن.
     *
     * @param  array{u: float, y: float}  $at
     */
    protected function arcSide(array $instance, array $at): ?string
    {
        if (in_array($instance['role'], ['sleeve', 'leg'], true)) {
            return $this->bodySide($instance);
        }

        $u = $this->wrap((float) ($at['u'] ?? 0));

        // نزدیکِ مرکزِ جلو یا مرکزِ پشت، کمان به هیچ سمتی تعلق ندارد
        if (abs($u) < 0.15 || abs(abs($u) - M_PI) < 0.15) {
            return null;
        }

        return $u < 0 ? 'left' : 'right';
    }

    /**
     * درزی که یک شریکش را جا گذاشته.
     *
     * پیراهنِ یوک‌دار نمونهٔ روشنش است: سرآستین باید هم به حلقهٔ تنه و هم به آن
     * تکه از حلقه که روی یوک افتاده دوخته شود. رابطه‌های سازنده ولی یک درز
     * می‌نویسند — سرآستین به حلقهٔ پشت — و لبهٔ ۵٫۹ سانتی‌متریِ حلقهٔ یوک بی‌دوخت
     * می‌ماند. نتیجه روی مانکن: ۱۸٫۴ سانتی‌متر سرآستین روی ۱۱٫۴ سانتی‌متر حلقه
     * چپانده می‌شود و یک زبانهٔ آزاد سر شانه تکان می‌خورد. در عکسِ کاربر همین
     * دیده می‌شد.
     *
     * قاعده‌ای که این را می‌گیرد اندازه‌پذیر است و به هیچ مدلی گره نخورده:
     * درزی که دو سرش بیش از حدِ آزادیِ پارچه ناهم‌طول‌اند، و کمانِ آزادی با همان
     * برچسب کنارش هست که تفاوت را پر می‌کند، شریکش را جا گذاشته. آن وقت کمانِ
     * بلند به نسبتِ طول میان دو شریک بریده می‌شود.
     *
     * @param  array<string, array<string, mixed>>  $instances
     * @param  array<int, array<string, mixed>>  $seams
     * @return array<int, array<string, mixed>>
     */
    protected function splice(array $instances, array $seams): array
    {
        $free = $this->freeArcs($instances, $seams);

        if ($free === []) {
            return $seams;
        }

        foreach ($seams as $index => $seam) {
            if (($seam['kind'] ?? 'seam') !== 'seam') {
                continue;
            }

            $long = $seam['a']['length'] >= $seam['b']['length'] ? 'a' : 'b';
            $short = $long === 'a' ? 'b' : 'a';
            $excess = (float) $seam[$long]['length'] - (float) $seam[$short]['length'];

            if ($excess < 2.0 || $excess / max(0.01, (float) $seam[$long]['length']) < static::EASE_SHARE) {
                continue; // در حدِ آزادیِ پارچه؛ درز سالم است
            }

            $arcs = $this->arcsOf($instances, $seam);

            if ($arcs === null) {
                continue;
            }

            // چینِ اعلام‌شده خودش توضیحِ اضافه‌طول است؛ آن‌جا شریکی جا نمانده
            if ($excess <= $this->declaredFullness($arcs[$long]['instance']) + 1.0) {
                continue;
            }

            $best = null;

            foreach ($free as $key => $candidate) {
                if ($candidate['piece'] === $seam['a']['piece'] || $candidate['piece'] === $seam['b']['piece']) {
                    continue;
                }

                /*
                 * برچسبِ کمانِ آزاد باید همان برچسبِ سرِ کوتاه باشد. سرِ کوتاه
                 * همان چیزی است که کم آورده — حلقهٔ آستینِ تنه — و شریکِ جامانده
                 * هم حتماً حلقهٔ آستین است. با پذیرفتنِ برچسبِ سرِ بلند هم،
                 * لبهٔ بی‌نامِ پاتلت به خط یقهٔ پشت دوخته شد؛ اندازه‌گیری نشانش داد.
                 */
                if ($candidate['tag'] !== $arcs[$short]['tag'] || $candidate['tag'] === 'default') {
                    continue;
                }

                // باید همان کمبود را پر کند، نه چیز دیگری را
                if (abs($candidate['length'] - $excess) / max($candidate['length'], $excess) > 0.4) {
                    continue;
                }

                $cost = $this->cost($candidate, $arcs[$long]);

                if ($cost > 25.0) {
                    continue;
                }

                if ($best === null || $cost < $best['cost']) {
                    $best = ['cost' => $cost, 'arc' => $candidate, 'key' => $key];
                }
            }

            if ($best === null) {
                continue;
            }

            $partners = [$arcs[$short], $best['arc']];
            $parts = $this->splitArc($arcs[$long], $this->shares($partners));

            if (count($parts) !== 2) {
                continue;
            }

            // کدام تکه به کدام شریک؟ هزینهٔ هر دو حالت سنجیده می‌شود
            $straight = $this->cost($parts[0], $partners[0]) + $this->cost($parts[1], $partners[1]);
            $swapped = $this->cost($parts[0], $partners[1]) + $this->cost($parts[1], $partners[0]);

            if ($swapped < $straight) {
                $parts = [$parts[1], $parts[0]];
            }

            $seams[$index] = $this->seam($parts[0], $partners[0], (string) ($seam['label'] ?? 'درز'), $seam['relation'] ?? null, $seam);
            $seams[] = $this->seam($parts[1], $partners[1], (string) ($seam['label'] ?? 'درز'), $seam['relation'] ?? null, $seam);

            unset($free[$best['key']]);
        }

        return array_values($seams);
    }

    /**
     * خطِ خوابِ یقه، به شکلِ y روی همان چندضلعیِ بسته.
     *
     * یقهٔ یک‌تکه روی این خط تا می‌شود و می‌خوابد. چون قطعه ممکن است باز یا سروته
     * شده باشد، فاصله را از خودِ لبهٔ برچسب‌خوردهٔ «neck» می‌سنجیم نه از کادر، و
     * جواب را در دستگاهِ همان چندضلعی می‌دهیم تا مرورگر بی حساب‌وکتاب بخواندش.
     *
     * @param  array<int, array{x: float, y: float}>  $polygon
     * @param  array<int, array{tag: string, start: int}>  $edges
     */
    protected function rollLine(array $piece, array $polygon, array $edges): ?float
    {
        $roll = $piece['meta']['roll_line'] ?? null;

        if (! is_numeric($roll) || (float) $roll <= 0.01 || $polygon === []) {
            return null;
        }

        $neck = null;

        foreach ($edges as $info) {
            if (($info['tag'] ?? '') === 'neck' && isset($polygon[$info['start']]['y'])) {
                $neck = (float) $polygon[$info['start']]['y'];

                break;
            }
        }

        if ($neck === null) {
            return null;
        }

        [, $minY, , $maxY] = Geometry::bounds($polygon);

        // خط یقه یا کفِ کادر است یا سقفش؛ خطِ خواب از همان‌جا به درونِ قطعه می‌رود
        $inward = abs($neck - $minY) < abs($neck - $maxY) ? 1 : -1;

        return round($neck + ($inward * (float) $roll), 3);
    }

    /** پُریِ اعلام‌شدهٔ یک قطعه: چین و پیلی، سانتی‌متر. */
    protected function declaredFullness(array $instance): float
    {
        $total = 0.0;

        foreach (['gathers', 'pleats'] as $key) {
            foreach ((array) ($instance['meta'][$key] ?? []) as $entry) {
                $total += abs((float) ($entry['amount'] ?? ($entry['depth'] ?? 0)));
            }
        }

        return $total;
    }

    /**
     * کمان‌های دوختنیِ بی‌درز، روی همهٔ قطعه‌ها.
     *
     * @param  array<string, array<string, mixed>>  $instances
     * @param  array<int, array<string, mixed>>  $seams
     * @return array<int, array<string, mixed>>
     */
    protected function freeArcs(array $instances, array $seams): array
    {
        $used = [];

        foreach ($seams as $seam) {
            foreach (['a', 'b'] as $end) {
                $used[$seam[$end]['piece'].'|'.$seam[$end]['from'].'|'.$seam[$end]['to']] = true;
            }
        }

        $free = [];

        foreach ($instances as $id => $instance) {
            foreach ($this->sewableArcs($instance) as $arc) {
                if (! isset($used[$id.'|'.$arc['from'].'|'.$arc['to']])) {
                    $free[] = $arc;
                }
            }
        }

        return $free;
    }

    /**
     * دو سر یک درز، به شکلِ کمانِ کامل (با نمونه و جای روی بدن).
     *
     * @param  array<string, array<string, mixed>>  $instances
     * @return array{a: array<string, mixed>, b: array<string, mixed>}|null
     */
    protected function arcsOf(array $instances, array $seam): ?array
    {
        $out = [];

        foreach (['a', 'b'] as $end) {
            $instance = $instances[$seam[$end]['piece']] ?? null;

            if ($instance === null) {
                return null;
            }

            $from = (int) $seam[$end]['from'];
            $to = (int) $seam[$end]['to'];
            $tag = 'default';

            foreach ($instance['edges'] as $info) {
                if ((int) $info['start'] === $from) {
                    $tag = (string) ($info['tag'] ?? 'default');

                    break;
                }
            }

            $out[$end] = [
                'piece' => $seam[$end]['piece'],
                'from' => $from,
                'to' => $to,
                'length' => (float) $seam[$end]['length'],
                'tag' => $tag,
                'instance' => $instance,
                'at' => $this->onBody($instance, DrapeGeometry::arcMidpoint($instance['polygon'], $from, $to)),
                'frame' => $this->frame($instance['role']),
                'body_side' => $this->bodySide($instance),
            ];
        }

        return $out;
    }

    /**
     * بستن مرکز جلو و مرکز پشت.
     *
     * قطعه‌ای که دو برشِ آینه‌ای دارد — بالاتنهٔ پشتِ زیپ‌دار، جلوی پیراهن
     * دکمه‌دار — روی تن با زیپ یا دکمه بسته می‌شود، ولی هیچ رابطهٔ دوختی برایش
     * نوشته نمی‌شود چون دوخته نمی‌شود. برای نمای سه‌بعدی همین کافی است که لباس
     * از پشت باز بماند و دو نیمه از تن آویزان شوند؛ چیزی که در نخستین نما دیده
     * شد.
     *
     * پس دو نیمه از همان لبه‌ای که به هم می‌رسند بسته می‌شوند: بلندترین کمانِ
     * هر نیمه که نزدیک مرز مشترکِ دو نیمه است. اگر چنین کمانی پیدا نشود یا دو
     * طول به هم نخورند، چیزی بسته نمی‌شود.
     *
     * @param  array<string, array<int, string>>  $byCode
     * @return array<int, array<string, mixed>>
     */
    protected function closures(array $instances, array $byCode): array
    {
        $out = [];

        foreach ($byCode as $ids) {
            if (count($ids) !== 2) {
                continue;
            }

            [$first, $second] = [$instances[$ids[0]], $instances[$ids[1]]];

            if (empty($second['payload']['mirrored'])) {
                continue;
            }

            // مرز مشترک دو نیمه: جایی که بازهٔ زاویه‌ای یکی تمام و دیگری شروع می‌شود
            $meeting = $this->meetingAngle($first['placement'], $second['placement']);

            if ($meeting === null) {
                continue;
            }

            $a = $this->closureArc($first, $meeting);
            $b = $this->closureArc($second, $meeting);

            if ($a === null || $b === null) {
                continue;
            }

            $longer = max($a['length'], $b['length']);

            if ($longer < 8.0 || abs($a['length'] - $b['length']) / $longer > 0.2) {
                continue;
            }

            $front = abs(atan2(sin($meeting), cos($meeting))) < M_PI_2;

            $out[] = $this->seam(
                $a,
                $b,
                $front ? 'بستن مرکز جلو' : 'بستن مرکز پشت',
                // این درز از هیچ رابطه‌ای نیامده؛ خودِ هندسه آن را می‌بندد
                null,
                ['reverse' => true],
            );
        }

        return $out;
    }

    /** زاویه‌ای که دو نیمه در آن به هم می‌رسند؛ null یعنی کنار هم نیستند. */
    protected function meetingAngle(array $a, array $b): ?float
    {
        foreach ([[$a['u1'], $b['u0']], [$a['u0'], $b['u1']]] as [$left, $right]) {
            if (abs($left - $right) < 0.05) {
                return ($left + $right) / 2;
            }
        }

        return null;
    }

    /**
     * کمانی از یک نمونه که روی مرز مشترک می‌نشیند.
     *
     * ملاک دوتاست و هر دو لازم است: نزدیکی به مرز، و بلندی. لبهٔ کوتاهِ کنارِ
     * مرز (مثل گوشهٔ یقه) نباید جای درزِ مرکز را بگیرد.
     */
    protected function closureArc(array $instance, float $meeting): ?array
    {
        $best = null;

        foreach ($instance['edges'] as $edge => $info) {
            if (in_array($info['tag'], ['hem', 'waist', 'neck', 'shoulder', 'armhole'], true)) {
                continue;
            }

            $middle = DrapeGeometry::arcMidpoint($instance['polygon'], $info['start'], $info['end']);
            $at = $this->onBody($instance, $middle);
            $gap = abs($at['u'] - $meeting);

            if ($gap > 0.25 || $info['length'] < 8.0) {
                continue;
            }

            if ($best === null || $info['length'] > $best['length']) {
                $best = [
                    'piece' => $instance['id'],
                    'from' => $info['start'],
                    'to' => $info['end'],
                    'length' => $info['length'],
                    'instance' => $instance,
                    'at' => $at,
                    'frame' => $this->frame($instance['role']),
                    'body_side' => $this->bodySide($instance),
                ];
            }
        }

        return $best;
    }

    /**
     * کمانی که چند رابطه سراغش را می‌گیرند، میانشان تقسیم می‌شود.
     *
     * کمربندِ دامن کلوش یک نوارِ بلند است و خط کمرِ دامن چند کمان؛ سازنده‌ی
     * رابطه‌ها برای هر کمانِ کمر یک رابطه می‌نویسد و در همه‌شان همان یک نوار را
     * می‌گذارد. اگر هر رابطه کلِ نوار را بردارد، نوار از چند جا هم‌زمان کشیده
     * می‌شود و لباس روی مانکن مچاله می‌شود؛ اندازه‌گیری روی کاتالوگ: نودتا از
     * ۲۳۶ مدل دست‌کم یک درز با بیش از ۲۵٪ اختلاف طول داشتند و بدترینشان ۹۲٪.
     *
     * پس نوار به نسبت طولِ سرِ مقابلِ هر رابطه بریده می‌شود و تکه‌ها به ترتیبِ
     * جایی که روی بدن می‌نشینند پخش می‌شوند — همان کاری که خیاط با نشانه‌گذاری
     * کمربند می‌کند.
     *
     * @param  array<int, array{left: array, right: array, relation: array}>  $resolved
     * @return array<int, array{left: array, right: array, relation: array}>
     */
    protected function share(array $resolved): array
    {
        foreach (['left', 'right'] as $side) {
            $other = $side === 'left' ? 'right' : 'left';
            $users = [];

            foreach ($resolved as $index => $entry) {
                $key = implode('+', array_map(
                    fn (array $arc) => $arc['piece'].'|'.$arc['from'].'|'.$arc['to'],
                    $entry[$side],
                ));

                $users[$key][] = $index;
            }

            foreach ($users as $indexes) {
                if (count($indexes) < 2) {
                    continue;
                }

                $band = $resolved[$indexes[0]][$side];

                if (count($band) === 1) {
                    $split = $this->shareAlong($resolved, $indexes, $side, $other, $band[0]);

                    if ($split !== null) {
                        $resolved = $split;

                        continue;
                    }
                }

                // ترتیب روی بدن، نه ترتیب فهرست؛ وگرنه تکه‌ی کمر جلو به پشت می‌رود
                usort($indexes, fn (int $a, int $b) => $this->arcAnchor($resolved[$a][$other])
                    <=> $this->arcAnchor($resolved[$b][$other]));

                $shares = array_map(
                    fn (int $index) => max(0.01, array_sum(array_column($resolved[$index][$other], 'length'))),
                    $indexes,
                );
                $total = array_sum($shares);
                $ratios = array_map(fn (float $share) => $share / $total, $shares);

                // هر کمانِ این سمت به همان نسبت‌ها بریده می‌شود؛ کمربندِ دوتکه
                // هم دو کمان دارد و هر دو باید میان همان رابطه‌ها پخش شوند
                foreach ($band as $position => $arc) {
                    $pieces = $this->splitArc($arc, $ratios);

                    if (count($pieces) !== count($indexes)) {
                        continue;
                    }

                    foreach ($indexes as $order => $index) {
                        $resolved[$index][$side][$position] = $pieces[$order];
                    }
                }
            }
        }

        return $resolved;
    }

    /**
     * بریدنِ یک کمانِ مشترک به ترتیبی که خودِ کمان دارد، نه به ترتیبِ زاویهٔ بدن.
     *
     * خط یقهٔ یقهٔ پیراهن این را لازم کرد. کمانِ یقه از نوکِ چپ می‌رود، از مرکز
     * پشت می‌گذرد و به نوکِ راست می‌رسد؛ پس تکهٔ «پشت» وسطِ کمان است، نه یک
     * سرش. با مرتب‌کردن رابطه‌ها بر پایهٔ میانگینِ زاویهٔ سرِ مقابل، میانگینِ دو
     * تنهٔ جلو صفر درمی‌آید — مرکزِ جلو — و کمان به [جلو][پشت] بریده می‌شود.
     * نتیجه: ۲۲٫۵ سانتی‌متر یقه روی خط یقهٔ ۱۹٫۲ سانتی‌متریِ پشت.
     *
     * پس جای هر سرِ مقابل روی خودِ کمان پیدا می‌شود: نزدیک‌ترین رأسِ کمان به آن،
     * و فاصله‌اش از سرِ کمان. آن وقت بریدن به ترتیبِ راه رفتنِ روی کمان است و
     * تکه‌های یک رابطه لازم نیست پشت‌سرِ هم باشند.
     *
     * @param  array<int, array{left: array, right: array, relation: array}>  $resolved
     * @param  array<int, int>  $indexes
     * @return array<int, array{left: array, right: array, relation: array}>|null
     */
    protected function shareAlong(array $resolved, array $indexes, string $side, string $other, array $arc): ?array
    {
        $targets = [];

        foreach ($indexes as $index) {
            foreach ($resolved[$index][$other] as $position => $partner) {
                $targets[] = [
                    'index' => $index,
                    'position' => $position,
                    'along' => $this->along($arc, $partner),
                    'length' => max(0.01, (float) $partner['length']),
                ];
            }
        }

        if (count($targets) < 2) {
            return null;
        }

        usort($targets, fn (array $a, array $b) => $a['along'] <=> $b['along']);

        $total = array_sum(array_column($targets, 'length'));
        $parts = $this->splitArc($arc, array_map(
            fn (array $target) => $target['length'] / $total,
            $targets,
        ));

        if (count($parts) !== count($targets)) {
            return null; // کمان جای بریدن نداشت؛ دست‌نخورده بماند
        }

        $byRelation = [];

        foreach ($targets as $order => $target) {
            $byRelation[$target['index']][$target['position']] = $parts[$order];
        }

        foreach ($byRelation as $index => $list) {
            ksort($list);
            $resolved[$index][$side] = array_values($list);
        }

        return $resolved;
    }

    /**
     * جای یک کمانِ روبه‌رو روی این کمان: فاصلهٔ نزدیک‌ترین رأس از سرِ کمان.
     *
     * @return float سانتی‌متر از سرِ کمان
     */
    protected function along(array $arc, array $partner): float
    {
        $polygon = $arc['instance']['polygon'];
        $count = count($polygon);
        $same = $arc['frame'] === $partner['frame'];
        $best = INF;
        $at = 0.0;
        $walked = 0.0;
        $index = $arc['from'];

        for ($step = 0; $step < $count; $step++) {
            $here = $this->distance($this->onBody($arc['instance'], $polygon[$index]), $partner['at'], $same);

            if ($here < $best) {
                $best = $here;
                $at = $walked;
            }

            if ($index === $arc['to']) {
                break;
            }

            $next = ($index + 1) % $count;
            $walked += Geometry::distance($polygon[$index], $polygon[$next]);
            $index = $next;
        }

        return $at;
    }

    /** جای یک سرِ رابطه روی بدن، برای مرتب کردن تکه‌های یک کمانِ مشترک. */
    protected function arcAnchor(array $arcs): float
    {
        if ($arcs === []) {
            return 0.0;
        }

        return array_sum(array_map(fn (array $arc) => (float) ($arc['at']['u'] ?? 0), $arcs)) / count($arcs);
    }

    /**
     * هم‌شمار کردن دو سر یک درز با شکستن کمانِ بلند.
     *
     * خط کمر لباس غلافی نمونهٔ روشنش است: بالاتنهٔ پشت دو نیمه است و دامنِ پشت
     * یک قطعهٔ کامل، پس یک کمانِ ۴۴ سانتی‌متری باید به دو کمانِ ۲۲ سانتی‌متری
     * برسد. با جفت‌سازی یک‌به‌یک، یکی از دو نیمه بی‌دوخت می‌ماند و روی مانکن از
     * تن آویزان می‌شود — همان چیزی که در نخستین نمای سه‌بعدی دیده شد.
     *
     * فقط حالت «یک در برابر چند» شکسته می‌شود. اگر هر دو سر چند کمان داشته
     * باشند و شمارشان یکی نباشد، دست نمی‌زنیم و همان‌طور که هست گزارش می‌شود؛
     * حدس زدن در آن حالت یعنی درزی که وجود ندارد.
     *
     * @return array{0: array<int, array>, 1: array<int, array>}
     */
    protected function balance(array $left, array $right): array
    {
        if (count($left) === count($right)) {
            return [$left, $right];
        }

        if (count($left) === 1 && count($right) > 1) {
            return [$this->splitArc($left[0], $this->shares($right)), $right];
        }

        if (count($right) === 1 && count($left) > 1) {
            return [$left, $this->splitArc($right[0], $this->shares($left))];
        }

        return [$left, $right];
    }

    /** سهم هر کمان از طول کل، برای شکستن کمان روبه‌رو به همان نسبت‌ها. */
    protected function shares(array $arcs): array
    {
        $total = array_sum(array_map(fn (array $arc) => (float) $arc['length'], $arcs));

        if ($total < 0.01) {
            return array_fill(0, count($arcs), 1 / max(1, count($arcs)));
        }

        return array_map(fn (array $arc) => (float) $arc['length'] / $total, $arcs);
    }

    /**
     * شکستن یک کمان به چند کمانِ پشت‌سرهم، به نسبت‌های خواسته‌شده.
     *
     * برش روی نزدیک‌ترین رأس انجام می‌شود، نه وسط یک پاره‌خط؛ قرارداد بسته
     * می‌گوید دو سر هر درز باید به رأس واقعی اشاره کنند.
     *
     * @param  array<int, float>  $shares
     * @return array<int, array<string, mixed>>
     */
    protected function splitArc(array $arc, array $shares): array
    {
        $polygon = $arc['instance']['polygon'];
        $count = count($polygon);
        $total = DrapeGeometry::arcLength($polygon, $arc['from'], $arc['to']);

        if ($total < 0.1 || $count < 3) {
            return [$arc];
        }

        // مرزهای برش را روی رأس‌ها پیدا کن
        $cuts = [$arc['from']];
        $target = 0.0;
        $walked = 0.0;
        $index = $arc['from'];
        $wanted = array_slice($shares, 0, count($shares) - 1);

        foreach ($wanted as $share) {
            $target += $share * $total;

            while ($index !== $arc['to']) {
                $next = ($index + 1) % $count;
                $step = Geometry::distance($polygon[$index], $polygon[$next]);

                // نزدیک‌ترین رأس، نه رأسِ پیش از هدف: با گردکردن به پایین، برشِ
                // خط یقه یک رأسِ کامل عقب می‌افتاد و ۲٫۶ سانتی‌متر جابه‌جا می‌شد
                if ($walked + ($step / 2) > $target) {
                    break;
                }

                $walked += $step;
                $index = $next;
            }

            $cuts[] = $index;
        }

        $cuts[] = $arc['to'];

        $pieces = [];

        for ($i = 0; $i < count($cuts) - 1; $i++) {
            [$from, $to] = [$cuts[$i], $cuts[$i + 1]];

            if ($from === $to) {
                return [$arc]; // برش بی‌معنا شد؛ کمان دست‌نخورده می‌ماند
            }

            $pieces[] = array_merge($arc, [
                'from' => $from,
                'to' => $to,
                'length' => DrapeGeometry::arcLength($polygon, $from, $to),
                'at' => $this->onBody($arc['instance'], DrapeGeometry::arcMidpoint($polygon, $from, $to)),
            ]);
        }

        return $pieces;
    }

    /**
     * جفت‌کردن کمان‌های دو سر یک رابطه.
     *
     * ملاک نزدیکی روی بدن است، نه شماره نمونه: کمانی که روی پهلوی چپ نشسته با
     * کمان پهلوی چپ جفت می‌شود. برای قطعه‌های دست و پا که هر دو نمونه‌شان روی یک
     * بازه زاویه‌ای می‌نشینند، سمت بدن حرف آخر را می‌زند.
     *
     * @return array{matched: array<int, array{0: array, 1: array}>, left: array, right: array}
     */
    protected function pairArcs(array $left, array $right): array
    {
        $costs = [];

        foreach ($left as $i => $a) {
            foreach ($right as $j => $b) {
                $costs[] = [$this->cost($a, $b), $i, $j];
            }
        }

        usort($costs, fn (array $a, array $b) => [$a[0], $a[1], $a[2]] <=> [$b[0], $b[1], $b[2]]);

        $matched = [];
        $usedLeft = [];
        $usedRight = [];

        foreach ($costs as [, $i, $j]) {
            if (isset($usedLeft[$i]) || isset($usedRight[$j])) {
                continue;
            }

            $usedLeft[$i] = true;
            $usedRight[$j] = true;
            $matched[] = [$left[$i], $right[$j]];
        }

        return [
            'matched' => $matched,
            'left' => array_values(array_diff_key($left, $usedLeft)),
            'right' => array_values(array_diff_key($right, $usedRight)),
        ];
    }

    /**
     * دستگاهی که زاویه یک قطعه در آن معنا دارد.
     *
     * زاویهٔ دورِ آستین دور بازو می‌چرخد و زاویهٔ تنه دور تن؛ مقایسه مستقیم این دو
     * بی‌معناست، پس برای درزی که میان دو دستگاه است فقط ارتفاع سنجیده می‌شود.
     */
    protected function frame(string $role): string
    {
        return match ($role) {
            'sleeve' => 'arm',
            'leg' => 'limb',
            default => 'body',
        };
    }

    /** هزینه جفت‌شدن دو کمان: فاصله‌شان روی بدن، به‌اضافه جریمه سمت مخالف. */
    protected function cost(array $a, array $b): float
    {
        $cost = $this->distance($a['at'], $b['at'], $a['frame'] === $b['frame']);

        if ($a['body_side'] !== null && $b['body_side'] !== null && $a['body_side'] !== $b['body_side']) {
            $cost += static::SIDE_PENALTY;
        }

        return round($cost, 4);
    }

    /**
     * یک درز از دو کمان جفت‌شده.
     *
     * @return array<string, mixed>
     */
    protected function seam(array $a, array $b, string $label, ?int $relation, array $source): array
    {
        return [
            'a' => ['piece' => $a['piece'], 'from' => $a['from'], 'to' => $a['to'], 'length' => round($a['length'], 3)],
            'b' => ['piece' => $b['piece'], 'from' => $b['from'], 'to' => $b['to'], 'length' => round($b['length'], 3)],
            'label' => $label,
            'reverse' => $this->reverse($a, $b, $source),
            'ease' => round($b['length'] - $a['length'], 3),
            'kind' => 'seam',
            'relation' => $relation,
        ];
    }

    /**
     * آیا سمت b باید وارونه پیموده شود؟
     *
     * حدس نمی‌زنیم: چهار سرِ دو کمان روی بدن گذاشته می‌شوند و هر دو حالت سنجیده
     * می‌شود. اگر دو حالت به‌اندازه هم خوب بودند (قطعه‌های هم‌جا)، همان چیزی که
     * سازنده رابطه گفته بود می‌ماند.
     */
    protected function reverse(array $a, array $b, array $source): bool
    {
        $same = $a['frame'] === $b['frame'];
        $aStart = $this->onBody($a['instance'], $a['instance']['polygon'][$a['from']]);
        $aEnd = $this->onBody($a['instance'], $a['instance']['polygon'][$a['to']]);
        $bStart = $this->onBody($b['instance'], $b['instance']['polygon'][$b['from']]);
        $bEnd = $this->onBody($b['instance'], $b['instance']['polygon'][$b['to']]);

        $straight = $this->distance($aStart, $bStart, $same) + $this->distance($aEnd, $bEnd, $same);
        $flipped = $this->distance($aStart, $bEnd, $same) + $this->distance($aEnd, $bStart, $same);

        // اگر دو حالت به‌اندازه هم خوب بودند، جواب هندسه نویز است. آن وقت همان
        // چیزی می‌ماند که سازنده رابطه گفته؛ و اگر او هم چیزی نگفته باشد، قاعده
        // کلیِ دوخت: دو قطعه‌ای که هم‌جهت بریده شده‌اند، درزشان را وارونه‌ی هم
        // می‌پیمایند.
        if (abs($straight - $flipped) < ($same ? 0.5 : 2.0)) {
            return (bool) ($source['reverse'] ?? true);
        }

        return $flipped < $straight;
    }

    /**
     * جای یک نقطه قطعه روی بدن: زاویه دور بدن و ارتفاع از کف.
     *
     * @return array{u: float, y: float}
     */
    protected function onBody(array $instance, array $point): array
    {
        [$minX, $minY, $maxX] = $instance['bounds'];
        $placement = $instance['placement'];
        $width = max(1e-6, $maxX - $minX);
        $ratio = (((float) $point['x']) - $minX) / $width;

        return [
            'u' => $placement['u0'] + (($placement['u1'] - $placement['u0']) * $ratio),
            'y' => $instance['top_cm'] - (((float) $point['y']) - $minY),
        ];
    }

    /** فاصله دو جای روی بدن؛ اختلاف زاویه با شعاع مرجع به سانتی‌متر برمی‌گردد. */
    protected function distance(array $a, array $b, bool $sameFrame = true): float
    {
        $height = abs($a['y'] - $b['y']);

        if (! $sameFrame) {
            return $height;
        }

        $angle = $this->wrap($a['u'] - $b['u']);

        return sqrt((($angle * static::REFERENCE_RADIUS) ** 2) + ($height ** 2));
    }

    /** سمت بدنی که این نمونه روی آن نشسته (اگر روی هر دو سمت باشد، null). */
    protected function bodySide(array $instance): ?string
    {
        if (in_array($instance['role'], ['sleeve', 'leg'], true)) {
            return $instance['mirrored'] ? 'right' : 'left';
        }

        $placement = $instance['placement'] ?? null;

        if ($placement === null) {
            return null;
        }

        $middle = $this->wrap(($placement['u0'] + $placement['u1']) / 2);

        if (abs($middle) < 1e-6 || abs(abs($middle) - M_PI) < 1e-6) {
            return null;
        }

        return $middle < 0 ? 'left' : 'right';
    }

    /** بردن یک زاویه به بازه (-π, π]. */
    protected function wrap(float $angle): float
    {
        while ($angle > M_PI) {
            $angle -= 2 * M_PI;
        }

        while ($angle <= -M_PI) {
            $angle += 2 * M_PI;
        }

        return $angle;
    }

    /**
     * گزارش رابطه‌ای که جفت نشد.
     *
     * @return array<string, mixed>
     */
    protected function unmatched(array $relation, int $index, string $reason): array
    {
        return [
            'relation' => $index,
            'label' => (string) ($relation['label'] ?? 'درز'),
            'from' => $relation['from'] ?? null,
            'to' => $relation['to'] ?? null,
            'reason' => $reason,
        ];
    }

    /* ---------------------------------------------------------------------
     |  بودجه مثلث‌بندی
     * ------------------------------------------------------------------- */

    /**
     * طول یال هدف طوری انتخاب می‌شود که مجموع رأس‌ها زیر سقف بماند.
     *
     * @return array{target_edge: float, max_vertices: int}
     */
    protected function budget(array $instances): array
    {
        $area = 0.0;

        foreach ($instances as $instance) {
            $area += abs(Geometry::signedArea($instance['polygon']));
        }

        // مثلث متساوی‌الاضلاع با یال e مساحتی نزدیک ۰٫۴۳ e² دارد و هر رأس میان
        // شش مثلث شریک است؛ پس تعداد رأس تقریباً area / (0.87 e²) است.
        $target = $area > 0
            ? sqrt($area / (0.87 * max(1, static::MAX_VERTICES * 0.7)))
            : static::TARGET_EDGE;

        return [
            'target_edge' => round(max(1.2, min(9.0, $target)), 2),
            'max_vertices' => static::MAX_VERTICES,
        ];
    }
}
