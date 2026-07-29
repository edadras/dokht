<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkshop;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['workshop_id', 'name', 'phone', 'email', 'gender', 'birth_date', 'notes'])]
class Customer extends Model
{
    use BelongsToWorkshop, HasFactory, RecordsActivity, SoftDeletes;

    public const GENDERS = [
        'female' => 'خانم',
        'male' => 'آقا',
        'child' => 'کودک',
    ];

    protected function casts(): array
    {
        return ['birth_date' => 'date'];
    }

    public function measurementSets(): HasMany
    {
        return $this->hasMany(MeasurementSet::class)->latest('id');
    }

    public function defaultMeasurementSet(): HasOne
    {
        return $this->hasOne(MeasurementSet::class)->ofMany([
            'is_default' => 'max',
            'id' => 'max',
        ]);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function genderLabel(): string
    {
        return static::GENDERS[$this->gender] ?? $this->gender;
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term = trim((string) $term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%");
        });
    }
}
