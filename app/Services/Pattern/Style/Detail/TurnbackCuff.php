<?php

namespace App\Services\Pattern\Style\Detail;

use App\Services\Pattern\Geometry;
use App\Support\Format;

/**
 * سر آستین لبه‌برگردان: نواری که روی خود آستین برمی‌گردد و دیده می‌شود.
 *
 * چون روی آستین می‌خوابد، باید به اندازه پهنای آستین در همان ارتفاعِ برگشت بریده
 * شود، نه به اندازه مچ؛ وگرنه برگردان بالا نمی‌رود.
 */
class TurnbackCuff extends BaseCuff
{
    public static function key(): string
    {
        return 'cuff_turnback';
    }

    public function label(): string
    {
        return 'سر آستین لبه‌برگردان';
    }

    public function description(): string
    {
        return 'نوار پهنی که روی آستین برمی‌گردد؛ برای آستین کوتاه و سه‌ربع.';
    }

    public function paramsSchema(): array
    {
        return [
            'height' => ['label' => 'بلندی برگردان', 'min' => 2, 'max' => 10, 'step' => 0.5, 'default' => 5, 'unit' => 'سانتی‌متر'],
            'flare' => [
                'label' => 'گشادی لبه بالا', 'min' => 0, 'max' => 6, 'step' => 0.5, 'default' => 1.5,
                'unit' => 'سانتی‌متر', 'hint' => 'چون آستین به سمت بالا پهن‌تر می‌شود، لبه بالای برگردان باید گشادتر باشد.',
            ],
            'shorten' => ['label' => 'آستین به اندازه برگردان کوتاه شود', 'type' => 'toggle', 'default' => false],
        ];
    }

    public function apply(array $pieces, array $context): array
    {
        $hosts = $this->cuffHosts($pieces);

        if ($hosts === []) {
            return $this->result($pieces, [$this->note('warning', 'آستینی برای لبه‌برگردان پیدا نشد.')]);
        }

        $height = $this->num($context, 'height', 5);
        $flare = $this->num($context, 'flare', 1.5);

        $index = $hosts[0];
        $piece = $pieces[$index];
        $edge = $this->edgeWithTag($piece, 'hem');

        $hem = round($this->edgeLength($piece, $edge) - $this->consumedOn($piece, $edge), 2);
        $top = round($hem + $flare, 2);

        $notes = [];

        if ($this->flag($context, 'shorten', false)) {
            $piece = $this->shortenSleeve($piece, $edge, $height);
            $notes[] = $this->note('info', 'آستین '.Format::cm($height).' کوتاه شد.');
        } else {
            $notes[] = $this->note('info', 'آستین کوتاه نشد؛ برگردان روی آستین می‌خوابد و قد آستین '
                .Format::cm($height).' بلندتر دیده می‌شود.');
        }

        $piece['markers'][] = $this->marker('fold', 'خط برگشت لبه', 0, 0, 3, 0);
        $pieces[$index] = $piece;

        // ذوزنقه: لبه پایین به اندازه دم آستین و لبه بالا گشادتر
        $full = $height * 2;
        $offset = ($top - $hem) / 2;

        $cuff = $this->piece([
            'code' => 'cuff-turnback',
            'name' => 'لبه‌برگردان آستین',
            'cut_quantity' => 4,
            'outline' => [
                Geometry::point($offset, 0),
                Geometry::point($offset + $hem, 0),
                Geometry::point($top, $full),
                Geometry::point(0, $full),
            ],
            'grainline' => $this->grainline($top / 2, 1, $full - 1),
            'markers' => [
                $this->marker('fold', 'خط تای برگردان', 0, $height, $top),
            ],
            'meta' => [
                'part' => 'cuff',
                'edges' => ['hem', 'side', 'default', 'side'],
                'fold_edges' => [],
                'interfacing' => true,
                'detail' => static::key(),
                'sleeve_hem' => $hem,
                'top_width' => $top,
            ],
        ]);

        $notes[] = $this->note('tip', 'لبه‌برگردان به اندازه دم آستین ('.Format::cm($hem)
            .') بریده شد و لبه بالایش '.Format::cm($top).' است تا روی آستینِ پهن‌تر بخوابد.');
        $notes[] = $this->note('info', 'چهار تکه بریده می‌شود: برای هر آستین یک رو و یک آستر.');
        $notes[] = $this->fabricNote($full, 'لبه‌برگردان آستین');

        return $this->result(array_merge($pieces, [$cuff]), $notes, [
            'sleeve_hem' => $hem,
            'top_width' => $top,
        ]);
    }
}
