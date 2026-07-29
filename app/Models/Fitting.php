<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkshop;
use App\Models\Concerns\RecordsActivity;
use App\Support\Alterations;
use App\Support\Jalali;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * یک نوبت پرو.
 *
 * خیاط لباس نیمه‌دوخته را روی تن مشتری امتحان می‌کند، آنچه دید را ثبت می‌کند و
 * سامانه همان را به اصلاح الگو تبدیل می‌کند. تا وقتی «اعمال» نشده، فقط یک
 * یادداشت است؛ پس از اعمال، الگو بازتولید و یک نسخه تازه ثبت می‌شود.
 */
#[Fillable([
    'workshop_id', 'project_id', 'pattern_id', 'fitted_on', 'round',
    'notes', 'adjustments', 'applied', 'applied_at', 'created_by',
])]
class Fitting extends Model
{
    use BelongsToWorkshop, HasFactory, RecordsActivity;

    protected function casts(): array
    {
        return [
            'fitted_on' => 'date',
            'adjustments' => 'array',
            'applied' => 'array',
            'applied_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function pattern(): BelongsTo
    {
        return $this->belongsTo(Pattern::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isApplied(): bool
    {
        return $this->applied_at !== null;
    }

    public function fittedOnJalali(): string
    {
        return $this->fitted_on ? Jalali::date($this->fitted_on) : '—';
    }

    /** خلاصه فارسی اصلاح‌های این پرو. */
    public function summary(): string
    {
        $parts = [];

        foreach ($this->adjustments ?? [] as $row) {
            $value = (float) ($row['value'] ?? 0);

            if (abs($value) < 0.01) {
                continue;
            }

            $parts[] = Alterations::label((string) ($row['key'] ?? ''))
                .' '.($value > 0 ? '+' : '−').Jalali::digits((string) abs($value));
        }

        return $parts === [] ? 'بدون اصلاح' : implode('، ', $parts);
    }
}
