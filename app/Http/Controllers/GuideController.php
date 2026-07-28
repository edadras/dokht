<?php

namespace App\Http\Controllers;

use App\Models\Guide;
use Illuminate\Http\Request;

/** بخش آموزش: راهنمای برش و دوخت. */
class GuideController extends Controller
{
    public function index(Request $request)
    {
        $term = trim((string) $request->query('q'));
        $scope = $request->query('scope');

        $guides = Guide::query()
            ->with(['fabricType', 'garmentType'])
            ->when($scope && isset(Guide::SCOPES[$scope]), fn ($query) => $query->where('scope', $scope))
            ->when($term !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('title', 'like', "%{$term}%")
                ->orWhere('summary', 'like', "%{$term}%")
                ->orWhere('body', 'like', "%{$term}%")))
            ->orderBy('scope')
            ->orderBy('sort')
            ->get();

        return view('guides.index', [
            'grouped' => $guides->groupBy('scope'),
            'scopes' => Guide::SCOPES,
            'scope' => $scope,
            'q' => $term,
            'total' => Guide::query()->count(),
        ]);
    }

    public function show(Guide $guide)
    {
        $guide->load(['fabricType', 'garmentType']);

        return view('guides.show', [
            'guide' => $guide,
            'paragraphs' => $this->paragraphs($guide->body),
            'related' => Guide::query()
                ->where('id', '!=', $guide->id)
                ->when(
                    $guide->fabric_type_id,
                    fn ($query) => $query->where('fabric_type_id', $guide->fabric_type_id),
                    fn ($query) => $query->where('scope', $guide->scope),
                )
                ->orderBy('sort')
                ->take(5)
                ->get(),
        ]);
    }

    /** متن راهنما به بندهای جدا تقسیم می‌شود تا خوانا نمایش داده شود. */
    protected function paragraphs(?string $body): array
    {
        if ($body === null || trim($body) === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            'trim',
            preg_split('/\R{2,}/u', $body) ?: [],
        ), fn ($paragraph) => $paragraph !== ''));
    }
}
