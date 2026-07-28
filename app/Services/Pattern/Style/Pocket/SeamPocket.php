<?php

namespace App\Services\Pattern\Style\Pocket;

use App\Services\Pattern\Geometry;
use App\Support\Format;

/**
 * جیب درزی: کیسه به درز پهلو دوخته می‌شود و از بیرون هیچ درزی دیده نمی‌شود.
 *
 * درز پهلوی جلو و پشت در ناحیه دهانه باز می‌ماند، پس هر دو قطعه نشانه می‌گیرند.
 */
class SeamPocket extends BasePocket
{
    public static function key(): string
    {
        return 'pocket_seam';
    }

    public function label(): string
    {
        return 'جیب درزی';
    }

    public function description(): string
    {
        return 'کیسه پشت درز پهلو پنهان می‌شود؛ فقط دهانه روی درز باز می‌ماند.';
    }

    public function paramsSchema(): array
    {
        return [
            'opening' => ['label' => 'دهانه جیب', 'min' => 10, 'max' => 22, 'step' => 0.5, 'default' => 16, 'unit' => 'سانتی‌متر'],
            'from_top' => [
                'label' => 'فاصله از کمر', 'min' => 2, 'max' => 30, 'step' => 0.5, 'default' => 7,
                'unit' => 'سانتی‌متر', 'hint' => 'سر دهانه این‌قدر پایین‌تر از خط کمر می‌نشیند.',
            ],
            'bag_depth' => ['label' => 'عمق کیسه', 'min' => 12, 'max' => 32, 'step' => 0.5, 'default' => 22, 'unit' => 'سانتی‌متر'],
        ];
    }

    /** قطعه‌های جلو و پشتی که درز پهلو دارند. */
    protected function sides(array $pieces): array
    {
        return $this->indexesWithTag($pieces, 'side', static::BODY_PARTS);
    }

    protected function supportsPocket(array $pieces, array $context): true|string
    {
        if (count($this->sides($pieces)) < 2) {
            return 'جیب درزی به درز پهلوی جلو و پشت نیاز دارد؛ این لباس درز پهلوی کامل ندارد.';
        }

        return true;
    }

    public function apply(array $pieces, array $context): array
    {
        $indexes = $this->sides($pieces);

        if (count($indexes) < 2) {
            return $this->result($pieces, [$this->note('warning', 'درز پهلو برای جیب درزی پیدا نشد.')]);
        }

        $opening = $this->num($context, 'opening', 16);
        $fromTop = $this->num($context, 'from_top', 7);
        $depth = $this->num($context, 'bag_depth', 22);
        $marked = [];

        foreach ($indexes as $index) {
            $piece = $pieces[$index];
            $edge = $this->edgeWithTag($piece, 'side');
            $length = $this->edgeLength($piece, $edge);

            if ($length < $opening + 2) {
                continue;
            }

            $start = min(0.9, $fromTop / $length);
            $end = min(0.98, ($fromTop + $opening) / $length);

            $from = Geometry::pointOnEdge($piece['outline'], $edge, $start);
            $to = Geometry::pointOnEdge($piece['outline'], $edge, $end);

            $piece['notches'][] = $this->notch($from['x'], $from['y'], $edge, 'سر دهانه جیب درزی', 'seam_pocket');
            $piece['notches'][] = $this->notch($to['x'], $to['y'], $edge, 'ته دهانه جیب درزی', 'seam_pocket');
            $piece['markers'][] = $this->marker('pocket', 'دهانه جیب درزی', $from['x'], $from['y'], $to['x'], $to['y']);
            $piece['meta']['openings'][] = [
                'type' => 'seam',
                'label' => 'دهانه جیب درزی روی درز پهلو',
                'length' => round($opening, 2),
                'edge' => $edge,
                'from' => Geometry::point($from['x'], $from['y']),
                'to' => Geometry::point($to['x'], $to['y']),
            ];

            $pieces[$index] = $piece;
            $marked[] = $piece['name'];
        }

        if ($marked === []) {
            return $this->result($pieces, [$this->note('warning',
                'درز پهلو کوتاه‌تر از دهانه خواسته‌شده است؛ دهانه جیب درزی را کم کنید.')]);
        }

        $bag = $this->bagPiece($opening * 0.75 + 6, $depth, 'pocket-seam-bag', 'کیسه جیب درزی', 4, 'outer');
        $bag['meta']['note'] = 'دو تا برای جلو و دو تا برای پشت؛ لبه صاف روی درز پهلو می‌نشیند.';

        return $this->result(array_merge($pieces, [$bag]), [
            $this->note('tip', 'دهانه '.Format::cm($opening).' روی درز پهلوی '.implode(' و ', $marked)
                .' علامت خورد؛ درز بین این دو نشانه دوخته نمی‌شود.'),
            $this->note('info', 'طول درز پهلو تغییری نکرده است؛ فقط بخشی از آن باز می‌ماند.'),
            $this->fabricNote($depth + 4, 'کیسه جیب درزی'),
        ], ['opening' => round($opening, 2)]);
    }
}
