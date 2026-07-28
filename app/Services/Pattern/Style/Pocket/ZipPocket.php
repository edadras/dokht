<?php

namespace App\Services\Pattern\Style\Pocket;

use App\Support\Format;

/** جیب زیپ‌دار: دهانه بریده می‌شود و زیپ زیر آن می‌نشیند. */
class ZipPocket extends BasePocket
{
    public static function key(): string
    {
        return 'pocket_zip';
    }

    public function label(): string
    {
        return 'جیب زیپ‌دار';
    }

    public function description(): string
    {
        return 'دهانه بریده و با زیپ بسته می‌شود؛ برای کاپشن، سویشرت و جیب پشت شلوار.';
    }

    public function paramsSchema(): array
    {
        return array_merge([
            'opening' => ['label' => 'دهانه جیب', 'min' => 8, 'max' => 24, 'step' => 0.5, 'default' => 15, 'unit' => 'سانتی‌متر'],
            'angle' => ['label' => 'شیب دهانه', 'min' => -45, 'max' => 45, 'step' => 1, 'default' => 0, 'unit' => 'درجه'],
            'bag_depth' => ['label' => 'عمق کیسه', 'min' => 10, 'max' => 26, 'step' => 0.5, 'default' => 16, 'unit' => 'سانتی‌متر'],
        ], $this->placementParams());
    }

    public function apply(array $pieces, array $context): array
    {
        $index = $this->firstIndexOfParts($pieces, $this->hostParts($context));

        if ($index === null) {
            return $this->result($pieces, [$this->note('warning', 'قطعه‌ای برای جیب زیپ‌دار پیدا نشد.')]);
        }

        $opening = $this->num($context, 'opening', 15);
        $angle = $this->num($context, 'angle', 0);
        $depth = $this->num($context, 'bag_depth', 16);

        $host = $pieces[$index];
        [$x, $y] = $this->anchor($host, $context, $opening, 4.0);

        $host = $this->addOpening($host, $x, $y, $opening, $angle, 'zip', 'برش دهانه جیب زیپ‌دار');
        $host['meta']['pockets'][] = [
            'key' => static::key(),
            'label' => 'جیب زیپ‌دار',
            'x' => $x,
            'y' => $y,
            'width' => round($opening, 2),
            'height' => 1.2,
        ];
        $host['meta']['notions'][] = [
            'type' => 'zip',
            'label' => 'زیپ جیب',
            'length' => round($opening + 3, 2),
            'count' => 1,
        ];
        $pieces[$index] = $host;

        $facing = $this->piece([
            'code' => 'pocket-zip-facing',
            'name' => 'سجاف دهانه زیپ',
            'cut_quantity' => 2,
            'outline' => $this->rect($opening + 4, 6),
            'grainline' => $this->grainline(($opening + 4) * 0.5, 0.8, 5.2),
            'markers' => [
                $this->marker('opening', 'خط برش دهانه', 2, 3, $opening + 2, 3),
            ],
            'meta' => [
                'part' => 'pocket_facing',
                'edges' => ['default', 'default', 'default', 'default'],
                'fold_edges' => [],
                'interfacing' => true,
                'pocket' => static::key(),
                'covers_opening' => round($opening, 2),
            ],
        ]);

        $bag = $this->bagPiece($opening + 4, $depth, 'pocket-zip-bag', 'کیسه جیب زیپ‌دار', 2);

        return $this->result(array_merge($pieces, [$facing, $bag]), [
            $this->note('tip', 'دهانه '.Format::cm($opening).' بریده شد و زیپ '
                .Format::cm($opening + 3).'ی زیر آن می‌نشیند.'),
            $this->note('info', 'سجاف دهانه از هر طرف ۲ سانتی‌متر بزرگ‌تر از برش است و لایی می‌خورد؛ '
                .'کیسه پشت آن دوخته می‌شود.'),
            $this->fabricNote($depth + 6, 'کیسه و سجاف جیب زیپ‌دار'),
        ], ['opening' => round($opening, 2), 'zip' => round($opening + 3, 2)]);
    }
}
