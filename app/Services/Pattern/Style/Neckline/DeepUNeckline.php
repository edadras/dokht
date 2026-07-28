<?php

namespace App\Services\Pattern\Style\Neckline;

use App\Services\Pattern\Geometry;

/** U عمیق: کف گرد و پهلوهای تقریباً راست که تا پایین سینه می‌آید. */
class DeepUNeckline extends BaseNeckline
{
    public static function key(): string
    {
        return 'neck_u_deep';
    }

    public function label(): string
    {
        return 'یقه U عمیق';
    }

    public function description(): string
    {
        return 'کف گرد و پهلوهای راست؛ گودتر و باریک‌تر از یقه گشاد.';
    }

    public function paramsSchema(): array
    {
        return [
            'depth' => $this->depthField(14, 6, 30),
            'width' => $this->widthField(1.5),
            'back_depth' => $this->backDepthField(2, 24),
        ] + $this->finishFields(5.5);
    }

    protected function frontPath(array $a, array $p, ?float $partnerAngle): array
    {
        $snp = $this->movedSnp($a, (float) $p['width']);
        $center = Geometry::point($a['center_x'], $a['cf']['y'] + (float) $p['depth']);
        $span = $snp['x'] - $a['center_x'];
        $rise = $center['y'] - $snp['y'];

        // کف گرد کوتاه، بعد پهلوی تقریباً راست تا سرگردن
        $corner = Geometry::curve(
            $a['center_x'] + ($span * 0.82),
            $center['y'] - ($rise * 0.42),
            $a['center_x'] + ($span * 0.82),
            $center['y'],
        );

        return [
            'points' => [$center, $corner, $this->arrive($corner, $this->continueDir($corner), $a, 90.0, $snp)],
            'tags' => ['neck', 'neck'],
            'alpha' => 90.0,
        ];
    }
}
