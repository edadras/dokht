<?php

namespace App\Services\Pattern\Style\Detail;

use App\Support\Format;

/** مچ کشی: نوار کشی که دم آستین را جمع می‌کند؛ بدون دکمه و بدون شکاف. */
class ElasticCuff extends BaseCuff
{
    public static function key(): string
    {
        return 'cuff_elastic';
    }

    public function label(): string
    {
        return 'مچ کشی';
    }

    public function description(): string
    {
        return 'نوار کشباف یا جای کش سر آستین؛ بدون دکمه و شکاف، مخصوص لباس راحتی.';
    }

    public function paramsSchema(): array
    {
        return [
            'height' => ['label' => 'بلندی مچ', 'min' => 3, 'max' => 12, 'step' => 0.5, 'default' => 6, 'unit' => 'سانتی‌متر'],
            'mode' => [
                'label' => 'جنس مچ', 'type' => 'select', 'default' => 'rib',
                'options' => ['rib' => 'کشباف (ریب)', 'casing' => 'جای کش روی خود پارچه'],
            ],
            'stretch' => [
                'label' => 'نسبت کشش', 'min' => 0.5, 'max' => 1, 'step' => 0.05, 'default' => 0.75,
                'hint' => 'مچ به این نسبت از دم آستین کوتاه‌تر بریده می‌شود.',
            ],
            'shorten' => ['label' => 'آستین به اندازه مچ کوتاه شود', 'type' => 'toggle', 'default' => true],
        ];
    }

    public function apply(array $pieces, array $context): array
    {
        $hosts = $this->cuffHosts($pieces);

        if ($hosts === []) {
            return $this->result($pieces, [$this->note('warning', 'آستینی برای مچ کشی پیدا نشد.')]);
        }

        $height = $this->num($context, 'height', 6);
        $mode = $this->text($context, 'mode', 'rib');
        $stretch = min(1.0, max(0.5, $this->num($context, 'stretch', 0.75)));

        $index = $hosts[0];
        $piece = $pieces[$index];
        $edge = $this->edgeWithTag($piece, 'hem');

        $flat = $this->edgeLength($piece, $edge);
        $finished = round($flat - $this->consumedOn($piece, $edge), 2);
        $wrist = $this->wristTarget($context, 3);

        $notes = [];
        $extra = [];

        if ($mode === 'casing') {
            // بدون قطعه جدا: خود دم آستین برگردانده می‌شود و کش داخلش می‌رود
            $piece['meta']['allowance_overrides']['hem'] = round($height + 1.5, 2);
            $piece['meta']['notions'][] = [
                'type' => 'elastic',
                'label' => 'کش دم آستین',
                'length' => $wrist,
                'count' => 2,
            ];
            $piece['markers'][] = $this->marker('fold', 'خط تای جای کش', 0, 0, 3, 0);

            $notes[] = $this->note('tip', 'دم آستین '.Format::cm($height + 1.5)
                .' برگردانده می‌شود تا جای کش درست شود؛ کش '.Format::cm($wrist).'ی داخلش می‌رود.');
            $notes[] = $this->note('info', 'دم آستین '.Format::cm($finished)
                .' است و با کش به '.Format::cm($wrist).' جمع می‌شود.');
            $notes[] = $this->fabricNote($height + 1.5, 'جای کش دم آستین');
        } else {
            $length = round(max($wrist, $finished * $stretch), 2);
            $band = $this->foldedBand($length, $height, 'cuff-rib', 'مچ کشباف', [
                'part' => 'cuff',
                'edges' => ['hem', 'side', 'default', 'side'],
                'sleeve_hem' => $finished,
                'stretch' => $stretch,
                'fabric' => 'کشباف',
                'detail' => static::key(),
            ], 2);

            $extra[] = $band;

            $notes[] = $this->note('tip', 'مچ کشباف '.Format::cm($length).' بریده شد، یعنی '
                .Format::cm(round($finished - $length, 2)).' کوتاه‌تر از دم آستین ('
                .Format::cm($finished).')؛ همین اختلاف با کشیدن مچ جمع می‌شود.');
            $notes[] = $this->note('warning', 'این مچ فقط با پارچه کشباف کار می‌کند؛ با پارچه معمولی دست رد نمی‌شود.');
        }

        if ($this->flag($context, 'shorten', true)) {
            $piece = $this->shortenSleeve($piece, $edge, $height);
            $notes[] = $this->note('info', 'آستین '.Format::cm($height).' کوتاه شد تا قد آستین ثابت بماند.');
        }

        $pieces[$index] = $piece;

        return $this->result(array_merge($pieces, $extra), $notes, [
            'finished_hem' => $finished,
            'mode' => $mode,
        ]);
    }
}
