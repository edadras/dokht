<?php

namespace App\Services\Assistant;

use App\Models\Fabric;
use App\Models\GarmentType;
use App\Models\Project;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * پاسخ دستیار با کمک یک مدل زبانی (Anthropic Messages API).
 *
 * این درایور جای دستیار قاعده‌محور را نمی‌گیرد؛ روی آن سوار می‌شود. اول همان پاسخ
 * قطعی ساخته می‌شود، بعد داده‌هایی که آن پاسخ را ساخته‌اند — شناسنامه پارچه با
 * برچسب‌های فارسی، مدل لباس، نمره سازگاری، قواعد برقرار و نتیجه آخرین شبیه‌سازی —
 * به‌شکل ساختاریافته به مدل داده می‌شود و از او یک پاسخ کوتاه فارسی برای خیاطِ سرِ
 * کار خواسته می‌شود.
 *
 * سه چیز تضمین می‌شود:
 * ۱) نبود کلید، خطا، یا کندی سرویس هرگز صفحه را از کار نمی‌اندازد؛ پاسخ قواعد
 *    برمی‌گردد و به کاربر گفته می‌شود کدام پاسخ را گرفته است.
 * ۲) فقط داده‌های خود کارگاه فرستاده می‌شود؛ نام و شماره تماس مشتری پاک می‌شود.
 * ۳) اگر داده‌ها جواب پرسش را ندهند، از مدل خواسته می‌شود همین را صریح بگوید.
 */
class ClaudeAssistant implements AssistantDriver
{
    /** جمله‌ای که به کاربر می‌گوید این پاسخ از کجا آمده است. */
    public const SOURCE_LABEL = 'این پاسخ با کمک مدل زبانی تهیه شده است';

    public function __construct(
        protected WorkshopAssistant $rules,
        protected array $config = [],
    ) {}

    public function driverName(): string
    {
        return 'claude';
    }

