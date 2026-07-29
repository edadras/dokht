<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Support\Jalali;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ], [], [
            'email' => 'ایمیل',
            'password' => 'رمز عبور',
        ]);

        // قفل روی «ایمیل + نشانی» جدا از محدودیت نرخ مسیر است: آن یکی جلوی سیل
        // درخواست را می‌گیرد، این یکی جلوی حدس‌زدن رمزِ یک حساب مشخص را.
        $this->ensureNotLocked($request);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($this->lockKey($request), 900);

            throw ValidationException::withMessages([
                'email' => 'ایمیل یا رمز عبور درست نیست.',
            ]);
        }

        RateLimiter::clear($this->lockKey($request));
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /** پنج تلاش ناموفق پشت سر هم، پانزده دقیقه قفل. */
    protected function ensureNotLocked(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->lockKey($request), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->lockKey($request));

        throw ValidationException::withMessages([
            'email' => 'تلاش‌های ناموفق زیاد بود. '
                .Jalali::digits((string) max(1, (int) ceil($seconds / 60)))
                .' دقیقه دیگر دوباره امتحان کنید.',
        ]);
    }

    /** کلید قفل: ایمیل به‌علاوه نشانی درخواست‌دهنده. */
    protected function lockKey(Request $request): string
    {
        return 'login:'.Str::lower((string) $request->input('email')).'|'.$request->ip();
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
