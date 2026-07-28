<?php

namespace App\Services\Pattern\Style\Neckline;

use App\Services\Pattern\Geometry;

/**
 * یقه‌باز شانه‌افتاده.
 *
 * درز سرشانه برداشته می‌شود: خط یقه از مرکز جلو تا نقطه‌ای روی حلقه آستین، پایین‌تر
 * از نوک سرشانه، می‌رود. جلو و پشت هر دو به همان اندازه از حلقه آستین پایین می‌آیند
 * تا دو لبه در درز پهلو/حلقه به هم برسند.
 */
class OffShoulderNeckline extends BaseNeckline
{
    public static function key(): string
    {
        return 'neck_off_shoulder';
    }

    public function label(): string
    {
        return 'یقه‌باز شانه‌افتاده';
    }

    public function description(): string
    {
        return 'خط یقه از روی شانه پایین می‌افتد و درز سرشانه حذف می‌شود؛ لبه باید با کش یا نوار محکم شود.';
    }

    public function paramsSchema(): array
    {
        return [
            'depth' => $this->depthField(6, 0, 20),
            'back_depth' => $this->backDepthField(5, 20),
            'shoulder_drop' => [
                'label' => 'پایین‌افتادن روی حلقه آستین', 'min' => 2, 'max' => 14, 'step' => 0.5, 'default' => 6,
                'unit' => 'سانتی‌متر', 'hint' => 'از نوک سرشانه روی حلقه آستین به پایین اندازه گرفته می‌شود.',
            ],
            'elastic' => [
                'label' => 'کش لبه بالا', 'type' => 'toggle', 'default' => true,
            ],
        ] + $this->finishFields(3);
    }

    protected function frontPath(array $a, array $p, ?float $partnerAngle): array
    {
        return $this->openShoulder($a, (float) $p['depth'], (float) $p['shoulder_drop'], ! empty($p['elastic']));
    }

    protected function backPath(array $a, array $p, ?float $partnerAngle): array
    {
        return $this->openShoulder($a, (float) $p['back_depth'], (float) $p['shoulder_drop'], false);
    }

    /**
     * مسیر از مرکز تا روی حلقه آستین، با برداشتن سرشانه.
     *
     * @return array<string, mixed>
     */
    protected function openShoulder(array $a, float $depth, float $drop, bool $elastic): array
    {
        $outline = array_values($a['piece']['outline']);
        $armhole = $a['armhole_edge'];
        $armholeLength = Geometry::edgeLength($outline, $armhole);
        $t = max(0.05, min(0.75, $drop / max(1.0, $armholeLength)));
        $end = Geometry::pointOnEdge($outline, $armhole, $t);
        $center = Geometry::point($a['center_x'], $a['cf']['y'] + $depth);

        $control = Geometry::lerp(
            ['x' => $center['x'] + (($end['x'] - $center['x']) * 0.55), 'y' => $center['y']],
            $end,
            0.15,
        );

        return [
            'points' => [$center, Geometry::curve($end['x'], $end['y'], $control['x'], $control['y'])],
            'tags' => ['neck'],
            'alpha' => null,
            'consume_shoulder' => true,
            'armhole_t' => $t,
            'meta' => ['no_shoulder_seam' => true, 'neck_open_shoulder' => round($drop, 2)],
            'notes' => $elastic
                ? ['لبه بالای یقه شانه‌افتاده با کش '.round($drop, 1).' سانتی‌متری زیر نوار دوخته می‌شود تا روی بازو بایستد.']
                : [],
        ];
    }

    protected function supportsNeckline(array $pieces, array $context): true|string
    {
        foreach ($pieces as $piece) {
            if (! $this->isBodyPiece($piece)) {
                continue;
            }

            if ($this->edgeWithTag($piece, 'armhole') === null) {
                return 'این سبک حلقه آستین می‌خواهد تا خط یقه روی آن پایین بیاید؛ «'.$piece['name'].'» حلقه آستین ندارد.';
            }
        }

        return true;
    }
}
