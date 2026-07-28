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

            if ($front && ($frontArmhole = static::edgesWithTag($front, 'armhole')) !== []) {
                $relations[] = static::relation($entry, $capEdges[0], $front, $frontArmhole[0], 'دوخت آستین به حلقه جلو');
            }

            $backArmhole = $back ? static::edgesWithTag($back, 'armhole') : [];

            if ($backArmhole !== []) {
                $relations[] = static::relation(
                    $entry,
                    $capEdges[count($capEdges) - 1],
                    $back,
                    $backArmhole[0],
                    'دوخت آستین به حلقه پشت',
                );
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

            foreach ([[$front, 'یقه به خط یقه جلو'], [$shoulderSource, 'یقه به خط یقه پشت']] as [$target, $label]) {
                if (! $target) {
                    continue;
                }

                $neckEdges = static::edgesWithTag($target, 'neck');

                if ($neckEdges !== []) {
                    $relations[] = static::relation($entry, $collarEdges[0], $target, $neckEdges[0], $label);
                }
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

                if ($waistEdges !== [] && $bandEdges !== []) {
                    $relations[] = static::relation(
                        $waistband,
                        $bandEdges[0],
                        $target,
                        $waistEdges[0],
                        'کمربند به خط کمر '.($target['side'] === 'back' ? 'پشت' : 'جلو'),
                    );
                }
            }
        }

        return array_values($relations);
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
