<?php

namespace App\Services\Pattern\Transform;

use App\Services\Pattern\Geometry;
use InvalidArgumentException;

/**
 * کار با ساسون — مهم‌ترین کارِ الگوسازی تخت.
 *
 * ساسون «اضافه پارچه»ای است که برای برجستگی بدن نگه داشته می‌شود و در دوخت تا
 * می‌خورد. ارزش یک ساسون نه پهنای دهانه‌اش، که «زاویه» آن حول نوک است؛ به همین
 * دلیل می‌شود همان ساسون را جای دیگری از قطعه باز کرد و لباس همان‌طور روی بدن
 * بنشیند. این همان چرخاندن ساسون است:
 *
 *   ساسون سینه ← سرشانه، حلقه آستین، یقه، کمر، ساسون فرانسوی، مرکز جلو…
 *
 * قرارداد ذخیره: مسیر قطعه دهانه ساسون را «صاف» نگه می‌دارد (دو پا روی لبه
 * می‌نشینند و V روی مسیر کشیده نمی‌شود) و خودِ ساسون در darts[] ثبت می‌شود؛
 * پس طول دوخته‌شده هر لبه برابر است با طول لبه منهای پهنای ساسون‌های روی آن.
 * همه متدهای این کلاس همین قرارداد را نگه می‌دارند.
 *
 * قانون طلایی: چرخاندن ساسون هیچ پارچه‌ای نمی‌سازد و از بین نمی‌برد. آنچه در یک
 * سر باز می‌شود در سر دیگر بسته می‌شود، نوک ساسون سرِ جایش می‌ماند و طول
 * دوخته‌شده همه لبه‌ها دست‌نخورده می‌ماند.
 */
final class DartTool
{
    /** رواداری هندسی (سانتی‌متر). */
    public const TOLERANCE = 0.01;

    /* ---------------------------------------------------------------------
     |  پیدا کردن و اندازه‌گیری ساسون
     * ------------------------------------------------------------------- */

    /**
     * شماره یک ساسون در فهرست ساسون‌های قطعه.
     *
     * ورودی می‌تواند شماره باشد یا نوع/برچسب ساسون («bust»، «waist»، «ساسون سینه»).
     */
    public static function find(array $piece, int|string $dart): int
    {
        $darts = array_values($piece['darts'] ?? []);

        if (is_int($dart)) {
            if (! isset($darts[$dart])) {
                throw new InvalidArgumentException("ساسون شماره {$dart} روی این قطعه نیست.");
            }

            return $dart;
        }

        foreach ($darts as $index => $candidate) {
            if (($candidate['type'] ?? null) === $dart || ($candidate['label'] ?? null) === $dart) {
                return $index;
            }
        }

        throw new InvalidArgumentException("ساسون «{$dart}» روی این قطعه پیدا نشد.");
    }

    /**
     * خودِ ساسون.
     *
     * @return array<string, mixed>
     */
    public static function get(array $piece, int|string $dart): array
    {
        return array_values($piece['darts'])[self::find($piece, $dart)];
    }

    /**
     * «ارزش» ساسون: زاویه آن حول نوک (رادیان).
     *
     * همین عدد است که در چرخاندن ساسون ثابت می‌ماند، نه پهنای دهانه.
     */
    public static function angleOf(array $dart): float
    {
        $apex = $dart['apex'] ?? null;
        $legs = array_values($dart['legs'] ?? []);

        if ($apex === null || count($legs) !== 2) {
            return 0.0;
        }

        return abs(Geometry::angleBetween($apex, $legs[0], $legs[1]));
    }

    /** مساحت مثلثی که با تا شدن ساسون از قطعه کم می‌شود (سانتی‌متر مربع). */
    public static function wedgeArea(array $dart): float
    {
        $apex = $dart['apex'] ?? null;
        $legs = array_values($dart['legs'] ?? []);

        if ($apex === null || count($legs) !== 2) {
            return 0.0;
        }

        $area = abs(
            (((float) $legs[0]['x']) - ((float) $apex['x'])) * (((float) $legs[1]['y']) - ((float) $apex['y']))
            - (((float) $legs[1]['x']) - ((float) $apex['x'])) * (((float) $legs[0]['y']) - ((float) $apex['y']))
        ) / 2;

        return round($area, 3);
    }

    /**
     * مساحت «دوخته‌شده» قطعه: مساحت مسیر منهای مثلث همه ساسون‌ها.
     *
     * این عدد در چرخاندن ساسون باید دست‌نخورده بماند و ملاک درستی کار است.
     */
    public static function netArea(array $piece): float
    {
        $area = Geometry::area($piece['outline'] ?? []);

        foreach ($piece['darts'] ?? [] as $dart) {
            $area -= self::wedgeArea($dart);
        }

        return round($area, 3);
    }

    /* ---------------------------------------------------------------------
     |  راست‌سازی پاهای ساسون
     * ------------------------------------------------------------------- */

