<?php

namespace App\Services\Pattern\Style\Neckline;

use App\Services\Pattern\Geometry;

/**
 * یقه V پشت.
 *
 * این‌جا پشت تعیین‌کننده است: هفت روی پشت درفت می‌شود و جلو یک یقه گرد آرام است که
 * زاویه‌اش با پشت جور می‌شود.
 */
class VBackNeckline extends BaseNeckline
{
    public static function key(): string
    {
        return 'neck_v_back';
    }

    public function label(): string
    {
        return 'یقه V پشت';
    }

    public function description(): string
    {
        return 'جلو بسته و گرد، پشت هفتِ باز؛ برای پیراهن مجلسی.';
    }

    protected function leadSide(): string
    {
        return 'back';
    }

    public function paramsSchema(): array
    {
        return [
            'back_depth' => $this->backDepthField(18, 40),
            'depth' => $this->depthField(1, 0, 12, 'گودی یقه جلو'),
            'width' => $this->widthField(1),
            'closure' => [
                'label' => 'بست بالای هفت پشت', 'type' => 'toggle', 'default' => true,
            ],
        ] + $this->finishFields();
    }

    protected function backPath(array $a, array $p, ?float $partnerAngle): array
    {
        $snp = $this->movedSnp($a, (float) $p['width']);
        $center = Geometry::point($a['center_x'], $a['cf']['y'] + (float) $p['back_depth']);
        $chord = $this->angleOfChord($a, $center, $snp);
        $alpha = $this->clampAngle($chord);

        return [
            'points' => [$center, $this->arrive($center, $this->unit($this->vec($center, $snp)), $a, $alpha, $snp)],
            'tags' => ['neck'],
            'alpha' => $alpha,
            'notes' => ! empty($p['closure'])
                ? ['هفت پشت باز است؛ اگر لباس زیپ پشت ندارد، بالای هفت را با دکمه و حلقه ببندید تا یقه سر جایش بماند.']
                : [],
        ];
    }

    protected function frontPath(array $a, array $p, ?float $partnerAngle): array
    {
        $snp = $this->movedSnp($a, (float) $p['width']);
        $alpha = $this->complement($partnerAngle);
        $center = Geometry::point($a['center_x'], $a['cf']['y'] + (float) $p['depth']);

        return [
            'points' => [$center, $this->arrive($center, ['x' => 1.0, 'y' => 0.0], $a, $alpha, $snp)],
            'tags' => ['neck'],
            'alpha' => $alpha,
        ];
    }
}
