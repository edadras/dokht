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

    /** شعاع مرجع برای تبدیل اختلاف زاویه به فاصله (سانتی‌متر). */
    protected const REFERENCE_RADIUS = 15.0;

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
        $flat = DrapeGeometry::flattenWithSpans($piece['outline']);
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
        $side = $this->side($model, $mirrored, $quantity);

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
            'darts' => $darts['darts'],
            'placement' => array_intersect_key($placement, array_flip([
                'zone', 'u0', 'u1', 'y_top', 'radius_hint', 'flip',
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
            'sleeve' => ['sleeve', 'آستین'],
            'collar' => ['collar', 'hood', 'lapel', 'یقه', 'کلاه'],
            'skirt' => ['skirt', 'peplum', 'godet', 'دامن'],
            'leg' => ['leg', 'pant', 'trouser', 'panty', 'short', 'شلوار', 'پاچه'],
            'detail' => [
                'cuff', 'pocket', 'facing', 'waistband', 'belt', 'placket', 'binding', 'band',
                'strap', 'tie', 'loop', 'trim', 'patch', 'gusset', 'veil',
                'مچ', 'جیب', 'سجاف', 'کمربند', 'نوار', 'بند',
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
    protected function side(PatternPiece $piece, bool $mirrored, int $quantity): ?string
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

        $span = $width / $radius;
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
            'flip' => $instance['mirrored'],
            // برای چیدن گروهی (فقط سمت سرور؛ در بسته نمی‌آید)
            'center' => $center,
            'span' => $span,
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

        foreach ($relations as $index => $relation) {
            $from = $this->relationSide($relation['from'] ?? []);
            $to = $this->relationSide($relation['to'] ?? []);
            $label = (string) ($relation['label'] ?? 'درز');

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

            [$left, $right] = $this->balance($left, $right);

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

                if ($walked + $step > $target) {
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
