<?php

namespace App\Services\Pattern\Style\Fullness;

use App\Services\Pattern\Geometry;
use App\Support\Format;

/**
 * های‌لو کردن دامنه.
 *
 * مرکز جلو بالا می‌آید و مرکز پشت پایین می‌رود، ولی سرِ پهلوی دم دست نمی‌خورد؛
 * برای همین طول درز پهلوی جلو و پشت بعد از این کار هم برابر می‌ماند و لازم نیست
 * دوباره راست‌سازی شود.
 *
 * برخلاف «لبه های‌لو» در گروه لبه، این سبک فقط روی پنل‌های پایین‌تنه اجرا می‌شود و
 * پارچه اضافه‌ای را که بلند شدن پشت می‌خواهد گزارش می‌کند.
 */
class HighLowSweep extends FullnessStyle
{
    public static function key(): string
    {
        return 'fullness_high_low';
    }

    public function label(): string
    {
        return 'های‌لو کردن دامنه';
    }

    public function description(): string
    {
        return 'جلو کوتاه و پشت بلند؛ نقطه پهلو ثابت می‌ماند تا درز پهلو نخورد.';
    }

    public function paramsSchema(): array
    {
        return [
            'front_rise' => [
                'label' => 'کوتاه شدن مرکز جلو', 'min' => 0, 'max' => 40, 'step' => 0.5, 'default' => 8,
                'unit' => 'سانتی‌متر',
            ],
            'back_drop' => [
                'label' => 'بلند شدن مرکز پشت', 'min' => 0, 'max' => 50, 'step' => 0.5, 'default' => 14,
                'unit' => 'سانتی‌متر',
            ],
            'curve' => [
                'label' => 'گودی منحنی دم', 'min' => 0, 'max' => 12, 'step' => 0.5, 'default' => 3,
                'unit' => 'سانتی‌متر',
            ],
        ];
    }

    public function supports(array $pieces, array $context): true|string
    {
        $hosts = $this->panelIndexes($pieces);

        if ($hosts === []) {
            return $this->noPanelMessage();
        }

        $sides = [];
        $rise = $this->num($context, 'front_rise', 8);

        foreach ($hosts as $index) {
            $sides[] = $pieces[$index]['meta']['side'] ?? null;
            $height = $this->heightOf($pieces[$index]);

            if ($height - $rise < 12) {
                return 'پنل «'.$pieces[$index]['name'].'» فقط '.Format::cm($height)
                    .' بلندی دارد و این‌قدر کوتاه شدن از آن چیزی باقی نمی‌گذارد.';
            }
        }

        if (! in_array('front', $sides, true) || ! in_array('back', $sides, true)) {
            return 'های‌لو کردن به پنل جلو و پشت با هم نیاز دارد؛ این لباس یکی از آن‌ها را ندارد.';
        }

        return true;
    }

    public function apply(array $pieces, array $context): array
    {
        $rise = max(0.0, $this->num($context, 'front_rise', 8));
        $drop = max(0.0, $this->num($context, 'back_drop', 14));
        $curve = $this->num($context, 'curve', 3);
        $hosts = $this->panelIndexes($pieces);

        foreach ($hosts as $index) {
            $piece = $pieces[$index];
            $isBack = ($piece['meta']['side'] ?? 'front') === 'back';
            $delta = $isBack ? $drop : -$rise;

            $piece = $this->shapeHem($piece, $delta, 0.0, $isBack ? $curve : -$curve);
            $piece['meta']['fullness_style'] = static::key();
            $piece['meta']['hem_delta'] = round($delta, 2);
            $pieces[$index] = $piece;
        }

        return $this->report($pieces, [
            $this->note('tip', 'مرکز جلو '.Format::cm($rise).' بالا آمد و مرکز پشت '
                .Format::cm($drop).' پایین رفت.'),
            $this->note('info', 'سرِ پهلوی دم تکان نخورد، پس درز پهلوی جلو و پشت هنوز هم‌اندازه‌اند.'),
        ], $drop + 2, [
            'front_rise' => round($rise, 2),
            'back_drop' => round($drop, 2),
        ]);
    }

    protected function heightOf(array $piece): float
    {
        return Geometry::height($piece['outline'] ?? []);
    }
}
