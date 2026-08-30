<?php

namespace App\Services\Pattern;

use App\Models\Pattern;
use App\Support\FabricProfile;
use App\Support\Stitches;

/**
 * نقشهٔ دوختِ یک الگو: هر لبه با کدام کوک، چه فاصله‌ای و چه درزی.
 *
 * الگو می‌گوید هر لبه چیست (یقه، سرشانه، پهلو، دم) و پارچه می‌گوید چه رفتاری
 * دارد. از این دو، دستورِ دوختِ همان لبه درمی‌آید — همان چیزی که خیاطِ باتجربه
 * بی‌فکر انجام می‌دهد و تازه‌کار نمی‌داند.
 *
 * چیزی این‌جا حدس نیست: فاصلهٔ کوک از وزنِ پارچه می‌آید، نوعِ درز از کشسانی و
 * شفافیت و ریش‌شدنش، و جای دوخت از خودِ الگو.
 */
class StitchPlanService
{
    /**
     * @return array{
     *     fabric: array<string, mixed>,
     *     edges: array<int, array<string, mixed>>,
     *     hand: array<int, array<string, mixed>>,
     *     notes: array<int, string>
     * }
     */
    public function plan(Pattern $pattern, ?FabricProfile $fabric = null, array $options = []): array
    {
        $fabric ??= FabricProfile::make();
        $gsm = (float) $fabric->get('weight_gsm');
        $family = $this->family($fabric, $pattern, $options);
        $weight = Stitches::weightClass($gsm);

        return [
            'fabric' => [
                'weight' => $weight['label'],
                'gsm' => $gsm,
                'needle' => $weight['needle'],
                'needle_kind' => $this->needleKind($fabric, $weight),
                'thread' => $this->thread($fabric),
                'length' => Stitches::length('lock', $gsm),
                'per_inch' => Stitches::perInch(Stitches::length('lock', $gsm)),
                'family' => $family,
                'family_label' => $this->familyLabel($family),
            ],
            'edges' => $this->edges($pattern, $fabric, $family, $gsm),
            'hand' => $this->handWork($pattern, $fabric, $options),
            'notes' => $this->notes($fabric, $family),
        ];
    }

    /**
     * ردهٔ پارچه، که انتخابِ درز را تعیین می‌کند.
     *
     * ترتیبش مهم است: پارچهٔ کشیِ شفاف باز هم کشی است و باید سردوز شود، ولی
     * پارچهٔ تختِ شفاف درزِ فرانسوی می‌خواهد.
     */
    protected function family(FabricProfile $fabric, Pattern $pattern, array $options): string
    {
        if ($fabric->maxStretch() >= 20) {
            return 'knit';
        }

        if ($fabric->isSheer() || (float) $fabric->get('weight_gsm') < 90) {
            return 'sheer';
        }

        if ((float) $fabric->get('weight_gsm') >= 250) {
            return 'heavy';
        }

        // پیراهنِ تخت، درزِ انگلیسی می‌خواهد: هر دو رویش تمیز است و آستر نمی‌خواهد
        if ($this->isShirt($pattern, $options)) {
            return 'shirt';
        }

        return 'woven';
    }

    protected function familyLabel(string $family): string
    {
        return match ($family) {
            'knit' => 'کشباف (ژرسه)',
            'sheer' => 'نازک و شفاف',
            'heavy' => 'سنگین',
            'shirt' => 'پیراهنی',
            default => 'تخت (معمولی)',
        };
    }

