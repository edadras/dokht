<?php

namespace App\Http\Controllers;

use App\Models\GarmentType;
use App\Models\Pattern;
use App\Models\PatternPiece;
use App\Models\PatternTemplate;
use App\Models\PatternVersion;
use App\Services\Pattern\SvgRenderer;
use App\Support\WorkshopContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * کتابخانه مشترک: الگوهای پایه و الگوهای منتشرشده کارگاه‌ها.
 *
 * همه‌چیز فقط برای دیدن و کپی‌کردن است؛ خرید و فروشی در میان نیست. الگوی منتشرشده
 * هر کارگاه برای همه دیده می‌شود و هر کارگاه می‌تواند یک نسخه از آن را برای خودش
 * بردارد و آزادانه تغییر دهد.
 */
class LibraryController extends Controller
{
    public function __construct(protected WorkshopContext $context) {}

    public function index(Request $request): View
    {
        $workshopId = $this->context->id();
        $tab = $request->query('tab') === 'published' ? 'published' : 'templates';
        $term = trim((string) $request->query('q'));
        $garmentTypeId = $request->query('garment_type');
        $category = $request->query('category');

        $templates = PatternTemplate::query()
            ->availableTo($workshopId)
            ->where(fn ($q) => $q->where('is_public', true)->orWhere('workshop_id', $workshopId))
            ->with('garmentType')
            ->when($term !== '', fn ($q) => $q->where(fn ($inner) => $inner
                ->where('name_fa', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")))
            ->when($garmentTypeId, fn ($q) => $q->where('garment_type_id', $garmentTypeId))
            ->when($category, fn ($q) => $q->whereHas('garmentType', fn ($inner) => $inner->where('category', $category)))
            ->orderBy('sort')
            ->orderBy('name_fa')
            ->get();

        $published = Pattern::published()
            ->acrossWorkshops()
            ->with(['workshop', 'garmentType', 'pieces'])
            ->when($term !== '', fn ($q) => $q->search($term))
            ->when($garmentTypeId, fn ($q) => $q->where('garment_type_id', $garmentTypeId))
            ->when($category, fn ($q) => $q->whereHas('garmentType', fn ($inner) => $inner->where('category', $category)))
            ->orderByDesc('copies_count')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return view('library.index', [
            'tab' => $tab,
            'term' => $term,
            'garmentTypeId' => $garmentTypeId,
            'category' => $category,
            'garmentTypes' => GarmentType::active()->orderBy('sort')->orderBy('name_fa')->get(),
            'templates' => $templates,
            'published' => $published,
            'thumbnails' => $published->getCollection()
                ->mapWithKeys(fn (Pattern $pattern) => [$pattern->id => $this->thumbnail($pattern)])
                ->all(),
        ]);
    }

    public function show(int $pattern): View
    {
        $pattern = $this->findPublished($pattern);

        return view('library.show', [
            'pattern' => $pattern,
            'preview' => $this->thumbnail($pattern, ['width' => 720]),
            'isMine' => $pattern->workshop_id === $this->context->id(),
        ]);
    }

    public function copy(int $pattern): RedirectResponse
    {
        $source = $this->findPublished($pattern);
        $workshopId = $this->context->id();

        abort_if($workshopId === null, 404);

        $copy = DB::transaction(function () use ($source, $workshopId) {
            $copy = Pattern::create([
                'workshop_id' => $workshopId,
                'garment_type_id' => $source->garment_type_id,
                'pattern_template_id' => $source->pattern_template_id,
                'measurement_set_id' => null, // دفترچه اندازه مشتری کارگاه دیگر منتقل نمی‌شود
                'name' => $this->copyName($source),
                'base_size' => $source->base_size,
                'measurements' => $source->measurements,
                'ease' => $source->ease,
                'seam_allowances' => $source->seam_allowances,
                'params' => $source->params,
                'sewing_relations' => $source->sewing_relations,
                'version' => 1,
                'notes' => $source->notes,
                'is_published' => false,
            ]);

            foreach ($source->pieces as $piece) {
                $copy->pieces()->create($this->pieceAttributes($piece));
            }

            PatternVersion::create([
                'pattern_id' => $copy->id,
                'version' => 1,
                'snapshot' => $this->snapshot($copy->load('pieces')),
                'note' => 'کپی از کتابخانه («'.$source->name.'» از '.($source->workshop?->name ?? 'کارگاه ناشناس').')',
                'created_by' => auth()->id(),
            ]);

            // شمارنده کپی روی الگوی مبدأ، بی‌توجه به محدودیت کارگاه
            Pattern::query()->acrossWorkshops()->whereKey($source->id)->increment('copies_count');

            return $copy;
        });

        return redirect()->route('patterns.show', $copy)
            ->with('status', 'یک نسخه از این الگو در کارگاه شما ساخته شد؛ آزادانه تغییرش دهید.');
    }

    /** الگوی منتشرشده از هر کارگاهی؛ الگوی منتشرنشده دیده نمی‌شود. */
    protected function findPublished(int $id): Pattern
    {
        return Pattern::query()
            ->acrossWorkshops()
            ->published()
            ->with(['pieces', 'workshop', 'garmentType', 'template'])
            ->findOrFail($id);
    }

    /**
     * نام نسخه کپی.
     *
     * کپی از الگوی خود کارگاه هم مجاز است، اما شماره می‌گیرد تا زنجیره «(کپی)(کپی)»
     * ساخته نشود و کاربر گم نشود.
     */
    protected function copyName(Pattern $source): string
    {
        $base = preg_replace('/\s*\(کپی(?:\s*\d+)?\)\s*$/u', '', $source->name) ?: $source->name;
        $name = $base.' (کپی)';
        $counter = 2;

        while (Pattern::where('name', $name)->exists()) {
            $name = $base.' (کپی '.$counter++.')';
        }

        return $name;
    }

    /** @return array<string, mixed> */
    protected function pieceAttributes(PatternPiece $piece): array
    {
        return [
            'code' => $piece->code,
            'name' => $piece->name,
            'layer' => $piece->layer,
            'cut_quantity' => $piece->cut_quantity,
            'on_fold' => $piece->on_fold,
            'mirror' => $piece->mirror,
            'outline' => $piece->outline,
            'grainline' => $piece->grainline,
            'darts' => $piece->darts,
            'notches' => $piece->notches,
            'drills' => $piece->drills,
            'pleats' => $piece->pleats,
            'markers' => $piece->markers,
            'edge_allowances' => $piece->edge_allowances,
            'meta' => $piece->meta,
            'sort' => $piece->sort,
        ];
    }

    /** عکس لحظه‌ای الگو برای تاریخچه نسخه‌ها. */
    protected function snapshot(Pattern $pattern): array
    {
        return [
            'pattern' => [
                'name' => $pattern->name,
                'base_size' => $pattern->base_size,
                'measurements' => $pattern->measurements,
                'ease' => $pattern->ease,
                'seam_allowances' => $pattern->seam_allowances,
                'params' => $pattern->params,
                'sewing_relations' => $pattern->sewing_relations,
            ],
            'pieces' => $pattern->pieces->map(fn (PatternPiece $piece) => $this->pieceAttributes($piece))->all(),
        ];
    }

    /** تصویر کوچک الگو؛ اگر موتور ترسیم در دسترس نباشد صفحه بدون تصویر کار می‌کند. */
    protected function thumbnail(Pattern $pattern, array $options = []): ?string
    {
        if (! class_exists(SvgRenderer::class)) {
            return null;
        }

        try {
            return app(SvgRenderer::class)->renderPattern($pattern, $options + [
                'width' => 320,
                'labels' => false,
            ]);
        } catch (\Throwable) {
            return null;
        }
    }
}
