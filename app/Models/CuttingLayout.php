<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkshop;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** چیدمان قطعات روی پارچه و محاسبه مصرف. */
#[Fillable([
    'workshop_id', 'project_id', 'pattern_id', 'fabric_id', 'fabric_width_cm',
    'required_length_cm', 'waste_percent', 'efficiency', 'respect_nap',
    'match_stripes', 'folded', 'placements', 'warnings',
])]
class CuttingLayout extends Model
{
    use BelongsToWorkshop, HasFactory;

    protected function casts(): array
    {
        return [
            'placements' => 'array',
            'warnings' => 'array',
            'fabric_width_cm' => 'float',
            'required_length_cm' => 'float',
            'waste_percent' => 'float',
            'efficiency' => 'float',
            'respect_nap' => 'boolean',
            'match_stripes' => 'boolean',
            'folded' => 'boolean',
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

    public function fabric(): BelongsTo
    {
        return $this->belongsTo(Fabric::class);
    }

    /** طول لازم به متر. */
    public function requiredMeters(): float
    {
        return round($this->required_length_cm / 100, 2);
    }

    /** هزینه پارچه بر پایه قیمت هر متر. */
    public function estimatedCost(): ?float
    {
        $price = $this->fabric?->price_per_meter;

        return $price ? round($this->requiredMeters() * $price) : null;
    }
}
