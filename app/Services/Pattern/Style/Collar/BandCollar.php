<?php

namespace App\Services\Pattern\Style\Collar;

use App\Support\Format;

/**
 * یقه آخوندی (پایه‌دار).
 *
 * یک نوار ایستاده که دور گردن می‌ماند و برنمی‌گردد. چون گردن به بالا باریک
 * می‌شود، نوار روی الگو کمان می‌خورد: لبه یقه روی کمان بیرونی می‌نشیند و لبه
 * بالا کوتاه‌تر درمی‌آید، پس یقه به گردن می‌چسبد و باز نمی‌ایستد.
 *
 * اندازه «بالا آمدن جلوی یقه» همان چیزی است که در کتاب‌های الگو نوشته می‌شود:
 * سر جلوی یقه چند سانتی‌متر از خط پایه بالاتر کشیده شود. هرچه بیشتر، یقه
 * جمع‌تر و ایستاده‌تر؛ صفر یعنی نوار راست که از گردن فاصله می‌گیرد.
 */
class BandCollar extends BaseCollar
{
    public static function key(): string
    {
        return 'collar_band';
    }

    public function label(): string
    {
        return 'یقه آخوندی (پایه‌دار)';
    }

    public function description(): string
    {
        return 'نوار ایستاده دور گردن، بدون برگشت؛ یقه پیراهن مردانه بی‌رویه و یقه چینی.';
    }

    public function paramsSchema(): array
    {
        return [
            'height' => [
                'label' => 'بلندی پایه', 'min' => 1.5, 'max' => 8, 'step' => 0.5, 'default' => 3,
                'unit' => 'سانتی‌متر', 'hint' => 'از خط یقه تا لبه بالای یقه.',
            ],
            'rise' => [
                'label' => 'بالا آمدن جلوی یقه', 'min' => 0, 'max' => 4, 'step' => 0.25, 'default' => 1.25,
                'unit' => 'سانتی‌متر', 'hint' => 'هرچه بیشتر، یقه جمع‌تر به گردن می‌چسبد؛ صفر یعنی نوار راست.',
            ],
            'extension' => [
                'label' => 'اضافه جای دکمه', 'min' => 0, 'max' => 4, 'step' => 0.5, 'default' => 1.5,
                'unit' => 'سانتی‌متر', 'hint' => 'روی لباس جلوباز، یقه به همین اندازه از مرکز جلو جلوتر می‌رود.',
            ],
            'front_shape' => [
                'label' => 'سر جلوی یقه', 'type' => 'select', 'default' => 'round',
                'options' => ['round' => 'گرد', 'square' => 'راست'],
            ],
            'ease' => $this->easeField(),
            'interfacing' => $this->interfacingField(),
        ];
    }

    protected function draft(array $neck, array $p, array $pieces, array $context): array
    {
        $height = (float) $p['height'];
        $rise = (float) $p['rise'];
        $open = $this->frontOpening($pieces, $context);
        [$extension, $extensionNote] = $this->frontExtension($pieces, $context, (float) $p['extension']);
        $target = max(5.0, $neck['half'] + (float) $p['ease']);

        [$piece, $length, $difference] = $this->fitToNeckline(
            fn (float $span) => $this->band($span, $height, $rise, $extension, (string) $p['front_shape']),
            $target,
        );

        $piece = $this->halfCollarNotches($piece, $neck, $target);
        $top = $this->seamOf($piece, 'hem');
        $made = [$piece];
        $notes = [];

        if (! empty($p['interfacing'])) {
            $made[] = $this->collarInterfacing($piece, 'لایه چسب یقه آخوندی');
        }

        $notes[] = 'یقه آخوندی روی تای مرکز پشت بریده می‌شود؛ دو لا (رو و آستر) و لایه چسب روی لای رو.';
        $notes[] = 'لبه بالای یقه '.Format::cm($top).' درآمد، یعنی '.Format::cm(max(0, $length - $top))
            .' کوتاه‌تر از لبه یقه؛ همین اختلاف است که یقه را به گردن می‌چسباند.';

        if ($extensionNote !== null) {
            $notes[] = $extensionNote;
        }

        if ($extension > 0) {
            $notes[] = 'سر جلوی یقه '.Format::cm($extension).' از مرکز جلو جلوتر رفت تا دکمه و جادکمه رویش بنشیند.';
        } elseif ($open) {
            $notes[] = 'اضافه جای دکمه صفر گرفته شد؛ دو سر یقه در مرکز جلو به هم می‌رسند و با قزن بسته می‌شوند.';
        }

        return [
            'pieces' => $made,
            'notes' => $notes,
            'meta' => [
                'target' => round($target, 2),
                'measured' => $length,
                'ease' => round((float) $p['ease'], 2),
                'difference' => $difference,
                'stand_height' => $height,
                'top_edge' => round($top, 2),
                'front_extension' => $extension,
            ],
        ];
    }

    /**
     * درفت نوار با طول لبه یقه داده‌شده.
     *
     * @return array<string, mixed>
     */
    protected function band(float $span, float $height, float $rise, float $extension, string $shape): array
    {
        $arc = $this->collarArc($span, $height, $this->riseRadius($span, $rise), 'stand');
        $front = $this->bandFrontEnd($arc, $extension, $shape);
        $shell = $this->assembleCollar($arc, $front['points'], 'hem', $front['tags']);

        return $this->newPiece([
            'code' => 'collar-band',
            'name' => 'یقه آخوندی',
            'cut_quantity' => 2,
            'on_fold' => true,
            'outline' => $shell['outline'],
            'grainline' => $this->collarGrainline($arc),
            'markers' => [
                $this->marker(
                    'cb',
                    'خط مرکز پشت',
                    $arc['cb_neck']['x'],
                    $arc['cb_neck']['y'],
                    $arc['cb_outer']['x'],
                    $arc['cb_outer']['y'],
                ),
            ],
            'meta' => [
                'part' => 'collar',
                'edges' => $shell['edges'],
                'fold_edges' => [count($shell['edges']) - 1],
                'interfacing' => true,
                'girth_role' => 'trim',
                'collar_kind' => 'stand',
                'stand_height' => round($height, 2),
                'radius' => $arc['radius'],
            ],
        ]);
    }
}
