<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopInvite;
use App\Support\Jalali;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * تنظیمات کارگاه و مدیریت اعضا.
 *
 * فقط مدیر کارگاه (نقش owner) به این بخش دسترسی دارد؛ این محدودیت با گیت
 * administer-workshop روی مسیرها اعمال شده است.
 */
class WorkshopController extends Controller
{
    /** لبه‌هایی که خیاط ممکن است جای دوخت جداگانه برایشان بخواهد. */
    public const EDGE_LABELS = [
        'neck' => 'یقه',
        'shoulder' => 'سرشانه',
        'side' => 'پهلو',
        'armhole' => 'حلقه آستین',
        'waist' => 'خط کمر',
    ];

    /** توضیح ساده هر نقش برای صفحه اعضا. */
    public const ROLE_NOTES = [
        'owner' => 'به همه بخش‌ها دسترسی دارد؛ تنظیمات کارگاه و اعضا را هم او تعیین می‌کند.',
        'designer' => 'الگو و پروژه می‌سازد، سفارش و مشتری ثبت می‌کند؛ به تنظیمات کارگاه دسترسی ندارد.',
        'tailor' => 'سفارش‌ها، مشتری‌ها و پارچه‌ها را ثبت و ویرایش می‌کند؛ الگوی تازه نمی‌سازد.',
        'viewer' => 'همه چیز را می‌بیند اما چیزی را تغییر نمی‌دهد؛ مناسب شاگرد تازه‌کار.',
    ];

    public function edit(Request $request): View
    {
        $workshop = $this->workshop($request);

        return view('workshop.edit', [
            'workshop' => $workshop,
            'allowances' => $workshop->defaultSeamAllowances(),
            'counts' => [
                'customers' => $workshop->customers()->count(),
                'orders' => $workshop->orders()->count(),
                'patterns' => $workshop->patterns()->count(),
                'fabrics' => $workshop->fabrics()->count(),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $workshop = $this->workshop($request);

        // کاربر ممکن است عدد را فارسی تایپ کند
        foreach (['phone', 'default_seam_allowance', 'default_hem_allowance', 'fabric_width'] as $field) {
            if ($request->has($field)) {
                $request->merge([$field => $this->latin($request->input($field))]);
            }
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:32'],
            'city' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'max:8'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
            'default_seam_allowance' => ['required', 'numeric', 'min:0', 'max:10'],
            'default_hem_allowance' => ['required', 'numeric', 'min:0', 'max:20'],
            'seam_allowances' => ['nullable', 'array'],
            'seam_allowances.*' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'fabric_width' => ['nullable', 'numeric', 'min:50', 'max:320'],
            'order_prefix' => ['nullable', 'string', 'max:12'],
        ], [], [
            'name' => 'نام کارگاه',
            'phone' => 'شماره تماس',
            'city' => 'شهر',
            'address' => 'نشانی',
            'currency' => 'واحد پول',
            'logo' => 'نشان کارگاه',
            'default_seam_allowance' => 'جای دوخت پیش‌فرض',
            'default_hem_allowance' => 'جای تودوزی پیش‌فرض',
            'fabric_width' => 'عرض پیش‌فرض پارچه',
            'order_prefix' => 'پیش‌شماره سفارش',
        ]);

        $settings = (array) ($workshop->settings ?? []);

        // جای دوخت هر لبه، در همان ساختاری که Workshop::defaultSeamAllowances() می‌خواند
        $edges = [];

        foreach (array_keys(static::EDGE_LABELS) as $edge) {
            $value = $data['seam_allowances'][$edge] ?? null;

            if ($value !== null && $value !== '') {
                $edges[$edge] = round((float) $this->latin($value), 1);
            }
        }

        $settings['seam_allowances'] = $edges;
        $settings['fabric_width'] = isset($data['fabric_width']) && $data['fabric_width'] !== ''
            ? round((float) $data['fabric_width'], 1)
            : null;
        $settings['order_prefix'] = ($data['order_prefix'] ?? null) ?: null;

        $attributes = [
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'city' => $data['city'] ?? null,
            'address' => $data['address'] ?? null,
            'currency' => ($data['currency'] ?? null) ?: 'IRT',
            'default_seam_allowance' => round((float) $data['default_seam_allowance'], 1),
            'default_hem_allowance' => round((float) $data['default_hem_allowance'], 1),
            'settings' => $settings,
        ];

        if ($request->hasFile('logo')) {
            $this->deleteLogo($workshop);
            $attributes['logo_path'] = $request->file('logo')->store('workshops', 'public');
        } elseif ($request->boolean('remove_logo')) {
            $this->deleteLogo($workshop);
            $attributes['logo_path'] = null;
        }

        $workshop->update($attributes);

        return back()->with('status', 'تنظیمات کارگاه ذخیره شد.');
    }

    public function members(Request $request): View
    {
        $workshop = $this->workshop($request);

        return view('workshop.members', [
            'workshop' => $workshop,
            'members' => $workshop->users()
                ->orderByRaw("case when role = 'owner' then 0 else 1 end")
                ->orderBy('name')
                ->get(),
            'invites' => $workshop->invites()->whereNull('accepted_at')->latest()->get(),
            'invitableRoles' => static::invitableRoles(),
        ]);
    }

    public function invite(Request $request): RedirectResponse
    {
        $workshop = $this->workshop($request);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in(array_keys(static::invitableRoles()))],
        ], [], [
            'email' => 'ایمیل همکار',
            'role' => 'نقش',
        ]);

