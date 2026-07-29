<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

/**
 * بازیابی رمز عبور.
 *
 * تا امروز کسی که رمزش را فراموش می‌کرد برای همیشه از کارگاه خودش بیرون می‌ماند —
 * با همه مشتری‌ها، الگوها و سفارش‌هایش. حالا لینک بازیابی به ایمیلش می‌رود.
 *
 * نکته امنیتی: پیام موفقیت همیشه یکسان است، چه ایمیل در سامانه باشد چه نباشد؛
 * وگرنه همین صفحه به ابزاری برای فهمیدن اینکه چه کسی اینجا حساب دارد تبدیل می‌شود.
 */
class PasswordResetController extends Controller
{
    /** پیام یکسان، چه ایمیل پیدا شود چه نه. */
    protected const SENT = 'اگر این ایمیل در سامانه باشد، لینک بازیابی برایش فرستاده شد. صندوق ورودی و پوشه هرزنامه را ببینید.';

    public function request(): View
    {
        return view('auth.forgot-password');
    }

    public function send(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'max:190'],
        ], [], ['email' => 'ایمیل']);

        Password::sendResetLink($request->only('email'));

        return back()->with('status', static::SENT);
    }

    public function reset(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [], [
            'email' => 'ایمیل',
            'password' => 'رمز عبور',
        ]);

        $status = Password::reset($data, function ($user, string $password) {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($user));
        });

        if ($status !== Password::PasswordReset) {
            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'این لینک بازیابی دیگر کار نمی‌کند؛ ممکن است منقضی شده یا یک بار استفاده شده باشد.',
            ]);
        }

        return redirect()->route('login')->with('status', 'رمز تازه ثبت شد؛ حالا وارد شوید.');
    }
}
