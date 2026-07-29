<?php

namespace App\Models\Concerns;

use App\Models\Activity;
use App\Support\WorkshopContext;
use Illuminate\Database\Eloquent\Model;

/**
 * ثبت خودکار ردّ کار روی یک مدل.
 *
 * سه قاعده که این ثبت را بی‌آزار نگه می‌دارد:
 *
 *   ۱. فقط نام فیلدهای عوض‌شده و مقدار پیش و پس ذخیره می‌شود، نه کل رکورد؛ وگرنه
 *      جدول ردّ کار از خود داده بزرگ‌تر می‌شود.
 *   ۲. فیلدهای پرحجم و بی‌معنا برای گزارش (مسیر قطعه، عکس شبیه‌سازی، توکن) کنار
 *      گذاشته می‌شوند.
 *   ۳. اگر هیچ فیلد معناداری عوض نشده باشد، ردیفی ثبت نمی‌شود.
 */
trait RecordsActivity
{
    /** فیلدهایی که هرگز در ردّ کار نمی‌آیند. */
    protected static array $activityIgnores = [
        'updated_at', 'created_at', 'remember_token', 'password',
        'outline', 'snapshot', 'placements', 'zones', 'payload', 'params',
        'measurements', 'sewing_relations', 'profile_overrides',
    ];

    public static function bootRecordsActivity(): void
    {
        static::created(fn (Model $model) => $model->recordActivity('created'));
        static::updated(fn (Model $model) => $model->recordActivity('updated'));
        static::deleted(fn (Model $model) => $model->recordActivity('deleted'));
    }

    /** نامی که در گزارش کنار نوع رکورد می‌آید. */
    public function activityLabel(): ?string
    {
        foreach (['name', 'title', 'code'] as $key) {
            if (! empty($this->{$key})) {
                return mb_substr((string) $this->{$key}, 0, 150);
            }
        }

        return null;
    }

    public function recordActivity(string $action): void
    {
        $workshop = $this->workshop_id ?? app(WorkshopContext::class)->id();

        if ($workshop === null || $this instanceof Activity) {
            return;
        }

        $changes = $action === 'updated' ? $this->activityChanges() : [];

        if ($action === 'updated' && $changes === []) {
            return;
        }

        Activity::withoutEvents(fn () => Activity::query()->create([
            'workshop_id' => $workshop,
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => class_basename(static::class),
            'subject_id' => $this->getKey(),
            'subject_label' => $this->activityLabel(),
            'changes' => $changes,
        ]));
    }

    /**
     * فیلدهای عوض‌شده، با مقدار پیش و پس.
     *
     * @return array<string, array{from: mixed, to: mixed}>
     */
    protected function activityChanges(): array
    {
        $out = [];

        foreach ($this->getChanges() as $field => $value) {
            if (in_array($field, static::$activityIgnores, true)) {
                continue;
            }

            $out[$field] = [
                'from' => $this->shortValue($this->getOriginal($field)),
                'to' => $this->shortValue($value),
            ];
        }

        return $out;
    }

    /** مقدارهای بلند و آرایه‌ای در گزارش کوتاه می‌شوند. */
    protected function shortValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return '…';
        }

        if (is_string($value) && mb_strlen($value) > 120) {
            return mb_substr($value, 0, 120).'…';
        }

        return $value;
    }
}
