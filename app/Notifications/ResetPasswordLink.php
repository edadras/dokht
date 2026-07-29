<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;
use App\Support\Jalali;

/**
 * ایمیل بازیابی رمز، به فارسی.
 *
 * پیام پیش‌فرض لاراول انگلیسی است و برای کاربر این سامانه بی‌معنا؛ پس همین‌جا
 * جایگزین می‌شود. مدت اعتبار لینک از همان تنظیمات خود لاراول خوانده می‌شود تا
 * عددی که در متن نوشته می‌شود با واقعیت یکی باشد.
 */
class ResetPasswordLink extends Notification
{
    use Queueable;

    public function __construct(public string $token) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutes = (int) Config::get('auth.passwords.'.Config::get('auth.defaults.passwords').'.expire', 60);

        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject('بازیابی رمز عبور — دوخت')
            ->greeting('سلام')
            ->line('برای حساب شما در «دوخت» درخواست بازیابی رمز عبور ثبت شده است.')
            ->action('ساختن رمز تازه', $url)
            ->line('این لینک تا '.Jalali::digits((string) $minutes).' دقیقه دیگر معتبر است.')
            ->line('اگر شما این درخواست را نداده‌اید، همین ایمیل را نادیده بگیرید؛ رمز فعلی شما دست‌نخورده می‌ماند.')
            ->salutation('کارگاه دوخت');
    }
}
