<?php

namespace App\Services\Pattern\Style\Neckline;

use App\Services\Pattern\Geometry;

/** یقه گرد: ساده‌ترین خط یقه؛ یک منحنی از مرکز جلو تا سرگردن. */
class RoundNeckline extends BaseNeckline
{
    public static function key(): string
    {
        return 'neck_round';
    }

    public function label(): string
    {
        return 'یقه گرد';
    }

    public function description(): string
    {
        return 'خط یقه گرد و آرام؛ پایه همه یقه‌های دیگر و مناسب هر پارچه‌ای.';
    }

    public function paramsSchema(): array
    {
        return [
            'depth' => $this->depthField(2, 0, 20),
            'width' => $this->widthField(1),
            'back_depth' => $this->backDepthField(0.5, 12),
        ] + $this->finishFields();
    }

    protected function frontPath(array $a, array $p, ?float $partnerAngle): array
    {
        $snp = $this->movedSnp($a, (float) $p['width']);
        $center = Geometry::point($a['center_x'], $a['cf']['y'] + (float) $p['depth']);
        $alpha = 90.0;

        return [
            'points' => [$center, $this->arrive($center, ['x' => 1.0, 'y' => 0.0], $a, $alpha, $snp)],
            'tags' => ['neck'],
            'alpha' => $alpha,
        ];
    }
}
