<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkshop;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * یک ردیف از ردّ کار کارگاه.
 *
 * کارگاهی که چند کاربر دارد — مدیر، طراح، خیاط — باید بتواند بپرسد «چه کسی این
 * مشتری را حذف کرد؟». الگو نسخه‌گیری خودش را دارد، ولی بقیه چیزها تا امروز هیچ
 * ردی نمی‌گذاشتند.
 */
#[Fillable([
    'workshop_id', 'user_id', 'action', 'subject_type', 'subject_id', 'subject_label', 'changes',
])]
class Activity extends Model
{
    use BelongsToWorkshop, HasFactory;

    public const UPDATED_AT = null;

    /** برچسب فارسی هر کار (برای نشان و فیلتر). */
    public const ACTIONS = [
        'created' => 'ساخت',
        'updated' => 'ویرایش',
        'deleted' => 'حذف',
        'restored' => 'بازگردانی',
    ];

    /** همان کار، این بار به شکل فعل جمله. */
    public const VERBS = [
        'created' => 'ساخت',
        'updated' => 'ویرایش کرد',
        'deleted' => 'حذف کرد',
        'restored' => 'بازگرداند',
    ];

    /** نام فارسی هر نوع رکورد. */
    public const SUBJECTS = [
        'Customer' => 'مشتری',
        'MeasurementSet' => 'دفترچه اندازه',
        'Fabric' => 'پارچه',
        'Material' => 'متعلقات',
        'Pattern' => 'الگو',
        'Project' => 'پروژه',
        'Order' => 'سفارش',
        'Payment' => 'پرداخت',
        'Fitting' => 'پرو',
        'User' => 'کاربر',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actionLabel(): string
    {
        return static::ACTIONS[$this->action] ?? $this->action;
    }

    public function subjectLabel(): string
    {
        return static::SUBJECTS[$this->subject_type] ?? $this->subject_type;
    }

    /** یک جمله فارسی: «مریم سفارش «کت مشکی» را ویرایش کرد». */
    public function sentence(): string
    {
        $who = $this->user?->name ?? 'کاربر حذف‌شده';
        $what = $this->subject_label ? '«'.$this->subject_label.'»' : '';

        $verb = static::VERBS[$this->action] ?? $this->action;

        return trim(preg_replace('/\s+/u', ' ', $who.' '.$this->subjectLabel().' '.$what.' را '.$verb));
    }
}
