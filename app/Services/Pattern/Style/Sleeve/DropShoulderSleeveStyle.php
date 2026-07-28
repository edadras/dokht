<?php

namespace App\Services\Pattern\Style\Sleeve;

use App\Services\Pattern\Geometry;
use App\Support\Format;

/**
 * آستین شانه‌افتاده.
 *
 * سرشانه در امتداد خودش به بیرون کشیده می‌شود و درز حلقه از روی استخوان شانه
 * پایین‌تر روی بازو می‌افتد. برای اینکه دست بسته نشود، زیر بغل هم دقیقاً به اندازه
 * خواسته‌شده پایین می‌رود و حلقه صاف‌تر و بلندتر می‌شود.
 *
 * چون حلقه بلندتر و بازتر شده، سر آستین باید کوتاه‌تر و پهن‌تر بشود؛ وگرنه آستین
 * روی بازو چروک می‌خورد. این سبک همین کار را می‌کند: ارتفاع سر آستین را کم می‌گیرد
 * و به‌جای آن پهنای آستین را آن‌قدر باز می‌کند که طول سرآستین به طول حلقه تازه
 * برسد. نتیجه، همان سرآستین «تخت» شانه‌افتاده است.
 */
class DropShoulderSleeveStyle extends SleeveBodiceStyle
{
    public static function key(): string
    {
        return 'sleeve_drop_shoulder';
    }

    public function label(): string
    {
        return 'آستین شانه‌افتاده';
    }

    public function description(): string
    {
        return 'سرشانه به بیرون کشیده و زیر بغل پایین می‌رود؛ سر آستین تخت‌تر و آستین پهن‌تر می‌شود.';
    }

    public function paramsSchema(): array
    {
        return [
            'shoulder_extension' => [
                'label' => 'کشیدن سرشانه به بیرون', 'min' => 1, 'max' => 14, 'step' => 0.5, 'default' => 4,
                'unit' => 'سانتی‌متر',
                'hint' => 'در امتداد خط سرشانه؛ همین اندازه درز حلقه روی بازو پایین می‌آید.',
            ],
            'armhole_drop' => [
                'label' => 'پایین‌آوردن زیر بغل', 'min' => 0, 'max' => 12, 'step' => 0.5, 'default' => 3,
                'unit' => 'سانتی‌متر',
                'hint' => 'نقطه زیر بغل دقیقاً همین اندازه پایین می‌رود تا دست بسته نشود.',
            ],
            'armhole_scoop' => [
                'label' => 'گودی حلقه تازه', 'min' => 0, 'max' => 4, 'step' => 0.25, 'default' => 1,
                'unit' => 'سانتی‌متر', 'hint' => 'شکم حلقه نسبت به خط راست؛ کمتر یعنی حلقه صاف‌تر.',
            ],
            'cap_softness' => [
                'label' => 'بلندی سر آستین', 'min' => 0.3, 'max' => 0.9, 'step' => 0.05, 'default' => 0.55,
                'hint' => 'نسبت به سر آستین حلقه‌ای؛ کمتر یعنی تخت‌تر، افتاده‌تر و پهن‌تر.',
            ],
            'cap_ease' => [
                'label' => 'آزادی سر آستین', 'min' => 0, 'max' => 5, 'step' => 0.25, 'default' => 1,
                'unit' => 'سانتی‌متر', 'hint' => 'همه این آزادی بالای نشانه‌ها، روی گردی سر آستین، جذب می‌شود.',
            ],
            'length_extra' => $this->lengthField(),
            'hem_ease' => [
                'label' => 'آزادی دم آستین', 'min' => 0, 'max' => 25, 'step' => 0.5, 'default' => 8, 'unit' => 'سانتی‌متر',
            ],
        ];
    }

