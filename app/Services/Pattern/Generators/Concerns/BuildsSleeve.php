<?php

namespace App\Services\Pattern\Generators\Concerns;

use App\Services\Pattern\Geometry;

/** ساخت آستین یک‌تکه و مچ‌بند؛ پیراهن و کت هم از همین استفاده می‌کنند. */
trait BuildsSleeve
{
    /**
     * قطعه‌های آستین.
     *
     * @param  array<string, mixed>  $options  armhole_length، prefix، sleeve_name، cut
     * @return array<int, array<string, mixed>>
     */
    protected function sleevePieces(array $m, array $ease, array $params, array $options = []): array
    {
        $bicep = $this->m($m, 'bicep', 28.5);
        $wrist = $this->m($m, 'wrist', 16.5);
        $armLength = $this->m($m, 'arm_length', 58);

        $armholeLength = (float) ($options['armhole_length'] ?? 0);

        if ($armholeLength <= 0) {
            $armholeLength = (float) $this->param($params, 'armhole_length', 0);
        }

        if ($armholeLength <= 0) {
            $armholeLength = $this->m($m, 'armhole', 42) + max(0, $this->ease($ease, 'bust', 6) / 4);
        }

        $capEase = (float) $this->param($params, 'cap_ease', 1.5);
        $bicepEase = $this->ease($ease, 'bicep', 4);
        $width = max($bicep * 0.8, $bicep + $bicepEase);

        [$width, $capHeight] = $this->fitCap($width, $armholeLength + $capEase);

        $hasCuff = $this->flag($params, 'cuff', false) && ! ($options['no_cuff'] ?? false);
        $cuffHeight = (float) $this->param($params, 'cuff_height', 6);
        $length = isset($options['length'])
            ? max(8.0, (float) $options['length'])
            : max(12.0, $armLength + (float) $this->param($params, 'length_extra', 0));
        $bodyLength = $hasCuff ? max(12.0, $length - $cuffHeight) : $length;

        // بلندی آستین همیشه از بلندی سرآستین بیشتر بماند. اگر بلندی ثابتی خواسته
        // شده باشد (مثلاً آستین کوتاه ۲۲ سانتی) و بدن آن‌قدر درشت باشد که کپ از
        // همان ۲۲ بلندتر شود، دم آستین بالای زیربغل می‌افتد و مسیر قطعه خودش را
        // قطع می‌کند. دست‌کم چهار سانتی‌متر زیر خط بازو نگه می‌داریم.
        $bodyLength = max($bodyLength, $capHeight + 4.0);

        $hemEase = (float) $this->param($params, 'hem_ease', 6);
        $hemWidth = $bodyLength < 32
            ? max($width * 0.62, $width - 4)
            : max(min($width - 2, $wrist + $hemEase), $width * 0.4);

        $center = $width / 2;
        $hemHalf = $hemWidth / 2;

        $outline = $this->capOutline($width, $capHeight);
        $capPoints = count($outline);
        $edges = ['armhole', 'armhole', 'armhole', 'armhole'];

        // درز پهلوی پشت آستین، دم آستین و درز جلو
        $outline[] = Geometry::curve(
            $center + $hemHalf,
            $bodyLength,
            $width - (($width - ($center + $hemHalf)) * 0.4),
            $capHeight + (($bodyLength - $capHeight) * 0.5),
        );
        $edges[] = 'side';

        $outline[] = Geometry::point($center - $hemHalf, $bodyLength);
        $edges[] = 'hem';
        $edges[] = 'side';

        $capLength = Geometry::edgesLength($outline, [0, 1, 2, 3]);

        // نیمه چپ منحنی سرآستین (لبه‌های ۰ و ۱) جلوی آستین است و نیمه راست (لبه‌های
        // ۲ و ۳) پشت آن؛ پس رأس (۰، ارتفاع سرآستین) زیر بغل جلو و رأس آخرِ سرآستین
        // (پهنا، ارتفاع سرآستین) زیر بغل پشت است. هر رأس روی دو لبه می‌نشیند و
        // نشانه زیر بغل را روی درز پهلو می‌گذاریم، نه روی حلقه.
        $topNotch = Geometry::pointOnEdge($outline, 1, 1.0);
        $frontNotch = Geometry::pointOnEdge($outline, 1, 0.55);
        $backOne = Geometry::pointOnEdge($outline, 2, 0.40);
        $backTwo = Geometry::pointOnEdge($outline, 2, 0.52);

        $notches = [
            $this->notchAt($outline, $edges, $topNotch['x'], $topNotch['y'], 'نوک آستین (سرشانه)', 'sleeve_top', 'armhole'),
            $this->notchAt($outline, $edges, $frontNotch['x'], $frontNotch['y'], 'نشانه تکی جلو آستین', 'armhole_front', 'armhole'),
            $this->notchAt($outline, $edges, $backOne['x'], $backOne['y'], 'نشانه دوتایی پشت آستین (۱)', 'armhole_back', 'armhole'),
            $this->notchAt($outline, $edges, $backTwo['x'], $backTwo['y'], 'نشانه دوتایی پشت آستین (۲)', 'armhole_back', 'armhole'),
            $this->notchAt($outline, $edges, 0, $capHeight, 'زیر بغل جلو', 'underarm', 'side'),
            $this->notchAt($outline, $edges, (float) $outline[$capPoints - 1]['x'], $capHeight, 'زیر بغل پشت', 'underarm', 'side'),
        ];

        $sleeve = $this->piece([
            'code' => ($options['prefix'] ?? '').'sleeve',
            'name' => $options['sleeve_name'] ?? 'آستین',
            'cut_quantity' => (int) ($options['cut'] ?? 2),
            'mirror' => true,
            'outline' => $outline,
            'grainline' => $this->grainline($center, $capHeight * 0.35, $bodyLength - 3),
            'notches' => $notches,
            'markers' => [
                $this->marker('bicep', 'خط بازو', 0, $capHeight, $width),
                $this->marker('sleeve_center', 'خط میانی آستین', $center, 0, $center, $bodyLength),
                $this->marker('elbow', 'خط آرنج', $center - ($hemHalf + 1), $capHeight + (($bodyLength - $capHeight) * 0.55), $center + $hemHalf + 1, $capHeight + (($bodyLength - $capHeight) * 0.55)),
            ],
            'meta' => [
                'part' => 'sleeve',
                'edges' => $edges,
                'fold_edges' => [],
                'cap_height' => round($capHeight, 2),
                'cap_length' => $capLength,
                'target_armhole' => round($armholeLength, 2),
                'bicep_width' => round($width, 2),
                'hem_width' => round($hemWidth, 2),
                'sleeve_length' => round($bodyLength, 2),
                'notch_convention' => 'یک نشانه جلو، دو نشانه پشت',
            ],
        ]);

        $pieces = [$sleeve];

        if ($hasCuff) {
            $pieces[] = $this->cuffPiece($wrist, $cuffHeight, $options);
        }

        return $pieces;
    }

