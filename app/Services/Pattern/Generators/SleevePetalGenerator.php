<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * آستین گلبرگی (لاله‌ای).
 *
 * آستین کوتاه از وسط به دو گلبرگ تقسیم می‌شود که روی هم می‌افتند: گلبرگ جلو
 * نیمه جلوی سرآستین را دارد و گلبرگ پشت نیمه پشت را، و هر کدام با یک قوس تا
 * پایین می‌آید. روی هم افتادن دو گلبرگ پارامتر است.
 */
class SleevePetalGenerator extends SleeveCatalogGenerator
{
    public static function key(): string
    {
        return 'sleeve_petal';
    }

    public function label(): string
    {
        return 'آستین گلبرگی (لاله)';
    }

    public function paramsSchema(): array
    {
        $schema = $this->commonSleeveSchema(1.0, 25, 4);
        unset($schema['length_percent']);

        return $schema + [
            'petal_length' => [
                'label' => 'بلندی گلبرگ', 'min' => 8, 'max' => 30, 'step' => 0.5, 'default' => 16,
                'unit' => 'سانتی‌متر',
            ],
            'overlap' => [
                'label' => 'روی هم افتادن دو گلبرگ', 'min' => 10, 'max' => 60, 'step' => 5, 'default' => 35,
                'unit' => 'درصد پهنای آستین',
            ],
        ];
    }

    protected function sleeveSpec(array $m, array $ease, array $params): array
    {
        return [
            'code' => 'sleeve-petal',
            'name' => 'آستین گلبرگی',
            'family' => 'petal',
            'ease_band' => [1.0, 2.5],
            'length' => (float) $this->param($params, 'petal_length', 16),
            'hem_ratio' => 1.0,
            'side_bulge' => 0.4,
        ];
    }

    protected function sleevePieces(array $frame, array $m, array $ease, array $params, array $spec): array
    {
        [$outline] = $this->sleeveBody($frame, $spec);

        $width = (float) $frame['width'];
        $length = (float) $frame['length'];
        $center = (float) $frame['center'];
        $overlap = max(0.1, min(0.6, ((float) $this->param($params, 'overlap', 35)) / 100)) * $width;

        $hemLeft = ['x' => (float) $outline[6]['x'], 'y' => (float) $outline[6]['y']];
        $hemRight = ['x' => (float) $outline[5]['x'], 'y' => (float) $outline[5]['y']];
        $apex = ['x' => (float) $outline[2]['x'], 'y' => (float) $outline[2]['y']];

        // گلبرگ جلو: نیمه جلوی سرآستین و قوسی که تا سمت پشت دم آستین می‌آید
        $frontEnd = ['x' => min($width, $center + $overlap), 'y' => $length];
        $frontPetal = [
            $outline[0],
            $outline[1],
            $outline[2],
            Geometry::curve($frontEnd['x'], $frontEnd['y'], $apex['x'] + ($overlap * 0.35), $length * 0.45),
            Geometry::point($hemLeft['x'], $hemLeft['y']),
        ];
        $frontEdges = ['armhole', 'armhole', 'default', 'hem', 'side'];

        // گلبرگ پشت: نیمه پشت سرآستین و قوسی که تا سمت جلوی دم آستین می‌آید
        $backEnd = ['x' => max(0.0, $center - $overlap), 'y' => $length];
        $backPetal = [
            Geometry::point($apex['x'], $apex['y']),
            $outline[3],
            $outline[4],
            Geometry::curve($hemRight['x'], $hemRight['y'], $outline[5]['cx'] ?? $width, $length * 0.6),
            Geometry::curve($backEnd['x'], $backEnd['y'], $center, $length + 1.2),
        ];
        $backEdges = ['armhole', 'armhole', 'side', 'hem', 'default'];

        $frontCap = Geometry::edgesLength($frontPetal, [0, 1]);
        $backCap = Geometry::edgesLength($backPetal, [0, 1]);
        $total = $frontCap + $backCap;

        $front = $this->finishSleeve($frontPetal, $frontEdges, $frame, array_merge($spec, [
            'code' => 'sleeve-petal-front',
            'name' => 'گلبرگ جلو',
            'cap_edges' => [0, 1],
            'armhole_share' => $total > 0 ? $frontCap / $total : 0.5,
            'notches' => [
                $this->notch($apex['x'], $apex['y'], 1, 'نوک آستین (سرشانه)', 'sleeve_top'),
                $this->notch(
                    Geometry::pointOnEdge($frontPetal, 1, 0.55)['x'],
                    Geometry::pointOnEdge($frontPetal, 1, 0.55)['y'],
                    1,
                    'نشانه تکی جلو آستین',
                    'armhole_front',
                ),
            ],
            'replace_markers' => true,
            'markers' => [$this->marker('bicep', 'خط بازو', 0, (float) $frame['cap_height'], $center)],
            'meta' => ['petal_role' => 'front', 'overlap' => round($overlap, 2)],
            'notes' => [
                'گلبرگ جلو روی گلبرگ پشت می‌افتد؛ '.round($overlap, 1).' سانتی‌متر روی هم می‌آیند.',
            ],
        ]));

        $back = $this->finishSleeve($backPetal, $backEdges, $frame, array_merge($spec, [
            'code' => 'sleeve-petal-back',
            'name' => 'گلبرگ پشت',
            'cap_edges' => [0, 1],
            'armhole_share' => $total > 0 ? $backCap / $total : 0.5,
            'notches' => [
                $this->notch($apex['x'], $apex['y'], 0, 'نوک آستین (سرشانه)', 'sleeve_top'),
                $this->notch(
                    Geometry::pointOnEdge($backPetal, 1, 0.40)['x'],
                    Geometry::pointOnEdge($backPetal, 1, 0.40)['y'],
                    1,
                    'نشانه دوتایی پشت آستین (۱)',
                    'armhole_back',
                ),
                $this->notch(
                    Geometry::pointOnEdge($backPetal, 1, 0.52)['x'],
                    Geometry::pointOnEdge($backPetal, 1, 0.52)['y'],
                    1,
                    'نشانه دوتایی پشت آستین (۲)',
                    'armhole_back',
                ),
            ],
            'replace_markers' => true,
            'markers' => [$this->marker('bicep', 'خط بازو', $center, (float) $frame['cap_height'], $width)],
            'meta' => ['petal_role' => 'back', 'overlap' => round($overlap, 2)],
            'notes' => ['دو گلبرگ اول روی هم بست می‌خورند و بعد با هم روی حلقه دوخته می‌شوند.'],
        ]));

        return [$front, $back];
    }
}
