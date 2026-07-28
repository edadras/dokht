<?php

namespace App\Services\Pattern\Style\Detail;

use App\Services\Pattern\Geometry;
use App\Support\Format;

/**
 * پایه مشترک مچ‌ها (سر آستین).
 *
 * قانون همیشگی: مچ به اندازه دم آستینِ «تمام‌شده» بریده می‌شود، نه دم آستین تخت.
 * اگر روی دم آستین چین یا پیلی ثبت شده باشد، همان مقدار از طول کم می‌شود؛ و اگر
 * دم آستین گشادتر از مچ باشد، خود این سبک پیلی یا چین لازم را ثبت می‌کند تا اندازه‌ها
 * جور دربیایند.
 */
abstract class BaseCuff extends DetailStyle
{
    public static function group(): string
    {
        return 'detail';
    }

    /** بیشترین اختلافی که با پیلی جمع می‌شود؛ بیشتر از این چین می‌خورد. */
    public const PLEAT_LIMIT = 8.0;

    public function supports(array $pieces, array $context): true|string
    {
        if ($this->cuffHosts($pieces) === []) {
            return 'مچ بدون آستین ساخته نمی‌شود؛ اول یک آستین انتخاب کنید.';
        }

        return true;
    }

    /** آستین‌هایی که دم دارند. */
    protected function cuffHosts(array $pieces): array
    {
        return $this->indexesWithTag($pieces, 'hem', ['sleeve']);
    }

    /** دور مچ تمام‌شده که دست از آن رد شود. */
    protected function wristTarget(array $context, float $ease = 4.0): float
    {
        $wrist = $this->measurement($context, 'wrist', 16.5);

        return round($wrist + $ease, 2);
    }

    /**
     * جور کردن دم آستین با اندازه مچ.
     *
     * @return array{piece: array<string, mixed>, finished: float, taken: float, method: string}
     */
    protected function fitSleeveHem(array $piece, int $edge, float $target, string $method = 'pleat'): array
    {
        $flat = $this->edgeLength($piece, $edge);
        $already = $this->consumedOn($piece, $edge);
        $finished = round($flat - $already, 2);
        $taken = 0.0;
        $used = 'none';

        if ($already > 0.05) {
            // آستین از قبل چین یا پیلی خورده است؛ همان تمام‌شده ملاک است
            return ['piece' => $piece, 'finished' => $finished, 'taken' => 0.0, 'method' => 'existing'];
        }

        $difference = round($finished - $target, 2);

        if ($difference > 0.6) {
            $taken = $difference;
            $used = $method === 'gather' || $difference > static::PLEAT_LIMIT ? 'gather' : 'pleat';

            if ($used === 'gather') {
                $piece = $this->recordGather($piece, $edge, $taken, 'چین دم آستین');
            } else {
                $piece = $this->addHemPleats($piece, $edge, $taken);
            }

            $finished = round($finished - $taken, 2);
        }

        return ['piece' => $piece, 'finished' => $finished, 'taken' => round($taken, 2), 'method' => $used];
    }

    /** دو پیلی روی دم آستین، نزدیک شکاف مچ. */
    protected function addHemPleats(array $piece, int $edge, float $amount): array
    {
        $half = round($amount / 2, 2);

        foreach ([0.3, 0.45] as $index => $t) {
            $on = Geometry::pointOnEdge($piece['outline'], $edge, $t);

            $piece['pleats'][] = [
                'edge' => $edge,
                'depth' => $half,
                'label' => 'پیلی دم آستین '.($index + 1),
                'from' => Geometry::point($on['x'], $on['y']),
                'to' => Geometry::point($on['x'], max(0.0, $on['y'] - 6)),
            ];
        }

        return $piece;
    }

    /** کوتاه‌کردن آستین به اندازه بلندی مچ. */
    protected function shortenSleeve(array $piece, int $edge, float $amount): array
    {
        if ($amount <= 0.05) {
            return $piece;
        }

        $piece = $this->raiseEdge($piece, $edge, $amount);
        $piece['meta']['shortened_for_cuff'] = round(
            ((float) ($piece['meta']['shortened_for_cuff'] ?? 0)) + $amount,
            2,
        );

        return Geometry::normalizePiece($piece);
    }

    /** شکاف مچ و نوار پاتلت آن. */
    protected function plackedPieces(array $piece, int $edge, float $slit, string $code): array
    {
        $on = Geometry::pointOnEdge($piece['outline'], $edge, 0.35);

        $piece['markers'][] = $this->marker('slit', 'شکاف مچ', $on['x'], $on['y'] - $slit, $on['x'], $on['y']);
        $piece['notches'][] = $this->notch($on['x'], $on['y'], $edge, 'جای شکاف مچ', 'cuff_slit');
        $piece['meta']['openings'][] = [
            'type' => 'slit',
            'label' => 'شکاف مچ',
            'length' => round($slit, 2),
            'from' => Geometry::point($on['x'], $on['y'] - $slit),
            'to' => Geometry::point($on['x'], $on['y']),
        ];

        $placket = $this->piece([
            'code' => $code,
            'name' => 'پاتلت شکاف مچ',
            'cut_quantity' => 2,
            'outline' => $this->rect(5, $slit + 4),
            'grainline' => $this->grainline(2.5, 1, $slit + 3),
            'markers' => [
                $this->marker('fold', 'خط تای پاتلت', 1.5, 0, 1.5, $slit + 4),
                $this->marker('fold', 'خط تای دوم', 3.5, 0, 3.5, $slit + 4),
            ],
            'meta' => [
                'part' => 'placket',
                'edges' => ['default', 'default', 'default', 'default'],
                'fold_edges' => [],
                'interfacing' => true,
                'detail' => static::key(),
            ],
        ]);

        return [$piece, $placket];
    }

    /** یادداشت مشترک «مچ به اندازه دم تمام‌شده بریده شد». */
    protected function fitNote(array $fit, float $cuffLength): array
    {
        return match ($fit['method']) {
            'existing' => $this->note('tip', 'دم آستین از قبل چین خورده بود؛ مچ به اندازه دم تمام‌شده ('
                .Format::cm($fit['finished']).') بریده شد، نه اندازه تخت آستین.'),
            'pleat' => $this->note('tip', Format::cm($fit['taken']).' گشادی دم آستین با دو پیلی جمع شد و مچ '
                .Format::cm($cuffLength).' بریده شد.'),
            'gather' => $this->note('tip', Format::cm($fit['taken']).' گشادی دم آستین چین خورد و مچ '
                .Format::cm($cuffLength).' بریده شد.'),
            default => $this->note('tip', 'مچ به اندازه دم آستین ('.Format::cm($fit['finished']).') بریده شد.'),
        };
    }
}
