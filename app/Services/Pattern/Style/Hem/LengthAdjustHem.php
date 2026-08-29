<?php

namespace App\Services\Pattern\Style\Hem;

use App\Services\Pattern\Geometry;
use App\Support\Format;

/**
 * کوتاه یا بلند کردنِ قدِ لباس — کارِ همیشگیِ خیاط.
 *
 * هر مدلِ کاتالوگ پارامترِ قدِ خودش را دارد، ولی نامِ آن از مدلی به مدلِ دیگر
 * فرق می‌کند (length، skirt_length، leg_length، body_height…) و بعضی مدل‌ها
 * اصلاً قد ندارند. خیاط ولی یک کارِ ساده می‌کند و برای همهٔ لباس‌ها یکی است:
 * «این را پنج سانتی‌متر کوتاه کن.» این سبک همان است.
 *
 * دو تصمیم که این را از «کشیدنِ لبه به پایین» جدا می‌کند:
 *
 *   ۱. **لبه در امتدادِ درزِ پهلو دراز می‌شود، نه عمودی.** لباسِ کلوش اگر لبه‌اش
 *      عمودی پایین برود، همان‌جا باریک می‌ماند و دامن به‌جای مخروط، لولهٔ چسبیده
 *      به مخروط می‌شود. پس سرِ پهلویِ لبه روی امتدادِ همان درزِ پهلو پیش می‌رود
 *      و شیبِ کلوش ادامه پیدا می‌کند — همان کاری که خیاط با خط‌کش می‌کند.
 *   ۲. **جلو و پشت با هم و به یک اندازه.** اگر یکی کوتاه شود و دیگری نه، دو
 *      درزِ پهلو دیگر هم‌اندازه نیستند و لباس روی تن می‌چرخد.
 *
 * کوتاه‌کردن سقف دارد: قطعه نباید آن‌قدر کوتاه شود که از خطِ باسن بالاتر بیاید.
 * اگر خواستهٔ خیاط از این بیشتر باشد، تا همان‌جا کوتاه می‌شود و در یادداشت
 * صادقانه گفته می‌شود که چقدر اجرا شد.
 */
class LengthAdjustHem extends BaseHem
{
    /** کمترین بلندیِ پذیرفتنیِ یک قطعه پس از کوتاه شدن (سانتی‌متر). */
    protected const FLOOR = 12.0;

    public static function key(): string
    {
        return 'hem_length_adjust';
    }

    public function label(): string
    {
        return 'تغییر قد لباس';
    }

    public function description(): string
    {
        return 'قد لباس را کوتاه یا بلند می‌کند؛ شیب درز پهلو و کلوش دم همان می‌ماند.';
    }

    public function paramsSchema(): array
    {
        return [
            'change' => [
                'label' => 'تغییر قد', 'min' => -40, 'max' => 40, 'step' => 0.5,
                'default' => 0, 'unit' => 'سانتی‌متر',
                'hint' => 'منفی یعنی کوتاه‌تر، مثبت یعنی بلندتر.',
            ],
            'sleeve_too' => [
                'label' => 'آستین هم به همان نسبت', 'type' => 'toggle', 'default' => false,
                'hint' => 'برای وقتی که کلِ لباس بزرگ یا کوچک است، نه فقط قدش.',
            ],
            'sleeve_change' => [
                'label' => 'تغییر قد آستین', 'min' => -20, 'max' => 20, 'step' => 0.5,
                'default' => 0, 'unit' => 'سانتی‌متر',
                'hint' => 'اگر «آستین هم» روشن نباشد، همین عدد جداگانه روی آستین می‌نشیند.',
            ],
            'allowance' => $this->allowanceParam(3.0),
        ];
    }

    public function supports(array $pieces, array $context): true|string
    {
        if ($this->hemHostIndexes($pieces) === [] && $this->sleeveIndexes($pieces) === []) {
            return 'این لباس دمی ندارد که کوتاه یا بلند شود.';
        }

        return true;
    }

