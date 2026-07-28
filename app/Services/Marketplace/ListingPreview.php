<?php

namespace App\Services\Marketplace;

use App\Models\FabricType;
use App\Models\GarmentType;
use App\Models\Pattern;
use App\Models\PatternPiece;
use App\Services\Fabric\FabricCompatibilityService;
use App\Support\Jalali;
use App\Support\Measurements;
use Illuminate\Support\Collection;

/**
 * چکیده‌ای از الگو برای ویترین بازارچه — به‌اندازه‌ای که خریدار بداند چه می‌خرد،
 * نه به‌اندازه‌ای که بتواند بدون خرید الگو را بسازد.
 *
 * پیش‌نمایش تصویری «سایه» است: فقط کادر دربرگیرنده هر قطعه کشیده می‌شود، بدون خط
 * برش، ساسون، چرت و درز. یعنی خریدار شمار و نسبت اندازه قطعه‌ها را می‌بیند، ولی
 * هندسه‌ای که ارزش الگوست منتقل نمی‌شود.
 */
class ListingPreview
{
    /** @return array<string, mixed> */
    public function build(Pattern $pattern): array
    {
        $pattern->loadMissing(['pieces', 'garmentType']);
        $pieces = $pattern->pieces;

        return [
            'piece_count' => $pieces->count(),
            'cut_count' => (int) $pieces->sum('cut_quantity'),
            'base_size' => (string) $pattern->base_size,
            'size_range' => $this->sizeRange(),
            'garment_type' => $pattern->garmentType?->name_fa,
            'layers' => $this->layers($pieces),
            'largest_piece' => $this->largestPiece($pieces),
            'fabrics' => $this->fabricSuggestions($pattern->garmentType),
            'silhouette' => $this->silhouette($pieces),
        ];
    }

    /** بازه سایزبندی که الگو با آن قابل بزرگ/کوچک کردن است. */
    public function sizeRange(): string
    {
        $sizes = Measurements::sizes();

        if ($sizes === []) {
            return '—';
        }

        return Jalali::digits(reset($sizes)).' تا '.Jalali::digits(end($sizes));
    }

    /**
     * شمار قطعه‌ها به تفکیک لایه (رویه، آستر، لایی و ...).
     *
     * @param  Collection<int, PatternPiece>  $pieces
     * @return array<string, int>
     */
    public function layers(Collection $pieces): array
    {
        return $pieces
            ->groupBy(fn (PatternPiece $piece) => $piece->layerLabel())
            ->map(fn (Collection $group) => $group->count())
            ->all();
    }

    /**
     * ابعاد بزرگ‌ترین قطعه؛ نشان می‌دهد الگو چقدر پارچه می‌خواهد.
     *
     * @param  Collection<int, PatternPiece>  $pieces
     * @return array{width: float, height: float}|null
     */
    public function largestPiece(Collection $pieces): ?array
    {
        $piece = $pieces->sortByDesc(fn (PatternPiece $piece) => $piece->width() * $piece->height())->first();

        if (! $piece) {
            return null;
        }

        return ['width' => $piece->width(), 'height' => $piece->height()];
    }

    /**
     * پیشنهاد پارچه بر پایه نوع لباس.
     *
     * @return array<int, string>
     */
    public function fabricSuggestions(?GarmentType $garmentType): array
    {
        if (! $garmentType || ! class_exists(FabricCompatibilityService::class)) {
            return [];
        }

        try {
            $prefs = app(FabricCompatibilityService::class)->preferences($garmentType);
        } catch (\Throwable) {
            return [];
        }

        $out = [];

        if ($families = array_filter((array) ($prefs['families'] ?? []))) {
            $labels = array_map(fn ($family) => FabricType::FAMILIES[$family] ?? $family, $families);
            $out[] = 'خانواده پارچه: '.implode('، ', $labels);
        }

        $min = $prefs['weight_gsm']['min'] ?? null;
        $max = $prefs['weight_gsm']['max'] ?? null;

        if ($min !== null && $max !== null) {
            $out[] = 'وزن مناسب: '.Jalali::digits((int) $min).' تا '.Jalali::digits((int) $max).' گرم بر مترمربع';
        }

        if (($stretch = $prefs['stretch']['max'] ?? null) !== null) {
            $out[] = 'کشسانی تا '.Jalali::digits((int) $stretch).'٪';
        }

        return $out;
    }

    /**
     * سایه الگو: کادر دربرگیرنده قطعه‌ها، بدون هیچ خط فنی.
     *
     * @param  Collection<int, PatternPiece>  $pieces
     */
    public function silhouette(Collection $pieces, int $width = 320, int $height = 200): string
    {
        $boxes = $pieces
            ->map(fn (PatternPiece $piece) => ['w' => max($piece->width(), 1.0), 'h' => max($piece->height(), 1.0)])
            ->filter()
            ->sortByDesc('h')
            ->take(8) // بیش از این، سایه شلوغ و بی‌معنا می‌شود
            ->values();

        if ($boxes->isEmpty()) {
            return '';
        }

        $gap = 4.0;
        $totalWidth = $boxes->sum('w') + $gap * ($boxes->count() - 1);
        $tallest = (float) $boxes->max('h');
        $scale = min(($width - 16) / max($totalWidth, 0.1), ($height - 16) / max($tallest, 0.1));

        $x = ($width - $totalWidth * $scale) / 2;
        $shapes = '';

        foreach ($boxes as $box) {
            $w = $box['w'] * $scale;
            $h = $box['h'] * $scale;
            $y = ($height - $h) / 2;

            $shapes .= sprintf(
                '<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="%.1f" fill="#e7e5e4" stroke="#a8a29e" stroke-width="1.2"/>',
                $x, $y, $w, $h, min($w, $h) * 0.12,
            );

            $x += $w + $gap * $scale;
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="100%%" role="img" aria-label="سایه قطعه‌های الگو">%s</svg>',
            $width, $height, $shapes,
        );
    }
}