    /**
     * هم‌اندازه کردن دو پای ساسون.
     *
     * دو پای ساسون باید از نوک تا لبه هم‌اندازه باشند وگرنه ساسون صاف دوخته
     * نمی‌شود. هر پا روی خط خودش تا میانگین دو پا کشیده یا کوتاه می‌شود، پس
     * زاویه ساسون (ارزش آن) دست نمی‌خورد و فقط لبه کمی جابه‌جا می‌شود.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    public static function trueLegs(array $piece, int|string $dart): array
    {
        $index = self::find($piece, $dart);
        $record = array_values($piece['darts'])[$index];
        $apex = $record['apex'] ?? null;
        $legs = array_values($record['legs'] ?? []);

        if ($apex === null || count($legs) !== 2) {
            return $piece;
        }

        $apex = ['x' => (float) $apex['x'], 'y' => (float) $apex['y']];
        $radii = [];

        foreach ($legs as $leg) {
            $radii[] = Geometry::distance($apex, ['x' => (float) $leg['x'], 'y' => (float) $leg['y']]);
        }

        if (abs($radii[0] - $radii[1]) < 1e-6 || min($radii) < 1e-6) {
            return $piece;
        }

        $target = ($radii[0] + $radii[1]) / 2;
        $trued = [];

        foreach ($legs as $position => $leg) {
            $scale = $target / $radii[$position];
            $trued[] = Geometry::point(
                $apex['x'] + ((((float) $leg['x']) - $apex['x']) * $scale),
                $apex['y'] + ((((float) $leg['y']) - $apex['y']) * $scale),
            );
        }

        $piece = self::moveMouth($piece, $index, $legs, $trued);
        $piece['darts'][$index]['legs'] = $trued;
        $piece['darts'][$index]['intake'] = round(Geometry::distance($trued[0], $trued[1]), 2);
        $piece['darts'][$index]['center'] = Geometry::point(
            ($trued[0]['x'] + $trued[1]['x']) / 2,
            ($trued[0]['y'] + $trued[1]['y']) / 2,
        );

        return Outline::reindex($piece);
    }

    /**
     * جابه‌جا کردن دهانه ساسون روی مسیر (وقتی پاها راست‌سازی می‌شوند).
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected static function moveMouth(array $piece, int $index, array $from, array $to): array
    {
        $outline = array_values($piece['outline']);
        $edge = $piece['darts'][$index]['edge'] ?? null;

        foreach ($from as $position => $leg) {
            $point = ['x' => (float) $leg['x'], 'y' => (float) $leg['y']];
            $near = $edge === null
                ? Geometry::nearestEdge($outline, $point)
                : ['edge' => (int) $edge, 't' => Geometry::edgeParameterOf($outline, (int) $edge, $point)];

            if (($near['t'] ?? 0.5) > 1e-4 && ($near['t'] ?? 0.5) < 1 - 1e-4) {
                continue; // پا داخل لبه است و رأسی برای جابه‌جا کردن ندارد
            }

            $vertex = ($near['t'] ?? 0) <= 1e-4 ? $near['edge'] : ($near['edge'] + 1) % count($outline);
            $outline[$vertex]['x'] = round((float) $to[$position]['x'], 3);
            $outline[$vertex]['y'] = round((float) $to[$position]['y'], 3);
        }

        $piece['outline'] = $outline;

        return $piece;
    }

    /* ---------------------------------------------------------------------
     |  چرخاندن ساسون
     * ------------------------------------------------------------------- */

    /**
     * چرخاندن ساسون: بستن ساسون در جای فعلی و باز کردن همان ارزش در جای تازه.
     *
     * قطعه از نوک ساسون تا جای تازه بریده می‌شود، یک لنگه حول نوک می‌چرخد تا دو
     * پای ساسون قدیم روی هم بیفتد، و همان‌قدر که آنجا بسته شد اینجا باز می‌شود.
     * نوک ساسون سرِ جایش می‌ماند.
     *
     * $to مشخصات جای تازه است:
     *   ['edge' => 'shoulder', 't' => 0.5]   وسط سرشانه
     *   ['edge' => 3, 't' => 0.25]           لبه شماره ۳
     *   ['at' => ['x' => .., 'y' => ..]]     نزدیک‌ترین جای محیط به این نقطه
     * و می‌تواند این‌ها را هم داشته باشد:
     *   label، type      برچسب و نوع ساسون تازه
     *   shorten          عقب‌کشیدن نوکِ ثبت‌شده از نقطه برجستگی (سانتی‌متر)
     *   tag              برچسب لبه دهانه تازه (پیش‌فرض: برچسب همان لبه)
     *   keep             'grain' (پیش‌فرض؛ راستای پارچه سرِ جایش می‌ماند) یا 'a' یا 'b'
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    public static function rotate(array $piece, int|string $dart, array $to): array
    {
        return self::pivot($piece, $dart, $to, record: true);
    }

    /**
     * بستن ساسون بدون ثبت ساسون تازه.
     *
     * چون بستن ساسون بدون باز شدن جای دیگر، کاغذ را از حالت تخت بیرون می‌برد،
     * اینجا هم قطعه از نوک تا لبه‌ای که در options['slash'] می‌آید بریده می‌شود؛
     * تفاوتش با rotate() تنها این است که دهانه تازه ساسون ثبت نمی‌شود و به شکل
     * «فرم» در همان لبه می‌ماند (یا با options['record'] به چین/آزادی می‌رود).
     *
     * اگر slash داده نشود، نزدیک‌ترین جای محیط به نوک ساسون — بیرون از لبه خود
     * ساسون — انتخاب می‌شود.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    public static function close(array $piece, int|string $dart, array $options = []): array
    {
        $slash = $options['slash'] ?? self::farthestAnchor($piece, $dart);

        return self::pivot($piece, $dart, array_merge($slash, $options), record: false);
    }

    /**
     * باز کردن یک دهانه تازه حول یک نوک.
     *
     * دو حالت دارد:
     *   • اگر from داده شود (شماره یا نوع یک ساسون موجود)، همان rotate() است:
     *     ارزش آن ساسون به جای تازه می‌رود.
     *   • اگر نوک روی محیط قطعه باشد، قطعه از at تا نوک بریده و یک لنگه حول نوک
     *     چرخانده می‌شود؛ اینجا پارچه واقعاً اضافه می‌شود (مثل باز کردن دامن از
     *     نقطه پهلو). مقدار باز شدن با intake یا angle داده می‌شود.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    public static function open(array $piece, array $options): array
    {
        if (isset($options['from'])) {
            return self::rotate($piece, $options['from'], $options);
        }

        $apexSpec = $options['apex'] ?? null;

        if ($apexSpec === null) {
            throw new InvalidArgumentException('برای باز کردن ساسون، نوک آن (apex) لازم است.');
        }

        $apex = ['x' => (float) $apexSpec['x'], 'y' => (float) $apexSpec['y']];
        $outline = array_values($piece['outline']);
        $onEdge = Geometry::nearestEdge($outline, $apex);

        if ($onEdge['distance'] > 0.2) {
            throw new InvalidArgumentException('نوک ساسون تازه روی محیط قطعه نیست؛ برای چرخاندن ساسون موجود from را بدهید.');
        }

        $tags = Geometry::edgeTags($piece);
        $target = Outline::anchor($piece, $options);

        $inserted = Outline::insertAnchors($outline, $tags, [
            'apex' => ['edge' => $onEdge['edge'], 't' => $onEdge['t']],
            'at' => ['edge' => $target['edge'], 't' => $target['t']],
        ]);

        $outline = $inserted['outline'];
        $tags = $inserted['tags'];
        $apexIndex = $inserted['index']['apex'];
        $atIndex = $inserted['index']['at'];

        if ($apexIndex === $atIndex) {
            throw new InvalidArgumentException('نوک و دهانه ساسون روی یک رأس افتادند.');
        }

        $apex = Outline::straighten($outline[$apexIndex]);
        $at = Outline::straighten($outline[$atIndex]);
        $radius = Geometry::distance($apex, $at);

        $angle = isset($options['angle'])
            ? (float) $options['angle']
            : 2 * asin(max(-1.0, min(1.0, ((float) ($options['intake'] ?? 0)) / (2 * max(0.01, $radius)))));

        if (abs($angle) < 1e-9) {
            return $piece;
        }

        $count = count($outline);
        $chainOne = Outline::chain($outline, $atIndex, $apexIndex);
        $chainTwo = Outline::chain($outline, $apexIndex, $atIndex);

        $tagsOne = [];

        for ($i = 0; $i < count($chainOne) - 1; $i++) {
            $tagsOne[$i] = $tags[($atIndex + $i) % $count] ?? 'default';
        }

        $tagsTwo = [];

        for ($i = 0; $i < count($chainTwo) - 1; $i++) {
            $tagsTwo[$i] = $tags[($apexIndex + $i) % $count] ?? 'default';
        }

        // جهت چرخش را طوری برمی‌گزینیم که دهانه باز شود، نه بسته
        $spun = self::spin($chainTwo, $apex, $angle);
        $opened = Geometry::area(array_merge($chainOne, array_slice($spun, 1)));
        $spunBack = self::spin($chainTwo, $apex, -$angle);
        $closed = Geometry::area(array_merge($chainOne, array_slice($spunBack, 1)));

        if ($closed > $opened) {
            $angle = -$angle;
            $spun = $spunBack;
        }

        $joined = Outline::join([
            ['points' => $chainOne, 'tags' => $tagsOne],
            ['points' => $spun, 'tags' => $tagsTwo],
        ], (string) ($options['tag'] ?? ($tags[$target['edge']] ?? 'default')));

        $piece = self::carryLobe($piece, $chainTwo, $apex, $angle, [$apex]);
        $mouth = [Geometry::point($at['x'], $at['y']), Geometry::point(
            end($spun)['x'],
            end($spun)['y'],
        )];

        if (($options['record'] ?? 'dart') === 'dart') {
            $piece['darts'][] = self::mouthDart($mouth, $options['apex_point'] ?? $apex, $options);
        }

        $piece = Outline::apply($piece, $joined['outline'], $joined['tags']);

        if (($options['record'] ?? 'dart') !== 'dart') {
            $piece = self::recordOpening($piece, $mouth, $options);
        }

        return $piece;
    }

    /* ---------------------------------------------------------------------
     |  هسته: چرخاندن یک لنگه حول نوک ساسون
     * ------------------------------------------------------------------- */

