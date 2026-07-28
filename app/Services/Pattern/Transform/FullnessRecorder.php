<?php

namespace App\Services\Pattern\Transform;

use App\Services\Pattern\Geometry;
use InvalidArgumentException;

/**
 * ثبت «گشادی» روی قطعه: چین، پیلی و آزادی دوخت.
 *
 * در خیاطی سه راه برای جا دادن پارچه اضافه روی یک درز هست و هر سه باید جایی
 * نوشته شوند تا هم نقشه‌کش، هم برش‌کار و هم برگه فنی بدانند چه خبر است:
 *
 *   چین (gathers) — پارچه با کوک درشت جمع می‌شود؛ آزادانه و نرم می‌ریزد.
 *   پیلی (pleats) — پارچه تا می‌خورد و تای آن دوخته یا اتو می‌شود؛
 *                   نوع تا: knife (یک‌طرفه)، box (جعبه‌ای)، inverted (جعبه‌ای برعکس)،
 *                   accordion (آکاردئونی).
 *   آزادی (ease)  — پارچه بدون چین دیده‌شدنی در درز جا داده می‌شود (مثل سرآستین).
 *
 * همه در meta ذخیره می‌شوند و شکل ثبت یکسان است:
 *
 *   meta.gathers[] = ['kind','edge','start','end','amount','ratio','label']
 *   meta.pleats[]  = ['kind','edge','start','end','amount','count','type','depth','label']
 *   meta.ease[]    = ['kind','edge','start','end','amount','label']
 *
 * start و end نسبت روی همان لبه‌اند (۰ تا ۱)، amount مقدار پارچه اضافه به
 * سانتی‌متر، و ratio نسبت طول لبه به طول دوخته‌شده.
 */
final class FullnessRecorder
{
    /** نوع‌های پیلی. */
    public const PLEAT_TYPES = ['knife', 'box', 'inverted', 'accordion'];

    /** کلید ذخیره هر گونه گشادی در meta. */
    public const KINDS = ['gathers', 'pleats', 'ease'];

    /**
     * ثبت چین روی یک لبه.
     *
     * گزینه‌ها: start، end (نسبت روی لبه، پیش‌فرض تمام لبه)، label، replace.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    public static function gathers(array $piece, int $edge, float $amount, array $options = []): array
    {
        return self::record($piece, 'gathers', $edge, $amount, $options + ['label' => 'چین']);
    }

    /**
     * ثبت پیلی روی یک لبه.
     *
     * گزینه‌ها: count (تعداد پیلی، پیش‌فرض ۱)، type (knife|box|inverted|accordion)،
     * start، end، label.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    public static function pleats(array $piece, int $edge, float $amount, array $options = []): array
    {
        $type = (string) ($options['type'] ?? 'knife');

        if (! in_array($type, self::PLEAT_TYPES, true)) {
            throw new InvalidArgumentException("نوع پیلی «{$type}» شناخته نشد.");
        }

        $count = max(1, (int) ($options['count'] ?? 1));
        // پیلی جعبه‌ای از دو تای روبه‌رو ساخته می‌شود، پس عمق هر تا نصف سهم آن است
        $folds = in_array($type, ['box', 'inverted'], true) ? 2 : 1;
        $depth = $count > 0 ? ($amount / $count) / (2 * $folds) : 0.0;

        return self::record($piece, 'pleats', $edge, $amount, array_merge($options, [
            'label' => $options['label'] ?? 'پیلی',
            'count' => $count,
            'type' => $type,
            'depth' => round($depth, 2),
        ]));
    }

    /**
     * ثبت آزادی دوخت روی یک لبه (بدون چین دیده‌شدنی).
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    public static function ease(array $piece, int $edge, float $amount, array $options = []): array
    {
        return self::record($piece, 'ease', $edge, $amount, $options + ['label' => 'آزادی دوخت']);
    }

    /**
     * ثبت خام یک گشادی.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    public static function record(array $piece, string $kind, int $edge, float $amount, array $options = []): array
    {
        if (! in_array($kind, self::KINDS, true)) {
            throw new InvalidArgumentException("گونه گشادی «{$kind}» شناخته نشد.");
        }

        $amount = round((float) $amount, 3);

        if ($amount <= 0) {
            return $piece;
        }

        $count = count($piece['outline'] ?? []);

        if ($count > 0) {
            $edge = (($edge % $count) + $count) % $count;
        }

        $start = max(0.0, min(1.0, (float) ($options['start'] ?? 0.0)));
        $end = max(0.0, min(1.0, (float) ($options['end'] ?? 1.0)));

        if ($end < $start) {
            [$start, $end] = [$end, $start];
        }

        $span = $count > 0 ? Geometry::edgeLength($piece['outline'], $edge) * ($end - $start) : 0.0;

        $entry = [
            'kind' => $kind,
            'edge' => $edge,
            'tag' => (string) ($options['tag'] ?? (Geometry::edgeTags($piece)[$edge] ?? 'default')),
            'start' => round($start, 4),
            'end' => round($end, 4),
            'amount' => $amount,
            'label' => (string) ($options['label'] ?? 'گشادی'),
        ];

        if ($span > 0.01) {
            $entry['ratio'] = round($span / max(0.01, $span - $amount), 3);
        }

        foreach (['count', 'type', 'depth', 'pair', 'source'] as $key) {
            if (array_key_exists($key, $options)) {
                $entry[$key] = $options[$key];
            }
        }

        if (! empty($options['replace'])) {
            $piece = self::clear($piece, $edge, $kind);
        }

        $piece['meta'][$kind] = array_values(array_merge($piece['meta'][$kind] ?? [], [$entry]));

        return $piece;
    }

    /**
     * پاک کردن گشادی‌های ثبت‌شده (همه، یا فقط یک لبه، یا فقط یک گونه).
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    public static function clear(array $piece, ?int $edge = null, ?string $kind = null): array
    {
        foreach (self::KINDS as $key) {
            if ($kind !== null && $key !== $kind) {
                continue;
            }

            if (empty($piece['meta'][$key])) {
                continue;
            }

            $piece['meta'][$key] = array_values(array_filter(
                $piece['meta'][$key],
                fn ($entry) => $edge !== null && ((int) ($entry['edge'] ?? -1)) !== $edge,
            ));

            if ($piece['meta'][$key] === []) {
                unset($piece['meta'][$key]);
            }
        }

        return $piece;
    }

    /**
     * همه گشادی‌های ثبت‌شده یک قطعه، پشت سر هم.
     *
     * @param  array<string, mixed>  $piece
     * @return array<int, array<string, mixed>>
     */
    public static function all(array $piece): array
    {
        $entries = [];

        foreach (self::KINDS as $kind) {
            foreach ($piece['meta'][$kind] ?? [] as $entry) {
                $entries[] = array_merge(['kind' => $kind], $entry);
            }
        }

        return $entries;
    }

