<?php

namespace App\Services\Pattern\Style\Seam;

use App\Services\Pattern\Geometry;
use App\Services\Pattern\Style\Detail\DetailStyle;
use App\Services\Pattern\Transform\StyleLineCutter;
use App\Support\Format;

/**
 * پایه مشترک سبک‌های «برش»: درزی که در بلوک پایه نیست و مدل می‌خواهدش.
 *
 * تا پیش از این گروه، هیچ سبکی در سامانه درز تازه نمی‌ساخت. سبک‌ها یا شکل لبه را
 * عوض می‌کردند (یقه، لبه)، یا پارچه اضافه می‌کردند (چین)، یا قطعه‌ای رویش
 * می‌گذاشتند (جیب). ولی امضای یک برند معمولاً همان درزی است که کس دیگری ندارد:
 * یوک پشت، پنل کناری شلوار، کالربلاک، برش مورب.
 *
 * موتور کار `StyleLineCutter` است که از اول در سامانه بود ولی فقط از داخل کد
 * صدا زده می‌شد. این گروه همان موتور را با پارامترهای ساده به دست کاربر می‌دهد.
 *
 * سه قاعده مشترک این گروه:
 *
 *   ۱. قطعهٔ اصلی «هویت» خودش را نگه می‌دارد (meta.part عوض نمی‌شود) و قطعهٔ تازه
 *      نامی مشتق می‌گیرد (back_bodice_yoke). وگرنه بقیه سامانه که با meta.part
 *      کار می‌کند — جیب، حلقه آستین، جورکردن کمر — گیج می‌شود.
 *   ۲. روی هر درز تازه نشانهٔ جفت گذاشته می‌شود؛ خودِ برنده این کار را می‌کند.
 *   ۳. هر برش، جای دوخت لبه تازه را هم می‌گذارد و در یادداشت می‌گوید چقدر درز و
 *      چقدر پارچه اضافه شد، چون هر درز تازه یعنی دو جای دوخت تازه.
 */
abstract class SeamStyle extends DetailStyle
{
    /** قطعه‌هایی که می‌شود بریدشان. */
    public const HOST_PARTS = [
        'front_bodice', 'back_bodice', 'skirt_front', 'skirt_back', 'front_leg', 'back_leg',
    ];

    /** جای برش ⇒ قطعه‌های میزبان. */
    public const WHERE_PARTS = [
        'front' => ['front_bodice', 'skirt_front', 'front_leg'],
        'back' => ['back_bodice', 'skirt_back', 'back_leg'],
        'both' => self::HOST_PARTS,
    ];

    public static function group(): string
    {
        return 'seam';
    }

    /* ---------------------------------------------------------------------
     |  پارامتر مشترک
     * ------------------------------------------------------------------- */

    /** پارامتر «کجا»؛ هر سبک برش پیش‌فرض خودش را می‌دهد. */
    protected function whereParam(string $default = 'back'): array
    {
        return [
            'label' => 'روی کدام قطعه',
            'type' => 'select',
            'default' => $default,
            'options' => ['back' => 'پشت', 'front' => 'جلو', 'both' => 'جلو و پشت'],
        ];
    }

    protected function where(array $context): string
    {
        $value = $this->text($context, 'where', 'back');

        return isset(static::WHERE_PARTS[$value]) ? $value : 'back';
    }

    /* ---------------------------------------------------------------------
     |  میزبان
     * ------------------------------------------------------------------- */

    /**
     * شماره قطعه‌هایی که این برش رویشان می‌نشیند.
     *
     * @return array<int, int>
     */
    protected function hostIndexes(array $pieces, array $context): array
    {
        $parts = static::WHERE_PARTS[$this->where($context)];
        $out = [];

        foreach ($pieces as $index => $piece) {
            if (! in_array($this->partOf($piece), $parts, true)) {
                continue;
            }

            // قطعه‌ای که خودِ همین سبک ساخته دوباره بریده نمی‌شود
            if (($piece['meta']['seam_style'] ?? null) === static::key()) {
                continue;
            }

            if (count($piece['outline'] ?? []) >= 3) {
                $out[] = (int) $index;
            }
        }

        return $out;
    }

