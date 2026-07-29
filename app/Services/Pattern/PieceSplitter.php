<?php

namespace App\Services\Pattern;

use App\Models\Pattern;
use App\Models\PatternPiece;
use App\Services\Pattern\Transform\StyleLineCutter;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * برش دلخواه: دو تکه کردن یک قطعهٔ ذخیره‌شده در امتداد خطی که کاربر کشیده.
 *
 * سبک‌های گروه «برش» جای درزهای شناخته‌شده را می‌دهند — یوک، پنل، کالربلاک — ولی
 * امضای یک برند معمولاً درزی است که در هیچ فهرستی نیست. موتور بریدن (StyleLineCutter)
 * از اول در سامانه بود؛ کاری که این کلاس می‌کند رساندن آن به ویرایشگر است.
 *
 * دو تفاوت مهم با سبک‌ها:
 *
 *   - سبک روی «قطعه‌های در حال ساخت» کار می‌کند و همه‌چیز در حافظه است؛ این‌جا
 *     قطعه در دیتابیس است، پس باید ردیف‌ها را جابه‌جا کرد و رابطه‌های دوخت را
 *     دوباره ساخت.
 *   - سبک پارامتر می‌گیرد و در همه سایزها دوباره اجرا می‌شود؛ برش دستی یک‌بار
 *     انجام می‌شود و از آن پس بخشی از خود الگوست. برای همین پیش از برش نسخه
 *     گرفته می‌شود تا برگشت‌پذیر باشد.
 */
class PieceSplitter
{
    public function __construct(protected SeamAllowanceService $seams = new SeamAllowanceService) {}

    /**
     * بریدن و ذخیره در یک گام.
     *
     * @param  array<int, array<string, mixed>>  $path
     * @param  array{names?: array<int, string>, tag?: string}  $options
     * @return array<int, PatternPiece>
     */
    public function split(Pattern $pattern, PatternPiece $piece, array $path, array $options = []): array
    {
        return $this->persist($pattern, $piece, $this->prepare($pattern, $piece, $path, $options));
    }

    /**
     * هندسهٔ دو نیمه، بدون دست زدن به دیتابیس.
     *
     * جدا بودن این گام از ذخیره فقط تمیزکاری نیست: تا وقتی معلوم نشده برش
     * درست درمی‌آید نباید نسخهٔ تازه‌ای ثبت شود، وگرنه هر خط ناموفقی یک نسخهٔ
     * بی‌معنا در تاریخچه جا می‌گذارد.
     *
     * $path فهرست نقطه در مختصات محلی همان قطعه است. نقطهٔ اول و آخر روی محیط
     * می‌نشینند (اگر دقیق روی محیط نباشند به نزدیک‌ترین جای محیط چسبانده می‌شوند).
     *
     * @param  array<int, array<string, mixed>>  $path
     * @param  array{names?: array<int, string>, tag?: string}  $options
     * @return array<int, array<string, mixed>> دو نیمه
     */
    public function prepare(Pattern $pattern, PatternPiece $piece, array $path, array $options = []): array
    {
        if ($piece->pattern_id !== $pattern->id) {
            throw new InvalidArgumentException('این قطعه برای این الگو نیست.');
        }

        $names = $options['names'] ?? [$piece->name.' ۱', $piece->name.' ۲'];
        $codes = $this->freeCodes($pattern, $piece);

        $halves = StyleLineCutter::cut($this->toArray($piece), $this->cleanPath($path), [
            'tag' => (string) ($options['tag'] ?? 'default'),
            'codes' => $codes,
            'names' => [$names[0] ?? $piece->name.' ۱', $names[1] ?? $piece->name.' ۲'],
            'pair' => 'hand-'.$piece->id,
        ]);

        if (count($halves) !== 2) {
            throw new InvalidArgumentException('برش دو قطعه نداد؛ خط را جور دیگری بکشید.');
        }

        $whole = abs(Geometry::area($piece->outline ?? []));
        $sum = 0.0;

        foreach ($halves as $half) {
            if (count($half['outline'] ?? []) < 3 || abs(Geometry::area($half['outline'])) < 4) {
                throw new InvalidArgumentException('یکی از دو تکه تقریباً بی‌مساحت درآمد؛ خط برش را جابه‌جا کنید.');
            }

            // خطی که نقطهٔ میانی‌اش بیرون قطعه بیفتد می‌تواند مسیر را در خودش بپیچاند
            if (Geometry::selfIntersects($half['outline'])) {
                throw new InvalidArgumentException('این خط، مسیر قطعه را در خودش می‌پیچاند؛ نقطه‌های میانی را داخل خود قطعه بگذارید.');
            }

            $sum += abs(Geometry::area($half['outline']));
        }

        // دو تکه روی هم باید همان‌قدر پارچه باشند که قطعهٔ کامل بود
        if ($whole > 0 && abs($sum - $whole) / $whole > 0.02) {
            throw new InvalidArgumentException('این خط قطعه را درست دو تکه نمی‌کند؛ دو سرش را روی لبهٔ قطعه بگذارید.');
        }

        return $halves;
    }