    /** مجموع پارچه اضافه‌ای که روی یک لبه ثبت شده است. */
    public static function amountOn(array $piece, int $edge, ?string $kind = null): float
    {
        $total = 0.0;

        foreach (self::all($piece) as $entry) {
            if (((int) ($entry['edge'] ?? -1)) !== $edge) {
                continue;
            }

            if ($kind !== null && ($entry['kind'] ?? null) !== $kind) {
                continue;
            }

            $total += (float) ($entry['amount'] ?? 0);
        }

        return round($total, 3);
    }

    /**
     * مقداری از یک لبه که در دوخت «خورده می‌شود»: ساسون + پیلی + چین.
     *
     * آزادی دوخت (ease) شمرده نمی‌شود چون پارچه در همان طول جا داده می‌شود.
     */
    public static function consumedOn(array $piece, int $edge): float
    {
        $total = 0.0;

        foreach ($piece['darts'] ?? [] as $dart) {
            if (((int) ($dart['edge'] ?? -1)) === $edge) {
                $total += (float) ($dart['intake'] ?? 0);
            }
        }

        foreach ($piece['pleats'] ?? [] as $pleat) {
            if (((int) ($pleat['edge'] ?? -1)) === $edge) {
                $total += (float) ($pleat['depth'] ?? 0);
            }
        }

        $total += self::amountOn($piece, $edge, 'gathers');
        $total += self::amountOn($piece, $edge, 'pleats');

        return round($total, 3);
    }

    /**
     * شماره لبه‌ها را بعد از تغییر شکل قطعه جابه‌جا می‌کند.
     *
     * @param  array<string, mixed>  $piece
     * @param  array<int, int>  $map  شماره قدیم ⇒ شماره تازه
     * @return array<string, mixed>
     */
    public static function remap(array $piece, array $map): array
    {
        foreach (self::KINDS as $kind) {
            if (empty($piece['meta'][$kind])) {
                continue;
            }

            $piece['meta'][$kind] = array_map(function (array $entry) use ($map) {
                $edge = (int) ($entry['edge'] ?? -1);

                if (array_key_exists($edge, $map)) {
                    $entry['edge'] = $map[$edge];
                }

                return $entry;
            }, $piece['meta'][$kind]);
        }

        return $piece;
    }
}