    protected function supportsSleeve(array $pieces, array $context): true|string
    {
        $sides = [];

        foreach ($this->armholePieces($pieces) as $index) {
            $anchors = $this->bodiceAnchors($pieces[$index]);

            if ($anchors !== null) {
                $sides[$anchors['side']] = true;
            }
        }

        if (count($sides) < 2) {
            return 'شانه‌افتاده جلو و پشت را با هم لازم دارد، چون سر آستین باید با مجموع حلقه جلو و پشت '
                .'ساخته شود؛ در این الگو فقط یک طرف حلقه آستین دارد.';
        }

        return true;
    }

    /* ---------------------------------------------------------------------
     |  اجرا
     * ------------------------------------------------------------------- */

    public function apply(array $pieces, array $context): array
    {
        $p = $this->params($context);
        $cuts = [];
        $notes = [];

        foreach ($this->armholePieces($pieces) as $index) {
            $anchors = $this->bodiceAnchors($pieces[$index]);

            if ($anchors === null) {
                continue;
            }

            $cut = $this->widenShoulder($pieces[$index], $anchors, $p);
            $pieces[$index] = $cut['piece'];
            $cuts[$anchors['side']] = $cut + ['index' => $index];
        }

        $sleeve = $this->sleevePiece($cuts, $p, $context);
        $walk = [];

        // از زیر بغل تا نشانه، حلقه و سرآستین باید دقیقاً هم‌اندازه باشند؛ همه آزادی
        // سرآستین بالای نشانه‌ها می‌ماند و همان‌جا جذب می‌شود
        foreach ($cuts as $side => $cut) {
            $walk[$side] = round(abs($sleeve['meta']['notch_from_underarm'][$side] - $cut['notch_at']), 3);
        }

        foreach ($cuts as $side => $cut) {
            $label = $side === 'front' ? 'جلو' : 'پشت';
            $notes[] = 'سرشانه '.$label.' '.Format::cm($cut['extension'], 1).' به بیرون کشیده شد و زیر بغل '
                .'دقیقاً '.Format::cm($cut['drop'], 1).' پایین رفت؛ حلقه از '.Format::cm($cut['before'], 1)
                .' به '.Format::cm($cut['after'], 1).' رسید و سرشانه '.Format::cm($cut['shoulder'], 1).' شد.';
        }

        $notes[] = 'سر آستین '.Format::cm($sleeve['meta']['cap_height'], 1).' بلندی و '
            .Format::cm($sleeve['meta']['bicep_width'], 1).' پهنا دارد (نسبت بلندی به پهنا '
            .Format::percent($sleeve['meta']['cap_ratio'] * 100).'). سر آستین حلقه‌ای همین اندازه‌ها با '
            .'حلقه اولیه '.Format::percent($sleeve['meta']['set_in_cap_ratio'] * 100).' می‌شد، پس این سر '
            .'آستین تخت‌تر است — همان چیزی که شانه‌افتاده می‌خواهد.';

        $notes[] = 'طول سرآستین '.Format::cm($sleeve['meta']['cap_length'], 1).' و طول حلقه تازه '
            .Format::cm($sleeve['meta']['target_armhole'], 1).' است؛ اختلاف '
            .Format::cm($sleeve['meta']['cap_ease'], 1).' آزادی سر آستین است که بالای نشانه‌ها جذب می‌شود.';

        $excess = $sleeve['meta']['cap_ease'] - (float) $p['cap_ease'];

        if ($excess > 0.15) {
            $notes[] = 'حلقه این بالاتنه از پهنای لازم آستین (دور بازو به‌علاوه آزادی، '
                .Format::cm($sleeve['meta']['bicep_width'], 1).') کوتاه‌تر است، پس سرآستین ناچار '
                .Format::cm($excess, 1).' بیشتر از آزادی خواسته‌شده درآمد. برای جورشدن دقیق، زیر بغل را '
                .'حدود '.Format::cm($excess / 2, 1).' بیشتر پایین بیاورید یا آزادی بازو را کم کنید.';
        }

        $notes[] = max($walk) <= 0.1
            ? 'حلقه جلو و پشت با سرآستین پیاده شد و از نشانه تا زیر بغل هر دو طرف با اختلاف کمتر از '
                .Format::cm(0.1, 1).' جور درآمد.'
            : 'در پیاده‌کردن حلقه با سرآستین بیشترین اختلاف '.Format::cm(max($walk), 2).' ماند.';

        return [
            'pieces' => array_merge(array_values($pieces), [$sleeve]),
            'notes' => $notes,
            'meta' => [
                'sleeve' => [
                    'style' => static::key(),
                    'shoulder_extension' => $cuts['front']['extension'],
                    'armhole_drop' => $cuts['front']['drop'],
                    'cap_height' => $sleeve['meta']['cap_height'],
                    'cap_ratio' => $sleeve['meta']['cap_ratio'],
                    'set_in_cap_ratio' => $sleeve['meta']['set_in_cap_ratio'],
                    'bicep_width' => $sleeve['meta']['bicep_width'],
                    'armhole' => $sleeve['meta']['target_armhole'],
                    'notch_walk' => $walk,
                ],
            ],
        ];
    }

