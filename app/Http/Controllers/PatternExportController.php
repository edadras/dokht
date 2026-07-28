<?php

namespace App\Http\Controllers;

use App\Models\Pattern;
use App\Models\PatternPiece;
use App\Services\Pattern\DxfExporter;
use App\Services\Pattern\SeamAllowanceService;
use App\Services\Pattern\SvgRenderer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * چاپ و خروجی الگو.
 *
 * چاپ ۱:۱ روی کاغذ A4 انجام می‌شود: هر قطعه به «کاشی»های ۱۹×۲۷٫۷ سانتی‌متری بریده
 * می‌شود و هر کاشی یک صفحه است. اندازه بیرونی SVG در میلی‌متر داده می‌شود و viewBox
 * در سانتی‌متر، پس مقیاس دقیقاً یک‌به‌یک است.
 */
class PatternExportController extends Controller
{
    /**
     * ناحیه چاپ‌شدنی روی یک برگ A4 (سانتی‌متر).
     *
     * کمی کوچک‌تر از حاشیه ۱۲ میلی‌متری A4 گرفته شده تا حاشیه صفحه وب هم جا شود و
     * مرورگر برای جاکردن تصویر مقیاس را تغییر ندهد؛ چاپ باید دقیقاً ۱:۱ بماند.
     */
    public const PAGE_WIDTH = 17.0;

    public const PAGE_HEIGHT = 25.0;

    public function __construct(
        protected SvgRenderer $renderer,
        protected DxfExporter $dxf,
        protected SeamAllowanceService $seams,
    ) {}

    public function print(Request $request, Pattern $pattern): View
    {
        $pattern->load(['pieces', 'garmentType']);
        $withSeam = ! $request->boolean('no_seam');
        $overlap = 1.0; // هم‌پوشانی صفحه‌ها برای چسباندن

        $pages = [];

        foreach ($pattern->pieces as $piece) {
            [$minX, $minY, $maxX, $maxY] = $this->pieceBox($piece, $withSeam);

            $width = max(1.0, $maxX - $minX);
            $height = max(1.0, $maxY - $minY);
            $columns = max(1, (int) ceil($width / (static::PAGE_WIDTH - $overlap)));
            $rows = max(1, (int) ceil($height / (static::PAGE_HEIGHT - $overlap)));

            for ($row = 0; $row < $rows; $row++) {
                for ($column = 0; $column < $columns; $column++) {
                    $x = $minX - ($overlap / 2) + ($column * (static::PAGE_WIDTH - $overlap));
                    $y = $minY - ($overlap / 2) + ($row * (static::PAGE_HEIGHT - $overlap));

                    $pages[] = [
                        'piece' => $piece,
                        'label' => $piece->name.' — ستون '.($column + 1).' از '.$columns.'، سطر '.($row + 1).' از '.$rows,
                        'svg' => $this->renderer->renderTile($piece, $x, $y, static::PAGE_WIDTH, static::PAGE_HEIGHT, [
                            'seam_allowance' => $withSeam,
                            'size' => $pattern->base_size,
                            'title' => $piece->name.' • سایز '.$pattern->base_size,
                            'tile' => ['column' => $column + 1, 'columns' => $columns, 'row' => $row + 1, 'rows' => $rows],
                        ]),
                    ];
                }
            }
        }

        return view('patterns.print', [
            'pattern' => $pattern,
            'pages' => $pages,
            'withSeam' => $withSeam,
        ]);
    }

    public function export(Pattern $pattern, string $format): Response
    {
        $pattern->load(['pieces', 'garmentType', 'template']);
        $name = Str::slug($pattern->name) ?: 'pattern';
        $base = 'pattern-'.$pattern->id.'-'.$name;

        return match ($format) {
            'svg' => $this->download(
                $this->renderer->renderPattern($pattern, ['seam_allowance' => true, 'labels' => true, 'scale' => 4]),
                $base.'.svg',
                'image/svg+xml',
            ),
            'dxf' => $this->download(
                $this->dxf->export($pattern, ['seam_allowance' => true]),
                $base.'.dxf',
                'application/dxf',
            ),
            default => $this->download(
                json_encode($this->payload($pattern), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                $base.'.json',
                'application/json',
            ),
        };
    }

    /**
     * ساختار قابل‌حمل الگو برای انتقال بین سامانه‌ها.
     *
     * @return array<string, mixed>
     */
    protected function payload(Pattern $pattern): array
    {
        return [
            'format' => 'dokht-pattern',
            'format_version' => 1,
            'exported_at' => now()->toIso8601String(),
            'geometry' => [
                'unit' => 'cm',
                'axes' => 'x به راست، y به پایین، مبدأ گوشه بالا-چپ هر قطعه',
                'curves' => 'نقطه با curve=true یعنی رسیدن به آن با منحنی درجه‌دو و نقطه کنترل (cx, cy)',
            ],
            'pattern' => [
                'name' => $pattern->name,
                'garment_type' => $pattern->garmentType?->code,
                'template' => $pattern->template?->code,
                'generator' => $pattern->template?->generator,
                'base_size' => $pattern->base_size,
                'version' => $pattern->version,
                'measurements' => $pattern->measurements,
                'ease' => $pattern->ease,
                'seam_allowances' => $pattern->seam_allowances,
                'params' => $pattern->params,
                'sewing_relations' => $pattern->sewing_relations,
                'notes' => $pattern->notes,
            ],
            'pieces' => $pattern->pieces->map(fn (PatternPiece $piece) => [
                'code' => $piece->code,
                'name' => $piece->name,
                'layer' => $piece->layer,
                'cut_quantity' => $piece->cut_quantity,
                'on_fold' => (bool) $piece->on_fold,
                'mirror' => (bool) $piece->mirror,
                'outline' => $piece->outline,
                'grainline' => $piece->grainline,
                'darts' => $piece->darts,
                'notches' => $piece->notches,
                'drills' => $piece->drills,
                'pleats' => $piece->pleats,
                'markers' => $piece->markers,
                'edge_allowances' => $piece->edge_allowances,
                'edge_tags' => $this->seams->edgeTags($piece),
                'meta' => $piece->meta,
                'measures' => [
                    'width' => $piece->width(),
                    'height' => $piece->height(),
                    'area' => $piece->area(),
                    'perimeter' => $piece->perimeter(),
                ],
            ])->values()->all(),
        ];
    }

    /** کادر قطعه با در نظر گرفتن خط برش. */
    protected function pieceBox(PatternPiece $piece, bool $withSeam): array
    {
        [$minX, $minY, $maxX, $maxY] = $piece->bounds();

        if ($withSeam) {
            foreach ($this->seams->cuttingLine($piece) as $point) {
                $minX = min($minX, $point['x']);
                $minY = min($minY, $point['y']);
                $maxX = max($maxX, $point['x']);
                $maxY = max($maxY, $point['y']);
            }
        }

        return [$minX - 1, $minY - 1, $maxX + 1, $maxY + 5];
    }

    protected function download(string $body, string $filename, string $contentType): Response
    {
        return response($body, 200, [
            'Content-Type' => $contentType.'; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($body),
        ]);
    }
}
