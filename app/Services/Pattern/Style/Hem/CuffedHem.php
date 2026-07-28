<?php

namespace App\Services\Pattern\Style\Hem;

use App\Support\Format;

/**
 * لبه‌برگردان: دم لباس به بیرون برمی‌گردد و دیده می‌شود (دوبل).
 *
 * قطعه به اندازه دو برابر برگردان بلندتر می‌شود، چون یک بار به بیرون و یک بار به
 * داخل تا می‌خورد.
 */
class CuffedHem extends BaseHem
{
    public static function key(): string
    {
        return 'hem_cuffed';
    }

    public function label(): string
    {
        return 'لبه‌برگردان (دوبل)';
    }

    public function description(): string
    {
        return 'دم لباس به بیرون تا می‌شود و دیده می‌شود؛ برای شلوار و آستین کوتاه.';
    }

    public function paramsSchema(): array
    {
        return [
            'depth' => [
                'label' => 'پهنای برگردان', 'min' => 1.5, 'max' => 10, 'step' => 0.5, 'default' => 4,
                'unit' => 'سانتی‌متر', 'hint' => 'همین اندازه از بیرون دیده می‌شود.',
            ],
            'include_sleeve' => [
                'label' => 'روی آستین هم اجرا شود', 'type' => 'toggle', 'default' => false,
            ],
        ];
    }

    public function apply(array $pieces, array $context): array
    {
        $depth = $this->num($context, 'depth', 4);
        $extra = round(($depth * 2) + 1, 2);
        $indexes = $this->hemHostIndexes($pieces);

        if ($this->flag($context, 'include_sleeve', false)) {
            $indexes = array_merge($indexes, $this->indexesWithTag($pieces, 'hem', ['sleeve']));
        }

        $names = [];

        foreach ($indexes as $index) {
            $piece = $pieces[$index];
            $edge = $this->edgeWithTag($piece, 'hem');

            if ($edge === null) {
                continue;
            }

            // قطعه به اندازه دو برابر برگردان بلندتر می‌شود
            $piece = $this->shapeHem($piece, $edge, $extra, $extra, 0.0);
            $piece = $this->setHemAllowance($piece, 0.0);
            $piece['meta']['hem_style'] = static::key();
            $piece['meta']['hem_turnup'] = round($depth, 2);

            $height = $this->heightOf($piece);
            $piece['markers'][] = $this->marker('fold', 'خط تای برگردان', 0, $height - $depth, 3, $height - $depth);
            $piece['markers'][] = $this->marker('fold', 'خط تای دوم برگردان', 0, $height - $extra, 3, $height - $extra);

            $pieces[$index] = $this->reindexAnchors($piece);
            $names[] = $piece['name'];
        }

        return $this->result($pieces, [
            $this->note('tip', 'لبه‌برگردان '.Format::cm($depth).'ی روی '.implode('، ', $names)
                .' گذاشته شد؛ قطعه‌ها '.Format::cm($extra).' بلندتر بریده شدند.'),
            $this->note('info', 'درز پهلو هم به همان اندازه بلندتر شد، پس جلو و پشت باز هم به هم می‌خورند.'),
            $this->fabricNote($extra, 'برگردان دم لباس'),
        ], ['depth' => round($depth, 2), 'added_length' => $extra]);
    }
}
