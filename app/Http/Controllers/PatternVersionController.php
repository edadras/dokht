<?php

namespace App\Http\Controllers;

use App\Models\Pattern;
use App\Models\PatternVersion;
use App\Services\Pattern\PatternVersionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * تاریخچه نسخه‌های یک الگو.
 *
 * پیش از هر تغییر هندسی یک نسخه ثبت می‌شود؛ اینجا می‌توان نسخه‌ها را دید، با نسخه
 * فعلی مقایسه کرد و هر نسخه را برگرداند.
 */
class PatternVersionController extends Controller
{
    public function __construct(protected PatternVersionService $versions) {}

    public function index(Pattern $pattern): View
    {
        $pattern->load('pieces');
        $versions = $pattern->versions()->with('author')->get();
        $current = $this->versions->buildSnapshot($pattern);

        $comparisons = [];
        $previous = $current;

        foreach ($versions as $version) {
            $comparisons[$version->id] = $this->versions->diff($version->snapshot ?? [], $previous);
            $previous = $version->snapshot ?? [];
        }

        return view('patterns.versions', [
            'pattern' => $pattern,
            'versions' => $versions,
            'current' => $current,
            'comparisons' => $comparisons,
        ]);
    }

    public function store(Request $request, Pattern $pattern): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:160'],
        ], [], ['note' => 'یادداشت']);

        $pattern->load('pieces');
        $version = $this->versions->snapshot($pattern, $data['note'] ?: 'ثبت دستی نسخه', bump: true);

        return back()->with('status', 'نسخه '.$version->version.' ثبت شد.');
    }

    public function restore(Pattern $pattern, PatternVersion $version): RedirectResponse
    {
        abort_unless($version->pattern_id === $pattern->id, 404);

        $pattern->load('pieces');
        $this->versions->restore($pattern, $version);

        return redirect()->route('patterns.show', $pattern)
            ->with('status', 'نسخه '.$version->version.' بازگردانده شد.');
    }
}
