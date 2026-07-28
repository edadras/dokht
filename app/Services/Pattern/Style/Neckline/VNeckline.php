<?php

namespace App\Services\Pattern\Style\Neckline;

use App\Services\Pattern\Geometry;

/**
 * یقه هفت.
 *
 * خط یقه یک پاره‌خط راست از مرکز جلو به سرگردن است، پس طول آن دقیقاً همان وتر
 * است. اگر زاویه رسیدن به سرشانه از بازه مجاز بیرون بزند (یقه هفت خیلی کم‌عمق)
 * انتهای خط کمی خم می‌شود تا با یقه پشت جور بماند.
 */
class VNeckline extends BaseNeckline
{
    public static function key(): string
    {
        return 'neck_v';
    }

    public function label(): string
    {
        return 'یقه هفت';
    }

    public function description(): string
    {
        return 'خط راست از مرکز جلو تا سرگردن؛ گردن را بلندتر نشان می‌دهد.';
    }

    public function paramsSchema(): array
    {
        return [
            'depth' => $this->depthField(10, 2, 30),
            'width' => $this->widthField(1),
            'back_depth' => $this->backDepthField(1, 12),
        ] + $this->finishFields();
    }

    protected function frontPath(array $a, array $p, ?float $partnerAngle): array
    {
        return $this->vPath($a, (float) $p['depth'], (float) $p['width']);
    }

    /** پاره‌خط هفت با زاویه سنجیده‌شده. */
    protected function vPath(array $a, float $depth, float $width): array
    {
        $snp = $this->movedSnp($a, $width);
        $center = Geometry::point($a['center_x'], $a['cf']['y'] + $depth);
        $chord = $this->angleOfChord($a, $center, $snp);
        $alpha = $this->clampAngle($chord);
        $notes = [];

        if (abs($alpha - $chord) > 0.5) {
            $notes[] = 'زاویه یقه هفت در سرگردن '.round($chord, 1).' درجه بود؛ برای جورشدن با یقه پشت'
                .' انتهای خط کمی خم شد. اگر خط کاملاً راست می‌خواهید، گودی یا گشادی یقه را تغییر دهید.';
        }

        return [
            'points' => [$center, $this->arrive($center, $this->unit($this->vec($center, $snp)), $a, $alpha, $snp)],
            'tags' => ['neck'],
            'alpha' => $alpha,
            'notes' => $notes,
            'meta' => ['neckline_chord' => round($this->length($this->vec($center, $snp)), 2)],
        ];
    }
}
