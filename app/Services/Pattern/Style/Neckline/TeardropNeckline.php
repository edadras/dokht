<?php

namespace App\Services\Pattern\Style\Neckline;

use App\Services\Pattern\Geometry;

/** یقه قطره‌ای: شکافی که پایینش گرد و پهن است و بالایش تنگ می‌شود. */
class TeardropNeckline extends BaseNeckline
{
    public static function key(): string
    {
        return 'neck_teardrop';
    }

    public function label(): string
    {
        return 'یقه قطره‌ای';
    }

    public function description(): string
    {
        return 'شکاف قطره‌ای در مرکز جلو: پایین گرد و پهن، بالا باریک؛ با دکمه و حلقه بسته می‌شود.';
    }

    public function paramsSchema(): array
    {
        return [
            'depth' => $this->depthField(1, 0, 8),
            'width' => $this->widthField(1),
            'drop' => [
                'label' => 'بلندی قطره', 'min' => 4, 'max' => 22, 'step' => 0.5, 'default' => 11, 'unit' => 'سانتی‌متر',
            ],
            'drop_width' => [
                'label' => 'پهنای قطره', 'min' => 3, 'max' => 16, 'step' => 0.5, 'default' => 8,
                'unit' => 'سانتی‌متر', 'hint' => 'پهنای کامل قطره در پهن‌ترین جا.',
            ],
            'back_depth' => $this->backDepthField(1, 12),
        ] + $this->finishFields(4);
    }

    protected function frontPath(array $a, array $p, ?float $partnerAngle): array
    {
        $snp = $this->movedSnp($a, (float) $p['width']);
        $neckY = $a['cf']['y'] + (float) $p['depth'];
        $half = max(1.0, ((float) $p['drop_width']) / 2);
        $bottom = $neckY + (float) $p['drop'];
        $waist = $half * 0.3;

        $wide = Geometry::curve(
            $a['center_x'] + $half,
            $bottom - ((float) $p['drop'] * 0.45),
            $a['center_x'] + ($half * 1.05),
            $bottom,
        );

        $narrow = Geometry::curve(
            $a['center_x'] + $waist,
            $neckY,
            $a['center_x'] + ($half * 0.95),
            $neckY + ((float) $p['drop'] * 0.16),
        );

        return [
            'points' => [
                Geometry::point($a['center_x'], $bottom),
                $wide,
                $narrow,
                $this->arrive($narrow, $this->unit(['x' => 0.6, 'y' => -1.0]), $a, 90.0, $snp),
            ],
            'tags' => ['neck', 'neck', 'neck'],
            'alpha' => 90.0,
            'markers' => [
                $this->marker('button_loop', 'حلقه و دکمه بالای قطره', $a['center_x'], $neckY, $a['center_x'] + $waist, $neckY),
            ],
            'notes' => ['بالای قطره روی مرکز جلو بسته می‌ماند؛ سجاف یک‌تکه بریده و پس از دوخت، شکاف بریده شود.'],
        ];
    }
}
