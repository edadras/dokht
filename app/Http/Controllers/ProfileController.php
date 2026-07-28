<?php

namespace App\Http\Controllers;

use App\Support\Jalali;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('profile.edit', ['user' => auth()->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'phone' => ['nullable', 'string', 'max:32'],
        ], [], [
            'name' => 'نام و نام خانوادگی',
            'email' => 'ایمیل',
            'phone' => 'شماره تماس',
        ]);

        $data['phone'] = Jalali::latinDigits($data['phone'] ?? null);

        $user->update($data);

        return back()->with('status', 'اطلاعات حساب شما ذخیره شد.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [], [
            'current_password' => 'رمز فعلی',
            'password' => 'رمز جدید',
        ]);

        $request->user()->update(['password' => Hash::make($data['password'])]);

        return back()->with('status', 'رمز عبور شما تغییر کرد.');
    }
}
