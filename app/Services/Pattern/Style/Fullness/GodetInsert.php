<?php

namespace App\Services\Pattern\Style\Fullness;

use App\Services\Pattern\Geometry;
use App\Support\Format;

/**
 * گودت.
 *
 * قاچی از دایره که در چاک یک درز دوخته می‌شود و فقط دم لباس را باز می‌کند. شعاع
 * قاچ همان بلندی چاک است و زاویه‌اش از پهنای دم گودت درمی‌آید:
 *
 *   θ = پهنای گودت ÷ بلندی گودت
 *
 * پس طول کمان دم گودت دقیقاً همان پهنای خواسته‌شده است و دو لبه راست گودت دقیقاً
 * به اندازه چاک درمی‌آیند و بدون کش آمدن دوخته می‌شوند.
 *
 * خط کمر و خط باسن دست نمی‌خورند؛ همه پارچه اضافه پایین سر چاک است.
 */
class GodetInsert extends FullnessStyle
{
    public static function key(): string
    {
        return 'fullness_godet';
    }

    public function label(): string
    {
        return 'گودت';
    }

    public function description(): string
    {
        return 'قاچ دایره‌ای که در چاک درز دوخته می‌شود و فقط دم لباس را موج‌دار می‌کند.';
    }

    public function paramsSchema(): array
    {
        return [
            'count' => [
                'label' => 'تعداد گودت', 'min' => 1, 'max' => 12, 'step' => 1, 'default' => 2,
                'hint' => 'هر گودت در یک درز می‌نشیند؛ درز پهلو دو تاست.',
            ],
            'height' => [
                'label' => 'بلندی گودت (چاک درز)', 'min' => 8, 'max' => 70, 'step' => 1, 'default' => 30,
                'unit' => 'سانتی‌متر',
            ],
            'width' => [
                'label' => 'پهنای دم هر گودت', 'min' => 6, 'max' => 60, 'step' => 1, 'default' => 20,
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

        $height = $this->num($context, 'height', 30);

        foreach ($hosts as $index) {
            $piece = $pieces[$index];
            $edges = $this->sideEdges($piece);

            if ($edges === []) {
                return 'پنل «'.$piece['name'].'» درز پهلو ندارد و چاک گودت جایی برای باز شدن ندارد.';
            }

            $seam = 0.0;

            foreach ($edges as $edge) {
                $seam += $this->edgeLength($piece, $edge);
            }

            if ($seam < $height + 8) {
                return 'درز پهلوی «'.$piece['name'].'» فقط '.Format::cm($seam)
                    .' است و گودت '.Format::cm($height).'ی در آن جا نمی‌شود.';
            }
        }

        return true;
    }

    public function apply(array $pieces, array $context): array
    {
        $count = max(1, (int) $this->num($context, 'count', 2));
        $height = max(4.0, $this->num($context, 'height', 30));
        $width = max(3.0, $this->num($context, 'width', 20));
        $hosts = $this->panelIndexes($pieces);

        $before = $this->rawGirth($pieces, 'hem', $hosts);

        foreach ($hosts as $index) {
            $piece = $pieces[$index];
            $edge = $this->edgeWithTag($piece, 'hem');
            $sides = $this->sideEdges($piece);
            $last = $sides[count($sides) - 1];
            $bottom = Geometry::height($piece['outline']);
            $topY = max(1.0, $bottom - $height);

            // سر چاک روی درز پهلو، در فاصله «بلندی گودت» از دم
            $spot = Geometry::pointOnEdge($piece['outline'], $last, max(0.0, min(1.0, 1 - ($height / max(0.1, $this->edgeLength($piece, $last))))));

            $piece['notches'][] = $this->notch($spot['x'], $spot['y'], $last, 'سر چاک گودت', 'godet');
            $piece['markers'][] = $this->marker('godet', 'چاک گودت', $spot['x'], $spot['y'], $spot['x'], $bottom);
            $piece['meta']['fullness_style'] = static::key();
            $piece['meta']['godet_slit'] = round($height, 2);
            $piece['meta']['godet_top_y'] = round($topY, 2);

            $pieces[$index] = $this->reindexAnchors($piece);
        }

        $pieces[] = $this->godetPiece($height, $width, $count);
        $added = $count * $width;

        return $this->report($pieces, [
            $this->note('tip', $this->fa($count).' گودت به بلندی '.Format::cm($height).' و پهنای '
                .Format::cm($width).' ساخته شد؛ دور دم لباس از '.Format::cm($before).' به '
                .Format::cm($before + $added).' می‌رسد.'),
            $this->note('info', 'درز را تا سر چاک بدوزید، بعد گودت را از نوک شروع کنید؛ نوک گودت را دو بار '
                .'کوک بزنید تا موقع برگرداندن پاره نشود.'),
        ], $added, [
            'count' => $count,
            'height' => round($height, 2),
            'width' => round($width, 2),
            'hem_before' => $before,
            'hem_after' => round($before + $added, 2),
        ]);
    }

    /**
     * قطعه گودت: قاچ دایره با نوک بالا.
     *
     * @return array<string, mixed>
     */
    protected function godetPiece(float $height, float $width, int $count): array
    {
        $sweep = min(M_PI, $width / $height);
        $arc = $this->arcPoints($height, -$sweep / 2, $sweep / 2);
        $outline = array_merge([Geometry::point(0, 0)], $arc);
        $edges = $this->arcEdgeCount($sweep);

        return $this->piece([
            'code' => 'godet',
            'name' => 'گودت',
            'cut_quantity' => $count,
            'outline' => $outline,
            'grainline' => $this->grainline(0, $height * 0.35, $height - 1),
            'notches' => [$this->notch(0, 0, 0, 'نوک گودت روی سر چاک', 'godet')],
            'meta' => [
                'part' => 'godet',
                'edges' => array_merge(['side'], array_fill(0, $edges, 'hem'), ['side']),
                'fold_edges' => [],
                'side_edges' => [0, $edges + 1],
                'hem_edges' => range(1, $edges),
                'godet_height' => round($height, 2),
                'godet_width' => round($width, 2),
                'fullness' => [],
                'notes' => [
                    'زاویه قاچ '.$this->fa(round(rad2deg($sweep), 1)).' درجه است: پهنای دم ÷ بلندی گودت.',
                ],
            ],
        ]);
    }
}
