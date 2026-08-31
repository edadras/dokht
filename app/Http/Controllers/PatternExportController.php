<?php

namespace App\Http\Controllers;

use App\Models\Pattern;
use App\Models\PatternPiece;
use App\Services\Export\AamaDxfExporter;
use App\Services\Export\ExportTooLargeException;
use App\Services\Export\PatternPdfExporter;
use App\Services\Export\PatternPngExporter;
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
 *
 * قالب‌های خروجی:
 *   svg   نقشه برداری همه قطعه‌ها
 *   pdf   سند برداری چندصفحه‌ای A4، آماده چاپ یک‌به‌یک (PatternPdfExporter)
 *   png   تصویر نقطه‌ای با چگالی دلخواه ?dpi= و ?seam= (PatternPngExporter)
 *   dxf   DXF ساده R12 با لایه‌های نام‌دار
 *   aama  DXF صنعتی با لایه‌های شماره‌دار AAMA
 *   astm  همان، در گویش ASTM D6673 با صفت‌های قطعه
 *   json  ساختار قابل‌حمل الگو
 *   sew3d بستهٔ دوختِ سه‌بعدی: قطعه‌ها + نقشهٔ دوخت + اندازه‌های مانکن + پارچه
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
        protected PatternPdfExporter $pdf = new PatternPdfExporter,
        protected PatternPngExporter $png = new PatternPngExporter,
        protected AamaDxfExporter $aama = new AamaDxfExporter,
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

    public function export(Request $request, Pattern $pattern, string $format): Response
    {
        $pattern->load(['pieces', 'garmentType', 'template']);
        $name = Str::slug($pattern->name) ?: 'pattern';
        $base = 'pattern-'.$pattern->id.'-'.$name;
        $withSeam = ! $request->boolean('no_seam');

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
            'pdf' => $this->binary(
                $this->pdf->export($pattern, ['seam_allowance' => $withSeam]),
                $base.'.pdf',
                'application/pdf',
            ),
            'png' => $this->exportPng($request, $pattern, $base),
            'aama' => $this->download(
                $this->aama->aama($pattern, ['sizes' => $this->sizes($request)]),
                $base.'-aama.dxf',
                'application/dxf',
            ),
            'astm' => $this->download(
                $this->aama->astm($pattern, ['sizes' => $this->sizes($request)]),
                $base.'-astm.dxf',
                'application/dxf',
            ),
            'sew3d' => $this->download(
                json_encode($this->sewingPackage($pattern), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                $base.'-sew3d.json',
                'application/json',
            ),
            default => $this->download(
                json_encode($this->payload($pattern), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                $base.'.json',
                'application/json',
            ),
        };
    }

    /**
     * خروجی PNG با گزینه‌های ?dpi= و ?seam=.
     *
     * اگر اندازه خواسته‌شده از سقف پیکسل بگذرد، به جای خطای ۵۰۰ یک پاسخ ۴۲۲ با
     * توضیح فارسی برمی‌گردد تا کاربر بداند چه چیزی را کم کند.
     */
    protected function exportPng(Request $request, Pattern $pattern, string $base): Response
    {
        try {
            $png = $this->png->export($pattern, [
                'dpi' => $request->query('dpi'),
                'seam_allowance' => $request->boolean('seam'),
            ]);
        } catch (ExportTooLargeException $exception) {
            return response($exception->getMessage(), 422, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }

        $dpi = PatternPngExporter::clampDpi($request->query('dpi'));

        return $this->binary($png, $base.'-'.$dpi.'dpi.png', 'image/png');
    }

    /**
     * سایزهای خواسته‌شده برای خروجی صنعتی: ?sizes=38,40,42
     *
     * @return array<int, string>
     */
    protected function sizes(Request $request): array
    {
        $sizes = $request->query('sizes');

        if (! is_string($sizes) || trim($sizes) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $sizes))));
    }

    /**
     * بستهٔ دوختِ سه‌بعدی — دقیقاً همانی که حل‌کنندهٔ مرورگر و سنجه می‌خورند.
     *
     * این خروجی برای موتورهای بیرونی است: هر شبیه‌سازِ پارچه‌ای (XPBD روی
     * GPU، FEM/IPC، بهینه‌سازِ الگو) که بخواهد لباسِ Dokht را بدوزد، همین
     * بسته را می‌گیرد و هیچ چیزِ دیگری لازم ندارد. قالبش در
     * docs/drape-contract.md مستند است و `format_version` قراردادِ آن سند است.
     *
     * @return array<string, mixed>
     */
    protected function sewingPackage(Pattern $pattern): array
    {
        $profile = \App\Support\FabricProfile::make();

        return [
            'format' => 'dokht-sew3d',
            'format_version' => 1,
            'exported_at' => now()->toIso8601String(),
            'pattern' => [
                'id' => $pattern->id,
                'name' => $pattern->name,
                'generator' => $pattern->template?->generator,
                'version' => $pattern->version,
            ],
            'drape' => app(\App\Services\Simulation\DrapePayloadService::class)->payload($pattern),
            'avatar' => \App\Support\Measurements::complete($pattern->measurements ?? []),
            'fabric' => [
                'drape' => round((float) $profile->get('drape'), 3),
                'physics' => $profile->physics(),
            ],
        ];
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

    /** دانلود دودویی (PDF و PNG): بدون charset، چون محتوا متنی نیست. */
    protected function binary(string $body, string $filename, string $contentType): Response
    {
        return response($body, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($body),
        ]);
    }
}
