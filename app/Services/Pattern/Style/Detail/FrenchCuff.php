<?php

namespace App\Services\Pattern\Style\Detail;

use App\Support\Format;

/** مچ دوبل (فرانسوی): دو برابر بلندی بریده می‌شود، برمی‌گردد و با دکمه سردست بسته می‌شود. */
class FrenchCuff extends BaseCuff
{
    public static function key(): string
    {
        return 'cuff_french';
    }

    public function label(): string
    {
        return 'مچ دوبل (فرانسوی)';
    }

    public function description(): string
    {
        return 'مچ بلند که به بیرون برمی‌گردد و با دکمه سردست بسته می‌شود؛ مچ پیراهن مجلسی.';
    }

    public function paramsSchema(): array
    {
        return [
            'height' => ['label' => 'بلندی دیده‌شده', 'min' => 4, 'max' => 9, 'step' => 0.5, 'default' => 6, 'unit' => 'سانتی‌متر'],
            'ease' => ['label' => 'آزادی دور مچ', 'min' => 1, 'max' => 12, 'step' => 0.5, 'default' => 5, 'unit' => 'سانتی‌متر'],
            'placket' => ['label' => 'شکاف مچ داشته باشد', 'type' => 'toggle', 'default' => true],
            'shorten' => ['label' => 'آستین به اندازه مچ کوتاه شود', 'type' => 'toggle', 'default' => true],
        ];
    }

    public function apply(array $pieces, array $context): array
    {
        $hosts = $this->cuffHosts($pieces);

        if ($hosts === []) {
            return $this->result($pieces, [$this->note('warning', 'آستینی برای مچ دوبل پیدا نشد.')]);
        }

        $height = $this->num($context, 'height', 6);
        $target = $this->wristTarget($context, $this->num($context, 'ease', 5));

        $index = $hosts[0];
        $piece = $pieces[$index];
        $edge = $this->edgeWithTag($piece, 'hem');

        $fit = $this->fitSleeveHem($piece, $edge, $target, 'pleat');
        $piece = $fit['piece'];

        $extra = [];
        $length = round($fit['finished'] + 1.5, 2); // مچ دوبل روی هم نمی‌افتد؛ دو لبه کنار هم می‌ایستند
        $notes = [$this->fitNote($fit, $length)];

        if ($this->flag($context, 'placket', true)) {
            [$piece, $placket] = $this->plackedPieces($piece, $edge, $height + 5, 'cuff-french-placket');
            $extra[] = $placket;
        }

        if ($this->flag($context, 'shorten', true)) {
            $piece = $this->shortenSleeve($piece, $edge, $height);
            $notes[] = $this->note('info', 'آستین '.Format::cm($height).' کوتاه شد تا قد آستین با مچ درست بماند.');
        }

        $pieces[$index] = $piece;

        // بلندی برش: دو برابر بلندی دیده‌شده، دولا ⇒ چهار برابر
        $cuff = $this->foldedBand($length, $height * 2, 'cuff-french', 'مچ دوبل', [
            'part' => 'cuff',
            'edges' => ['hem', 'side', 'default', 'side'],
            'sleeve_hem' => $fit['finished'],
            'turnback' => round($height, 2),
            'detail' => static::key(),
        ], 2);

        foreach ([1.5, $length - 1.5] as $i => $x) {
            $cuff['drills'][] = $this->drill($x, $height, 'buttonhole', 'جای دکمه سردست '.($i + 1));
        }

        $notes[] = $this->note('info', 'مچ دوبل '.Format::cm($length).' طول و '
            .Format::cm($height * 4).' ارتفاع برش دارد؛ نصف می‌شود و بعد برمی‌گردد، پس '
            .Format::cm($height).' دیده می‌شود.');
        $notes[] = $this->note('warning', 'چهار جادکمه روبه‌روی هم لازم است چون با دکمه سردست بسته می‌شود.');
        $notes[] = $this->fabricNote($height * 4, 'مچ دوبل');

        return $this->result(array_merge($pieces, [$cuff], $extra), $notes, [
            'length' => $length,
            'finished_hem' => $fit['finished'],
        ]);
    }
}
