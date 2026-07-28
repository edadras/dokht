<?php

namespace App\Services\Pattern\Style\Pocket;

use App\Support\Format;

/** جیب رودوزی: چهارگوش، گرد یا نوک‌دار، با درپوش اختیاری. */
class PatchPocket extends BasePocket
{
    public static function key(): string
    {
        return 'pocket_patch';
    }

    public function label(): string
    {
        return 'جیب رودوزی';
    }

    public function description(): string
    {
        return 'جیبی که آماده می‌شود و روی لباس دوخته می‌شود؛ کف آن چهارگوش، گرد یا نوک‌دار.';
    }

    public function paramsSchema(): array
    {
        return array_merge([
            'width' => ['label' => 'پهنای جیب', 'min' => 6, 'max' => 26, 'step' => 0.5, 'default' => 13, 'unit' => 'سانتی‌متر'],
            'height' => ['label' => 'بلندی جیب', 'min' => 6, 'max' => 28, 'step' => 0.5, 'default' => 14, 'unit' => 'سانتی‌متر'],
            'shape' => [
                'label' => 'کف جیب', 'type' => 'select', 'default' => 'square',
                'options' => ['square' => 'چهارگوش', 'round' => 'گرد', 'point' => 'نوک‌دار'],
            ],
            'top_hem' => [
                'label' => 'لبه بالای جیب', 'min' => 1.5, 'max' => 6, 'step' => 0.5, 'default' => 3.5, 'unit' => 'سانتی‌متر',
                'hint' => 'این اندازه به بالای جیب اضافه و تو برگردانده می‌شود.',
            ],
            'flap' => ['label' => 'درپوش داشته باشد', 'type' => 'toggle', 'default' => false],
            'count' => [
                'label' => 'چند تا', 'type' => 'select', 'default' => '1',
                'options' => ['1' => 'یکی', '2' => 'یک جفت'],
            ],
        ], $this->placementParams());
    }

    public function apply(array $pieces, array $context): array
    {
        $index = $this->firstIndexOfParts($pieces, $this->hostParts($context));

        if ($index === null) {
            return $this->result($pieces, [$this->note('warning', 'قطعه‌ای برای گذاشتن جیب رودوزی پیدا نشد.')]);
        }

        $width = $this->num($context, 'width', 13);
        $height = $this->num($context, 'height', 14);
        $topHem = $this->num($context, 'top_hem', 3.5);
        $shape = $this->text($context, 'shape', 'square');
        $count = max(1, (int) $this->num($context, 'count', 1));

        [$x, $y] = $this->anchor($pieces[$index], $context, $width, $height);

        $host = $pieces[$index];
        $pieces[$index] = $this->markPlacement($host, $x, $y, $width, $height, 'جای جیب رودوزی');

        $full = $height + $topHem;

        $outline = match ($shape) {
            'round' => $this->roundedBottom($width, $full, min(4.0, $width / 3)),
            'point' => $this->pointedBottom($width, $full, min(4.0, $height / 3)),
            default => $this->rect($width, $full),
        };

        $pocket = $this->piece([
            'code' => 'pocket-patch',
            'name' => 'جیب رودوزی',
            'cut_quantity' => $count,
            'outline' => $outline,
            'grainline' => $this->grainline($width * 0.5, 1, $full - 1),
            'markers' => [
                $this->marker('fold', 'خط تای لبه بالای جیب', 0, $topHem, $width, $topHem),
            ],
            'notches' => [
                $this->notch(0, $topHem, count($outline) - 1, 'تای لبه بالا'),
            ],
            'meta' => [
                'part' => 'pocket',
                'edges' => array_merge(['hem'], array_fill(1, count($outline) - 1, 'default')),
                'fold_edges' => [],
                'pocket' => static::key(),
                'placed_on' => $this->partOf($host),
                'placement' => ['x' => $x, 'y' => $y],
            ],
        ]);

        $result = [$pocket];
        $notes = [
            $this->note('tip', 'جیب رودوزی '.Format::cm($width).'×'.Format::cm($height)
                .' روی «'.$host['name'].'» گذاشته شد؛ چهار سوراخ مته جای دقیق آن را نشان می‌دهد.'),
            $this->note('info', 'بالای جیب '.Format::cm($topHem).' بلندتر بریده شده تا تو برگردد؛ '
                .'دور جیب هم جای دوخت می‌خواهد.'),
        ];

        if ($this->flag($context, 'flap', false)) {
            $result[] = $this->flapPiece($width + 0.6, max(4.0, $height * 0.35), 'pocket-patch-flap', 'درپوش جیب رودوزی', $shape === 'round' ? 'round' : 'square');
            $notes[] = $this->note('info', 'درپوش جیب کمی پهن‌تر از خود جیب بریده شد تا رویش بخوابد.');
        }

        return $this->result(array_merge($pieces, $result), $notes, [
            'placement' => ['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height],
        ]);
    }
}
