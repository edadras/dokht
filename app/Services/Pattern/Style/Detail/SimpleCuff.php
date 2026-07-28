<?php

namespace App\Services\Pattern\Style\Detail;

use App\Support\Format;

/** مچ ساده: نوار دولا با دکمه، همان مچ پیراهن معمولی. */
class SimpleCuff extends BaseCuff
{
    public static function key(): string
    {
        return 'cuff_simple';
    }

    public function label(): string
    {
        return 'مچ ساده';
    }

    public function description(): string
    {
        return 'نوار دولای دکمه‌دار سر آستین؛ دم آستین با پیلی داخل آن جمع می‌شود.';
    }

    public function paramsSchema(): array
    {
        return [
            'height' => ['label' => 'بلندی مچ', 'min' => 3, 'max' => 12, 'step' => 0.5, 'default' => 6, 'unit' => 'سانتی‌متر'],
            'ease' => ['label' => 'آزادی دور مچ', 'min' => 1, 'max' => 12, 'step' => 0.5, 'default' => 4, 'unit' => 'سانتی‌متر'],
            'overlap' => ['label' => 'روی هم افتادن (جای دکمه)', 'min' => 1, 'max' => 5, 'step' => 0.5, 'default' => 2, 'unit' => 'سانتی‌متر'],
            'placket' => ['label' => 'شکاف مچ داشته باشد', 'type' => 'toggle', 'default' => true],
            'shorten' => [
                'label' => 'آستین به اندازه مچ کوتاه شود', 'type' => 'toggle', 'default' => true,
                'hint' => 'اگر خاموش باشد، آستین به اندازه بلندی مچ بلندتر می‌شود.',
            ],
        ];
    }

    public function apply(array $pieces, array $context): array
    {
        $hosts = $this->cuffHosts($pieces);

        if ($hosts === []) {
            return $this->result($pieces, [$this->note('warning', 'آستینی برای مچ پیدا نشد.')]);
        }

        $height = $this->num($context, 'height', 6);
        $overlap = $this->num($context, 'overlap', 2);
        $target = $this->wristTarget($context, $this->num($context, 'ease', 4));

        $index = $hosts[0];
        $piece = $pieces[$index];
        $edge = $this->edgeWithTag($piece, 'hem');

        $fit = $this->fitSleeveHem($piece, $edge, $target, 'pleat');
        $piece = $fit['piece'];

        $extra = [];
        $notes = [$this->fitNote($fit, round($fit['finished'] + $overlap, 2))];

        if ($this->flag($context, 'placket', true)) {
            [$piece, $placket] = $this->plackedPieces($piece, $edge, $height + 4, 'cuff-placket');
            $extra[] = $placket;
            $notes[] = $this->note('info', 'شکاف مچ '.Format::cm($height + 4)
                .'ی روی دم آستین علامت خورد و نوار پاتلت آن جدا بریده شد.');
        }

        if ($this->flag($context, 'shorten', true)) {
            $piece = $this->shortenSleeve($piece, $edge, $height);
            $notes[] = $this->note('info', 'آستین '.Format::cm($height)
                .' کوتاه شد تا با مچ، قد آستین همان اندازه بماند.');
        } else {
            $notes[] = $this->note('warning', 'آستین کوتاه نشد، پس لباس '.Format::cm($height).' بلندتر می‌شود.');
        }

        $pieces[$index] = $piece;

        $length = round($fit['finished'] + $overlap, 2);
        $cuff = $this->foldedBand($length, $height, 'cuff', 'مچ آستین', [
            'part' => 'cuff',
            'edges' => ['hem', 'side', 'default', 'side'],
            'sleeve_hem' => $fit['finished'],
            'overlap' => round($overlap, 2),
            'detail' => static::key(),
        ], 2);

        $cuff['drills'][] = $this->drill($length - ($overlap / 2), $height * 0.5, 'buttonhole', 'جادکمه مچ');
        $cuff['drills'][] = $this->drill($overlap / 2, $height * 0.5, 'button', 'دکمه مچ');

        $notes[] = $this->note('info', 'مچ '.Format::cm($length).'×'.Format::cm($height)
            .' بریده شد ('.Format::cm($overlap).' روی هم برای دکمه) و دولا تا می‌خورد، پس ارتفاع برش '
            .Format::cm($height * 2).' است.');

        return $this->result(array_merge($pieces, [$cuff], $extra), $notes, [
            'length' => $length,
            'finished_hem' => $fit['finished'],
        ]);
    }
}
