<?php

namespace App\Services\Pattern\Style\Detail;

use App\Support\Format;

/** مچ چین‌دار: دم آستین چین می‌خورد و داخل یک مچ باریک می‌نشیند، با یا بدون فرفری. */
class GatheredCuff extends BaseCuff
{
    public static function key(): string
    {
        return 'cuff_gathered';
    }

    public function label(): string
    {
        return 'مچ چین‌دار';
    }

    public function description(): string
    {
        return 'دم آستین چین می‌خورد و داخل مچ باریک می‌رود؛ با فرفری اختیاری زیر مچ.';
    }

    public function paramsSchema(): array
    {
        return [
            'height' => ['label' => 'بلندی مچ', 'min' => 2, 'max' => 10, 'step' => 0.5, 'default' => 4, 'unit' => 'سانتی‌متر'],
            'ease' => ['label' => 'آزادی دور مچ', 'min' => 1, 'max' => 12, 'step' => 0.5, 'default' => 4, 'unit' => 'سانتی‌متر'],
            'overlap' => ['label' => 'روی هم افتادن', 'min' => 1, 'max' => 5, 'step' => 0.5, 'default' => 2, 'unit' => 'سانتی‌متر'],
            'frill' => ['label' => 'فرفری زیر مچ', 'min' => 0, 'max' => 12, 'step' => 0.5, 'default' => 0, 'unit' => 'سانتی‌متر'],
            'shorten' => ['label' => 'آستین به اندازه مچ کوتاه شود', 'type' => 'toggle', 'default' => true],
        ];
    }

    public function apply(array $pieces, array $context): array
    {
        $hosts = $this->cuffHosts($pieces);

        if ($hosts === []) {
            return $this->result($pieces, [$this->note('warning', 'آستینی برای مچ چین‌دار پیدا نشد.')]);
        }

        $height = $this->num($context, 'height', 4);
        $overlap = $this->num($context, 'overlap', 2);
        $frill = $this->num($context, 'frill', 0);
        $target = $this->wristTarget($context, $this->num($context, 'ease', 4));

        $index = $hosts[0];
        $piece = $pieces[$index];
        $edge = $this->edgeWithTag($piece, 'hem');

        $fit = $this->fitSleeveHem($piece, $edge, $target, 'gather');
        $piece = $fit['piece'];

        $notes = [$this->fitNote($fit, round($fit['finished'] + $overlap, 2))];

        if ($this->flag($context, 'shorten', true)) {
            $piece = $this->shortenSleeve($piece, $edge, $height + $frill);
        }

        $pieces[$index] = $piece;

        $length = round($fit['finished'] + $overlap, 2);
        $cuff = $this->foldedBand($length, $height, 'cuff-gathered', 'مچ چین‌دار', [
            'part' => 'cuff',
            'edges' => ['hem', 'side', 'default', 'side'],
            'sleeve_hem' => $fit['finished'],
            'gathered' => $fit['taken'],
            'detail' => static::key(),
        ], 2);

        $cuff['drills'][] = $this->drill($length - ($overlap / 2), $height * 0.5, 'buttonhole', 'جادکمه مچ');
        $extra = [$cuff];

        if ($frill > 0.2) {
            $frillLength = round($length * 1.8, 2);
            $extra[] = $this->piece([
                'code' => 'cuff-frill',
                'name' => 'فرفری زیر مچ',
                'cut_quantity' => 2,
                'outline' => $this->rect($frillLength, $frill + 1),
                'grainline' => $this->grainline($frillLength * 0.5, 0.5, $frill + 0.5),
                'meta' => [
                    'part' => 'frill',
                    'edges' => ['default', 'side', 'hem', 'side'],
                    'fold_edges' => [],
                    'gathers' => [['edge' => 0, 'amount' => round($frillLength - $length, 2), 'label' => 'چین فرفری']],
                    'detail' => static::key(),
                ],
            ]);

            $notes[] = $this->note('info', 'فرفری '.Format::cm($frill).'ی با '.Format::cm($frillLength)
                .' طول (یک‌ونیم برابر مچ) بریده شد تا چین‌خورده زیر مچ بنشیند.');
        }

        $notes[] = $this->note('info', 'مچ '.Format::cm($length).'×'.Format::cm($height)
            .' و دولا؛ ارتفاع برش '.Format::cm($height * 2).' است.');

        return $this->result(array_merge($pieces, $extra), $notes, [
            'length' => $length,
            'gathered' => $fit['taken'],
        ]);
    }
}
