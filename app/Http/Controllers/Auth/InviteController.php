<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WorkshopInvite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** پذیرش دعوت همکاری در یک کارگاه. */
class InviteController extends Controller
{
    public function show(string $token): View|RedirectResponse
    {
        $invite = WorkshopInvite::with('workshop')->where('token', $token)->first();

        if ($invite === null || $invite->isAccepted()) {
            return redirect()->route('home')->with('error', 'این دعوت‌نامه معتبر نیست یا قبلاً استفاده شده است.');
        }

        return view('auth.invite', ['invite' => $invite]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invite = WorkshopInvite::where('token', $token)->firstOrFail();

        if ($invite->isAccepted()) {
            return redirect()->route('home')->with('error', 'این دعوت‌نامه قبلاً استفاده شده است.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [], [
            'name' => 'نام و نام خانوادگی',
            'password' => 'رمز عبور',
        ]);

        $user = User::where('email', $invite->email)->first();

        if ($user) {
            $user->update([
                'workshop_id' => $invite->workshop_id,
                'role' => $invite->role,
            ]);
        } else {
            $user = User::create([
                'workshop_id' => $invite->workshop_id,
                'name' => $data['name'],
                'email' => $invite->email,
                'password' => $data['password'],
                'role' => $invite->role,
            ]);
        }

        $invite->update(['accepted_at' => now()]);

        Auth::login($user, remember: true);

        return redirect()->route('dashboard')
            ->with('status', 'به کارگاه '.$invite->workshop->name.' خوش آمدید.');
    }
}
