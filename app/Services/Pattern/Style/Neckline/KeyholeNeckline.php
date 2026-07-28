<?php

namespace App\Services\Pattern\Style\Neckline;

use App\Services\Pattern\Geometry;

/**
 * یقه کیهول.
 *
 * یقه گرد با یک شکاف باریک گردته در مرکز جلو. چون قطعه روی تای پارچه بریده
 * می‌شود، نیمِ شکاف روی نیم‌قطعه درفت می‌شود و نیمه دیگر قرینه‌اش است.
 */
class KeyholeNeckline extends BaseNeckline
{
    public static function key(): string
    {
        return 'neck_keyhole';
    }

    public function label(): string
    {
        return 'یقه کیهول';
    }

    public function description(): string
    {
        return 'یقه گرد با شکاف باریک و گردته در مرکز؛ بالای شکاف با دکمه و حلقه بسته می‌شود.';
    }

    public function paramsSchema(): array
    {
        return [
            'depth' => $this->depthField(1.5, 0, 8),
            'width' => $this->widthField(1),
            'slit' => [
                'label' => 'بلندی شکاف', 'min' => 3, 'max' => 20, 'step' => 0.5, 'default' => 8,
                'unit' => 'سانتی‌متر', 'hint' => 'از خط یقه به پایین.',
            ],
            'slit_width' => [
                'label' => 'پهنای شکاف', 'min' => 1, 'max' => 8, 'step' => 0.5, 'default' => 2.5,
                'unit' => 'سانتی‌متر', 'hint' => 'پهنای کامل شکاف در بازترین جا.',
            ],
            'back_depth' => $this->backDepthField(1, 12),
        ] + $this->finishFields(4);
    }

    protected function frontPath(array $a, array $p, ?float $partnerAngle): array
    {
        $snp = $this->movedSnp($a, (float) $p['width']);
        $neckY = $a['cf']['y'] + (float) $p['depth'];
        $half = max(0.5, ((float) $p['slit_width']) / 2);
        $bottom = $neckY + (float) $p['slit'];

        // کف گردِ شکاف روی خط مرکز، سپس پهلوی تقریباً راست شکاف
        $round = Geometry::curve($a['center_x'] + $half, $bottom - $half, $a['center_x'] + $half, $bottom);
        $top = Geometry::point($a['center_x'] + ($half * 1.15), $neckY);

        return [
            'points' => [
                Geometry::point($a['center_x'], $bottom),
                $round,
                $top,
                $this->arrive($top, $this->unit(['x' => 0.35, 'y' => -1.0]), $a, 90.0, $snp),
            ],
            'tags' => ['neck', 'neck', 'neck'],
            'alpha' => 90.0,
            'markers' => [
                $this->marker('button_loop', 'حلقه و دکمه بالای شکاف', $a['center_x'], $neckY, $a['center_x'] + ($half * 1.15), $neckY),
            ],
            'notes' => ['شکاف کیهول با سجاف یک‌تکه تمام می‌شود؛ سجاف را پیش از بریدن شکاف بدوزید و بعد ببرید.'],
        ];
    }
}
