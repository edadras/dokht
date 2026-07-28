<?php

namespace App\Services\Pattern\Style\Pocket;

use App\Support\Format;

/**
 * جیب فیلتابی: لباس بریده می‌شود و لبه برش با یک یا دو فیلتاب پوشانده می‌شود.
 *
 * فیلتاب همیشه از دهانه جیب بلندتر بریده می‌شود (۱٫۵ سانتی‌متر از هر سر) تا دو سر
 * برش زیر آن بماند و چهارگوش تمام شود.
 */
class WeltPocket extends BasePocket
{
    /** بلندی اضافه فیلتاب از هر سر دهانه. */
    public const OVERLAP = 1.5;

    public static function key(): string
    {
        return 'pocket_welt';
    }

    public function label(): string
    {
        return 'جیب فیلتابی';
    }

    public function description(): string
    {
        return 'دهانه بریده می‌شود و با یک فیلتاب (یا دو فیلتاب باریک) پوشانده می‌شود.';
    }

    public function paramsSchema(): array
    {
        return array_merge([
            'opening' => ['label' => 'دهانه جیب', 'min' => 8, 'max' => 22, 'step' => 0.5, 'default' => 14, 'unit' => 'سانتی‌متر'],
            'welt_height' => ['label' => 'پهنای فیلتاب', 'min' => 0.6, 'max' => 5, 'step' => 0.2, 'default' => 2, 'unit' => 'سانتی‌متر'],
            'double' => ['label' => 'دو فیلتابی', 'type' => 'toggle', 'default' => false],
            'angle' => ['label' => 'شیب دهانه', 'min' => -30, 'max' => 30, 'step' => 1, 'default' => 0, 'unit' => 'درجه'],
            'bag_depth' => ['label' => 'عمق کیسه', 'min' => 8, 'max' => 24, 'step' => 0.5, 'default' => 15, 'unit' => 'سانتی‌متر'],
        ], $this->placementParams());
    }

    public function apply(array $pieces, array $context): array
    {
        $index = $this->firstIndexOfParts($pieces, $this->hostParts($context));

        if ($index === null) {
            return $this->result($pieces, [$this->note('warning', 'قطعه‌ای برای جیب فیلتابی پیدا نشد.')]);
        }

        $opening = $this->num($context, 'opening', 14);
        $welt = $this->num($context, 'welt_height', 2);
        $double = $this->flag($context, 'double', false);
        $angle = $this->num($context, 'angle', 0);
        $depth = $this->num($context, 'bag_depth', 15);

        $host = $pieces[$index];
        [$x, $y] = $this->anchor($host, $context, $opening, max(3.0, $welt * 2));

        $host = $this->addOpening($host, $x, $y, $opening, $angle, 'welt', 'برش دهانه جیب فیلتابی');
        $host['meta']['pockets'][] = [
            'key' => static::key(),
            'label' => 'جیب فیلتابی',
            'x' => $x,
            'y' => $y,
            'width' => round($opening, 2),
            'height' => round($welt, 2),
        ];
        $pieces[$index] = $host;

        $weltLength = round($opening + (2 * static::OVERLAP), 2);
        $weltCut = round(($welt * 2) + 2, 2); // دولا + جای دوخت دو طرف

        $pocketPieces = [
            $this->piece([
                'code' => $double ? 'pocket-welt-strip' : 'pocket-welt',
                'name' => $double ? 'فیلتاب (دو تکه)' : 'فیلتاب جیب',
                'cut_quantity' => $double ? 4 : 2,
                'outline' => $this->rect($weltLength, $double ? round($welt * 2 + 1.5, 2) : $weltCut),
                'grainline' => $this->grainline($weltLength * 0.5, 0.6, ($double ? $welt * 2 + 1.5 : $weltCut) - 0.6),
                'markers' => [
                    $this->marker('fold', 'خط تای فیلتاب', 0, $double ? $welt : $welt + 1, $weltLength),
                ],
                'meta' => [
                    'part' => 'pocket_welt',
                    'edges' => ['default', 'default', 'default', 'default'],
                    'fold_edges' => [],
                    'interfacing' => true,
                    'pocket' => static::key(),
                    'covers_opening' => round($opening, 2),
                    'finished_length' => $weltLength,
                ],
            ]),
            $this->bagPiece($opening + 4, $depth, 'pocket-welt-bag', 'کیسه جیب فیلتابی', 2),
            $this->piece([
                'code' => 'pocket-welt-facing',
                'name' => 'سجاف کیسه جیب',
                'cut_quantity' => 2,
                'outline' => $this->rect($opening + 4, 6),
                'grainline' => $this->grainline(($opening + 4) * 0.5, 0.8, 5.2),
                'meta' => [
                    'part' => 'pocket_facing',
                    'edges' => ['default', 'default', 'default', 'default'],
                    'fold_edges' => [],
                    'pocket' => static::key(),
                ],
            ]),
        ];

        $notes = [
            $this->note('tip', 'دهانه '.Format::cm($opening).' روی «'.$pieces[$index]['name']
                .'» بریده شد؛ فیلتاب '.Format::cm($weltLength).' یعنی '
                .Format::cm(static::OVERLAP).' از هر سر روی برش را می‌پوشاند.'),
            $this->note('warning', 'خط برش دهانه با دو سوراخ مته علامت خورده است؛ '
                .'سر برش را به شکل مثلث (Y) بزنید تا گوشه‌ها تیز بمانند.'),
            $this->fabricNote($depth + 6, 'کیسه و سجاف این جیب'),
        ];

        if ($double) {
            $notes[] = $this->note('info', 'دو فیلتاب باریک بالا و پایین دهانه می‌نشیند؛ چهار نوار بریده می‌شود (دو تا برای هر جیب).');
        }

        return $this->result(array_merge($pieces, $pocketPieces), $notes, [
            'opening' => round($opening, 2),
            'welt_length' => $weltLength,
        ]);
    }
}
