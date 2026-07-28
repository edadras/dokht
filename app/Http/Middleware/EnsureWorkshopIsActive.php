<?php

namespace App\Http\Middleware;

use App\Support\WorkshopContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** کارگاه کاربر وارد‌شده را به‌عنوان کارگاه فعال درخواست تعیین می‌کند. */
class EnsureWorkshopIsActive
{
    public function __construct(protected WorkshopContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        if ($user->workshop_id === null) {
            auth()->logout();

            return redirect()->route('login')
                ->with('error', 'حساب شما به هیچ کارگاهی متصل نیست. لطفاً دوباره ثبت‌نام کنید.');
        }

        $this->context->set($user->workshop_id);

        return $next($request);
    }
}