    /**
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected static function pivot(array $piece, int|string $dart, array $to, bool $record): array
    {
        $piece = ($to['true_legs'] ?? true) ? self::trueLegs($piece, $dart) : $piece;

        $index = self::find($piece, $dart);
        $source = array_values($piece['darts'])[$index];
        $apex = ['x' => (float) $source['apex']['x'], 'y' => (float) $source['apex']['y']];
        $legs = array_values($source['legs']);

        $outline = array_values($piece['outline']);
        $tags = Geometry::edgeTags($piece);
        $edge = $source['edge'];

        if ($edge === null) {
            throw new InvalidArgumentException('این ساسون روی لبه‌ای ننشسته و چرخانده نمی‌شود.');
        }

        $edge = (int) $edge;
        $legAnchors = [];

        foreach ($legs as $position => $leg) {
            $legAnchors[$position] = [
                'edge' => $edge,
                't' => Geometry::edgeParameterOf($outline, $edge, ['x' => (float) $leg['x'], 'y' => (float) $leg['y']]),
            ];
        }

        // پای «اول» آن است که زودتر روی مسیر می‌آید
        $order = $legAnchors[0]['t'] <= $legAnchors[1]['t'] ? [0, 1] : [1, 0];
        $target = Outline::anchor($piece, $to);

        if ($target['edge'] === $edge
            && $target['t'] > min($legAnchors[0]['t'], $legAnchors[1]['t']) - 1e-6
            && $target['t'] < max($legAnchors[0]['t'], $legAnchors[1]['t']) + 1e-6) {
            throw new InvalidArgumentException('جای تازه ساسون داخل دهانه خودِ ساسون است.');
        }

        $inserted = Outline::insertAnchors($outline, $tags, [
            'first' => $legAnchors[$order[0]],
            'second' => $legAnchors[$order[1]],
            'target' => ['edge' => $target['edge'], 't' => $target['t']],
        ]);

        $outline = $inserted['outline'];
        $tags = $inserted['tags'];
        $count = count($outline);
        $firstIndex = $inserted['index']['first'];
        $secondIndex = $inserted['index']['second'];
        $targetIndex = $inserted['index']['target'];

        if ($targetIndex === $firstIndex || $targetIndex === $secondIndex) {
            throw new InvalidArgumentException('جای تازه ساسون روی پای همان ساسون افتاد.');
        }

        // دهانه ساسون باید دقیقاً روی دو رأس تازه بنشیند
        $outline[$firstIndex] = Geometry::point((float) $legs[$order[0]]['x'], (float) $legs[$order[0]]['y']);
        $outline[$secondIndex] = Geometry::point((float) $legs[$order[1]]['x'], (float) $legs[$order[1]]['y']);

        $angle = Geometry::angleBetween($apex, $outline[$secondIndex], $outline[$firstIndex]);

        $chainA = Outline::chain($outline, $targetIndex, $firstIndex);
        $chainB = Outline::chain($outline, $secondIndex, $targetIndex);

        $tagsA = [];

        for ($i = 0; $i < count($chainA) - 1; $i++) {
            $tagsA[$i] = $tags[($targetIndex + $i) % $count] ?? 'default';
        }

        $tagsB = [];

        for ($i = 0; $i < count($chainB) - 1; $i++) {
            $tagsB[$i] = $tags[($secondIndex + $i) % $count] ?? 'default';
        }

        $spun = self::spin($chainB, $apex, $angle);
        $mouthTag = (string) ($to['tag'] ?? ($tags[$targetIndex] ?? 'default'));

        $joined = Outline::join([
            ['points' => $chainA, 'tags' => $tagsA],
            ['points' => $spun, 'tags' => $tagsB],
        ], $mouthTag);

        // ساسون قدیم برداشته و آنچه در لنگه چرخان بود با آن می‌چرخد
        $darts = array_values($piece['darts']);
        unset($darts[$index]);
        $piece['darts'] = array_values($darts);

        $piece = self::carryLobe($piece, $chainB, $apex, $angle, [$apex]);

        $mouth = [
            Geometry::point((float) end($spun)['x'], (float) end($spun)['y']),
            Geometry::point((float) $chainA[0]['x'], (float) $chainA[0]['y']),
        ];

        if ($record) {
            $piece['darts'][] = self::mouthDart($mouth, $apex, array_merge([
                'type' => $source['type'] ?? 'dart',
                'label' => $source['label'] ?? 'ساسون',
            ], $to));
        }

        $piece = Outline::apply($piece, $joined['outline'], $joined['tags'], normalize: false);

        if (! $record) {
            $piece = self::recordOpening($piece, $mouth, $to);
        }

        $piece = self::reorient($piece, $chainB, $apex, $angle, $to);
        $piece['meta']['dart_rotations'] = array_values(array_merge($piece['meta']['dart_rotations'] ?? [], [[
            'from' => $source['label'] ?? ($source['type'] ?? 'ساسون'),
            'to' => $mouthTag,
            'angle' => round($angle, 6),
            'intake' => round(Geometry::distance($mouth[0], $mouth[1]), 2),
        ]]));

        return Geometry::normalizePiece($piece);
    }

    /**
     * چرخاندن یک زنجیره حول یک مرکز.
     *
     * @return array<int, array<string, mixed>>
     */
    protected static function spin(array $chain, array $center, float $angle): array
    {
        return array_map(fn (array $point) => Geometry::rotatePoint($point, $center, $angle), $chain);
    }