    public function apply(array $pieces, array $context): array
    {
        $change = $this->num($context, 'change', 0);
        $allowance = $this->num($context, 'allowance', 3);
        $sleeveToo = $this->flag($context, 'sleeve_too', false);
        $sleeveChange = $sleeveToo ? $change : $this->num($context, 'sleeve_change', 0);

        $notes = [];
        $applied = $change;

        if (abs($change) > 0.01) {
            // کوتاه‌کردن به اندازهٔ کوتاه‌ترین قطعه محدود می‌شود، وگرنه یکی از دو
            // قطعهٔ هم‌درز بیشتر از دیگری کوتاه می‌شود و درزها به هم نمی‌رسند
            if ($change < 0) {
                $room = INF;

                foreach ($this->hemHostIndexes($pieces) as $index) {
                    $room = min($room, max(0.0, $this->heightOf($pieces[$index]) - static::FLOOR));
                }

                $applied = $room === INF ? 0.0 : -min(abs($change), $room);
            }

            if (abs($applied) > 0.01) {
                $names = [];

                foreach ($this->hemHostIndexes($pieces) as $index) {
                    $pieces[$index] = $this->stretchToHem($pieces[$index], $applied);
                    $pieces[$index] = $this->setHemAllowance($pieces[$index], $allowance);
                    $pieces[$index]['meta']['length_change'] = round($applied, 2);
                    $names[] = $pieces[$index]['name'];
                }

                $notes[] = $this->note(
                    'tip',
                    ($applied > 0 ? 'قد لباس ' : 'قد لباس ')
                        .Format::cm(abs($applied))
                        .($applied > 0 ? ' بلندتر شد' : ' کوتاه‌تر شد')
                        .' روی '.implode('، ', $names).'.',
                );
            }

            if (abs($applied - $change) > 0.05) {
                $notes[] = $this->note(
                    'warning',
                    'خواستهٔ شما '.Format::cm(abs($change)).' کوتاه‌کردن بود ولی فقط '
                        .Format::cm(abs($applied)).' اجرا شد؛ بیشتر از این، قطعه از خط باسن بالاتر می‌آمد.',
                );
            }
        }

        if (abs($sleeveChange) > 0.01) {
            $sleeves = 0;

            foreach ($this->sleeveIndexes($pieces) as $index) {
                $height = $this->heightOf($pieces[$index]);
                $room = max(0.0, $height - 10.0);
                $delta = $sleeveChange < 0 ? -min(abs($sleeveChange), $room) : $sleeveChange;

                if (abs($delta) < 0.01) {
                    continue;
                }

                $pieces[$index] = $this->stretchToHem($pieces[$index], $delta);
                $pieces[$index]['meta']['length_change'] = round($delta, 2);
                $sleeves++;
            }

            if ($sleeves > 0) {
                $notes[] = $this->note(
                    'tip',
                    'قد آستین هم '.Format::cm(abs($sleeveChange))
                        .($sleeveChange > 0 ? ' بلندتر شد.' : ' کوتاه‌تر شد.'),
                );
            }
        }

        if ($notes === []) {
            $notes[] = $this->note('info', 'تغییر قدی خواسته نشده بود؛ الگو دست‌نخورده ماند.');
        }

        if (abs($applied) > 0.01) {
            $notes[] = $this->fabricNote(abs($applied) + $allowance, 'تغییر قد لباس');
        }

        return $this->result($pieces, $notes, [
            'length_change' => round($applied, 2),
            'sleeve_change' => round($sleeveChange, 2),
        ]);
    }

    /**
     * لبهٔ دم را به اندازهٔ خواسته‌شده جابه‌جا می‌کند و شیبِ پهلو را ادامه می‌دهد.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function stretchToHem(array $piece, float $delta): array
    {
        $edges = $this->edgesWithTag($piece, 'hem');

        if ($edges === []) {
            return $piece;
        }

        $outline = array_values($piece['outline'] ?? []);
        $count = count($outline);

        if ($count < 3) {
            return $piece;
        }

        // همهٔ نقطه‌هایی که روی لبهٔ دم‌اند، یک‌بار
        $touched = [];

        foreach ($edges as $edge) {
            $touched[$edge % $count] = true;
            $touched[($edge + 1) % $count] = true;
        }

        foreach (array_keys($touched) as $index) {
            $slope = $this->sideSlopeAt($outline, $index, $count);

            $outline[$index]['y'] = round(((float) $outline[$index]['y']) + $delta, 3);
            $outline[$index]['x'] = round(((float) $outline[$index]['x']) + ($slope * $delta), 3);

            if (isset($outline[$index]['cx'], $outline[$index]['cy'])) {
                $outline[$index]['cy'] = round(((float) $outline[$index]['cy']) + ($delta * 0.6), 3);
                $outline[$index]['cx'] = round(((float) $outline[$index]['cx']) + ($slope * $delta * 0.6), 3);
            }
        }

        $piece['outline'] = $outline;

        return Geometry::normalizePiece($piece);
    }

    /**
     * شیبِ افقیِ درزی که به این نقطه می‌رسد (تغییرِ x به ازای هر واحد y).
     *
     * روی خطِ مرکز صفر است (مرکز عمودی است و باید عمودی بماند)، و روی درزِ پهلوی
     * یک دامنِ کلوش مثبت — پس لبه که پایین می‌رود، پهلو هم به بیرون می‌رود و
     * مخروط ادامه پیدا می‌کند.
     *
     * @param  array<int, array<string, mixed>>  $outline
     */
    protected function sideSlopeAt(array $outline, int $index, int $count): float
    {
        $here = $outline[$index];
        $best = 0.0;

        foreach ([($index - 1 + $count) % $count, ($index + 1) % $count] as $other) {
            $dy = ((float) $here['y']) - ((float) $outline[$other]['y']);

            // فقط همسایه‌ای که *بالای* این نقطه است، درزِ پهلوست
            if ($dy < 1.0) {
                continue;
            }

            $dx = ((float) $here['x']) - ((float) $outline[$other]['x']);
            $slope = $dx / $dy;

            if (abs($slope) > abs($best)) {
                $best = $slope;
            }
        }

        // شیبِ بیش از این یعنی لبهٔ افقی را درزِ پهلو گرفته‌ایم؛ دست نگه می‌داریم
        return max(-1.2, min(1.2, $best));
    }
}
