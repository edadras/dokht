<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** دعوت همکار به کارگاه. */
#[Fillable(['workshop_id', 'email', 'role', 'token', 'accepted_at'])]
class WorkshopInvite extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $invite) {
            $invite->token ??= Str::random(48);
        });
    }

    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function roleLabel(): string
    {
        return User::ROLES[$this->role] ?? $this->role;
    }

    public function url(): string
    {
        return route('invites.show', $this->token);
    }
}