    /**
     * ساسون، نشانه و خط نشانه‌ای که داخل لنگه چرخان است با آن می‌چرخد.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected static function carryLobe(array $piece, array $chain, array $center, float $angle, array $extra = []): array
    {
        $polygon = Outline::polygon($chain, $extra);

        // نقطه‌ای که دقیقاً روی محیطِ لنگه چرخان نشسته (دهانه ساسون، نشانه درز)
        // با «داخل بودن» سنجیده نمی‌شود؛ پرتاب پرتو روی مرز جواب دلخواه می‌دهد.
        // پس جدا هم می‌سنجیم که روی خودِ زنجیره نشسته باشد.
        $inside = function (array $point) use ($polygon, $chain): bool {
            $at = ['x' => (float) $point['x'], 'y' => (float) $point['y']];

            return Geometry::containsPoint($polygon, $at) || self::onChain($chain, $at);
        };

        return Geometry::mapPiece($piece, function (array $point) use ($inside, $center, $angle) {
            if (! isset($point['x'], $point['y'])) {
                return $point;
            }

            return $inside($point) ? Geometry::rotatePoint($point, $center, $angle) : $point;
        });
    }

    /**
     * آیا نقطه روی خودِ زنجیره (نه روی خط‌های بسته‌شدن آن) نشسته است؟
     *
     * @param  array<int, array<string, mixed>>  $chain
     */
    protected static function onChain(array $chain, array $point, float $tolerance = 0.05): bool
    {
        $chain = array_values($chain);
        $count = count($chain);

        for ($i = 0; $i < $count - 1; $i++) {
            $from = Outline::straighten($chain[$i]);
            $to = $chain[$i + 1];
            $end = Outline::straighten($to);

            if (! Geometry::isCurve($to)) {
                if (Outline::onSegment($point, [$from, $end], $tolerance)) {
                    return true;
                }

                continue;
            }

            $control = ['x' => (float) $to['cx'], 'y' => (float) $to['cy']];
            $previous = $from;

            for ($s = 1; $s <= Geometry::CURVE_SEGMENTS; $s++) {
                $current = Geometry::quadraticAt($from, $control, $end, $s / Geometry::CURVE_SEGMENTS);

                if (Outline::onSegment($point, [$previous, $current], $tolerance)) {
                    return true;
                }

                $previous = $current;
            }
        }

        return false;
    }

    /**
     * اگر راستای پارچه در لنگه چرخان افتاده باشد، کل قطعه به اندازه همان زاویه
     * برمی‌گردد تا راستای پارچه سرِ جایش بماند.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected static function reorient(array $piece, array $chain, array $center, float $angle, array $options): array
    {
        $keep = (string) ($options['keep'] ?? 'grain');

        if ($keep === 'a') {
            return $piece;
        }

        if ($keep === 'b') {
            return Geometry::rotatePiece($piece, $center, -$angle);
        }

        $reference = null;

        if (! empty($options['reference'])) {
            $reference = $options['reference'];
        } elseif (! empty($piece['grainline']['from']) && ! empty($piece['grainline']['to'])) {
            $reference = Geometry::lerp(
                ['x' => (float) $piece['grainline']['from']['x'], 'y' => (float) $piece['grainline']['from']['y']],
                ['x' => (float) $piece['grainline']['to']['x'], 'y' => (float) $piece['grainline']['to']['y']],
                0.5,
            );
        }

        if ($reference === null) {
            return $piece;
        }

        $spunReference = Geometry::rotatePoint(
            ['x' => (float) $reference['x'], 'y' => (float) $reference['y']],
            $center,
            $angle,
        );

        $polygon = Outline::polygon(self::spin($chain, $center, $angle), [$center]);

        return Geometry::containsPoint($polygon, $spunReference)
            ? Geometry::rotatePiece($piece, $center, -$angle)
            : $piece;
    }

    /**
     * ساخت رکورد ساسون از یک دهانه و یک نوک.
     *
     * @return array<string, mixed>
     */
    protected static function mouthDart(array $mouth, array $apex, array $options): array
    {
        $intake = Geometry::distance($mouth[0], $mouth[1]);
        $center = Geometry::point(
            (((float) $mouth[0]['x']) + ((float) $mouth[1]['x'])) / 2,
            (((float) $mouth[0]['y']) + ((float) $mouth[1]['y'])) / 2,
        );

        $apexPoint = ['x' => (float) $apex['x'], 'y' => (float) $apex['y']];
        $shorten = (float) ($options['shorten'] ?? 0);

        if ($shorten > 0) {
            $length = Geometry::distance($apexPoint, $center);

            if ($length > $shorten + 0.5) {
                $apexPoint = Geometry::lerp($apexPoint, $center, $shorten / $length);
            }
        }

        $axis = abs(((float) $mouth[0]['x']) - ((float) $mouth[1]['x']))
            >= abs(((float) $mouth[0]['y']) - ((float) $mouth[1]['y'])) ? 'x' : 'y';

        return [
            'type' => (string) ($options['type'] ?? 'dart'),
            'label' => (string) ($options['label'] ?? 'ساسون'),
            'edge' => null, // Outline::reindex() شماره درست را می‌گذارد
            'axis' => $axis,
            'intake' => round($intake, 2),
            'center' => $center,
            'apex' => Geometry::point($apexPoint['x'], $apexPoint['y']),
            'legs' => [Geometry::point((float) $mouth[0]['x'], (float) $mouth[0]['y']), Geometry::point((float) $mouth[1]['x'], (float) $mouth[1]['y'])],
        ];
    }