    /* ---------------------------------------------------------------------
     |  بالاتنه
     * ------------------------------------------------------------------- */

    /**
     * سرشانه را بیرون می‌کشد، زیر بغل را پایین می‌آورد و حلقه را دوباره می‌کشد.
     *
     * @return array<string, mixed>
     */
    protected function widenShoulder(array $piece, array $a, array $p): array
    {
        $outline = array_values($piece['outline']);
        $drop = (float) $p['armhole_drop'];
        $direction = $this->unit($this->vec($a['snp'], $a['tip']));

        // نوک سرشانه نباید از خط پهلو بیرون بزند، وگرنه حلقه به بیرون کج می‌شود
        $room = ($a['underarm']['x'] - $a['tip']['x'] - 1.0) / max(0.2, $direction['x']);
        $extension = max(0.5, min((float) $p['shoulder_extension'], $room));

        $tip = $this->move($a['tip'], $direction, $extension);
        $underarm = ['x' => $a['underarm']['x'], 'y' => $a['underarm']['y'] + $drop];

        // درز پهلو با زیر بغلِ پایین‌آمده جابه‌جا می‌شود تا منحنی‌اش نشکند
        $next = $outline[$a['side_edge'] + 1];

        if (isset($next['cx'], $next['cy']) && $drop > 0.01) {
            $next['cy'] = round(((float) $next['cy']) + ($drop * 0.5), 3);
        }

        $outline[$a['side_edge'] + 1] = $next;
        $piece['outline'] = $outline;

        $armhole = $this->curveTo($tip, $underarm, (float) $p['armhole_scoop'], $a['centroid']);
        $after = Geometry::edgeLength([Geometry::point($tip['x'], $tip['y']), $armhole], 0);

        // نوک سرشانه و همه لبه‌های حلقه با یک حلقه تازه جایگزین می‌شوند
        $piece = $this->replacePoints(
            $piece,
            $a['armhole_edge'],
            ($a['side_edge'] - $a['armhole_edge']) + 1,
            [Geometry::point($tip['x'], $tip['y']), $armhole],
            ['armhole', 'side'],
        );

        $side = $a['side'];
        $label = $side === 'front' ? 'جلو' : 'پشت';
        $armholeEdge = $a['armhole_edge'];

        $piece = $this->dropArmholeNotches($this->dropNotches($piece, ['shoulder']));
        $shoulderMid = Geometry::pointOnEdge($piece['outline'], $a['shoulder_edge'], 0.5);

        // نشانه دقیقاً در نیمه طول حلقه از زیر بغل، تا روی آستین هم همان‌جا بیفتد
        $mid = $this->alongEdge($piece['outline'], $armholeEdge, $after / 2, true);
        $piece['notches'][] = $this->notch($mid['x'], $mid['y'], $armholeEdge, 'نشانه حلقه '.$label, 'armhole_'.$side);
        $piece['notches'][] = $this->notch($shoulderMid['x'], $shoulderMid['y'], $a['shoulder_edge'], 'نشانه سرشانه', 'shoulder');
        $piece['markers'][] = $this->marker(
            'old_shoulder_point',
            'نوک سرشانه بلوک',
            (float) $a['tip']['x'],
            (float) $a['tip']['y'] - 1.5,
            (float) $a['tip']['x'],
            (float) $a['tip']['y'] + 1.5,
        );

        $piece['meta']['armhole_edge'] = $armholeEdge;
        $piece['meta']['armhole_length'] = round($after, 2);
        $piece['meta']['drop_shoulder'] = [
            'style' => static::key(),
            'extension' => round($extension, 2),
            'armhole_drop' => round($drop, 2),
            'shoulder_length' => round($a['shoulder_length'] + $extension, 2),
            'armhole_before' => $a['armhole_length'],
            'armhole_after' => round($after, 2),
        ];

        return [
            'piece' => Geometry::normalizePiece($piece),
            'side' => $side,
            'extension' => round($extension, 2),
            'drop' => round($drop, 2),
            'before' => $a['armhole_length'],
            'after' => round($after, 3),
            'shoulder' => round($a['shoulder_length'] + $extension, 2),
            'armhole_edge' => $armholeEdge,
            'notch_at' => round($after / 2, 3),
        ];
    }

