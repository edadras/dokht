<?php

namespace App\Services\Pattern\Style\Pocket;

use App\Services\Pattern\Geometry;
use App\Support\Format;

/**
 * جیب دست‌گرمی کتی: دو فیلتاب باریک (جِت) با درپوش که از زیر آن بیرون می‌آید.
 *
 * جیب کلاسیک کت؛ دهانه روی تنه بریده می‌شود، دو نوار باریک لبه برش را می‌پوشانند و
 * درپوش از دهانه بیرون می‌آید.
 */
class JettedFlapPocket extends BasePocket
{
    public static function key(): string
    {
        return 'pocket_jetted_flap';
    }

    public function label(): string
    {
        return 'جیب دست‌گرمی کتی';
    }

    public function description(): string
    {
        return 'دو نوار باریک دور دهانه با درپوشی که از میان آن بیرون می‌آید؛ جیب کلاسیک کت.';
    }

    public function paramsSchema(): array
    {
        return array_merge([
            'opening' => ['label' => 'دهانه جیب', 'min' => 10, 'max' => 20, 'step' => 0.5, 'default' => 15, 'unit' => 'سانتی‌متر'],
            'jet' => ['label' => 'پهنای هر نوار', 'min' => 0.4, 'max' => 1.5, 'step' => 0.1, 'default' => 0.6, 'unit' => 'سانتی‌متر'],
            'flap_height' => ['label' => 'بلندی درپوش', 'min' => 4, 'max' => 8, 'step' => 0.5, 'default' => 5.5, 'unit' => 'سانتی‌متر'],
            'angle' => ['label' => 'شیب دهانه', 'min' => -12, 'max' => 12, 'step' => 1, 'default' => 4, 'unit' => 'درجه'],
            'bag_depth' => ['label' => 'عمق کیسه', 'min' => 12, 'max' => 24, 'step' => 0.5, 'default' => 16, 'unit' => 'سانتی‌متر'],
        ], $this->placementParams());
    }

    protected function supportsPocket(array $pieces, array $context): true|string
    {
        if ($this->firstIndexOfParts($pieces, ['front_bodice']) === null) {
            return 'جیب دست‌گرمی روی تنه جلو بریده می‌شود؛ این لباس بالاتنه جلو ندارد.';
        }

        return true;
    }

    public function apply(array $pieces, array $context): array
    {
        $index = $this->firstIndexOfParts($pieces, ['front_bodice']);

        if ($index === null) {
            return $this->result($pieces, [$this->note('warning', 'تنه جلو برای جیب دست‌گرمی پیدا نشد.')]);
        }

        $opening = $this->num($context, 'opening', 15);
        $jet = $this->num($context, 'jet', 0.6);
        $flapHeight = $this->num($context, 'flap_height', 5.5);
        $angle = $this->num($context, 'angle', 4);
        $depth = $this->num($context, 'bag_depth', 16);

        $host = $pieces[$index];
        $hostHeight = Geometry::height($host['outline']);

        // جیب دست‌گرمی پایین‌تر از خط سینه، حدود یک‌سوم پایین تنه می‌نشیند
        [$x, $y] = $this->fit(
            $host,
            $this->centerX($host) + 3.5 + $this->num($context, 'offset_x', 0),
            ($hostHeight * 0.62) + $this->num($context, 'offset_y', 0),
            $opening,
            max(4.0, $flapHeight),
        );

        $host = $this->addOpening($host, $x, $y, $opening, $angle, 'jetted', 'برش دهانه جیب دست‌گرمی');
        $host['meta']['pockets'][] = [
            'key' => static::key(),
            'label' => 'جیب دست‌گرمی کتی',
            'x' => $x,
            'y' => $y,
            'width' => round($opening, 2),
            'height' => round($jet * 2, 2),
        ];
        $pieces[$index] = $host;

        $jetLength = round($opening + 3, 2);

        $jets = $this->piece([
            'code' => 'pocket-jet',
            'name' => 'نوار (جِت) جیب',
            'cut_quantity' => 4,
            'outline' => $this->rect($jetLength, ($jet * 2) + 2),
            'grainline' => $this->grainline($jetLength * 0.5, 0.5, ($jet * 2) + 1.5),
            'markers' => [
                $this->marker('fold', 'خط تای نوار', 0, $jet + 1, $jetLength),
            ],
            'meta' => [
                'part' => 'pocket_welt',
                'edges' => ['default', 'default', 'default', 'default'],
                'fold_edges' => [],
                'interfacing' => true,
                'pocket' => static::key(),
                'covers_opening' => round($opening, 2),
                'finished_length' => $jetLength,
            ],
        ]);

        $flap = $this->flapPiece($opening + 0.4, $flapHeight, 'pocket-jetted-flap', 'درپوش جیب دست‌گرمی', 'square');
        $bag = $this->bagPiece($opening + 4, $depth, 'pocket-jetted-bag', 'کیسه جیب دست‌گرمی', 4);

        $facing = $this->piece([
            'code' => 'pocket-jetted-facing',
            'name' => 'سجاف کیسه دست‌گرمی',
            'cut_quantity' => 2,
            'outline' => $this->rect($opening + 4, 6.5),
            'grainline' => $this->grainline(($opening + 4) * 0.5, 0.8, 5.7),
            'meta' => [
                'part' => 'pocket_facing',
                'edges' => ['default', 'default', 'default', 'default'],
                'fold_edges' => [],
                'pocket' => static::key(),
            ],
        ]);

        return $this->result(array_merge($pieces, [$jets, $flap, $bag, $facing]), [
            $this->note('tip', 'دهانه '.Format::cm($opening).' با شیب '.$angle
                .' درجه روی تنه جلو بریده شد؛ دو نوار '.Format::cm($jet)
                .'ی بالا و پایین برش را می‌پوشانند (نوار '.Format::cm($jetLength).' بریده می‌شود).'),
            $this->note('info', 'درپوش '.Format::cm($opening + 0.4).' است تا هم‌اندازه دهانه از میان نوارها بیرون بیاید.'),
            $this->note('warning', 'پشت دهانه لایی چسب بزنید؛ بدون لایی برش کش می‌آید و جیب باز می‌ماند.'),
            $this->fabricNote($depth + 8, 'کیسه و سجاف جیب دست‌گرمی'),
        ], ['opening' => round($opening, 2), 'jet_length' => $jetLength]);
    }
}