    protected function isShirt(Pattern $pattern, array $options): bool
    {
        $hay = mb_strtolower(implode(' ', array_filter([
            $options['garment'] ?? null,
            $pattern->garmentType?->key,
            $pattern->garmentType?->name_fa,
            $pattern->template?->key,
            $pattern->name,
        ])));

        foreach (['shirt', 'پیراهن مرد', 'پیراهن کلاسیک'] as $needle) {
            if ($hay !== '' && str_contains($hay, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * برای هر برچسبِ لبه‌ای که در الگو هست، یک دستورِ دوخت.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function edges(Pattern $pattern, FabricProfile $fabric, string $family, float $gsm): array
    {
        $out = [];

        foreach ($this->tagsInPattern($pattern) as $tag => $length) {
            $place = Stitches::PLACES[$tag] ?? Stitches::PLACES['default'];
            $recipe = $this->recipe($place, $family);
            $seam = Stitches::SEAMS[$recipe['seam']] ?? Stitches::SEAMS['plain_open'];
            $stitchKey = $recipe['stitch'];
            $mm = Stitches::length($stitchKey, $gsm);

            $row = [
                'tag' => $tag,
                'name' => $place['name'] ?? SeamAllowanceService::TAGS[$tag] ?? $tag,
                'total_cm' => round($length, 1),
                'stitch' => $stitchKey,
                'stitch_name' => Stitches::name($stitchKey),
                'length_mm' => $mm,
                'per_inch' => Stitches::perInch($mm),
                'seam' => $recipe['seam'],
                'seam_name' => $seam['name'],
                'finish' => $seam['finish'],
                'allowance_cm' => $this->allowance($pattern, $tag, $seam),
                'why' => $place['why'] ?? null,
                'watch' => $place['watch'] ?? null,
                'before' => null,
                'after' => null,
            ];

            if (! empty($place['before'])) {
                $row['before'] = [
                    'stitch' => $place['before'],
                    'name' => Stitches::name($place['before']),
                    'length_mm' => Stitches::length($place['before'], $gsm),
                ];
            }

            if (! empty($recipe['after'])) {
                $row['after'] = [
                    'stitch' => $recipe['after'],
                    'name' => Stitches::name($recipe['after']),
                    'length_mm' => Stitches::length($recipe['after'], $gsm),
                ];
            }

            if (! empty($seam['note'])) {
                $row['note'] = $seam['note'];
            }

            $out[] = $row;
        }

        return $out;
    }

    /**
     * نسخهٔ همین لبه برای همین ردهٔ پارچه.
     *
     * اگر ردهٔ دقیق در قاعده نبود، به «تخت» برمی‌گردیم — نه به چیزی که هست.
     */
    protected function recipe(array $place, string $family): array
    {
        foreach ([$family, 'woven', 'default'] as $key) {
            if (isset($place[$key]) && is_array($place[$key])) {
                return $place[$key];
            }
        }

        return ['seam' => 'plain_serged', 'stitch' => 'lock'];
    }

    /**
     * جای دوخت: اول حرفِ خودِ الگو، بعد پیشنهادِ درز.
     *
     * الگو با یک جای دوختِ مشخص بریده شده و همان عدد باید دوخته شود، وگرنه
     * لباس تنگ یا گشاد درمی‌آید. پیشنهادِ درز فقط وقتی به کار می‌آید که الگو
     * چیزی نگفته باشد.
     */
    protected function allowance(Pattern $pattern, string $tag, array $seam): float
    {
        $map = $pattern->seam_allowances ?? [];
        $value = $map[$tag] ?? $map['default'] ?? null;

        return round((float) ($value ?? $seam['allowance']), 2);
    }

    /**
     * برچسبِ لبه‌ها ⇒ مجموع طولشان در همهٔ قطعه‌ها، به سانتی‌متر.
     *
     * لبهٔ روی تای پارچه دوخته نمی‌شود، پس شمرده هم نمی‌شود.
     *
     * @return array<string, float>
     */
    protected function tagsInPattern(Pattern $pattern): array
    {
        $totals = [];

        foreach ($pattern->pieces as $piece) {
            $outline = $piece->outline ?? [];
            $tags = $piece->meta['edges'] ?? [];
            $folds = array_flip($piece->meta['fold_edges'] ?? []);
            $count = count($outline);

            if ($count < 3) {
                continue;
            }

            for ($i = 0; $i < $count; $i++) {
                if (isset($folds[$i])) {
                    continue;
                }

                $tag = (string) ($tags[$i] ?? 'default');

                if ($tag === '' || $tag === 'default') {
                    $tag = 'default';
                }

                $from = $outline[$i];
                $to = $outline[($i + 1) % $count];
                $step = hypot(
                    (float) $to['x'] - (float) $from['x'],
                    (float) $to['y'] - (float) $from['y'],
                );

                $totals[$tag] = ($totals[$tag] ?? 0) + ($step * max(1, (int) ($piece->cut_quantity ?? 1)));
            }
        }

        // ترتیبِ دوخت، نه ترتیبِ الفبا: از بالا به پایین لباس
        $order = ['neck', 'shoulder', 'armhole', 'side', 'waist', 'strap', 'hem', 'default'];

        uksort($totals, function (string $one, string $two) use ($order) {
            $a = array_search($one, $order, true);
            $b = array_search($two, $order, true);

            return ($a === false ? 99 : $a) <=> ($b === false ? 99 : $b);
        });

        return $totals;
    }

    /**
     * کارِ دستی: آن‌جاهایی که چرخ جوابگو نیست.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function handWork(Pattern $pattern, FabricProfile $fabric, array $options): array
    {
        $out = [];
        $add = function (string $key, string $where, string $why) use (&$out) {
            $stitch = Stitches::HAND[$key] ?? null;

            if ($stitch === null) {
                return;
            }

            $out[] = [
                'stitch' => $key,
                'name' => $stitch['name'],
                'length_mm' => $stitch['length'][1],
                'where' => $where,
                'why' => $why,
            ];
        };

        $add('tack', 'نوکِ ساسون، جای جیب، نشانه‌ها', 'نشانه‌های الگو باید روی پارچه بیایند و رد هم نگذارند.');

        if ((float) $fabric->get('slippage') >= 0.5 || $fabric->isSheer()) {
            $add('baste', 'پیش از هر چرخ‌کاری', 'این پارچه زیر پایهٔ چرخ سُر می‌خورد؛ سنجاق کافی نیست.');
        }

        if ((float) $fabric->get('weight_gsm') >= 250) {
            $add('catch', 'دم لباس', 'روی پارچهٔ سنگین، کوکِ ضربدری دم را نگه می‌دارد بی‌آنکه از رو خط بیندازد.');
        } else {
            $add('hem', 'دم لباس', 'دمِ دست‌دوز از رو دیده نمی‌شود؛ روی لباس مجلسی همیشه دستی است.');
        }

        if ($this->hasLining($pattern)) {
            $add('slip', 'بستنِ آستر به بدنه', 'آستر باید بی‌آنکه نخی دیده شود بسته شود.');
        }

        if ($this->hasCollar($pattern) && (float) $fabric->get('weight_gsm') >= 200) {
            $add('pad', 'یقه و برگردانِ کت', 'پددوزی است که یقه را برمی‌گرداند؛ بی آن، یقهٔ کت تخت می‌ماند.');
            $add('prick', 'لبهٔ یقه و سجاف', 'رودوزیِ نامرئی، به‌جای رودوزیِ چرخ.');
        }

        return $out;
    }

    protected function hasLining(Pattern $pattern): bool
    {
        return $pattern->pieces->contains(fn ($piece) => ($piece->layer ?? 'outer') === 'lining');
    }

    protected function hasCollar(Pattern $pattern): bool
    {
        return $pattern->pieces->contains(function ($piece) {
            $part = (string) ($piece->meta['part'] ?? '');

            return str_contains($part, 'collar') || str_contains((string) $piece->code, 'collar');
        });
    }

    /** @return array<int, string> */
    protected function notes(FabricProfile $fabric, string $family): array
    {
        $notes = [];
        $gsm = (float) $fabric->get('weight_gsm');

        $notes[] = 'روی یک تکه پارچهٔ اضافی از همین پارچه کوک بزنید و فاصله و کشش را تنظیم کنید؛ '
            .'هر پارچه‌ای با عددِ جدول کمی فرق دارد.';

        if ($family === 'knit') {
            $notes[] = 'ژرسه با کوکِ راسته درزش می‌شکند. اگر سردوز ندارید، زیگزاگِ باریک یا کوکِ کشی بزنید.';
            $notes[] = 'سوزنِ سرگرد (بال‌پوینت) بگذارید؛ سوزنِ معمولی حلقه‌های بافت را می‌بُرد و درز سوراخ می‌شود.';
        }

        if ($family === 'sheer') {
            $notes[] = 'روی پارچهٔ شفاف، لبهٔ خام از رو دیده می‌شود؛ درزِ فرانسوی تنها راهِ تمیزِ کار است.';
            $notes[] = 'زیرِ پارچه کاغذِ نازک بگذارید تا چرخ آن را نبلعد، و بعد پاره کنید.';
        }

        if ((float) $fabric->get('fraying') >= 0.6) {
            $notes[] = 'لبهٔ این پارچه ریش می‌شود؛ بلافاصله پس از برش پاکدوزی کنید، نه در پایان کار.';
        }

        if ((float) $fabric->get('curling') >= 0.5) {
            $notes[] = 'لبه لوله می‌شود؛ پیش از دوخت با چسبِ حلال یا نوارِ نازک بخوابانید.';
        }

        if ((float) $fabric->get('heat_sensitivity') >= 0.6) {
            $notes[] = 'اتوی داغ این پارچه را برق می‌اندازد؛ با پارچهٔ محافظ و دمای کم اتو کنید.';
        }

        if ($gsm >= 300) {
            $notes[] = 'روی پارچهٔ کلفت، درزها را پیش از دوختِ بعدی اتو کنید؛ کلفتیِ نااتو، درزِ بعدی را کج می‌کند.';
        }

        return $notes;
    }

    protected function needleKind(FabricProfile $fabric, array $weight): string
    {
        return match (true) {
            $fabric->maxStretch() >= 20 => 'سرگرد (بال‌پوینت) یا استرچ',
            $fabric->isSheer() => 'میکروتکس (نوک‌تیز)',
            (float) $fabric->get('weight_gsm') >= 350 => 'جین',
            default => $weight['kind'],
        };
    }

    protected function thread(FabricProfile $fabric): string
    {
        return match (true) {
            $fabric->maxStretch() >= 20 => 'پلی‌استر همه‌کاره ۱۲۰ (نه نخ نخی؛ نخِ نخی کش نمی‌آید)',
            $fabric->isSheer() => 'پلی‌استر نازک ۱۵۰ یا نخ ابریشم',
            (float) $fabric->get('weight_gsm') >= 350 => 'پلی‌استر ۷۵ یا نخ جین‌دوزی',
            default => 'پلی‌استر همه‌کاره ۱۲۰',
        };
    }
}
