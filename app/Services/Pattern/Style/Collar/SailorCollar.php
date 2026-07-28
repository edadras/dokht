<?php

namespace App\Services\Pattern\Style\Collar;

use App\Services\Pattern\Geometry;
use App\Support\Format;

/**
 * یقه ملوانی.
 *
 * یقه‌ای کاملاً خوابیده که پشتش یک بادگیر چهارگوش روی کتف می‌افتد و جلویش با دو
 * نوار باریک تا هفت سینه می‌آید. از نظر الگو همان یقه پیتر‌پن است ولی بسیار
 * پهن‌تر، پس کمانش تندتر می‌شود و لبه بیرونی خیلی بلندتر از لبه یقه درمی‌آید.
 *
 * لبه بیرونی به جای کمان، با دو خط راست و یک گوشه روی سرشانه بسته می‌شود تا
 * بادگیر پشت چهارگوش دربیاید؛ همان کاری که خیاط با خط‌کش روی کاغذ می‌کند.
 *
 * چون یقه پهن است، خط یقه لباس باید باز باشد (هفت یا چهارگوش)؛ روی یقه تنگ،
 * ملوانی روی گردن جمع می‌شود و نمی‌خوابد. این هشدار در یادداشت‌ها می‌آید.
 */
class SailorCollar extends BaseCollar
{
    public static function key(): string
    {
        return 'collar_sailor';
    }

    public function label(): string
    {
        return 'یقه ملوانی';
    }

    public function description(): string
    {
        return 'یقه خوابیده با بادگیر چهارگوش پشت و دو نوار باریک جلو؛ یقه لباس ملوانی و مدرسه‌ای.';
    }

    public function paramsSchema(): array
    {
        return [
            'back_depth' => [
                'label' => 'بلندی بادگیر پشت', 'min' => 8, 'max' => 30, 'step' => 0.5, 'default' => 17,
                'unit' => 'سانتی‌متر', 'hint' => 'از خط یقه پشت تا لبه پایین بادگیر.',
            ],
            'front_width' => [
                'label' => 'پهنای نوار جلو', 'min' => 3, 'max' => 14, 'step' => 0.5, 'default' => 7,
                'unit' => 'سانتی‌متر', 'hint' => 'سر جلوی یقه باریک‌تر از بادگیر پشت است.',
            ],
            'stand' => [
                'label' => 'بلندی پایه', 'min' => 0, 'max' => 2, 'step' => 0.25, 'default' => 0.5,
                'unit' => 'سانتی‌متر', 'hint' => 'کم بگیرید؛ یقه ملوانی باید بخوابد.',
            ],
            'ease' => $this->easeField(),
            'interfacing' => $this->interfacingField(false),
        ];
    }

    protected function draft(array $neck, array $p, array $pieces, array $context): array
    {
        $depth = (float) $p['back_depth'];
        $front = min($depth - 1.0, (float) $p['front_width']);
        $stand = min((float) $p['stand'], $depth - 0.5);
        $target = max(5.0, $neck['half'] + (float) $p['ease']);
        $radius = $this->standRadius($neck['full'], $depth, $stand);

        [$piece, $length, $difference] = $this->fitToNeckline(
            fn (float $span) => $this->collar($span, $depth, $front, $radius, $neck),
            $target,
        );

        $piece = $this->halfCollarNotches($piece, $neck, $target);
        $outer = $this->seamOf($piece, 'hem');
        $made = [$piece];
        $notes = [];

        if (! empty($p['interfacing'])) {
            $made[] = $this->collarInterfacing($piece, 'لایه چسب یقه ملوانی');
        }

        $notes[] = 'لبه بیرونی '.Format::cm($outer).' درآمد در برابر لبه یقه '.Format::cm($length)
            .'؛ این اضافه بزرگ همان چیزی است که بادگیر را روی کتف می‌خواباند.';
        $notes[] = 'بادگیر پشت '.Format::cm($depth).' و نوار جلو '.Format::cm($front)
            .' پهنا دارد؛ گوشه لبه بیرونی روی درز سرشانه می‌افتد.';
        $notes[] = 'یقه ملوانی روی خط یقه باز (هفت یا چهارگوش) درست می‌نشیند؛ روی خط یقه تنگ، لبه بیرونی جا نمی‌شود و یقه روی گردن باد می‌کند.';
        $notes[] = 'یقه دولا (رو و آستر) بریده می‌شود؛ لایه چسب لازم نیست، چون یقه باید نرم بخوابد.';

        return [
            'pieces' => $made,
            'notes' => $notes,
            'meta' => [
                'target' => round($target, 2),
                'measured' => $length,
                'ease' => round((float) $p['ease'], 2),
                'difference' => $difference,
                'back_depth' => $depth,
                'front_width' => round($front, 2),
                'outer_edge' => round($outer, 2),
            ],
        ];
    }

    /**
     * درفت نیم‌یقه ملوانی: کمان لبه یقه، سر جلوی باریک و لبه بیرونی خط‌کشی‌شده.
     *
     * @return array<string, mixed>
     */
    protected function collar(float $span, float $depth, float $front, float $radius, array $neck): array
    {
        $arc = $this->collarArc($span, $depth, $radius, 'fall');
        $cfNeck = $this->pt($arc['cf_neck']);
        $cfOuter = $this->pt($arc['cf_outer']);
        $across = $this->unit($this->vec($cfNeck, $cfOuter));
        $tip = $this->add($cfNeck, $across, $front);

        // گوشه بادگیر روی سرشانه: نقطه‌ای روی کمان بیرونی، هم‌راستای درز سرشانه
        $ratio = $span > 0.01 ? max(0.1, min(0.9, $neck['back'] / $span)) : 0.5;
        $angle = 90.0 + ((float) $arc['span'] * (1 - $ratio));
        $corner = $this->arcStart(['x' => 0.0, 'y' => 0.0], (float) $arc['outer_radius'], $angle);

        $outline = array_merge(
            [Geometry::point($arc['cb_neck']['x'], $arc['cb_neck']['y'])],
            $arc['neck'],
            [
                Geometry::point($tip['x'], $tip['y']),
                Geometry::point($corner['x'], $corner['y']),
                Geometry::point($arc['cb_outer']['x'], $arc['cb_outer']['y']),
            ],
        );

        $edges = array_merge(
            array_fill(0, count($arc['neck']), 'neck'),
            ['side', 'hem', 'hem', 'default'],
        );

        return $this->newPiece([
            'code' => 'collar-sailor',
            'name' => 'یقه ملوانی',
            'cut_quantity' => 2,
            'on_fold' => true,
            'outline' => $outline,
            'grainline' => $this->collarGrainline($arc),
            'markers' => [
                $this->marker('cb', 'خط مرکز پشت', $arc['cb_neck']['x'], $arc['cb_neck']['y'], $arc['cb_outer']['x'], $arc['cb_outer']['y']),
                $this->marker('shoulder_corner', 'گوشه بادگیر روی سرشانه', $corner['x'], $corner['y'], $corner['x'], $corner['y'] - 1.5),
            ],
            'meta' => [
                'part' => 'collar',
                'edges' => $edges,
                'fold_edges' => [count($edges) - 1],
                'interfacing' => false,
                'girth_role' => 'trim',
                'collar_kind' => 'flat',
                'back_depth' => round($depth, 2),
                'radius' => $arc['radius'],
            ],
        ]);
    }
}