    /**
     * نشانه‌ای که شماره لبه‌اش از روی جای واقعی نقطه درمی‌آید، نه با شمردن دستی.
     *
     * یک رأس همیشه روی دو لبه است (لبه‌ای که به آن می‌رسد و لبه‌ای که از آن
     * می‌رود)؛ نشانه باید لبه‌ای را ادعا کند که هنگام دوخت روی آن پیاده می‌شود.
     * پس از میان لبه‌هایی که نقطه واقعاً رویشان می‌نشیند، آن‌که برچسب خواسته‌شده
     * را دارد انتخاب می‌شود و اگر چنین لبه‌ای نبود، نزدیک‌ترین لبه.
     *
     * @param  array<int, array<string, mixed>>  $outline
     * @param  array<int, string>  $edges
     * @return array<string, mixed>
     */
    protected function notchAt(
        array $outline,
        array $edges,
        float $x,
        float $y,
        string $label,
        ?string $pair = null,
        ?string $prefer = null,
    ): array {
        $at = ['x' => $x, 'y' => $y];
        $count = count($outline);
        $best = null;
        $bestDistance = INF;
        $chosen = null;

        for ($edge = 0; $edge < $count; $edge++) {
            $t = Geometry::edgeParameterOf($outline, $edge, $at, 32);
            $distance = Geometry::distance(Geometry::pointOnEdge($outline, $edge, $t), $at);

            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $best = $edge;
            }

            // «روی لبه» یعنی کمتر از یک میلی‌متر؛ همان دقتی که روی کاغذ معنا دارد
            if ($distance <= 0.1 && $chosen === null && ($prefer === null || ($edges[$edge] ?? null) === $prefer)) {
                $chosen = $edge;
            }
        }

