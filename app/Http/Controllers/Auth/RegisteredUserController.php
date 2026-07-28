<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workshop;
use App\Support\Jalali;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * ثبت‌نام.
 *
 * فقط چهار فیلد: نام، ایمیل، رمز و نام کارگاه (که اگر خالی بماند خودمان می‌سازیم).
 * با ثبت‌نام، کارگاه شخصی کاربر هم ساخته می‌شود و نیازی به مرحله دوم نیست.
 */
class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'workshop_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:32'],
        ], [], [
            'name' => 'نام و نام خانوادگی',
            'email' => 'ایمیل',
            'password' => 'رمز عبور',
            'workshop_name' => 'نام کارگاه',
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => 'owner',
                'phone' => Jalali::latinDigits($data['phone'] ?? null),
            ]);

            $workshop = Workshop::create([
                'name' => ($data['workshop_name'] ?? null) ?: 'کارگاه '.$data['name'],
                'owner_id' => $user->id,
                'phone' => $user->phone,
            ]);

            $user->update(['workshop_id' => $workshop->id]);

            return $user;
        });

        event(new Registered($user));

        Auth::login($user, remember: true);

        return redirect()->route('dashboard')
            ->with('status', 'خوش آمدید! کارگاه شما آماده است. برای شروع یک سفارش یا پروژه بسازید.');
    }
}
