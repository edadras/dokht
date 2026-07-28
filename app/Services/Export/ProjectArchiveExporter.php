<?php

namespace App\Services\Export;

use App\Models\Pattern;
use App\Models\PatternPiece;
use App\Models\Project;
use App\Services\Cutting\LayoutRenderer;
use App\Services\Cutting\NestingService;
use App\Services\TechPack\TechPackBuilder;
use App\Support\Jalali;
use Illuminate\Support\Facades\File;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use ZipArchive;

/**
 * بسته خروجی پروژه: هر چیزی که برای تولید یا آرشیو لازم است، در یک فایل زیپ.
 *
 * محتویات: الگو به SVG و DXF، کارت فنی به JSON، نقشه برش به SVG، برگه اندازه و صورت
 * مواد به CSV و ترتیب دوخت به متن ساده.
 */
class ProjectArchiveExporter
{
    public function __construct(
        protected TechPackBuilder $techPack = new TechPackBuilder,
        protected MeasurementSheetBuilder $measurements = new MeasurementSheetBuilder,
        protected SewingInstructionsBuilder $sewing = new SewingInstructionsBuilder,
        protected BillOfMaterialsBuilder $bom = new BillOfMaterialsBuilder,
        protected LayoutRenderer $renderer = new LayoutRenderer,
        protected NestingService $nesting = new NestingService,
    ) {}

    /**
     * فایل‌های بسته خروجی: نام => محتوا.
     *
     * @return array<string, string>
     */
    public function files(Project $project): array
    {
        $files = [];
        $pattern = $project->pattern;

        if ($pattern) {
            $files['pattern.svg'] = $this->patternSvg($pattern);
            $files['pattern.dxf'] = $this->patternDxf($pattern);
        }

        $files['tech-pack.json'] = $this->techPackJson($project);

        $layout = $project->latestCuttingLayout;

        if ($layout) {
            $files['cutting-layout.svg'] = $this->safe(fn () => $this->renderer->render($layout, ['print' => true]), '');
        }

        $files['measurement-sheet.csv'] = $this->safe(fn () => $this->measurements->csv($project), '');
        $files['bill-of-materials.csv'] = $this->safe(fn () => $this->bom->csv($project), '');
        $files['sewing-instructions.txt'] = $this->safe(fn () => $this->sewing->toText($project), '');
        $files['README.txt'] = $this->readme($project, $files);

        return array_filter($files, fn ($contents) => is_string($contents) && $contents !== '');
    }

    /** یک فایل تنها، برای دانلود قالب‌های جداگانه (svg|dxf|json|csv). */
    public function file(Project $project, string $format): ?array
    {
        $pattern = $project->pattern;

        return match ($format) {
            'svg' => $pattern ? [
                'name' => 'pattern.svg',
                'contents' => $this->patternSvg($pattern),
                'mime' => 'image/svg+xml',
            ] : null,
            'dxf' => $pattern ? [
                'name' => 'pattern.dxf',
                'contents' => $this->patternDxf($pattern),
                'mime' => 'application/dxf',
            ] : null,
            'json' => [
                'name' => 'tech-pack.json',
                'contents' => $this->techPackJson($project),
                'mime' => 'application/json',
            ],
            'csv' => [
                'name' => 'measurement-sheet.csv',
                'contents' => $this->measurements->csv($project),
                'mime' => 'text/csv; charset=UTF-8',
            ],
            default => null,
        };
    }

