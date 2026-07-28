<?php

namespace App\Services\Pattern\Style\Pocket;

use App\Services\Pattern\Geometry;
use App\Support\Format;

/**
 * جیب کانگورویی: یک جیب پهن روی مرکز جلو با دو دهانه اریب در دو طرف.
 *
 * روی تای پارچه بریده می‌شود تا کامل قرینه باشد و مثل خود لباس روی مرکز جلو بنشیند.
 */
class KangarooPocket extends BasePocket
{
    public static function key(): string
    {
        return 'pocket_kangaroo';
    }

    public function label(): string
    {
        return 'جیب کانگورویی';
    }

    public function description(): string
    {
        return 'جیب پهن سویشرتی روی مرکز جلو با دو دهانه اریب برای دو دست.';
    }

    public function paramsSchema(): array
    {
        return [
            'width' => ['label' => 'پهنای کل جیب', 'min' => 20, 'max' => 50, 'step' => 1, 'default' => 34, 'unit' => 'سانتی‌متر'],
            'height' => ['label' => 'بلندی جیب', 'min' => 12, 'max' => 30, 'step' => 0.5, 'default' => 20, 'unit' => 'سانتی‌متر'],
            'opening' => ['label' => 'دهانه هر دست', 'min' => 8, 'max' => 20, 'step' => 0.5, 'default' => 14, 'unit' => 'سانتی‌متر'],
            'from_hem' => [
                'label' => 'فاصله از دم لباس', 'min' => 0, 'max' => 20, 'step' => 0.5, 'default' => 4,
                'unit' => 'سانتی‌متر', 'hint' => 'لبه پایین جیب این‌قدر بالاتر از دم لباس می‌نشیند.',
            ],
        ];
    }

    protected function supportsPocket(array $pieces, array $context): true|string
    {
        $index = $this->firstIndexOfParts($pieces, ['front_bodice']);

        if ($index === null) {
            return 'جیب کانگورویی روی تنه جلو می‌نشیند؛ این لباس بالاتنه جلو ندارد.';
        }

        if (! empty($pieces[$index]['meta']['button_stand'])) {
            return 'جیب کانگورویی روی لباس دکمه‌خور نمی‌نشیند، چون مرکز جلو باز است.';
        }

        return true;
    }

    public function apply(array $pieces, array $context): array
    {
        $index = $this->firstIndexOfParts($pieces, ['front_bodice']);

        if ($index === null) {
            return $this->result($pieces, [$this->note('warning', 'تنه جلو برای جیب کانگورویی پیدا نشد.')]);
        }

        $width = $this->num($context, 'width', 34);
        $height = $this->num($context, 'height', 20);
        $opening = $this->num($context, 'opening', 14);
        $fromHem = $this->num($context, 'from_hem', 4);

        $host = $pieces[$index];
        $hostWidth = Geometry::width($host['outline']);
        $hostHeight = Geometry::height($host['outline']);
        $half = min($width / 2, $hostWidth - 2);
        $top = max(1.0, $hostHeight - $fromHem - $height);

        // روی نیم‌قطعه جلو فقط نیمی از جیب دیده می‌شود
        $host['markers'][] = $this->marker('pocket', 'بالای جیب کانگورویی', 0, $top, $half, $top);
        $host['markers'][] = $this->marker('pocket', 'پایین جیب کانگورویی', 0, $hostHeight - $fromHem, $half, $hostHeight - $fromHem);
        $host['drills'][] = $this->drill($half, $top, 'pocket', 'گوشه بالای جیب کانگورویی');
        $host['drills'][] = $this->drill($half, $hostHeight - $fromHem, 'pocket', 'گوشه پایین جیب کانگورویی');
        $host['meta']['pockets'][] = [
            'key' => static::key(),
            'label' => 'جیب کانگورویی',
            'x' => 0.0,
            'y' => round($top, 2),
            'width' => round($half, 2),
            'height' => round($height, 2),
        ];

        $pieces[$index] = $host;

        // نیم‌جیب روی تای پارچه: دهانه اریب از بالا-کنار تا پایین-مرکز
        $slant = min($height - 2, $opening * 0.72);

        $outline = [
            Geometry::point(0, 0),
            Geometry::point($half - $opening * 0.7, 0),
            Geometry::curve($half, $slant, $half - ($opening * 0.2), $slant * 0.45),
            Geometry::point($half, $height),
            Geometry::point(0, $height),
        ];

        $pocket = $this->piece([
            'code' => 'pocket-kangaroo',
            'name' => 'جیب کانگورویی',
            'cut_quantity' => 1,
            'on_fold' => true,
            'outline' => $outline,
            'grainline' => $this->grainline($half * 0.5, 1, $height - 1),
            'notches' => [
                $this->notch($half - ($opening * 0.7), 0, 1, 'سر دهانه دست'),
                $this->notch($half, $slant, 2, 'ته دهانه دست'),
            ],
            'markers' => [
                $this->marker('fold', 'خط تای مرکز جلو', 0, 0, 0, $height),
            ],
            'meta' => [
                'part' => 'pocket',
                'edges' => ['hem', 'default', 'side', 'hem', 'default'],
                'fold_edges' => [4],
                'pocket' => static::key(),
                'opening' => round($opening, 2),
                'note' => 'دهانه اریب لبه‌برگردان دارد؛ دو سانتی‌متر برای برگرداندن دهانه حساب شده است.',
            ],
        ]);

        $facing = $this->piece([
            'code' => 'pocket-kangaroo-facing',
            'name' => 'سجاف دهانه کانگورویی',
            'cut_quantity' => 2,
            'outline' => $this->rect($opening + 3, 4),
            'grainline' => $this->grainline(($opening + 3) * 0.5, 0.6, 3.4),
            'meta' => [
                'part' => 'pocket_facing',
                'edges' => ['default', 'default', 'default', 'default'],
                'fold_edges' => [],
                'pocket' => static::key(),
            ],
        ]);

        return $this->result(array_merge($pieces, [$pocket, $facing]), [
            $this->note('tip', 'جیب کانگورویی '.Format::cm($width).' پهنا و '.Format::cm($height)
                .' بلندی روی مرکز جلو نشست؛ روی تای پارچه بریده می‌شود تا کاملاً قرینه باشد.'),
            $this->note('info', 'دو دهانه اریب '.Format::cm($opening).' برای دست‌ها، با سجاف جدا.'),
            $this->fabricNote($height + 6, 'جیب کانگورویی'),
        ], ['opening' => round($opening, 2)]);
    }
}
