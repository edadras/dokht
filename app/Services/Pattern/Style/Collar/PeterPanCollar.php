<?php

namespace App\Services\Pattern\Style\Collar;

use App\Services\Pattern\Geometry;
use App\Support\Format;

/**
 * یقه پیتر‌پن (یقه خوابیده).
 *
 * یقه‌ای که پایه ندارد یا پایه‌اش خیلی کوتاه است و روی سرشانه می‌خوابد. روی
 * الگو دقیقاً وارونِ یقه آخوندی درفت می‌شود: لبه یقه روی کمان درونی می‌نشیند و
 * لبه بیرونی بلندتر درمی‌آید، پس یقه به بیرون باز می‌شود و می‌خوابد.
 *
 * «بلندی پایه» تنها چیزی است که یقه خوابیده را از یقه ایستاده جدا می‌کند:
 * گردن استوانه‌ای به شعاع r = دور یقه ÷ ۲π است؛ یقه‌ای که s سانت پایه دارد لبه
 * بیرونی‌اش روی شعاع r + (پهنا − s) می‌افتد، پس شعاع کمان روی الگو
 * پهنا × r ÷ (پهنا − s) می‌شود. با پایه صفر یقه کاملاً تخت است و با پایه برابر
 * پهنا به نوار راست می‌رسد.
 *
 * دو سرِ یقه گرد یا نوک‌تیز درمی‌آید؛ همان تفاوت پیتر‌پن کلاسیک و یقه بچگانه
 * نوک‌دار.
 */
class PeterPanCollar extends BaseCollar
{
    public static function key(): string
    {
        return 'collar_peter_pan';
    }

    public function label(): string
    {
        return 'یقه پیتر‌پن (خوابیده)';
    }

    public function description(): string
    {
        return 'یقه تخت روی سرشانه، با سر گرد یا نوک‌تیز؛ پایه کوتاه یا بدون پایه.';
    }

    public function paramsSchema(): array
    {
        return [
            'width' => [
                'label' => 'پهنای یقه', 'min' => 3, 'max' => 14, 'step' => 0.5, 'default' => 6,
                'unit' => 'سانتی‌متر', 'hint' => 'از خط یقه تا لبه بیرونی.',
            ],
            'stand' => [
                'label' => 'بلندی پایه', 'min' => 0, 'max' => 3, 'step' => 0.25, 'default' => 0.6,
                'unit' => 'سانتی‌متر', 'hint' => 'صفر یعنی کاملاً تخت؛ هرچه بیشتر، یقه بالاتر می‌ایستد و لبه بیرونی کوتاه‌تر می‌شود.',
            ],
            'shape' => [
                'label' => 'سر یقه', 'type' => 'select', 'default' => 'round',
                'options' => ['round' => 'گرد', 'pointed' => 'نوک‌تیز'],
            ],
            'point_length' => [
                'label' => 'بلندی نوک', 'min' => 0.5, 'max' => 8, 'step' => 0.5, 'default' => 2.5,
                'unit' => 'سانتی‌متر', 'hint' => 'فقط برای سر نوک‌تیز.',
            ],
            'gap' => [
                'label' => 'فاصله دو سر یقه در مرکز جلو', 'min' => 0, 'max' => 6, 'step' => 0.5, 'default' => 0,
                'unit' => 'سانتی‌متر', 'hint' => 'یقه به همین اندازه پیش از مرکز جلو تمام می‌شود.',
            ],
            'ease' => $this->easeField(),
            'interfacing' => $this->interfacingField(),
        ];
    }