        return $this->notch($x, $y, (int) ($chosen ?? $best ?? 0), $label, $pair);
    }

    /** مچ‌بند: نواری که دور مچ بسته می‌شود (دولا با خط تای وسط). */
    protected function cuffPiece(float $wrist, float $height, array $options = []): array
    {
        $width = $wrist + 5;
        $full = $height * 2;

        return $this->piece([
            'code' => ($options['prefix'] ?? '').'cuff',
            'name' => 'مچ آستین',
            'cut_quantity' => 2,
            'mirror' => false,
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point($width, 0),
                Geometry::point($width, $full),
                Geometry::point(0, $full),
            ],
            'grainline' => $this->grainline($width * 0.5, 1.5, $full - 1.5),
            'markers' => [
                $this->marker('fold', 'خط تای مچ‌بند', 0, $height, $width),
            ],
            'drills' => [[
                'key' => 'button_1',
                'label' => 'دکمه مچ',
                'x' => round($width - 2.0, 2),
                'y' => round($height / 2, 2),
            ]],
            'meta' => [
                'part' => 'cuff',
                'edges' => ['default', 'side', 'hem', 'side'],
                'fold_edges' => [],
                'interfacing' => true,
                // مچ‌بند دور مچ بسته می‌شود و پنج سانتی‌متر رویهم‌آمدن دارد؛ آن
                // رویهم‌آمدن با یک دکمه بسته می‌شود و چون قطعه دو بار بریده
                // می‌شود (یک مچ برای هر آستین)، دکمه هم دو تا می‌شود.
                'notions' => [[
                    'type' => 'button',
                    'label' => 'دکمه مچ آستین',
                    'count' => 1,
                    'per_cut' => true,
                ]],
            ],
        ]);
    }

    /**
     * منحنی سرآستین با چهار کمان درجه‌دو: دو کمان جلو (گودتر) و دو کمان پشت (پرتر).
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

    /**
     * پهنا و ارتفاع سرآستین را با هم تنظیم می‌کند تا طول سرآستین به هدف برسد.
     *
     * ارتفاع کپ سقف دارد (بیش از ۰٫۷ پهنا، آستین لوله‌ای و غیرقابل دوخت می‌شود)؛
     * پس روی بدنی با حلقه آستین بزرگ نسبت به دور بازو، فقط با بلندکردن کپ نمی‌شود
     * به طول حلقه رسید و باید خود آستین پهن‌تر شود. آستین پهن‌تر یعنی آزادی بیشتر
     * روی بازو، که درست است؛ آستین کوتاه‌تر از حلقه اصلاً دوخته نمی‌شود.
     *
     * @return array{0: float, 1: float}  پهنا و ارتفاع
     */
    protected function fitCap(float $width, float $target): array
    {
        $height = $this->fitCapHeight($width, $target);

        for ($i = 0; $i < 12; $i++) {
            $length = Geometry::edgesLength($this->capOutline($width, $height), [0, 1, 2, 3]);

            if ($length >= $target - 0.15) {
                break;
            }

            $width += ($target - $length) * 0.5;
            $height = $this->fitCapHeight($width, $target);
        }

        return [round($width, 2), $height];
    }

    /** ارتفاع کپ را تنظیم می‌کند تا طول سرآستین به اندازه هدف برسد. */
    protected function fitCapHeight(float $width, float $target): float
    {
        $height = $width * 0.42;
        $min = $width * 0.24;
        $max = $width * 0.70;

        for ($i = 0; $i < 14; $i++) {
            $outline = $this->capOutline($width, $height);
            $length = Geometry::edgesLength($outline, [0, 1, 2, 3]);
            $difference = $target - $length;

            if (abs($difference) < 0.15) {
                break;
            }

            $height = max($min, min($max, $height + ($difference * 0.9)));
        }

        return round($height, 2);
    }
}
