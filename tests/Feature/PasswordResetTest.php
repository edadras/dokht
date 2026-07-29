<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * بازیابی رمز و بستن در برابر حدس‌زدن رمز.
 *
 * بنیاد این سامانه «هر کسی ثبت‌نام می‌کند و کارگاه خودش را دارد» است؛ پس کسی که
 * رمزش را فراموش می‌کند نباید کارگاهش را از دست بدهد.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_forgot_password_page_opens_and_login_links_to_it(): void
    {
        $this->get(route('password.request'))->assertOk()->assertSee('رمزتان را فراموش کرده‌اید؟');
        $this->get(route('login'))->assertOk()->assertSee(route('password.request'), false);
    }

    public function test_a_reset_link_is_sent_in_persian(): void
    {
        Notification::fake();
        $user = $this->actingAsWorkshopUser();
        auth()->logout();

        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPasswordLink::class, function (ResetPasswordLink $mail) use ($user) {
            $message = $mail->toMail($user);

            $this->assertStringContainsString('بازیابی رمز عبور', $message->subject);
            $this->assertNotEmpty($message->actionUrl);

            return true;
        });
    }

    public function test_an_unknown_email_gets_the_same_answer_so_it_leaks_nothing(): void
    {
        Notification::fake();

        $response = $this->post(route('password.email'), ['email' => 'nobody@example.test']);

        $response->assertSessionHas('status');
        $response->assertSessionHasNoErrors();
        Notification::assertNothingSent();
    }

    public function test_a_valid_token_sets_a_new_password(): void
    {
        $user = $this->actingAsWorkshopUser();
        auth()->logout();

        $token = Password::createToken($user);

        $this->get(route('password.reset', ['token' => $token]).'?email='.urlencode($user->email))->assertOk();

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'رمز-تازه-۱۲۳۴',
            'password_confirmation' => 'رمز-تازه-۱۲۳۴',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('رمز-تازه-۱۲۳۴', $user->fresh()->password));
    }

    public function test_a_stale_token_is_refused(): void
    {
        $user = $this->actingAsWorkshopUser();
        auth()->logout();
        $before = $user->fresh()->password;

        $this->post(route('password.update'), [
            'token' => 'یک-توکن-ساختگی',
            'email' => $user->email,
            'password' => 'رمز-تازه-۱۲۳۴',
            'password_confirmation' => 'رمز-تازه-۱۲۳۴',
        ])->assertSessionHasErrors('email');

        $this->assertSame($before, $user->fresh()->password);
    }

    public function test_five_wrong_passwords_lock_that_account_for_a_while(): void
    {
        RateLimiter::clear('login:locked@dokht.test|127.0.0.1');

        $user = $this->actingAsWorkshopUser('owner', ['email' => 'locked@dokht.test']);
        auth()->logout();

        for ($try = 1; $try <= 5; $try++) {
            $this->post(route('login'), ['email' => $user->email, 'password' => 'غلط'])
                ->assertSessionHasErrors('email');
        }

        // ششمین بار، حتی با رمز درست، باید قفل باشد
        $response = $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertStringContainsString(
            'تلاش‌های ناموفق زیاد بود',
            implode(' ', session('errors')->get('email')),
        );
    }

    public function test_a_successful_login_clears_the_lock_counter(): void
    {
        RateLimiter::clear('login:clean@dokht.test|127.0.0.1');

        $user = $this->actingAsWorkshopUser('owner', ['email' => 'clean@dokht.test']);
        auth()->logout();

        $this->post(route('login'), ['email' => $user->email, 'password' => 'غلط'])->assertSessionHasErrors();
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])->assertRedirect();

        $this->assertAuthenticatedAs($user);
        $this->assertSame(0, RateLimiter::attempts('login:clean@dokht.test|127.0.0.1'));
    }
}