    /**
     * نشاندن دو نیمه در جدول: نیمهٔ اول جای همان ردیف قبلی و نیمهٔ دوم ردیف تازه.
     *
     * @param  array<int, array<string, mixed>>  $halves
     * @return array<int, PatternPiece>
     */
    public function persist(Pattern $pattern, PatternPiece $piece, array $halves): array
    {
        return DB::transaction(function () use ($pattern, $piece, $halves) {
            $first = $this->store($pattern, $piece, $halves[0], $piece->sort, replacing: $piece);
            $second = $this->store($pattern, $piece, $halves[1], $piece->sort + 1);

            // شماره بقیه قطعه‌ها یکی جلو می‌رود تا ترتیب چاپ به هم نخورد
            $pattern->pieces()
                ->whereNotIn('id', [$first->id, $second->id])
                ->where('sort', '>', $piece->sort)
                ->increment('sort');

            $pattern->load('pieces');
            $pattern->forceFill(['sewing_relations' => SewingRelationBuilder::suggest($pattern)])->save();

            return [$first, $second];
        });
    }

    /* ---------------------------------------------------------------------
     |  تبدیل بین ردیف جدول و آرایهٔ هندسی
     * ------------------------------------------------------------------- */

    /** @return array<string, mixed> */
    protected function toArray(PatternPiece $piece): array
    {
        return [
            'code' => $piece->code,
            'name' => $piece->name,
            'layer' => $piece->layer,
            'cut_quantity' => $piece->cut_quantity,
            'on_fold' => (bool) $piece->on_fold,
            'mirror' => (bool) $piece->mirror,
            'outline' => $piece->outline ?? [],
            'grainline' => $piece->grainline,
            'darts' => $piece->darts ?? [],
            'notches' => $piece->notches ?? [],
            'drills' => $piece->drills ?? [],
            'pleats' => $piece->pleats ?? [],
            'markers' => $piece->markers ?? [],
            'edge_allowances' => $piece->edge_allowances ?? [],
            'meta' => array_merge($piece->meta ?? [], [
                'edges' => $this->seams->edgeTags($piece),
            ]),
            'sort' => $piece->sort,
        ];
    }

    /**
     * ذخیره یک نیمه: یا روی همان ردیف قبلی می‌نشیند یا ردیف تازه می‌سازد.
     *
     * @param  array<string, mixed>  $half
     */
    protected function store(Pattern $pattern, PatternPiece $origin, array $half, int $sort, ?PatternPiece $replacing = null): PatternPiece
    {
        $meta = array_merge($half['meta'] ?? [], [
            'hand_cut' => true,
            'cut_of' => $origin->code,
        ]);

        // جای دوخت را نمی‌شود از قطعهٔ قبلی کپی کرد: شماره لبه‌ها با برش عوض شده و
        // عدد هر لبه روی لبهٔ دیگری می‌افتاد. پس از روی برچسب لبه‌ها دوباره ساخته
        // می‌شود — همان کاری که هنگام ساخت الگو انجام می‌شود.
        $allowances = $this->seams->allowancesFor($half, $pattern->seam_allowances ?? []);

        $attributes = [
            'pattern_id' => $pattern->id,
            'code' => $half['code'],
            'name' => $half['name'],
            'layer' => $half['layer'] ?? $origin->layer,
            'cut_quantity' => $half['cut_quantity'] ?? $origin->cut_quantity,
            'on_fold' => (bool) ($half['on_fold'] ?? false),
            'mirror' => (bool) ($half['mirror'] ?? $origin->mirror),
            'outline' => Geometry::round($half['outline']),
            'grainline' => $half['grainline'] ?? null,
            'darts' => $half['darts'] ?? [],
            'notches' => $half['notches'] ?? [],
            'drills' => $half['drills'] ?? [],
            'pleats' => $half['pleats'] ?? [],
            'markers' => $half['markers'] ?? [],
            'edge_allowances' => $allowances,
            'meta' => $meta,
            'sort' => $sort,
        ];

        if ($replacing !== null) {
            $replacing->update($attributes);

            return $replacing->refresh();
        }

        return PatternPiece::query()->create($attributes);
    }

    /**
     * دو کد آزاد برای دو نیمه.
     *
     * @return array<int, string>
     */
    protected function freeCodes(Pattern $pattern, PatternPiece $piece): array
    {
        $taken = $pattern->pieces()->pluck('code')->all();
        $base = mb_substr((string) $piece->code, 0, 40);
        $out = [];

        foreach (['a', 'b'] as $suffix) {
            $code = $base.'-'.$suffix;
            $counter = 2;

            while (in_array($code, $taken, true) || in_array($code, $out, true)) {
                $code = $base.'-'.$suffix.$counter;
                $counter++;
            }

            $out[] = $code;
        }

        return $out;
    }

    /**
     * پاک‌سازی خط برش: فقط کلیدهای هندسی می‌مانند.
     *
     * @param  array<int, array<string, mixed>>  $path
     * @return array<int, array<string, mixed>>
     */
    protected function cleanPath(array $path): array
    {
        $out = [];

        foreach (array_values($path) as $point) {
            if (! isset($point['x'], $point['y'])) {
                continue;
            }

            $clean = ['x' => round((float) $point['x'], 3), 'y' => round((float) $point['y'], 3)];

            if (! empty($point['curve']) && isset($point['cx'], $point['cy'])) {
                $clean['curve'] = true;
                $clean['cx'] = round((float) $point['cx'], 3);
                $clean['cy'] = round((float) $point['cy'], 3);
            }

            $out[] = $clean;
        }

        if (count($out) < 2) {
            throw new InvalidArgumentException('خط برش دست‌کم دو نقطه می‌خواهد.');
        }

        return $out;
    }
}