        $email = mb_strtolower(trim($data['email']));

        $alreadyMember = $workshop->users()
            ->whereRaw('lower(email) = ?', [$email])
            ->exists();

        if ($alreadyMember) {
            return back()
                ->withInput()
                ->withErrors(['email' => 'این ایمیل همین حالا عضو کارگاه شماست.'])
                ->with('error', 'این ایمیل همین حالا عضو کارگاه شماست.');
        }

        // اگر دعوت باز برای همین ایمیل هست، همان را به‌روز می‌کنیم تا لینک تکراری ساخته نشود
        $invite = $workshop->invites()
            ->whereNull('accepted_at')
            ->whereRaw('lower(email) = ?', [$email])
            ->first();

        if ($invite) {
            $invite->update(['role' => $data['role']]);
        } else {
            $invite = $workshop->invites()->create([
                'email' => $email,
                'role' => $data['role'],
            ]);
        }

        return redirect()->route('workshop.members')
            ->with('status', 'دعوت‌نامه برای '.$invite->email.' ساخته شد. لینک زیر را برای او بفرستید.')
            ->with('invite_link', $invite->url())
            ->with('invite_id', $invite->id);
    }

    public function cancelInvite(Request $request, WorkshopInvite $invite): RedirectResponse
    {
        $this->ensureBelongsToWorkshop($request, $invite->workshop_id);

        $email = $invite->email;
        $invite->delete();

        return redirect()->route('workshop.members')
            ->with('status', 'دعوت‌نامه '.$email.' لغو شد.');
    }

    public function updateMember(Request $request, User $user): RedirectResponse
    {
        $workshop = $this->workshop($request);
        $this->ensureBelongsToWorkshop($request, $user->workshop_id);

        $data = $request->validate([
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
        ], [], ['role' => 'نقش']);

        if ($user->id === $request->user()->id && $data['role'] !== $user->role) {
            return back()->with('error', 'نقش خودتان را نمی‌توانید تغییر دهید؛ از مدیر دیگری بخواهید این کار را انجام دهد.');
        }

        $losesOwner = $user->role === 'owner' && $data['role'] !== 'owner';

        if ($losesOwner && ($user->id === $workshop->owner_id || $this->ownerCount($workshop) <= 1)) {
            return back()->with('error', 'کارگاه باید همیشه دست‌کم یک مدیر داشته باشد؛ ابتدا مدیر دیگری تعیین کنید.');
        }

        $user->update(['role' => $data['role']]);

        return redirect()->route('workshop.members')
            ->with('status', 'نقش '.$user->name.' به «'.$user->roleLabel().'» تغییر کرد.');
    }

    public function removeMember(Request $request, User $user): RedirectResponse
    {
        $workshop = $this->workshop($request);
        $this->ensureBelongsToWorkshop($request, $user->workshop_id);

        if ($user->id === $request->user()->id) {
            return back()->with('error', 'خودتان را نمی‌توانید از کارگاه حذف کنید.');
        }

        if ($user->role === 'owner' || $user->id === $workshop->owner_id) {
            return back()->with('error', 'مدیر کارگاه را نمی‌توان حذف کرد؛ اول نقش او را تغییر دهید.');
        }

        $user->update(['workshop_id' => null]);

        return redirect()->route('workshop.members')
            ->with('status', $user->name.' از کارگاه حذف شد.');
    }

    /** نقش‌هایی که می‌شود با دعوت‌نامه داد؛ مدیر کارگاه با دعوت ساخته نمی‌شود. */
    public static function invitableRoles(): array
    {
        return array_diff_key(User::ROLES, ['owner' => '']);
    }

    protected function workshop(Request $request): Workshop
    {
        return $request->user()->workshop ?? abort(404);
    }

    /** هر رکورد هدف باید متعلق به کارگاه فعال باشد. */
    protected function ensureBelongsToWorkshop(Request $request, ?int $workshopId): void
    {
        abort_if($workshopId === null || $workshopId !== $request->user()->workshop_id, 404);
    }

    protected function ownerCount(Workshop $workshop): int
    {
        return $workshop->users()->where('role', 'owner')->count();
    }

    /** رقم فارسی را به لاتین تبدیل می‌کند و مقدارهای غیرمتنی را دست‌نخورده برمی‌گرداند. */
    protected function latin(mixed $value): mixed
    {
        return is_string($value) ? Jalali::latinDigits($value) : $value;
    }

    protected function deleteLogo(Workshop $workshop): void
    {
        if ($workshop->logo_path) {
            Storage::disk('public')->delete($workshop->logo_path);
        }
    }
}
