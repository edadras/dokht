<?php

namespace App\Services\Pattern\Style\Neckline;

use App\Services\Pattern\Geometry;

/** یقه گشاد: کاسه‌ای پهن و گود با کف نرم. */
class ScoopNeckline extends BaseNeckline
{
    public static function key(): string
    {
        return 'neck_scoop';
    }

    public function label(): string
    {
        return 'یقه گشاد';
    }

    public function description(): string
    {
        return 'کاسه‌ای پهن و گود؛ کف یقه پهن است و به آرامی به سرشانه می‌رسد.';
    }

    public function paramsSchema(): array
    {
        return [
            'depth' => $this->depthField(7, 1, 24),
            'width' => $this->widthField(3),
            'back_depth' => $this->backDepthField(3, 20),
        ] + $this->finishFields();
    }

    protected function frontPath(array $a, array $p, ?float $partnerAngle): array
    {
        return $this->scoop($a, (float) $p['depth'], (float) $p['width'], 90.0);
    }

    protected function backPath(array $a, array $p, ?float $partnerAngle): array
    {
        return $this->scoop($a, (float) $p['back_depth'], (float) $p['width'], $this->complement($partnerAngle));
    }

    /** کاسه دو تکه: کف پهن، سپس بالا رفتن تا سرگردن. */
    protected function scoop(array $a, float $depth, float $width, float $alpha): array
    {
        $snp = $this->movedSnp($a, $width);
        $center = Geometry::point($a['center_x'], $a['cf']['y'] + $depth);
        $span = $snp['x'] - $a['center_x'];
        $rise = $center['y'] - $snp['y'];

        $mid = Geometry::curve(
            $a['center_x'] + ($span * 0.55),
            $center['y'] - ($rise * 0.18),
            $a['center_x'] + ($span * 0.28),
            $center['y'],
        );

        return [
            'points' => [$center, $mid, $this->arrive($mid, $this->continueDir($mid), $a, $alpha, $snp)],
            'tags' => ['neck', 'neck'],
            'alpha' => $alpha,
        ];
    }
}