    /**
     * وقتی دهانه تازه به شکل ساسون ثبت نمی‌شود، به شکل چین یا آزادی نوشته می‌شود.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected static function recordOpening(array $piece, array $mouth, array $options): array
    {
        $record = $options['record'] ?? null;

        if (! in_array($record, FullnessRecorder::KINDS, true)) {
            return $piece;
        }

        $amount = Geometry::distance($mouth[0], $mouth[1]);
        $center = [
            'x' => (((float) $mouth[0]['x']) + ((float) $mouth[1]['x'])) / 2,
            'y' => (((float) $mouth[0]['y']) + ((float) $mouth[1]['y'])) / 2,
        ];

        $near = Geometry::nearestEdge($piece['outline'], $center);

        return FullnessRecorder::record($piece, $record, $near['edge'], $amount, [
            'label' => (string) ($options['label'] ?? 'گشادی جای ساسون'),
        ]);
    }

    /**
     * دورترین جای محیط از نوک ساسون که روی لبه خودِ ساسون نباشد.
     *
     * @return array{edge: int, t: float}
     */
    protected static function farthestAnchor(array $piece, int|string $dart): array
    {
        $record = self::get($piece, $dart);
        $apex = ['x' => (float) $record['apex']['x'], 'y' => (float) $record['apex']['y']];
        $outline = array_values($piece['outline']);
        $count = count($outline);
        $own = $record['edge'] === null ? -1 : (int) $record['edge'];

        $best = ['edge' => 0, 't' => 0.5];
        $bestDistance = INF;

        for ($edge = 0; $edge < $count; $edge++) {
            if ($edge === $own) {
                continue;
            }

            for ($step = 1; $step < 8; $step++) {
                $t = $step / 8;
                $distance = Geometry::distance(Geometry::pointOnEdge($outline, $edge, $t), $apex);

                if ($distance < $bestDistance) {
                    $bestDistance = $distance;
                    $best = ['edge' => $edge, 't' => $t];
                }
            }
        }

        return $best;
    }

    /* ---------------------------------------------------------------------
     |  تقسیم، چین و آزادی
     * ------------------------------------------------------------------- */