    /** ساخت و فرستادن فایل زیپ؛ فایل موقت پس از ارسال پاک می‌شود. */
    public function download(Project $project): Response
    {
        $directory = storage_path('app/private/exports');
        File::ensureDirectoryExists($directory);

        $path = $directory.DIRECTORY_SEPARATOR.sprintf('project-%d-%s.zip', $project->id, now()->format('Ymd-His'));

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'ساخت فایل زیپ ممکن نشد.');
        }

        foreach ($this->files($project) as $name => $contents) {
            $zip->addFromString($name, $contents);
        }

        $zip->close();

        return response()
            ->download($path, sprintf('dokht-project-%d.zip', $project->id), [
                'Content-Type' => 'application/zip',
            ])
            ->deleteFileAfterSend(true);
    }

    protected function techPackJson(Project $project): string
    {
        $latest = $project->techPacks()->first();
        $data = $latest?->data ?? $this->safe(fn () => $this->techPack->build($project), []);

        return (string) json_encode([
            'version' => $latest?->version ?? 0,
            'project_id' => $project->id,
            'generated_at' => Jalali::dateTime(now()),
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /** الگو به SVG: قطعه‌ها کنار هم با نام و شماره برش. */
    public function patternSvg(Pattern $pattern): string
    {
        $pieces = $pattern->pieces;
        $x = 0.0;
        $y = 0.0;
        $rowHeight = 0.0;
        $maxWidth = 0.0;
        $gap = 4.0;
        $wrapAt = 200.0;
        $body = [];

        foreach ($pieces as $piece) {
            [$minX, $minY] = $piece->bounds();
            $width = max(1.0, $piece->width());
            $height = max(1.0, $piece->height());

            if ($x > 0 && $x + $width > $wrapAt) {
                $x = 0.0;
                $y += $rowHeight + $gap + 6;
                $rowHeight = 0.0;
            }

            $body[] = sprintf(
                '<g transform="translate(%s %s)"><path d="%s" fill="#f5f5f4" stroke="#1c1917" stroke-width="0.25" />'
                .'<text x="%s" y="-1.5" font-size="2.6" fill="#1c1917" text-anchor="middle" direction="rtl">%s</text></g>',
                $this->num($x - $minX),
                $this->num($y - $minY),
                htmlspecialchars($this->nesting->piecePath($piece, false), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                $this->num($minX + $width / 2),
                htmlspecialchars(sprintf(
                    '%s (%s عدد)',
                    (string) $piece->name,
                    Jalali::digits((string) $piece->cut_quantity),
                ), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            );

            $x += $width + $gap;
            $rowHeight = max($rowHeight, $height);
            $maxWidth = max($maxWidth, $x);
        }

        $totalWidth = max(20.0, $maxWidth) + $gap;
        $totalHeight = max(20.0, $y + $rowHeight) + $gap + 6;

        return sprintf(
            '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<svg xmlns="http://www.w3.org/2000/svg" viewBox="-4 -8 %s %s" width="%smm" height="%smm">'
            ."\n<title>%s</title>\n%s\n</svg>\n",
            $this->num($totalWidth + 8),
            $this->num($totalHeight + 12),
            $this->num(($totalWidth + 8) * 10, 1),
            $this->num(($totalHeight + 12) * 10, 1),
            htmlspecialchars((string) $pattern->name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            implode("\n", $body),
        );
    }

    /** الگو به DXF؛ در صورت وجود از خروجی‌گیر اختصاصی الگو استفاده می‌شود. */
    public function patternDxf(Pattern $pattern): string
    {
        $exporter = 'App\Services\Pattern\DxfExporter';

        if (class_exists($exporter)) {
            foreach (['export', 'toDxf', 'dxf', 'contents', 'build'] as $candidate) {
                if (! method_exists($exporter, $candidate)) {
                    continue;
                }

                try {
                    $method = new ReflectionMethod($exporter, $candidate);
                    $result = $method->invoke($method->isStatic() ? null : app($exporter), $pattern);

                    if (is_string($result) && trim($result) !== '') {
                        return $result;
                    }
                } catch (Throwable) {
                    // سراغ روش بعدی می‌رویم
                }
            }
        }

        return $this->simpleDxf($pattern);
    }

    /** خروجی DXF ساده (نسخه R12) از خط دور قطعه‌ها. */
    protected function simpleDxf(Pattern $pattern): string
    {
        $lines = ['0', 'SECTION', '2', 'ENTITIES'];

        foreach ($pattern->pieces as $piece) {
            $points = $piece->points();

            if (count($points) < 2) {
                continue;
            }

            $layer = $this->dxfLayer($piece);

            $lines = array_merge($lines, ['0', 'POLYLINE', '8', $layer, '66', '1', '70', '1']);

            foreach ($points as $point) {
                $lines = array_merge($lines, [
                    '0', 'VERTEX', '8', $layer,
                    '10', $this->num($point['x'], 3),
                    '20', $this->num(-$point['y'], 3),
                    '30', '0.0',
                ]);
            }

            $lines = array_merge($lines, ['0', 'SEQEND', '8', $layer]);
        }

        $lines = array_merge($lines, ['0', 'ENDSEC', '0', 'EOF']);

        return implode("\r\n", $lines)."\r\n";
    }

    /** نام لایه DXF فقط با نویسه‌های امن. */
    protected function dxfLayer(PatternPiece $piece): string
    {
        $layer = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) $piece->code);

        return $layer === '' || $layer === null ? 'PIECE_'.$piece->id : $layer;
    }

    protected function readme(Project $project, array $files): string
    {
        $lines = [
            'بسته خروجی پروژه: '.$project->name,
            'کارگاه: '.($project->workshop?->name ?? '—'),
            'تاریخ: '.Jalali::dateTime(now()),
            '',
            'فایل‌ها:',
        ];

        $descriptions = [
            'pattern.svg' => 'الگو به شکل برداری؛ برای چاپ در اندازه واقعی یا پلاتر',
            'pattern.dxf' => 'الگو برای نرم‌افزارهای برش و پلاتر',
            'tech-pack.json' => 'کارت فنی کامل به شکل داده',
            'cutting-layout.svg' => 'نقشه چیدمان قطعه‌ها روی پارچه',
            'measurement-sheet.csv' => 'برگه اندازه‌های بدن',
            'bill-of-materials.csv' => 'صورت مواد و برآورد هزینه',
            'sewing-instructions.txt' => 'ترتیب دوخت و راهنمای پارچه',
        ];

        foreach (array_keys($files) as $name) {
            if (isset($descriptions[$name])) {
                $lines[] = '- '.$name.' : '.$descriptions[$name];
            }
        }

        return implode("\n", $lines)."\n";
    }

    protected function safe(callable $callback, mixed $fallback): mixed
    {
        try {
            return $callback();
        } catch (Throwable) {
            return $fallback;
        }
    }

    protected function num(mixed $value, int $decimals = 2): string
    {
        $formatted = rtrim(rtrim(number_format((float) $value, $decimals, '.', ''), '0'), '.');

        return $formatted === '' || $formatted === '-' ? '0' : $formatted;
    }
}
