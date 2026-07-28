<?php

namespace App\Services\Pattern\Style\Neckline;

use App\Services\Pattern\Geometry;

/**
 * یقه هالتر.
 *
 * سرشانه برداشته می‌شود و به جای آن یک بند باریک از جلو بالا می‌رود و پشت گردن
 * بسته می‌شود. جلو: خط یقه از مرکز جلو به بند می‌رسد، بند از خط سرشانه بالاتر
 * می‌رود و از آن‌جا لبه حلقه تا زیر بغل پایین می‌آید. پشت: لبه صاف زیر کتف.
 */
class HalterNeckline extends BaseNeckline
{
    public static function key(): string
    {
        return 'neck_halter';
    }

    public function label(): string
    {
        return 'یقه هالتر';
    }

    public function description(): string
    {
        return 'بند از جلو بالا می‌رود و پشت گردن گره می‌خورد؛ شانه و کتف باز می‌ماند.';
    }

    public function paramsSchema(): array
    {
        return [
            'depth' => $this->depthField(8, 2, 24),
            'strap' => [
                'label' => 'پهنای بند گردن', 'min' => 2, 'max' => 10, 'step' => 0.5, 'default' => 4, 'unit' => 'سانتی‌متر',
            ],
            'strap_rise' => [
                'label' => 'بالا رفتن بند از خط سرشانه', 'min' => 1, 'max' => 10, 'step' => 0.5, 'default' => 4, 'unit' => 'سانتی‌متر',
            ],
            'shoulder_drop' => [
                'label' => 'گودی حلقه از نوک سرشانه', 'min' => 3, 'max' => 18, 'step' => 0.5, 'default' => 9, 'unit' => 'سانتی‌متر',
            ],
            'back_depth' => $this->backDepthField(12, 30),
            'tie_length' => [
                'label' => 'بلندی بند گره', 'min' => 0, 'max' => 70, 'step' => 1, 'default' => 40,
                'unit' => 'سانتی‌متر', 'hint' => 'صفر یعنی بند دوخته‌شده بدون گره.',
            ],
        ] + $this->finishFields(3);
    }

    protected function frontPath(array $a, array $p, ?float $partnerAngle): array
    {
        $outline = array_values($a['piece']['outline']);
        $armhole = $a['armhole_edge'];
        $t = max(0.05, min(0.8, ((float) $p['shoulder_drop']) / max(1.0, Geometry::edgeLength($outline, $armhole))));
        $end = Geometry::pointOnEdge($outline, $armhole, $t);

        $strap = (float) $p['strap'];
        $rise = (float) $p['strap_rise'];
        $center = Geometry::point($a['center_x'], $a['cf']['y'] + (float) $p['depth']);
        $inner = Geometry::point($a['center_x'] + max(1.0, $a['neck_width'] * 0.45), $a['snp']['y'] - $rise);
        $outer = Geometry::point($inner['x'] + $strap, $inner['y']);

        $toStrap = Geometry::curve(
            $inner['x'],
            $inner['y'],
            $a['center_x'] + ($inner['x'] - $a['center_x']) * 0.75,
            $center['y'] * 0.72,
        );

        $toArmhole = Geometry::curve(
            $end['x'],
            $end['y'],
            $outer['x'] + (($end['x'] - $outer['x']) * 0.35),
            $end['y'] * 0.5,
        );

        $pieces = [];

        if (((float) $p['tie_length']) > 0) {
            $pieces[] = $this->strapPiece($strap, (float) $p['tie_length']);
        }

        return [
            'points' => [$center, $toStrap, $outer, $toArmhole],
            'tags' => ['neck', 'neck', 'armhole'],
            'alpha' => null,
            'consume_shoulder' => true,
            'armhole_t' => $t,
            'pieces' => $pieces,
            'meta' => ['no_shoulder_seam' => true, 'halter_strap' => round($strap, 2)],
            'notes' => ['بند هالتر روی خط راستای پارچه بریده شود تا کشیده نشود؛ وزن جلوی لباس روی همین بند است.'],
        ];
    }

    protected function backPath(array $a, array $p, ?float $partnerAngle): array
    {
        $outline = array_values($a['piece']['outline']);
        $armhole = $a['armhole_edge'];
        $t = max(0.05, min(0.85, (((float) $p['shoulder_drop']) + 3) / max(1.0, Geometry::edgeLength($outline, $armhole))));
        $end = Geometry::pointOnEdge($outline, $armhole, $t);
        $center = Geometry::point($a['center_x'], $a['cf']['y'] + (float) $p['back_depth']);

        $control = ['x' => $center['x'] + (($end['x'] - $center['x']) * 0.62), 'y' => $center['y'] - 0.5];

        return [
            'points' => [$center, Geometry::curve($end['x'], $end['y'], $control['x'], $control['y'])],
            'tags' => ['neck'],
            'alpha' => null,
            'consume_shoulder' => true,
            'armhole_t' => $t,
            'meta' => ['no_shoulder_seam' => true],
        ];
    }

    /** بند گره: نوار دولا با طول کافی برای گره پشت گردن. */
    protected function strapPiece(float $width, float $length): array
    {
        $full = round(max(2.0, $width) * 2 + 2, 2);
        $long = round($length + 6, 2);

        return $this->newPiece([
            'code' => 'halter-strap',
            'name' => 'بند هالتر',
            'cut_quantity' => 2,
            'mirror' => true,
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point($long, 0),
                Geometry::point($long, $full),
                Geometry::point(0, $full),
            ],
            'grainline' => $this->grainline($long * 0.5, 1, $full - 1),
            'markers' => [
                $this->marker('fold', 'خط تای بند', 0, $full / 2, $long, $full / 2),
            ],
            'meta' => [
                'part' => 'strap',
                'edges' => ['side', 'default', 'hem', 'default'],
                'fold_edges' => [],
                'neckline_style' => static::key(),
                'note' => 'بند از وسط تا می‌شود و از رو دوخته می‌شود؛ ۶ سانتی‌متر برای درز و گره اضافه شده است.',
            ],
        ]);
    }

    protected function supportsNeckline(array $pieces, array $context): true|string
    {
        foreach ($pieces as $piece) {
            if ($this->isBodyPiece($piece) && $this->edgeWithTag($piece, 'armhole') === null) {
                return 'یقه هالتر حلقه آستین می‌خواهد؛ «'.$piece['name'].'» حلقه آستین ندارد.';
            }
        }

        return true;
    }
}
