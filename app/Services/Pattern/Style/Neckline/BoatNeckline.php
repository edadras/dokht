<?php

namespace App\Services\Pattern\Style\Neckline;

use App\Services\Pattern\Geometry;

/**
 * یقه قایقی.
 *
 * پهن می‌شود ولی گود نمی‌شود: سرگردن روی خط سرشانه به بیرون می‌رود و کف یقه تقریباً
 * همان‌جای بلوک می‌ماند. انتهای خط، نزدیک سرشانه، کمی بالا کشیده می‌شود تا یقه روی
 * شانه بخوابد و پس از دوختن سرشانه نوک‌تیز نشود.
 */
class BoatNeckline extends BaseNeckline
{
    public static function key(): string
    {
        return 'neck_boat';
    }

    public function label(): string
    {
        return 'یقه قایقی';
    }

    public function description(): string
    {
        return 'خط پهن و کم‌گود از شانه تا شانه؛ گودی یقه تقریباً تغییر نمی‌کند.';
    }

    public function paramsSchema(): array
    {
        return [
            'width' => $this->widthField(7, 1, 16),
            'depth' => $this->depthField(1, 0, 8),
            'back_depth' => $this->backDepthField(0.5, 8),
        ] + $this->finishFields(4);
    }

    protected function frontPath(array $a, array $p, ?float $partnerAngle): array
    {
        return $this->boat($a, (float) $p['depth'], (float) $p['width'], 118.0);
    }

    protected function backPath(array $a, array $p, ?float $partnerAngle): array
    {
        return $this->boat($a, (float) $p['back_depth'], (float) $p['width'], $this->complement($partnerAngle));
    }

    protected function boat(array $a, float $depth, float $width, float $alpha): array
    {
        $snp = $this->movedSnp($a, $width);
        $center = Geometry::point($a['center_x'], $a['cf']['y'] + $depth);
        $alpha = $this->clampAngle($alpha);

        return [
            'points' => [$center, $this->arrive($center, ['x' => 1.0, 'y' => 0.0], $a, $alpha, $snp)],
            'tags' => ['neck'],
            'alpha' => $alpha,
            'notes' => $a['side'] === 'front' && $width >= 6
                ? ['یقه قایقی پهن، درز سرشانه را کوتاه می‌کند؛ اگر سرشانه کمتر از ۴ سانتی‌متر بماند بند لباس زیر بیرون می‌زند.']
                : [],
        ];
    }
}
