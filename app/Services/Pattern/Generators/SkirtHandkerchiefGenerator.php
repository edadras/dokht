<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * دامن دستمالی.
 *
 * کمر روی یک دایره کامل است (پس شعاع کمر = دور کمر ÷ ۲π) ولی دم دامن به جای دایره
 * یک مربع است؛ به همین دلیل دامن در چهار نقطه نوک‌تیز آویزان می‌شود:
 *   کوتاه‌ترین قد (وسط هر ضلع) = پارامتر بلندی
 *   بلندترین قد (نوک‌ها) = (شعاع کمر + بلندی) × √۲ − شعاع کمر
 */
class SkirtHandkerchiefGenerator extends SkirtBaseGenerator
{
    public static function key(): string
    {
        return 'skirt_handkerchief';
    }

    public function label(): string
    {
        return 'دامن دستمالی';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->lengthParam(50, 20, 100),
            $this->waistParams(0.5, 4),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $waist = $this->m($measurements, 'waist', 74) + $this->ease($ease, 'waist', 4);
        $length = (float) $this->param($params, 'length', 50);
        $radius = $this->circleRadius($waist, 1.0);
        $outer = $radius + $length;
        $point = ($outer * M_SQRT2) - $radius;

        $note = 'کوتاه‌ترین قد دامن '.$this->fa(round($length, 1)).' سانتی‌متر و نوک‌های آن '
            .$this->fa(round($point, 1)).' سانتی‌متر است؛ نوک‌ها میان مرکز جلو و درز پهلو می‌افتند.';

        $pieces = [];

        foreach (['front', 'back'] as $side) {
            $pieces[] = $this->squarePanel($waist, $radius, $outer, $side, $note);
        }

        if ($this->flag($params, 'waistband', true)) {
            $pieces[] = $this->bandPiece($waist, [
                'height' => (float) $this->param($params, 'waistband_height', 4),
            ]);
        }

        return $this->finishSkirt($pieces, $params);
    }

    /** نیمِ جلو یا پشت: یک‌چهارم مربع با کمانِ کمر در گوشه آن. */
    protected function squarePanel(float $waist, float $radius, float $outer, string $side, string $note): array
    {
        $isFront = $side === 'front';
        $arc = $this->arcPoints($radius, 0, M_PI / 2);
        $arcEdges = $this->arcEdgeCount(M_PI / 2);

        $outline = array_merge($arc, [
            Geometry::point($outer, 0),
            Geometry::point($outer, $outer),
            Geometry::point(0, $outer),
        ]);

        $edges = array_merge(
            array_fill(0, $arcEdges, 'waist'),
            ['side', 'hem', 'hem', 'default'],
        );

        return $this->piece([
            'code' => 'hanky-'.$side,
            'name' => $isFront ? 'دامن دستمالی جلو' : 'دامن دستمالی پشت',
            'cut_quantity' => 1,
            'on_fold' => true,
            'outline' => $outline,
            'grainline' => $this->grainline(min(2.0, $radius * 0.25), $radius + 3, $outer - 3),
            'notches' => [$this->notch($radius, 0, $arcEdges, 'نشانه درز پهلو', 'side')],
            'markers' => [
                $this->marker($isFront ? 'cf' : 'cb', $isFront ? 'خط مرکز جلو' : 'خط مرکز پشت', 0, $radius, 0, $outer),
                $this->marker('point', 'نوک دستمال', 0, 0, $outer, $outer),
            ],
            'meta' => [
                'part' => $isFront ? 'skirt_front' : 'skirt_back',
                'edges' => $edges,
                'fold_edges' => [count($outline) - 1],
                'side' => $side,
                'waist_edges' => range(0, $arcEdges - 1),
                'side_edges' => [$arcEdges],
                'hem_edges' => [$arcEdges + 1, $arcEdges + 2],
                'waist_target' => round($waist, 3),
                'waist_finished' => round($waist / 4, 2),
                'seam_length' => round($outer - $radius, 2),
                'waist_radius' => round($radius, 2),
                'point_length' => round(($outer * M_SQRT2) - $radius, 2),
                'fullness' => [],
                'notes' => [$note],
            ],
        ]);
    }
}
