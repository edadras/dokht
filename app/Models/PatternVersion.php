<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['pattern_id', 'version', 'snapshot', 'note', 'created_by'])]
class PatternVersion extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }

    public function pattern(): BelongsTo
    {
        return $this->belongsTo(Pattern::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function pieceCount(): int
    {
        return count($this->snapshot['pieces'] ?? []);
    }
}
