<?php

namespace App\Services\Pattern;

use App\Models\Pattern;
use App\Models\PatternPiece;
use App\Models\PatternVersion;

/**
 * نسخه‌های الگو.
 *
 * پیش از هر تغییری روی هندسه، یک «عکس لحظه‌ای» از الگو و همه قطعه‌ها ذخیره می‌شود تا
 * کاربر بتواند هر وقت خواست به عقب برگردد. شماره نسخه ذخیره‌شده همان شماره نسخه
 * جاری الگو است و بعد از ثبت، نسخه جاری یک شماره جلو می‌رود.
 */
class PatternVersionService
{
    /** کلیدهای الگو که در عکس لحظه‌ای نگه داشته می‌شوند. */
    public const PATTERN_KEYS = [
        'name', 'base_size', 'measurements', 'ease', 'seam_allowances', 'params', 'sewing_relations', 'notes',
    ];

    /** کلیدهای قطعه که در عکس لحظه‌ای نگه داشته می‌شوند. */
    public const PIECE_KEYS = [
        'code', 'name', 'layer', 'cut_quantity', 'on_fold', 'mirror', 'outline', 'grainline',
        'darts', 'notches', 'drills', 'pleats', 'markers', 'edge_allowances', 'meta', 'sort',
    ];

    /**
     * ثبت نسخه؛ با $bump نسخه جاری الگو یک شماره جلو می‌رود.
     *
     * عکس لحظه‌ای همیشه با شماره نسخه جاری ثبت می‌شود؛ اگر برای همین شماره از قبل
     * رکوردی باشد (مثلاً عکس لحظه اول ساخت) همان به‌روز می‌شود، چون محتوایش همان
     * وضعیت فعلی است.
     */
    public function snapshot(Pattern $pattern, ?string $note = null, bool $bump = false): PatternVersion
    {
        $version = max(1, (int) $pattern->version);

        $record = $pattern->versions()->updateOrCreate(
            ['version' => $version],
            [
                'snapshot' => $this->buildSnapshot($pattern, $version),
                'note' => $note,
                'created_by' => auth()->id(),
            ],
        );

        if ($bump) {
            $pattern->forceFill(['version' => $version + 1])->save();
        }

        $pattern->unsetRelation('versions');

        return $record;
    }

    /**
     * عکس لحظه‌ای الگو.
     *
     * @return array<string, mixed>
     */
    public function buildSnapshot(Pattern $pattern, ?int $version = null): array
    {
        $pieces = $pattern->pieces()->get();

        return [
            'version' => $version ?? (int) $pattern->version,
            'created_at' => now()->toIso8601String(),
            'pattern' => collect($pattern->only(static::PATTERN_KEYS))->all(),
            'pieces' => $pieces->map(fn (PatternPiece $piece) => collect($piece->only(static::PIECE_KEYS))->all())->all(),
            'summary' => [
                'piece_count' => $pieces->count(),
                'cut_count' => (int) $pieces->sum('cut_quantity'),
                'total_area' => round($pieces->sum(fn (PatternPiece $piece) => $piece->area() * $piece->cut_quantity), 1),
            ],
        ];
    }

    /** بازگردانی یک نسخه؛ وضعیت فعلی هم پیش از بازگردانی ثبت می‌شود. */
    public function restore(Pattern $pattern, PatternVersion $version): Pattern
    {
        $this->snapshot($pattern, 'پیش از بازگردانی نسخه '.$version->version, bump: true);

        $snapshot = $version->snapshot ?? [];
        $attributes = array_intersect_key($snapshot['pattern'] ?? [], array_flip(static::PATTERN_KEYS));

        if ($attributes !== []) {
            $pattern->fill($attributes);
        }

        $pattern->save();

        $pattern->pieces()->delete();

        foreach ($snapshot['pieces'] ?? [] as $piece) {
            $pattern->pieces()->create(array_intersect_key($piece, array_flip(static::PIECE_KEYS)));
        }

        return $pattern->load('pieces');
    }

    /**
     * مقایسه دو عکس لحظه‌ای: چه قطعه‌ای اضافه/کم شده و مساحت هر قطعه چقدر تغییر کرده.
     *
     * @return array{added: array<int, string>, removed: array<int, string>, changed: array<int, array<string, mixed>>}
     */
    public function diff(array $from, array $to): array
    {
        $left = $this->areaMap($from);
        $right = $this->areaMap($to);

        $changed = [];

        foreach ($left as $code => $item) {
            if (! isset($right[$code])) {
                continue;
            }

            $delta = round($right[$code]['area'] - $item['area'], 1);

            if (abs($delta) >= 0.5) {
                $changed[] = [
                    'code' => $code,
                    'name' => $right[$code]['name'],
                    'from' => $item['area'],
                    'to' => $right[$code]['area'],
                    'delta' => $delta,
                ];
            }
        }

        return [
            'added' => array_values(array_diff(array_keys($right), array_keys($left))),
            'removed' => array_values(array_diff(array_keys($left), array_keys($right))),
            'changed' => $changed,
        ];
    }

    /**
     * مساحت قطعه‌های یک عکس لحظه‌ای.
     *
     * @return array<string, array{name: string, area: float}>
     */
    protected function areaMap(array $snapshot): array
    {
        $map = [];

        foreach ($snapshot['pieces'] ?? [] as $piece) {
            $code = (string) ($piece['code'] ?? '');

            if ($code === '') {
                continue;
            }

            $map[$code] = [
                'name' => (string) ($piece['name'] ?? $code),
                'area' => Geometry::area($piece['outline'] ?? []),
            ];
        }

        return $map;
    }
}
