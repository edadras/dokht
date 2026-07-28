<?php

namespace App\Services\Pattern\Style\Pocket;

use App\Support\Format;

/** جیب ساعتی: جیب کوچک زیر خط کمر، معمولاً داخل جیب مورب یا روی شلوار. */
class CoinPocket extends BasePocket
{
    public static function key(): string
    {
        return 'pocket_coin';
    }

    public function label(): string
    {
        return 'جیب ساعتی';
    }

    public function description(): string
    {
        return 'جیب کوچک زیر خط کمر برای سکه و ساعت؛ روی شلوار و جلیقه.';
    }

    public function paramsSchema(): array
    {
        return array_merge([
            'width' => ['label' => 'پهنای جیب', 'min' => 4, 'max' => 12, 'step' => 0.5, 'default' => 7, 'unit' => 'سانتی‌متر'],
            'height' => ['label' => 'بلندی جیب', 'min' => 4, 'max' => 12, 'step' => 0.5, 'default' => 7, 'unit' => 'سانتی‌متر'],
            'from_waist' => [
                'label' => 'فاصله از خط کمر', 'min' => 0, 'max' => 8, 'step' => 0.5, 'default' => 1,
                'unit' => 'سانتی‌متر', 'hint' => 'صفر یعنی درست زیر کمربند.',
            ],
        ], $this->placementParams(withHost: false));
    }

    public function supports(array $pieces, array $context): true|string
    {
        if ($this->indexesWithTag($pieces, 'waist', static::FRONT_PARTS) === []) {
            return 'جیب ساعتی زیر خط کمر می‌نشیند؛ این لباس قطعه جلوی کمردار ندارد.';
        }

        return true;
    }

    public function apply(array $pieces, array $context): array
    {
        $index = $this->indexesWithTag($pieces, 'waist', static::FRONT_PARTS)[0] ?? null;

        if ($index === null) {
            return $this->result($pieces, [$this->note('warning', 'قطعه کمرداری برای جیب ساعتی پیدا نشد.')]);
        }

        $width = $this->num($context, 'width', 7);
        $height = $this->num($context, 'height', 7);
        $fromWaist = $this->num($context, 'from_waist', 1);

        $host = $pieces[$index];
        [$x, $y] = $this->fit(
            $host,
            ($host['meta']['quarter_waist'] ?? null) ? (float) $host['meta']['quarter_waist'] * 0.55 : 6.0,
            $fromWaist + $this->num($context, 'offset_y', 0),
            $width,
            $height,
        );
        $x = max(0.5, $x + $this->num($context, 'offset_x', 0));

        $pieces[$index] = $this->markPlacement($host, $x, $y, $width, $height, 'جای جیب ساعتی');

        $pocket = $this->piece([
            'code' => 'pocket-coin',
            'name' => 'جیب ساعتی',
            'cut_quantity' => 1,
            'outline' => $this->rect($width, $height + 3),
            'grainline' => $this->grainline($width * 0.5, 0.8, $height + 2.2),
            'markers' => [
                $this->marker('fold', 'خط تای لبه بالا', 0, 3, $width),
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

        return $this->result(array_merge($pieces, [$pocket]), [
            $this->note('tip', 'جیب ساعتی '.Format::cm($width).'×'.Format::cm($height)
                .' زیر خط کمر «'.$host['name'].'» گذاشته شد.'),
            $this->note('info', 'لبه بالای جیب ۳ سانتی‌متر بلندتر بریده شده تا تو برگردد؛ '
                .'دو سر دهانه را چند دوخت رفت‌وبرگشت بزنید.'),
        ], ['placement' => ['x' => $x, 'y' => $y]]);
    }
}