    /* ---------------------------------------------------------------------
     |  آستین
     * ------------------------------------------------------------------- */

    /**
     * آستینی با سر تخت که طول سرآستینش به حلقه تازه می‌رسد.
     *
     * @return array<string, mixed>
     */
    protected function sleevePiece(array $cuts, array $p, array $context): array
    {
        $bicep = $this->measurement($context, 'bicep', 28.5);
        $wrist = $this->measurement($context, 'wrist', 16.5);
        $arm = $this->measurement($context, 'arm_length', 58.5);
        $ease = (float) ($context['ease']['bicep'] ?? 4);

        $armhole = $cuts['front']['after'] + $cuts['back']['after'];
        $target = $armhole + (float) $p['cap_ease'];

        // سر آستین حلقه‌ای، برای مقایسه: همان آستینی که این برنامه با حلقه دوخته
        // درفت می‌کند — پهنای بازو، و ارتفاعی که طول سرآستین را به دور حلقه بدن برساند
        $setInWidth = max($bicep * 0.9, $bicep + $ease);
        $setInHeight = $this->fitCapHeight($setInWidth, $this->measurement($context, 'armhole', 42) + (float) $p['cap_ease']);

        // سر آستین شانه‌افتاده: ارتفاع را کم می‌گیریم و پهنا را باز می‌کنیم تا طول جور شود
        $height = max(2.0, (float) $p['cap_softness'] * $setInHeight);
        $width = $this->fitCapWidth($height, $target, $setInWidth);

        // آستین باید به‌اندازه کافی پایین‌تر از خط بازو تمام شود وگرنه دم آستین به سرآستین می‌خورد
        $length = max($height + 9.0, ($arm - (float) $p['shoulder_extension']) + (float) $p['length_extra']);
        $hemHalf = max(4.0, min($width / 2 - 1.0, ($wrist + (float) $p['hem_ease']) / 2));
        $centre = $width / 2;

        $outline = $this->capOutline($width, $height);
        $outline[] = Geometry::curve(
            $centre + $hemHalf,
            $length,
            $width - (($width - ($centre + $hemHalf)) * 0.4),
            $height + (($length - $height) * 0.5),
        );
        $outline[] = Geometry::point($centre - $hemHalf, $length);

        $capLength = Geometry::edgesLength($outline, [0, 1, 2, 3]);

        $piece = $this->piece([
            'code' => 'drop-shoulder-sleeve',
            'name' => 'آستین شانه‌افتاده',
            'cut_quantity' => 2,
            'mirror' => true,
            'outline' => $outline,
            'markers' => [
                $this->marker('bicep', 'خط بازو', 0, $height, $width, $height),
                $this->marker('sleeve_centre', 'خط میانی آستین', $centre, 0, $centre, $length),
            ],
            'meta' => [
                'part' => 'sleeve',
                'edges' => ['armhole', 'armhole', 'armhole', 'armhole', 'side', 'hem', 'side'],
                'fold_edges' => [],
                'sleeve_style' => static::key(),
                'cap_height' => round($height, 2),
                'cap_length' => round($capLength, 2),
                'cap_ease' => round($capLength - ($target - (float) $p['cap_ease']), 2),
                'cap_ratio' => round($height / max(1.0, $width), 4),
                'set_in_cap_ratio' => round($setInHeight / max(1.0, $setInWidth), 4),
                'target_armhole' => round($target - (float) $p['cap_ease'], 2),
                'bicep_width' => round($width, 2),
                'hem_width' => round($hemHalf * 2, 2),
                'sleeve_length' => round($length, 2),
            ],
        ]);

        // نشانه‌ها دقیقاً به همان فاصله‌ای که روی بالاتنه از زیر بغل گرفته شد
        $front = $this->alongChain($piece['outline'], [0, 1], $cuts['front']['notch_at']);
        $back = $this->alongChain($piece['outline'], [3, 2], $cuts['back']['notch_at'], true);
        $top = Geometry::pointOnEdge($piece['outline'], 1, 1.0);

        $piece['notches'] = [
            $this->notch($front['x'], $front['y'], (int) $front['edge'], 'نشانه حلقه جلو', 'armhole_front'),
            $this->notch($back['x'], $back['y'], (int) $back['edge'], 'نشانه حلقه پشت', 'armhole_back'),
            $this->notch($top['x'], $top['y'], 1, 'نوک آستین (سرشانه)', 'shoulder'),
        ];

        $piece['grainline'] = $this->grainline($centre, $height * 0.4, $length - 3);
        $piece['meta']['notch_from_underarm'] = [
            'front' => round($front['walked'], 3),
            'back' => round($back['walked'], 3),
        ];

        return Geometry::normalizePiece($piece);
    }

