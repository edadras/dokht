<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Workshop extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'owner_id', 'phone', 'city', 'address', 'logo_path',
        'currency', 'default_seam_allowance', 'default_hem_allowance', 'settings',
    ];

    protected $casts = [
        'settings' => 'array',
        'default_seam_allowance' => 'float',
        'default_hem_allowance' => 'float',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $workshop) {
            $workshop->slug ??= static::uniqueSlug($workshop->name);
        });
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'workshop';
        $slug = $base;
        $i = 2;

        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function invites(): HasMany
    {
        return $this->hasMany(WorkshopInvite::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function fabrics(): HasMany
    {
        return $this->hasMany(Fabric::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    public function patterns(): HasMany
    {
        return $this->hasMany(Pattern::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    /** جای دوخت پیش‌فرض کارگاه، آماده برای الگوی جدید. */
    public function defaultSeamAllowances(): array
    {
        return array_merge([
            'default' => $this->default_seam_allowance,
            'hem' => $this->default_hem_allowance,
            'neck' => 0.7,
            'shoulder' => 1.0,
            'side' => 1.5,
            'armhole' => 1.0,
            'waist' => 1.0,
        ], (array) $this->setting('seam_allowances', []));
    }
}
