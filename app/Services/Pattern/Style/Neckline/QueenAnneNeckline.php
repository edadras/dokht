<?php

namespace App\Services\Pattern\Style\Neckline;

use App\Services\Pattern\Geometry;

/**
 * یقه ملکه‌آن.
 *
 * کف یقه مثل یقه دلبری قلبی است، ولی پهلوهای یقه بالا می‌آید و کنار گردن می‌ایستد؛
 * پس سرگردن به جای گشادشدن، تنگ‌تر می‌شود و خط یقه از کنار گردن بالا می‌رود.
 */
class QueenAnneNeckline extends BaseNeckline
{
    public static function key(): string
    {
        return 'neck_queen_anne';
    }

    public function label(): string
    {
        return 'یقه ملکه‌آن';
    }

    public function description(): string
    {
        return 'کف قلبی با پهلوهای بالا کنار گردن؛ یقه مجلسی کلاسیک با پایه ایستاده.';
    }

    public function paramsSchema(): array
    {
        return [
            'depth' => $this->depthField(7, 2, 20),
            'lobe' => [
                'label' => 'پهنای کاسه', 'min' => 3, 'max' => 12, 'step' => 0.5, 'default' => 5.5, 'unit' => 'سانتی‌متر',
            ],
            'rise' => [
                'label' => 'بالاآمدن کنار گردن', 'min' => 0, 'max' => 6, 'step' => 0.5, 'default' => 2,
                'unit' => 'سانتی‌متر', 'hint' => 'سرگردن به همین اندازه بالاتر از خط سرشانه می‌رود.',
            ],
            'width' => $this->widthField(-1, -4, 3),
            'back_depth' => $this->backDepthField(2, 16),
        ] + $this->finishFields(4.5);
    }

    /**
     * یقه پشت هم به همان اندازه بالا می‌رود، وگرنه دو سر درز سرشانه روی هم نمی‌افتد.
     */
    protected function backPath(array $a, array $p, ?float $partnerAngle): array
    {
        $snp = $this->raisedSnp($a, $p);
        $alpha = $this->complement($partnerAngle);
        $center = Geometry::point($a['center_x'], $a['cf']['y'] + (float) $p['back_depth']);

        return [
            'points' => [$center, $this->arrive($center, ['x' => 1.0, 'y' => 0.0], $a, $alpha, $snp)],
            'tags' => ['neck'],
            'alpha' => $alpha,
        ];
    }

    /** سرگردن تنگ‌شده و بالا رفته. */
    protected function raisedSnp(array $a, array $p): array
    {
        $snp = $this->movedSnp($a, (float) $p['width']);

        return Geometry::point($snp['x'], $snp['y'] - (float) $p['rise']);
    }

    protected function frontPath(array $a, array $p, ?float $partnerAngle): array
    {
        $rise = (float) $p['rise'];
        $snp = $this->raisedSnp($a, $p);
        $center = Geometry::point($a['center_x'], $a['cf']['y'] + (float) $p['depth']);
        $lobe = min((float) $p['lobe'], max(2.0, ($snp['x'] - $a['center_x']) * 0.8));

        $top = Geometry::curve(
            $a['center_x'] + $lobe,
            $center['y'] - ((float) $p['depth'] * 0.35),
            $a['center_x'] + ($lobe * 0.85),
            $center['y'],
        );

        return [
            'points' => [$center, $top, $this->arrive($top, $this->continueDir($top), $a, 90.0, $snp)],
            'tags' => ['neck', 'neck'],
            'alpha' => 90.0,
            'notes' => $rise > 0
                ? ['پهلوی یقه '.round($rise, 1).' سانتی‌متر بالاتر از خط سرشانه ایستاده است؛ این تکه باید لایه‌دار و با سجاف قالبی دوخته شود.']
                : [],
        ];
    }
}
