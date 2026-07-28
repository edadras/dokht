<?php

namespace App\Models\Concerns;

use App\Models\Scopes\WorkshopScope;
use App\Models\Workshop;
use App\Support\WorkshopContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** داده‌های این مدل همیشه به کارگاه فعال محدود می‌شود. */
trait BelongsToWorkshop
{
    public static function bootBelongsToWorkshop(): void
    {
        static::addGlobalScope(new WorkshopScope);

        static::creating(function (Model $model) {
            if ($model->getAttribute('workshop_id') === null) {
                $model->setAttribute('workshop_id', app(WorkshopContext::class)->id());
            }
        });
    }

    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }

    /** بدون محدودیت کارگاه (برای پنل مدیریت و گزارش‌های سراسری). */
    public function scopeAcrossWorkshops(Builder $query): Builder
    {
        return $query->withoutGlobalScope(WorkshopScope::class);
    }
}
