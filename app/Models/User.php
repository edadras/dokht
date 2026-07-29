<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Notifications\ResetPasswordLink;
use Illuminate\Notifications\Notifiable;

#[Fillable(['workshop_id', 'name', 'email', 'password', 'role', 'is_admin', 'phone', 'avatar_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** نقش‌ها و برچسب فارسی آن‌ها. */
    public const ROLES = [
        'owner' => 'مدیر کارگاه',
        'designer' => 'طراح و الگوساز',
        'tailor' => 'خیاط',
        'viewer' => 'فقط مشاهده',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    /** ایمیل بازیابی رمز، به فارسی. */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordLink($token));
    }

    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }

    public function roleLabel(): string
    {
        return static::ROLES[$this->role] ?? (string) $this->role;
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    /** اجازه ایجاد و ویرایش داده‌های کارگاه. */
    public function canManage(): bool
    {
        return in_array($this->role, ['owner', 'designer', 'tailor'], true);
    }

    /** اجازه طراحی الگو و پروژه. */
    public function canDesign(): bool
    {
        return in_array($this->role, ['owner', 'designer'], true);
    }

    /** اجازه تنظیمات کارگاه و مدیریت اعضا. */
    public function canAdministerWorkshop(): bool
    {
        return $this->role === 'owner';
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];

        return mb_substr($parts[0] ?? '؟', 0, 1).(isset($parts[1]) ? mb_substr($parts[1], 0, 1) : '');
    }
}
