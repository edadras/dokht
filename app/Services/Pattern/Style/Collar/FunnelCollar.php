<?php

namespace App\Services\Pattern\Style\Collar;

use App\Support\Format;

/**
 * یقه قیفی.
 *
 * نوار بلند ایستاده‌ای که برعکس یقه آخوندی، به بالا باز می‌شود: لبه بالایش از
 * لبه یقه بلندتر است، پس دور صورت مثل قیف می‌ایستد و به گردن نمی‌چسبد. روی
 * الگو یعنی لبه یقه روی کمان درونی و لبه بالا روی کمان بیرونی.
 *
 * با «بازشدن» صفر، یقه به یک نوار راست می‌رسد که مستقیم بالا می‌ایستد؛ هرچه
 * بیشتر، دهانه یقه بازتر و یقه نرم‌تر می‌افتد. چون یقه بلند است و از گردن فاصله
 * می‌گیرد، برای ایستادن به لایه چسب یا آستر محکم نیاز دارد.
 */
class FunnelCollar extends BaseCollar
{
    public static function key(): string
    {
        return 'collar_funnel';
    }

    public function label(): string
    {
        return 'یقه قیفی';
    }

    public function description(): string
    {
        return 'یقه بلند ایستاده که به بالا باز می‌شود؛ یقه پالتو و مانتوی قیفی.';
    }

    public function paramsSchema(): array
    {
        return [
            'height' => [
                'label' => 'بلندی یقه', 'min' => 3, 'max' => 16, 'step' => 0.5, 'default' => 7,
                'unit' => 'سانتی‌متر',
            ],
            'flare' => [
                'label' => 'بازشدن دهانه', 'min' => 0, 'max' => 12, 'step' => 0.5, 'default' => 3,
                'unit' => 'سانتی‌متر', 'hint' => 'لبه بالا همین اندازه از لبه یقه بلندتر می‌شود؛ صفر یعنی نوار راست.',
            ],
            'extension' => [
                'label' => 'اضافه جای بست', 'min' => 0, 'max' => 5, 'step' => 0.5, 'default' => 0,
                'unit' => 'سانتی‌متر', 'hint' => 'روی لباس جلوباز، یقه به همین اندازه از مرکز جلو جلوتر می‌رود.',
            ],
            'ease' => $this->easeField(),
            'interfacing' => $this->interfacingField(),
        ];
    }

    protected function draft(array $neck, array $p, array $pieces, array $context): array
    {
        $height = (float) $p['height'];
        $flare = (float) $p['flare'];
        $open = $this->frontOpening($pieces, $context);
        [$extension, $extensionNote] = $this->frontExtension($pieces, $context, (float) $p['extension']);
        $target = max(5.0, $neck['half'] + (float) $p['ease']);

        [$piece, $length, $difference] = $this->fitToNeckline(
            fn (float $span) => $this->funnel($span, $height, $flare, $extension),
            $target,
        );

        $piece = $this->halfCollarNotches($piece, $neck, $target);
        $top = $this->seamOf($piece, 'hem');
        $made = [$piece];
        $notes = [];

        if (! empty($p['interfacing'])) {
            $made[] = $this->collarInterfacing($piece, 'لایه چسب یقه قیفی');
        }

        $notes[] = 'دهانه بالای یقه '.Format::cm($top * 2).' دور دارد در برابر خط یقه '
            .Format::cm($length * 2).'؛ یعنی '.Format::cm(max(0, ($top - $length) * 2)).' بازتر.';
        $notes[] = $flare < 0.3
            ? 'بازشدن تقریباً صفر گرفته شد، پس یقه یک نوار راست است و مستقیم بالا می‌ایستد.'
            : 'یقه دولا (رو و آستر) بریده می‌شود و لایه چسب می‌خورد؛ یقه قیفی بدون لایه، روی شانه می‌افتد.';

        if ($extensionNote !== null) {
            $notes[] = $extensionNote;
        }

        if (! $open) {
            $notes[] = 'این لباس چاک جلو ندارد؛ اگر دور دهانه یقه از دور سر کمتر است، در مرکز پشت چاک و زیپ بگذارید.';
        }

        return [
            'pieces' => $made,
            'notes' => $notes,
            'meta' => [
                'target' => round($target, 2),
                'measured' => $length,
                'ease' => round((float) $p['ease'], 2),
                'difference' => $difference,
                'height' => $height,
                'flare' => $flare,
                'top_edge' => round($top, 2),
                'opening' => round($top * 2, 2),
            ],
        ];
    }

    /**
     * درفت نیم‌یقه قیفی.
     *
     * @return array<string, mixed>
     */
    protected function funnel(float $span, float $height, float $flare, float $extension): array
    {
        $arc = $this->collarArc($span, $height, $this->arcRadiusFor($span, $height, $flare), 'fall');
        $front = $this->bandFrontEnd($arc, $extension, 'square');
        $shell = $this->assembleCollar($arc, $front['points'], 'hem', $front['tags']);

        return $this->newPiece([
            'code' => 'collar-funnel',
            'name' => 'یقه قیفی',
            'cut_quantity' => 2,
            'on_fold' => true,
            'outline' => $shell['outline'],
            'grainline' => $this->collarGrainline($arc),
            'markers' => [
                $this->marker('cb', 'خط مرکز پشت', $arc['cb_neck']['x'], $arc['cb_neck']['y'], $arc['cb_outer']['x'], $arc['cb_outer']['y']),
            ],
            'meta' => [
                'part' => 'collar',
                'edges' => $shell['edges'],
                'fold_edges' => [count($shell['edges']) - 1],
                'interfacing' => true,
                'girth_role' => 'trim',
                'collar_kind' => 'funnel',
                'height' => round($height, 2),
                'radius' => $arc['radius'],
            ],
        ]);
    }
}
