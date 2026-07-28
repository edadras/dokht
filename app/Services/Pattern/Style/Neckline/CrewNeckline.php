<?php

namespace App\Services\Pattern\Style\Neckline;

use App\Services\Pattern\Geometry;

/** یقه خدمه: یقه گرد چسبان دور گردن که با نوار کشی تمام می‌شود. */
class CrewNeckline extends BaseNeckline
{
    public static function key(): string
    {
        return 'neck_crew';
    }

    public function label(): string
    {
        return 'یقه خدمه';
    }

    public function description(): string
    {
        return 'یقه گرد و نزدیک به گردن، مثل تی‌شرت؛ لبه‌اش با نوار کشی تمام می‌شود.';
    }

    public function paramsSchema(): array
    {
        return [
            'depth' => $this->depthField(0.5, 0, 6),
            'width' => $this->widthField(0.5, 0, 4),
            'back_depth' => $this->backDepthField(0, 4),
        ] + $this->finishFields(2.5);
    }

    protected function frontPath(array $a, array $p, ?float $partnerAngle): array
    {
        $snp = $this->movedSnp($a, (float) $p['width']);
        $center = Geometry::point($a['center_x'], $a['cf']['y'] + (float) $p['depth']);

        return [
            'points' => [$center, $this->arrive($center, ['x' => 1.0, 'y' => 0.0], $a, 90.0, $snp)],
            'tags' => ['neck'],
            'alpha' => 90.0,
            'notes' => $a['side'] === 'front'
                ? ['یقه خدمه تنگ است؛ دور یقه تمام‌شده باید دست‌کم اندازه دور سر باشد، وگرنه از نوار کشی یا چاک پشت استفاده کنید.']
                : [],
        ];
    }
}
