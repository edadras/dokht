<?php

namespace App\Models\Scopes;

use App\Support\WorkshopContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/** محدود کردن پرس‌وجو به کارگاه فعال. */
class WorkshopScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $workshopId = app(WorkshopContext::class)->id();

        if ($workshopId !== null) {
            $builder->where($model->qualifyColumn('workshop_id'), $workshopId);
        }
    }
}
