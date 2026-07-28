<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkshop;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** کارت فنی لباس؛ خروجی نهایی برای تولید. */
#[Fillable(['workshop_id', 'project_id', 'version', 'data'])]
class TechPack extends Model
{
    use BelongsToWorkshop, HasFactory;

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function section(string $key, mixed $default = null): mixed
    {
        return data_get($this->data, $key, $default);
    }

    /** چیزهایی که برای کامل شدن کارت فنی کم است. */
    public function missing(): array
    {
        return (array) $this->section('missing', []);
    }

    public function isComplete(): bool
    {
        return $this->missing() === [];
    }

    /** شماره نسخه بعدی کارت فنی یک پروژه. */
    public static function nextVersion(Project $project): int
    {
        return (int) static::query()
            ->where('project_id', $project->id)
            ->max('version') + 1;
    }
}
