<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkshop;
use App\Support\Jalali;
use App\Support\Measurements;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workshop_id', 'customer_id', 'title', 'unit', 'base_size', 'values', 'is_default', 'notes'])]
class MeasurementSet extends Model
{
    use BelongsToWorkshop, HasFactory;

    protected function casts(): array
    {
        return [
            'values' => 'array',
            'is_default' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** اندازه‌ها با پر شدن خودکار موارد خالی. */
    public function completed(): array
    {
        return Measurements::complete($this->values ?? []);
    }

    public function value(string $key): ?float
    {
        return isset($this->values[$key]) ? (float) $this->values[$key] : null;
    }

    public function displayTitle(): string
    {
        return $this->title ?: 'اندازه‌های '.($this->customer?->name ?? 'بدون نام');
    }

    /** خلاصه سه‌عددی برای نمایش در فهرست‌ها. */
    public function summary(): string
    {
        $values = $this->values ?? [];

        return collect(['bust' => 'سینه', 'waist' => 'کمر', 'hip' => 'باسن'])
            ->filter(fn ($label, $key) => isset($values[$key]))
            ->map(fn ($label, $key) => $label.' '.Jalali::digits((string) $values[$key]))
            ->implode(' • ');
    }
}