    protected function draft(array $neck, array $p, array $pieces, array $context): array
    {
        $width = (float) $p['width'];
        $stand = min((float) $p['stand'], $width - 0.5);
        $gap = (float) $p['gap'];
        $target = max(5.0, $neck['half'] + (float) $p['ease'] - $gap);
        $radius = $this->standRadius($neck['full'], $width, $stand);

        [$piece, $length, $difference] = $this->fitToNeckline(
            fn (float $span) => $this->collar($span, $width, $radius, $stand, $p),
            $target,
        );

        $piece = $this->halfCollarNotches($piece, $neck, $target);
        $outer = $this->seamOf($piece, 'hem');
        $made = [$piece];
        $notes = [];

        if (! empty($p['interfacing'])) {
            $made[] = $this->collarInterfacing($piece, 'لایه چسب یقه پیتر‌پن');
        }

        $notes[] = 'لبه بیرونی یقه '.Format::cm($outer).' درآمد، یعنی '.Format::cm(max(0, $outer - $length))
            .' بلندتر از لبه یقه؛ همین اضافه است که یقه را می‌خواباند.';
        $notes[] = $stand < 0.05
            ? 'پایه صفر گرفته شد، پس یقه کاملاً تخت می‌خوابد و درز خط یقه از رو دیده می‌شود؛ برای پنهان شدن درز دست‌کم نیم سانت پایه بگذارید.'
            : 'پایه '.Format::cm($stand).' گرفته شد؛ یقه همین اندازه بالا می‌ایستد و درز خط یقه را می‌پوشاند.';

        if ($gap > 0) {
            $notes[] = 'دو سر یقه '.Format::cm($gap).' پیش از مرکز جلو تمام می‌شود، پس روی هم نمی‌افتند.';
        }

        return [
            'pieces' => $made,
            'notes' => $notes,
            'meta' => [
                'target' => round($target, 2),
                'measured' => $length,
                'ease' => round((float) $p['ease'], 2),
                'difference' => $difference,
                'outer_edge' => round($outer, 2),
                'spread' => round($outer - $length, 2),
                'stand' => round($stand, 2),
                'gap' => $gap,
            ],
        ];
    }

    /**
     * درفت نیم‌یقه خوابیده.
     *
     * @return array<string, mixed>
     */
    protected function collar(float $span, float $width, float $radius, float $stand, array $p): array
    {
        $arc = $this->collarArc($span, $width, $radius, 'fall');
        $front = $this->frontEnd($arc, (string) $p['shape'], (float) $p['point_length']);
        $shell = $this->assembleCollar($arc, $front['points'], 'hem', $front['tags']);

        $markers = [
            $this->marker(
                'cb',
                'خط مرکز پشت',
                $arc['cb_neck']['x'],
                $arc['cb_neck']['y'],
                $arc['cb_outer']['x'],
                $arc['cb_outer']['y'],
            ),
        ];

        if ($stand > 0.05) {
            $markers[] = $this->rollLineMarker($arc, $stand, 'خط خواب یقه');
        }

        return $this->newPiece([
            'code' => 'collar-peter-pan',
            'name' => 'یقه پیتر‌پن',
            'cut_quantity' => 2,
            'on_fold' => true,
            'outline' => $shell['outline'],
            'grainline' => $this->collarGrainline($arc),
            'markers' => $markers,
            'meta' => [
                'part' => 'collar',
                'edges' => $shell['edges'],
                'fold_edges' => [count($shell['edges']) - 1],
                'interfacing' => true,
                'girth_role' => 'trim',
                'collar_kind' => 'flat',
                'stand_height' => round($stand, 2),
                'roll_line' => $stand > 0.05 ? round($stand, 2) : null,
                'radius' => $arc['radius'],
            ],
        ]);
    }

    /**
     * سر جلوی یقه: گرد یا نوک‌تیز.
     *
     * @return array{points: array<int, array<string, mixed>>, tags: array<int, string>}
     */
    protected function frontEnd(array $arc, string $shape, float $point): array
    {
        $neck = $this->pt($arc['cf_neck']);
        $outer = $this->pt($arc['cf_outer']);
        $tangent = $this->unit($arc['cf_tangent']);
        $across = $this->unit($this->vec($neck, $outer));
        $width = max(0.5, $this->length($this->vec($neck, $outer)));

        if ($shape === 'pointed') {
            $tip = $this->add($this->add($outer, $tangent, $point), $across, $point * 0.35);

            return [
                'points' => [
                    Geometry::point($tip['x'], $tip['y']),
                    Geometry::point($outer['x'], $outer['y']),
                ],
                'tags' => ['side', 'hem'],
            ];
        }

        // سر گرد: یک منحنی از لبه یقه تا لبه بیرونی، با نقطه کنترل روی گوشه فرضی
        $corner = $this->add($neck, $tangent, $width * 0.78);

        return [
            'points' => [Geometry::curve($outer['x'], $outer['y'], $corner['x'], $corner['y'])],
            'tags' => ['side'],
        ];
    }
}
