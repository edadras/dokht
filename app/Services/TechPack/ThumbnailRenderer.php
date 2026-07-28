<?php

namespace App\Services\TechPack;

use App\Models\Pattern;
use App\Models\PatternPiece;
use App\Services\Cutting\NestingService;

/**
 * بندانگشتی قطعه‌های الگو برای کارت فنی: نمای جلو، پشت و سایر قطعه‌ها.
 *
 * قطعه‌ها بر پایه کد یا نامشان دسته‌بندی می‌شوند و هر دسته در یک SVG کوچک کنار هم
 * کشیده می‌شود؛ همان هندسه الگو، بدون نیاز به تصویر آماده.
 */
class ThumbnailRenderer
{
    protected const GROUPS = [
        'front' => ['label' => 'نمای جلو', 'needles' => ['front', 'جلو', 'cf']],
        'back' => ['label' => 'نمای پشت', 'needles' => ['back', 'پشت', 'cb']],
        'side' => ['label' => 'آستین و سایر قطعه‌ها', 'needles' => []],
    ];

    public function __construct(protected NestingService $nesting = new NestingService) {}

    /**
     * @return array<int, array{key: string, label: string, piece_count: int, svg: string}>
     */
    public function groups(?Pattern $pattern): array
    {
        if ($pattern === null || $pattern->pieces->isEmpty()) {
            return [];
        }

        $assigned = [];
        $out = [];

        foreach (static::GROUPS as $key => $group) {
            $pieces = $pattern->pieces->filter(function (PatternPiece $piece) use ($group, $assigned, $key) {
                if (in_array($piece->id, $assigned, true)) {
                    return false;
                }

                if ($key === 'side') {
                    return true;
                }

                $haystack = mb_strtolower($piece->code.' '.$piece->name);

                foreach ($group['needles'] as $needle) {
                    if (str_contains($haystack, $needle)) {
                        return true;
                    }
                }

                return false;
            });

            if ($pieces->isEmpty()) {
                continue;
            }

            foreach ($pieces as $piece) {
                $assigned[] = $piece->id;
            }

            $out[] = [
                'key' => $key,
                'label' => $group['label'],
                'piece_count' => (int) $pieces->sum('cut_quantity'),
                'svg' => $this->render($pieces->all()),
            ];
        }

        return $out;
    }

    /**
     * @param  array<int, PatternPiece>  $pieces
     */
    public function render(array $pieces, array $options = []): string
    {
        $gap = 3.0;
        $x = 0.0;
        $height = 0.0;
        $body = [];

        foreach ($pieces as $piece) {
            [$minX, $minY] = $piece->bounds();
            $width = max(1.0, $piece->width());

            $body[] = sprintf(
                '<g transform="translate(%s %s)"><path d="%s" fill="%s" fill-opacity="0.7" stroke="%s" '
                .'stroke-width="0.3" stroke-linejoin="round" /></g>',
                $this->num($x - $minX),
                $this->num(-$minY),
                htmlspecialchars($this->nesting->piecePath($piece, false), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                $options['fill'] ?? '#e7e5e4',
                $options['stroke'] ?? '#57534e',
            );

            $x += $width + $gap;
            $height = max($height, max(1.0, $piece->height()));
        }

        if ($body === []) {
            return '';
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="-2 -2 %s %s" style="width:100%%;height:auto" '
            .'role="img" aria-label="قطعه‌های الگو">%s</svg>',
            $this->num($x + 2),
            $this->num($height + 4),
            implode('', $body),
        );
    }

    protected function num(mixed $value, int $decimals = 2): string
    {
        $formatted = rtrim(rtrim(number_format((float) $value, $decimals, '.', ''), '0'), '.');

        return $formatted === '' || $formatted === '-' ? '0' : $formatted;
    }
}
