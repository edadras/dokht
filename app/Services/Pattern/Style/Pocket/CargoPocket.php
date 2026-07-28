<?php

namespace App\Services\Pattern\Style\Pocket;

use App\Support\Format;

/**
 * جیب کارگو: جیب رودوزی حجم‌دار با نوار جناغی (گاست) دور آن و درپوش دکمه‌دار.
 */
class CargoPocket extends BasePocket
{
    public static function key(): string
    {
        return 'pocket_cargo';
    }

    public function label(): string
    {
        return 'جیب کارگو';
    }

    public function description(): string
    {
        return 'جیب بزرگ حجم‌دار با نوار کناری و درپوش؛ برای شلوار و کت اسپرت.';
    }

    public function paramsSchema(): array
    {
        return array_merge([
            'width' => ['label' => 'پهنای جیب', 'min' => 10, 'max' => 26, 'step' => 0.5, 'default' => 16, 'unit' => 'سانتی‌متر'],
            'height' => ['label' => 'بلندی جیب', 'min' => 10, 'max' => 28, 'step' => 0.5, 'default' => 18, 'unit' => 'سانتی‌متر'],
            'gusset' => [
                'label' => 'پهنای گاست', 'min' => 1.5, 'max' => 6, 'step' => 0.5, 'default' => 3,
                'unit' => 'سانتی‌متر', 'hint' => 'همین اندازه به حجم جیب اضافه می‌کند.',
            ],
            'flap_height' => ['label' => 'بلندی درپوش', 'min' => 3, 'max' => 10, 'step' => 0.5, 'default' => 5.5, 'unit' => 'سانتی‌متر'],
        ], $this->placementParams());
    }

    public function apply(array $pieces, array $context): array
    {
        $index = $this->firstIndexOfParts($pieces, $this->hostParts($context));

        if ($index === null) {
            return $this->result($pieces, [$this->note('warning', 'قطعه‌ای برای جیب کارگو پیدا نشد.')]);
        }

        $width = $this->num($context, 'width', 16);
        $height = $this->num($context, 'height', 18);
        $gusset = $this->num($context, 'gusset', 3);
        $flapHeight = $this->num($context, 'flap_height', 5.5);

        $host = $pieces[$index];
        [$x, $y] = $this->anchor($host, $context, $width, $height + $flapHeight);
        $pieces[$index] = $this->markPlacement($host, $x, $y, $width, $height, 'جای جیب کارگو');

        $bodyHeight = $height + 4; // ۴ سانتی‌متر لبه بالای جیب
        $gussetLength = round(($height * 2) + $width, 2);

        $body = $this->piece([
            'code' => 'pocket-cargo',
            'name' => 'بدنه جیب کارگو',
            'cut_quantity' => 2,
            'outline' => $this->rect($width, $bodyHeight),
            'grainline' => $this->grainline($width * 0.5, 1, $bodyHeight - 1),
            'markers' => [
                $this->marker('fold', 'خط تای لبه بالا', 0, 4, $width),
            ],
            'meta' => [
                'part' => 'pocket',
                'edges' => ['hem', 'default', 'default', 'default'],
                'fold_edges' => [],
                'pocket' => static::key(),
                'placed_on' => $this->partOf($host),
                'placement' => ['x' => $x, 'y' => $y],
            ],
        ]);

        $gussetPiece = $this->piece([
            'code' => 'pocket-cargo-gusset',
            'name' => 'گاست جیب کارگو',
            'cut_quantity' => 2,
            'outline' => $this->rect($gussetLength, ($gusset * 2) + 2),
            'grainline' => $this->grainline($gussetLength * 0.5, 1, ($gusset * 2) + 1),
            'markers' => [
                $this->marker('fold', 'خط تای گاست', 0, $gusset + 1, $gussetLength),
            ],
            'meta' => [
                'part' => 'pocket_gusset',
                'edges' => ['default', 'default', 'default', 'default'],
                'fold_edges' => [],
                'pocket' => static::key(),
                'wraps' => $gussetLength,
            ],
        ]);

        $flap = $this->flapPiece($width + 1, $flapHeight, 'pocket-cargo-flap', 'درپوش جیب کارگو', 'square');
        $flap['drills'][] = $this->drill(($width + 1) / 2, $flapHeight - 1.5, 'buttonhole', 'جادکمه درپوش');

        return $this->result(array_merge($pieces, [$body, $gussetPiece, $flap]), [
            $this->note('tip', 'جیب کارگو '.Format::cm($width).'×'.Format::cm($height)
                .' با گاست '.Format::cm($gusset).' گذاشته شد؛ گاست دور سه لبه جیب می‌پیچد ('
                .Format::cm($gussetLength).').'),
            $this->note('info', 'درپوش '.Format::cm($width + 1).' یعنی از خود جیب پهن‌تر است تا رویش بخوابد؛ '
                .'یک جادکمه وسط آن علامت خورده است.'),
            $this->fabricNote($bodyHeight + ($gusset * 2) + $flapHeight, 'هر جیب کارگو'),
        ], ['gusset' => round($gusset, 2)]);
    }
}