    protected function noHostMessage(array $context): string
    {
        return match ($this->where($context)) {
            'front' => 'این لباس قطعهٔ جلویی برای بریدن ندارد.',
            'back' => 'این لباس قطعهٔ پشتی برای بریدن ندارد.',
            default => 'این لباس قطعهٔ تنه یا پایین‌تنه‌ای برای بریدن ندارد.',
        };
    }

    /** آیا این برش پیش‌تر روی این مجموعه اجرا شده؟ */
    protected function alreadyCut(array $pieces): bool
    {
        foreach ($pieces as $piece) {
            if (($piece['meta']['seam_style'] ?? null) === static::key()) {
                return true;
            }
        }

        return false;
    }

    /* ---------------------------------------------------------------------
     |  اجرای برش
     * ------------------------------------------------------------------- */

    /**
     * بریدن یک میزبان و نشاندن دو نیمه در جای خودش.
     *
     * $keep می‌گوید کدام نیمه «همان قطعهٔ قبلی» است و هویتش را نگه می‌دارد؛ نیمهٔ
     * دیگر قطعهٔ تازه است و نام مشتق می‌گیرد.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @param  array<int, array<string, mixed>>  $halves  خروجی برنده
     * @return array{0: array<int, array<string, mixed>>, 1: float} قطعه‌ها و طول درز تازه
     */
    protected function placeHalves(
        array $pieces,
        int $index,
        array $halves,
        int $keep,
        string $suffix,
        string $newName,
    ): array {
        $host = $pieces[$index];
        $new = $keep === 0 ? 1 : 0;

        $primary = $halves[$keep];
        $secondary = $halves[$new];

        $primary['meta']['seam_style'] = static::key();
        $primary['meta']['part'] = $this->partOf($host);
        $primary['sort'] = $host['sort'] ?? 0;

        $secondary['meta']['seam_style'] = static::key();
        $secondary['meta']['part'] = trim((string) $this->partOf($host).'_'.$suffix, '_');
        $secondary['name'] = $newName;
        $secondary['code'] = ($host['code'] ?? 'piece').'-'.$suffix;
        $secondary['sort'] = ($host['sort'] ?? 0) + 1;

        // درز تازه هم مثل هر درز دیگری جای دوخت می‌خواهد
        $seam = 0.0;

        foreach ([&$primary, &$secondary] as &$half) {
            foreach ($half['meta']['cut_edges'] ?? [] as $edge) {
                $seam = max($seam, Geometry::edgeLength($half['outline'], (int) $edge));
            }
        }

        unset($half);

        array_splice($pieces, $index, 1, [$primary, $secondary]);

        return [array_values($pieces), round($seam, 2)];
    }

    /**
     * بریدن روی یک خط عمودی در فاصلهٔ نسبی از لبهٔ چپ قطعه.
     *
     * دو سرِ خط لازم نیست دقیقاً روی محیط باشند؛ برنده آن‌ها را به نزدیک‌ترین جای
     * محیط می‌چسباند. برای همین یک خط از بالای کادر تا پایین کادر کافی است.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function cutVertical(array $piece, float $x, array $options = [], float $bow = 0.0): array
    {
        [$minX, $minY, $maxX, $maxY] = Geometry::bounds($piece['outline']);
        $x = max($minX + 0.5, min($maxX - 0.5, $x));

        $path = [['x' => $x, 'y' => $minY - 1]];

        if (abs($bow) > 0.05) {
            $path[] = [
                'x' => $x, 'y' => ($minY + $maxY) / 2,
                'curve' => true, 'cx' => $x + $bow, 'cy' => ($minY + $maxY) / 2,
            ];
        }

        $path[] = ['x' => $x, 'y' => $maxY + 1];

        return StyleLineCutter::cut($piece, $path, $options);
    }

    /* ---------------------------------------------------------------------
     |  گزارش
     * ------------------------------------------------------------------- */

    /** هر درز تازه یعنی دو جای دوخت تازه روی پارچه. */
    protected function seamNote(float $length, int $count, float $allowance = 1.0): array
    {
        $fabric = $length * $count * 2 * $allowance;

        return $this->fabricNote($fabric, 'درزهای تازهٔ این برش (روی هم '.Format::cm($length * $count).' درز)');
    }
}