    public function ask(
        string $question,
        ?Fabric $fabric = null,
        ?GarmentType $garmentType = null,
        ?Project $project = null,
    ): array {
        // پاسخ قطعی همیشه ساخته می‌شود: هم زمینه پرسش است، هم تکیه‌گاه.
        $base = $this->rules->ask($question, $fabric, $garmentType, $project);

        if (! $this->configured()) {
            return $this->fallback($base, 'کلید سرویس مدل زبانی ثبت نشده است.');
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => (string) $this->config['key'],
                'anthropic-version' => (string) ($this->config['version'] ?? '2023-06-01'),
                'content-type' => 'application/json',
            ])
                ->timeout((int) ($this->config['timeout'] ?? 20))
                ->post(
                    rtrim((string) ($this->config['base_url'] ?? 'https://api.anthropic.com'), '/').'/v1/messages',
                    $this->payload($question, $base, $fabric, $garmentType, $project),
                );
        } catch (Throwable $exception) {
            Log::warning('دستیار: تماس با مدل زبانی ناموفق بود.', ['error' => $exception->getMessage()]);

            return $this->fallback($base, 'سرویس مدل زبانی در دسترس نبود.');
        }

        if (! $response->successful()) {
            Log::warning('دستیار: مدل زبانی خطا برگرداند.', ['status' => $response->status()]);

            return $this->fallback($base, 'سرویس مدل زبانی خطا برگرداند.');
        }

        $text = $this->textOf($response->json());

        if ($text === '') {
            return $this->fallback($base, 'پاسخ مدل زبانی خالی یا ناقص بود.');
        }

        return array_merge($base, [
            'headline' => $text,
            'source' => 'claude',
            'source_label' => static::SOURCE_LABEL,
            'reasons' => array_merge(
                [[
                    'label' => 'پاسخ با کمک مدل زبانی',
                    'text' => sprintf(
                        'متن بالا را مدل «%s» بر پایه همین داده‌های سامانه نوشته است؛ نکته‌ها و استدلال‌های زیر همچنان از قواعد خود سامانه می‌آید.',
                        (string) ($this->config['model'] ?? ''),
                    ),
                ]],
                $base['reasons'],
            ),
        ]);
    }

    /** آیا این درایور اصلاً قابل استفاده است؟ */
    public function configured(): bool
    {
        return trim((string) ($this->config['key'] ?? '')) !== '';
    }

    /**
     * بدنه درخواست Messages API.
     *
     * @return array<string, mixed>
     */
    protected function payload(
        string $question,
        array $base,
        ?Fabric $fabric,
        ?GarmentType $garmentType,
        ?Project $project,
    ): array {
        $context = $this->context($base, $fabric, $garmentType, $project);

        return [
            'model' => (string) ($this->config['model'] ?? 'claude-opus-5'),
            'max_tokens' => max(256, (int) ($this->config['max_tokens'] ?? 1024)),
            // پاسخ کوتاه است و به استدلال طولانی نیاز ندارد؛ با خاموش بودن تفکر،
            // همه سقف توکن به خود پاسخ می‌رسد و جواب دیرتر از دست کاربر در نمی‌رود
            'thinking' => ['type' => 'disabled'],
            'system' => $this->systemPrompt(),
            'messages' => [[
                'role' => 'user',
                'content' => [[
                    'type' => 'text',
                    'text' => $this->userPrompt($question, $context),
                ]],
            ]],
        ];
    }

    protected function systemPrompt(): string
    {
        return implode("\n", [
            'شما دستیار یک کارگاه خیاطی فارسی‌زبان هستید و با خیاطی حرف می‌زنید که همین حالا سرِ کار است.',
            'پاسخ را کوتاه بنویسید: حداکثر چهار جمله، ساده، عملی و به فارسی روان.',
            'فقط بر پایه «داده‌های سامانه» که در پیام کاربر آمده پاسخ بدهید؛ عدد یا ویژگی‌ای که در داده‌ها نیست از خودتان نسازید.',
            'اگر داده‌ها برای پاسخ دادن کافی نیست، صریح بگویید که این داده‌ها جواب را نمی‌دهند و بگویید چه چیزی باید در سامانه ثبت شود.',
            'از عنوان، فهرست شماره‌دار، ایموجی و تگ‌های داخلی یا XML استفاده نکنید؛ فقط چند جمله پیوسته بنویسید.',
        ]);
    }

    protected function userPrompt(string $question, array $context): string
    {
        return implode("\n\n", [
            'پرسش خیاط: '.$question,
            "داده‌های سامانه (تنها منبع مجاز):\n".json_encode(
                $context,
                JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            ),
            'حالا پاسخ کوتاه فارسی خود را بنویسید.',
        ]);
    }

    /**
     * زمینه ساختاریافته‌ای که به مدل داده می‌شود.
     *
     * هر رشته‌ای که از اینجا بیرون می‌رود از فیلتر «پاک‌سازی» رد می‌شود تا نام و
     * شماره تماس مشتری در آن نماند.
     *
     * @return array<string, mixed>
     */
    protected function context(array $base, ?Fabric $fabric, ?GarmentType $garmentType, ?Project $project): array
    {
        $context = $this->rules->facts($fabric, $garmentType, $project);

        $context['موضوع پرسش'] = $base['topic_label'];
        $context['پاسخ قاعده‌محور سامانه'] = array_filter([
            'خلاصه' => $base['headline'],
            'نکته‌ها' => $base['points'],
        ]);

        if ($project) {
            $context['پروژه'] = array_filter([
                'نام' => $project->name,
                'مدل' => $project->garmentType?->name_fa,
                'وضعیت' => $project->statusLabel(),
                'مرحله' => $project->stepTitle(),
                'سایز' => $project->size,
            ]);
        }

        return $this->scrub($context, $project);
    }

    /**
     * پاک کردن داده‌های شخصی مشتری از هر رشته‌ای که به بیرون می‌رود.
     *
     * نام مشتری (و تکه‌های آن) و هر رشته رقمی که به شماره تماس می‌خورد برداشته
     * می‌شود؛ ارقام فارسی هم مثل ارقام لاتین دیده می‌شوند.
     */
    protected function scrub(mixed $value, ?Project $project = null): mixed
    {
        if (is_array($value)) {
            $out = [];

            foreach ($value as $key => $item) {
                $out[is_string($key) ? (string) $this->scrub($key, $project) : $key] = $this->scrub($item, $project);
            }

            return $out;
        }

        if (! is_string($value)) {
            return $value;
        }

        $customer = $project?->customer;

        foreach ([$customer?->name, $customer?->phone, $customer?->email] as $secret) {
            $secret = trim((string) $secret);

            if ($secret === '') {
                continue;
            }

            $value = str_replace($secret, '—', $value);

            foreach (preg_split('/\s+/u', $secret) ?: [] as $part) {
                if (mb_strlen($part) >= 3) {
                    $value = str_replace($part, '—', $value);
                }
            }
        }

        // هر رشته دست‌کم هفت‌رقمی، شماره تماس فرض می‌شود
        return (string) preg_replace('/[0-9\x{06F0}-\x{06F9}\x{0660}-\x{0669}][0-9\x{06F0}-\x{06F9}\x{0660}-\x{0669}\-\s]{6,}/u', '—', $value);
    }

    /** بیرون کشیدن متن پاسخ از بدنه Messages API. */
    protected function textOf(mixed $body): string
    {
        if (! is_array($body)) {
            return '';
        }

        // پاسخ رد شده (stop_reason = refusal) متن قابل استفاده ندارد
        if (($body['stop_reason'] ?? null) === 'refusal') {
            return '';
        }

        $text = collect($body['content'] ?? [])
            ->filter(fn ($block) => is_array($block) && ($block['type'] ?? null) === 'text')
            ->map(fn (array $block) => trim((string) ($block['text'] ?? '')))
            ->filter()
            ->implode("\n");

        // اگر مدل تگ داخلی بیرون داد، همان‌جا پاک می‌شود
        return trim((string) preg_replace('/<\/?[a-zA-Z_][^>]*>/', '', $text));
    }

    /** برگشت به پاسخ قاعده‌محور، با گفتن اینکه چرا. */
    protected function fallback(array $base, string $reason): array
    {
        $base['source'] = 'rules';
        $base['source_label'] = WorkshopAssistant::SOURCE_LABEL;
        $base['fallback_reason'] = $reason;

        $base['reasons'] = array_merge([[
            'label' => 'مدل زبانی در دسترس نبود',
            'text' => $reason.' پاسخ بالا با قواعد خود سامانه ساخته شد و کاملاً قابل اتکاست.',
        ]], $base['reasons']);

        return $base;
    }
}
