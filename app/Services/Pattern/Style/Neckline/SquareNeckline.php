<?php

namespace App\Services\Pattern\Style\Neckline;

use App\Services\Pattern\Geometry;

/** یقه مربع: کف صاف افقی و دو پهلوی راست تا سرگردن. */
class SquareNeckline extends BaseNeckline
{
    public static function key(): string
    {
        return 'neck_square';
    }

    public function label(): string
    {
        return 'یقه مربع';
    }

    public function description(): string
    {
        return 'کف افقی و پهلوهای راست؛ خطی و رسمی، مناسب پارچه بدن‌دار.';
    }

    public function paramsSchema(): array
    {
        return [
            'depth' => $this->depthField(8, 2, 26),
            'width' => $this->widthField(3),
            'back_depth' => $this->backDepthField(4, 24),
            'square_back' => [
                'label' => 'پشت هم مربع باشد', 'type' => 'toggle', 'default' => true,
            ],
        ] + $this->finishFields();
    }

    protected function frontPath(array $a, array $p, ?float $partnerAngle): array
    {
        return $this->squarePath($a, (float) $p['depth'], (float) $p['width']);
    }

    protected function backPath(array $a, array $p, ?float $partnerAngle): array
    {
        if (empty($p['square_back'])) {
            return parent::backPath($a, $p, $partnerAngle);
        }

        return $this->squarePath($a, (float) $p['back_depth'], (float) $p['width']);
    }

    /** کف افقی تا زیر سرگردن، سپس بالا رفتن راست. */
    protected function squarePath(array $a, float $depth, float $width): array
    {
        $snp = $this->movedSnp($a, $width);
        $center = Geometry::point($a['center_x'], $a['cf']['y'] + $depth);
        $corner = Geometry::point($snp['x'], $center['y']);
        $alpha = $this->angleBetween($this->vec($snp, $corner), $a['shoulder']);

        return [
            'points' => [$center, $corner, Geometry::point($snp['x'], $snp['y'])],
            'tags' => ['neck', 'neck'],
            'alpha' => $alpha,
            'meta' => ['neckline_corner' => $corner],
        ];
    }
}