    /**
     * تقسیم یک ساسون به چند ساسون کوچک‌تر.
     *
     * یک ساسون پهن روی کمر پارچه را «کیسه» می‌کند؛ خیاط همان مقدار را به دو یا
     * سه ساسون باریک‌تر پخش می‌کند تا فرم نرم‌تر بنشیند. مجموع پهنای ساسون‌ها و
     * در نتیجه طول دوخته‌شده لبه دست‌نخورده می‌ماند.
     *
     * گزینه‌ها:
     *   weights   سهم هر ساسون (پیش‌فرض برابر)
     *   gap       فاصله دو دهانه از هم (پیش‌فرض ۱ سانتی‌متر)
     *   spread    پخش کردن نوک‌ها به اندازه این مقدار در راستای لبه
     *   depths    ژرفای هر ساسون به نسبت ساسون اصلی (پیش‌فرض همه ۱)
     *   labels    برچسب هر ساسون
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    public static function split(array $piece, int|string $dart, int $count, array $options = []): array
    {
        $count = max(1, $count);
        $index = self::find($piece, $dart);
        $source = array_values($piece['darts'])[$index];

        if ($count === 1) {
            return $piece;
        }

        $edge = $source['edge'];

        if ($edge === null) {
            throw new InvalidArgumentException('ساسونی که روی لبه ننشسته تقسیم نمی‌شود.');
        }

        $edge = (int) $edge;
        $outline = array_values($piece['outline']);
        $length = Geometry::edgeLength($outline, $edge);
        $intake = (float) $source['intake'];
        $apex = ['x' => (float) $source['apex']['x'], 'y' => (float) $source['apex']['y']];

        $weights = $options['weights'] ?? array_fill(0, $count, 1.0);
        $weights = array_slice(array_map('floatval', $weights), 0, $count);
        $weights = array_pad($weights, $count, 1.0);
        $sum = array_sum($weights) ?: 1.0;

        $gap = (float) ($options['gap'] ?? 1.0);
        $legs = array_values($source['legs']);

        $positions = [];

        foreach ($legs as $leg) {
            $positions[] = Geometry::edgeParameterOf($outline, $edge, ['x' => (float) $leg['x'], 'y' => (float) $leg['y']]) * $length;
        }

        sort($positions);
        $centre = ($positions[0] + $positions[1]) / 2;
        $span = $intake + ($gap * ($count - 1));

        // اگر جای کافی روی لبه نباشد فاصله‌ها کم می‌شود
        if ($span > $length - 0.4) {
            $gap = max(0.0, ($length - 0.4 - $intake) / max(1, $count - 1));
            $span = $intake + ($gap * ($count - 1));
        }

        $cursor = max(0.0, min($length - $span, $centre - ($span / 2)));
        $spread = (float) ($options['spread'] ?? 0);
        $depths = $options['depths'] ?? array_fill(0, $count, 1.0);

        $darts = array_values($piece['darts']);
        unset($darts[$index]);
        $darts = array_values($darts);

        for ($i = 0; $i < $count; $i++) {
            $share = ($weights[$i] / $sum) * $intake;
            $legA = Geometry::pointOnEdge($outline, $edge, $cursor / max(0.01, $length));
            $legB = Geometry::pointOnEdge($outline, $edge, ($cursor + $share) / max(0.01, $length));
            $cursor += $share + $gap;

            $centreX = ($legA['x'] + $legB['x']) / 2;
            $centreY = ($legA['y'] + $legB['y']) / 2;

            $offset = $count > 1 ? ($spread * (($i / ($count - 1)) - 0.5)) : 0.0;
            $depth = (float) ($depths[$i] ?? 1.0);

            $tip = [
                'x' => $apex['x'] + $offset + ((($centreX - $apex['x']) * (1 - $depth))),
                'y' => $apex['y'] + ((($centreY - $apex['y']) * (1 - $depth))),
            ];

            $darts[] = [
                'type' => $source['type'] ?? 'dart',
                'label' => (string) ($options['labels'][$i] ?? (($source['label'] ?? 'ساسون').' '.($i + 1))),
                'edge' => $edge,
                'axis' => $source['axis'] ?? 'x',
                'intake' => round($share, 2),
                'center' => Geometry::point($centreX, $centreY),
                'apex' => Geometry::point($tip['x'], $tip['y']),
                'legs' => [Geometry::point($legA['x'], $legA['y']), Geometry::point($legB['x'], $legB['y'])],
            ];
        }

        $piece['darts'] = $darts;

        return Outline::reindex($piece);
    }

    /**
     * تبدیل ساسون به چین.
     *
     * پارچه اضافه سرِ جایش می‌ماند ولی به‌جای تا شدن، با کوک درشت جمع می‌شود —
     * برای پارچه نرم و مدل‌های آزاد. مسیر قطعه دست نمی‌خورد؛ فقط ثبت عوض می‌شود.
     *
     * گزینه‌ها: span (پهن‌تر کردن ناحیه چین به سانتی‌متر)، label.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    public static function toGathers(array $piece, int|string $dart, array $options = []): array
    {
        return self::toFullness($piece, $dart, 'gathers', $options + ['label' => 'چین جای ساسون']);
    }

    /**
     * تبدیل ساسون به آزادی دوخت.
     *
     * همان مقدار پارچه بدون چین دیده‌شدنی در درز جا داده می‌شود؛ برای پارچه‌های
     * بدن‌دار و مقدارهای کم.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    public static function toEase(array $piece, int|string $dart, array $options = []): array
    {
        return self::toFullness($piece, $dart, 'ease', $options + ['label' => 'آزادی جای ساسون']);
    }

    /**
     * تبدیل ساسون به پیلی.
     *
     * گزینه‌ها: count، type (knife|box|inverted|accordion)، span، label.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    public static function toPleats(array $piece, int|string $dart, array $options = []): array
    {
        return self::toFullness($piece, $dart, 'pleats', $options + ['label' => 'پیلی جای ساسون']);
    }

    /**
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected static function toFullness(array $piece, int|string $dart, string $kind, array $options): array
    {
        $index = self::find($piece, $dart);
        $source = array_values($piece['darts'])[$index];
        $edge = $source['edge'];

        if ($edge === null) {
            throw new InvalidArgumentException('ساسونی که روی لبه ننشسته به چین تبدیل نمی‌شود.');
        }

        $edge = (int) $edge;
        $outline = array_values($piece['outline']);
        $length = max(0.01, Geometry::edgeLength($outline, $edge));
        $legs = array_values($source['legs']);

        $positions = [];

        foreach ($legs as $leg) {
            $positions[] = Geometry::edgeParameterOf($outline, $edge, ['x' => (float) $leg['x'], 'y' => (float) $leg['y']]);
        }

        sort($positions);
        $span = ((float) ($options['span'] ?? 0)) / $length;
        $start = max(0.0, $positions[0] - ($span / 2));
        $end = min(1.0, $positions[1] + ($span / 2));

        $darts = array_values($piece['darts']);
        unset($darts[$index]);
        $piece['darts'] = array_values($darts);

        $piece = FullnessRecorder::record($piece, $kind, $edge, (float) $source['intake'], array_merge($options, [
            'start' => $start,
            'end' => $end,
            'source' => $source['label'] ?? ($source['type'] ?? 'ساسون'),
        ]));

        return Outline::reindex($piece);
    }

    /* ---------------------------------------------------------------------
     |  ساسون ← درز (برش شاهزاده‌ای)
     * ------------------------------------------------------------------- */