    /**
     * سرآستین با چهار کمان: دو کمان جلو (گودتر) و دو کمان پشت (پرتر).
     *
     * همان شکلی است که آستین حلقه‌ای این برنامه دارد، تا مقایسه بلندی سر آستین
     * بین شانه‌افتاده و حلقه‌ای روی یک شکل انجام شود.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function capOutline(float $width, float $height): array
    {
        return [
            Geometry::point(0, $height),
            Geometry::curve($width * 0.25, $height * 0.42, $width * 0.05, $height * 0.80),
            Geometry::curve($width * 0.50, 0, $width * 0.34, $height * 0.06),
            Geometry::curve($width * 0.75, $height * 0.36, $width * 0.66, $height * 0.02),
            Geometry::curve($width, $height, $width * 0.95, $height * 0.74),
        ];
    }

    /** ارتفاع سرآستین را تنظیم می‌کند تا طولش به هدف برسد. */
    protected function fitCapHeight(float $width, float $target): float
    {
        $height = $width * 0.42;
        $low = $width * 0.20;
        $high = $width * 0.75;

        for ($i = 0; $i < 30; $i++) {
            $height = ($low + $high) / 2;

            if (Geometry::edgesLength($this->capOutline($width, $height), [0, 1, 2, 3]) < $target) {
                $low = $height;
            } else {
                $high = $height;
            }
        }

        return round(($low + $high) / 2, 3);
    }

    /** پهنای آستین را باز می‌کند تا با ارتفاع کم، طول سرآستین به هدف برسد. */
    protected function fitCapWidth(float $height, float $target, float $minimum): float
    {
        $low = $minimum;
        $high = max($minimum * 1.2, $target);

        for ($i = 0; $i < 40; $i++) {
            $width = ($low + $high) / 2;

            if (Geometry::edgesLength($this->capOutline($width, $height), [0, 1, 2, 3]) < $target) {
                $low = $width;
            } else {
                $high = $width;
            }
        }

        return round(($low + $high) / 2, 3);
    }
}