    /**
     * تبدیل ساسون به درز و بریدن قطعه به دو پانل.
     *
     * برش شاهزاده‌ای (پرنسس): خطی از حلقه آستین یا سرشانه، از روی برجستگی سینه
     * می‌گذرد و به دهانه ساسون کمر می‌رسد. قطعه از همان خط بریده می‌شود و ساسون
     * در درز حل می‌شود؛ در نتیجه دو پانل به دست می‌آید که درز تازه‌شان دقیقاً
     * هم‌اندازه است و مجموع مساحتشان برابر مساحت قطعه منهای مثلث ساسون‌هاست.
     *
     * گزینه‌ها:
     *   to        سرِ خط برش: ['edge' => 'armhole'|'shoulder'|شماره، 't' => نسبت]
     *             (پیش‌فرض وسط‌بالای حلقه آستین)
     *   absorb    شماره یا نوع ساسون دیگری (مثل ساسون سینه) که اول به همان نقطه
     *             چرخانده و در درز حل شود؛ true یعنی هر ساسونی که نوکش نزدیک
     *             همین نوک است
     *   bow       خمیدگی خط برش بالای نوک (سانتی‌متر، پیش‌فرض ۰)
     *   tag       برچسب لبه درز تازه (پیش‌فرض side)
     *   codes، names، notches
     *
     * @param  array<string, mixed>  $piece
     * @return array<int, array<string, mixed>> دو پانل
     */
    public static function toSeam(array $piece, int|string $dart, array $options = []): array
    {
        $to = $options['to'] ?? ['edge' => 'armhole', 't' => 0.45];

        $absorb = $options['absorb'] ?? true;
        $index = self::find($piece, $dart);
        $apexOf = array_values($piece['darts'])[$index]['apex'];
        $label = array_values($piece['darts'])[$index]['label'] ?? 'ساسون';

        if ($absorb === true) {
            $absorb = self::sharingApex($piece, $index, (float) ($options['apex_tolerance'] ?? 2.5));
        } elseif ($absorb === false || $absorb === null) {
            $absorb = [];
        } elseif (! is_array($absorb)) {
            $absorb = [$absorb];
        }

        $topMouth = null;

        foreach ($absorb as $other) {
            $piece = self::rotate($piece, $other, array_merge($to, [
                'type' => 'style',
                'label' => 'ساسون در خط برش',
                'keep' => 'a',
            ]));

            $topMouth = end($piece['darts']);
        }

        $index = self::find($piece, $label);
        $piece = self::trueLegs($piece, $index);
        $source = array_values($piece['darts'])[$index];
        $apex = ['x' => (float) $source['apex']['x'], 'y' => (float) $source['apex']['y']];

        if ($topMouth !== null) {
            $apex = ['x' => (float) $topMouth['apex']['x'], 'y' => (float) $topMouth['apex']['y']];
        }

        $outline = array_values($piece['outline']);
        $tags = Geometry::edgeTags($piece);

        $anchors = [];
        $bottomLegs = array_values($source['legs']);
        $bottomEdge = (int) $source['edge'];

        foreach ($bottomLegs as $position => $leg) {
            $anchors['bottom'.$position] = [
                'edge' => $bottomEdge,
                't' => Geometry::edgeParameterOf($outline, $bottomEdge, ['x' => (float) $leg['x'], 'y' => (float) $leg['y']]),
            ];
        }

        if ($topMouth !== null) {
            $topEdge = (int) $topMouth['edge'];

            foreach (array_values($topMouth['legs']) as $position => $leg) {
                $anchors['top'.$position] = [
                    'edge' => $topEdge,
                    't' => Geometry::edgeParameterOf($outline, $topEdge, ['x' => (float) $leg['x'], 'y' => (float) $leg['y']]),
                ];
            }
        } else {
            $top = Outline::anchor($piece, $to);
            $anchors['top0'] = ['edge' => $top['edge'], 't' => $top['t']];
            $anchors['top1'] = ['edge' => $top['edge'], 't' => $top['t']];
        }

        $inserted = Outline::insertAnchors($outline, $tags, $anchors);
        $outline = $inserted['outline'];
        $tags = $inserted['tags'];
        $count = count($outline);

        $topPair = [$inserted['index']['top0'], $inserted['index']['top1']];
        $bottomPair = [$inserted['index']['bottom0'], $inserted['index']['bottom1']];

        // دو سر هر دهانه باید در جهت خودِ مسیر مرتب شوند، نه بر پایه شماره؛ وگرنه
        // دهانه‌ای که روی نقطه صفرِ مسیر می‌افتد وارونه خوانده می‌شود و یکی از
        // پانل‌ها دهانه ساسون را به جای بستن، در خودش نگه می‌دارد.
        $topPair = self::orderMouth($topPair, $bottomPair, $count);
        $bottomPair = self::orderMouth($bottomPair, $topPair, $count);

        [$firstPair, $secondPair] = [$topPair, $bottomPair];

        $tag = (string) ($options['tag'] ?? 'side');
        $bow = (float) ($options['bow'] ?? 0);

        $panels = [];

        foreach ([[$firstPair[1], $secondPair[0]], [$secondPair[1], $firstPair[0]]] as $side => [$from, $until]) {
            $chain = Outline::chain($outline, $from, $until);
            $chainTags = [];

            for ($i = 0; $i < count($chain) - 1; $i++) {
                $chainTags[$i] = $tags[($from + $i) % $count] ?? 'default';
            }

            $joined = Outline::join([
                ['points' => $chain, 'tags' => $chainTags],
                ['points' => [self::bowed($chain[count($chain) - 1], $apex, $bow), $apex], 'tags' => [$tag, $tag]],
            ], $tag);

            // درز تازه دقیقاً همان دو پاره‌خطی است که تازه کشیده شد: از ته زنجیره
            // تا نوک، و از نوک تا سر زنجیره. نمی‌شود آن را از روی برچسب پیدا کرد،
            // چون برچسب درز (پیش‌فرض side) روی درز پهلوی خودِ قطعه هم هست.
            $seamSegments = [
                [Outline::straighten($chain[count($chain) - 1]), Outline::straighten($apex)],
                [Outline::straighten($apex), Outline::straighten($chain[0])],
            ];

            $panel = $piece;
            $panel['code'] = (string) ($options['codes'][$side] ?? (($piece['code'] ?? 'piece').'-'.($side === 0 ? 'a' : 'b')));
            $panel['name'] = (string) ($options['names'][$side] ?? (($piece['name'] ?? 'قطعه').' '.($side === 0 ? '۱' : '۲')));
            $panel['darts'] = [];
            $panel['meta']['cut_of'] = $piece['code'] ?? null;
            $panel['meta']['cut_side'] = $side === 0 ? 'a' : 'b';
            $panel['meta']['princess'] = ['from' => $label, 'apex' => Geometry::point($apex['x'], $apex['y'])];

            $panel = Outline::apply($panel, $joined['outline'], $joined['tags'], normalize: false);
            $panel = self::keepInside($panel, $piece);
            $panel['meta']['cut_edges'] = Outline::refoldEdges($panel, $seamSegments);
            $panel['meta']['cut_pair'] = 'princess-'.substr(md5(($piece['code'] ?? '').$label), 0, 8);
            $panel['meta']['fold_edges'] = Outline::refoldEdges($panel, self::foldSegments($piece));
            $panel['on_fold'] = $panel['meta']['fold_edges'] !== [];

            $panels[] = $panel;
        }

        $panels = self::seamNotches($panels, $apex, $options);

        if ($options['normalize'] ?? true) {
            $panels = array_map(fn (array $panel) => Geometry::normalizePiece($panel), $panels);
        }

        // پانلی که خط مرکز (تای پارچه) دارد اول می‌آید
        usort($panels, function (array $a, array $b) {
            $foldA = empty($a['meta']['fold_edges']) ? 1 : 0;
            $foldB = empty($b['meta']['fold_edges']) ? 1 : 0;

            return [$foldA, Geometry::centroid($a['outline'])['x']] <=> [$foldB, Geometry::centroid($b['outline'])['x']];
        });

        return array_values($panels);
    }

    /**
     * ترتیب دو سر یک دهانه در جهت مسیر.
     *
     * خروجی [ورود، خروج] است، طوری که کمانِ رو به جلو از ورود تا خروج هیچ‌کدام از
     * لنگرهای دیگر ($others) را در بر نگیرد؛ یعنی همان کمانی که با بستن ساسون از
     * بین می‌رود.
     *
     * @param  array<int, int>  $pair
     * @param  array<int, int>  $others
     * @return array<int, int>
     */
    protected static function orderMouth(array $pair, array $others, int $count): array
    {
        [$a, $b] = [(int) $pair[0], (int) $pair[1]];
        $count = max(1, $count);
        $offset = fn (int $from, int $to): int => ((($to - $from) % $count) + $count) % $count;
        $span = $offset($a, $b);

        foreach ($others as $other) {
            $at = $offset($a, (int) $other);

            if ($at > 0 && $at < $span) {
                return [$b, $a];
            }
        }

        return [$a, $b];
    }

    /**
     * نقطه‌ای که خط برش را کمی خم می‌کند (بالای نوک سینه، مثل کار خیاط با پیستوله).
     *
     * @return array<string, mixed>
     */
    protected static function bowed(array $from, array $apex, float $bow): array
    {
        $point = Geometry::point((float) $apex['x'], (float) $apex['y']);

        if (abs($bow) < 1e-6) {
            return $point;
        }

        $middle = Geometry::lerp(
            ['x' => (float) $from['x'], 'y' => (float) $from['y']],
            ['x' => (float) $apex['x'], 'y' => (float) $apex['y']],
            0.5,
        );

        $dx = ((float) $apex['x']) - ((float) $from['x']);
        $dy = ((float) $apex['y']) - ((float) $from['y']);
        $norm = sqrt(($dx * $dx) + ($dy * $dy)) ?: 1.0;

        return Geometry::curve(
            (float) $apex['x'],
            (float) $apex['y'],
            $middle['x'] + (($dy / $norm) * $bow),
            $middle['y'] - (($dx / $norm) * $bow),
        );
    }

    /**
     * ساسون‌هایی که نوکشان نزدیک نوک این ساسون است.
     *
     * @return array<int, int>
     */
    protected static function sharingApex(array $piece, int $index, float $tolerance): array
    {
        $darts = array_values($piece['darts'] ?? []);
        $apex = $darts[$index]['apex'] ?? null;

        if ($apex === null) {
            return [];
        }

        $found = [];

        foreach ($darts as $other => $candidate) {
            if ($other === $index || ! isset($candidate['apex']['x'])) {
                continue;
            }

            $distance = Geometry::distance(
                ['x' => (float) $apex['x'], 'y' => (float) $apex['y']],
                ['x' => (float) $candidate['apex']['x'], 'y' => (float) $candidate['apex']['y']],
            );

            if ($distance <= $tolerance) {
                $found[] = $candidate['label'] ?? $other;
            }
        }

        return $found;
    }

    /**
     * @return array<int, array<int, array{x: float, y: float}>>
     */
    protected static function foldSegments(array $piece): array
    {
        $outline = array_values($piece['outline'] ?? []);
        $count = count($outline);
        $segments = [];

        foreach ($piece['meta']['fold_edges'] ?? [] as $edge) {
            $edge = (int) $edge;

            if ($edge >= 0 && $edge < $count) {
                $segments[] = [
                    ['x' => (float) $outline[$edge]['x'], 'y' => (float) $outline[$edge]['y']],
                    ['x' => (float) $outline[($edge + 1) % $count]['x'], 'y' => (float) $outline[($edge + 1) % $count]['y']],
                ];
            }
        }

        return $segments;
    }

    /**
     * ساسون، نشانه و خط نشانه بیرون از پانل حذف می‌شود.
     *
     * @param  array<string, mixed>  $panel
     * @return array<string, mixed>
     */
    protected static function keepInside(array $panel, array $source): array
    {
        $outline = $panel['outline'];

        $inside = function (?array $point) use ($outline): bool {
            if ($point === null || ! isset($point['x'], $point['y'])) {
                return false;
            }

            $at = ['x' => (float) $point['x'], 'y' => (float) $point['y']];

            return Geometry::containsPoint($outline, $at) || Geometry::nearestEdge($outline, $at)['distance'] < 0.15;
        };

        $panel['notches'] = array_values(array_filter($source['notches'] ?? [], $inside));
        $panel['drills'] = array_values(array_filter($source['drills'] ?? [], $inside));
        $panel['markers'] = array_values(array_filter(
            $source['markers'] ?? [],
            fn ($marker) => $inside($marker['from'] ?? null) || $inside($marker['to'] ?? null),
        ));

        // راستای پارچه باید تمامش روی همین پانل بیفتد، نه فقط سرِ آن
        if (! empty($source['grainline']['from'])
            && (! $inside($source['grainline']['from']) || ! $inside($source['grainline']['to'] ?? null))) {
            $bounds = Geometry::bounds($outline);
            $panel['grainline'] = [
                'from' => Geometry::point(($bounds[0] + $bounds[2]) / 2, $bounds[1] + 1),
                'to' => Geometry::point(($bounds[0] + $bounds[2]) / 2, $bounds[3] - 1),
                'label' => 'راستای پارچه',
            ];
        }

        return Outline::reindex($panel);
    }

    /**
     * نشانه‌های جفت روی درز تازه دو پانل (نوک سینه و میانه‌ها).
     *
     * @param  array<int, array<string, mixed>>  $panels
     * @return array<int, array<string, mixed>>
     */
    protected static function seamNotches(array $panels, array $apex, array $options): array
    {
        if (($options['notches'] ?? true) === false) {
            return $panels;
        }

        $pair = $panels[0]['meta']['cut_pair'] ?? 'princess';
        $fractions = is_array($options['notches'] ?? null) ? $options['notches'] : [0.3, 0.75];

        foreach ($panels as $index => $panel) {
            $edges = $panel['meta']['cut_edges'] ?? [];

            if ($edges === []) {
                continue;
            }

            $near = Geometry::nearestEdge($panel['outline'], ['x' => (float) $apex['x'], 'y' => (float) $apex['y']]);

            $panel['notches'][] = [
                'x' => round($near['point']['x'], 2),
                'y' => round($near['point']['y'], 2),
                'edge' => $near['edge'],
                'label' => 'نوک برجستگی روی درز',
                'pair' => $pair.'-apex',
            ];

            $length = PieceOps::edgeLength($panel, $edges);

            foreach ($fractions as $position => $fraction) {
                $on = PieceOps::pointAlong($panel, $edges, ((float) $fraction) * $length);

                $panel['notches'][] = [
                    'x' => $on['x'],
                    'y' => $on['y'],
                    'edge' => $on['edge'],
                    'label' => 'نشانه درز برش',
                    'pair' => $pair.'-'.$position,
                ];
            }

            $panels[$index] = $panel;
        }

        return $panels;
    }
}
