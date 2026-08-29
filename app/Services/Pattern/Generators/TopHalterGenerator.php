<?php

namespace App\Services\Pattern\Generators;

/**
 * تاپ هالتر.
 *
 * بند از جلو بالا می‌رود و پشت گردن بسته می‌شود؛ سرشانه و کتف کاملاً باز است و
 * پشت هم پایین‌تر از حلقه بریده می‌شود.
 *
 * نکته‌ای که هالتر را از بقیه جدا می‌کند این است که تمام وزن جلوی لباس روی گردن
 * می‌افتد. دو چیز از این‌جا می‌آید و هر دو در الگو هست: بند باید پهن‌تر از بند
 * کمیزول باشد (کمتر از دو سانتی‌متر روی گردن می‌بُرد)، و لبهٔ بالای پشت باید کش
 * یا کِش‌ناپذیر بسته شود وگرنه لباس از پشت پایین می‌آید.
 */
class TopHalterGenerator extends TopBaseGenerator
{
    public static function key(): string
    {
        return 'top_halter';
    }

    public function label(): string
    {
        return 'تاپ هالتر';
    }

    public function paramsSchema(): array
    {
        return $this->topSchema(array_merge(
            $this->topLineParam(6, 'گودی یقهٔ جلو از زیر بغل'),
            [
                'back_drop' => [
                    'label' => 'گودی خط بالای پشت', 'min' => 0, 'max' => 30, 'step' => 0.5,
                    'default' => 14, 'unit' => 'سانتی‌متر',
                ],
            ],
            $this->strapParam(2.5, 'پهنای بند گردن'),
            [
                'tie' => [
                    'label' => 'بستن بند', 'type' => 'select', 'default' => 'tie',
                    'options' => ['tie' => 'گره پشت گردن', 'button' => 'دکمه یا قزن', 'fixed' => 'دوخته و ثابت'],
                ],
                'tie_length' => [
                    'label' => 'بلندی هر بند', 'min' => 20, 'max' => 90, 'step' => 1,
                    'default' => 42, 'unit' => 'سانتی‌متر',
                ],
                'bust_dart' => [
                    'label' => 'ساسون سینه', 'type' => 'toggle', 'default' => true,
                ],
            ],
        ), length: 4);
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $grow = $this->fitGrow($params, ['fitted' => -0.5, 'regular' => 0.5, 'loose' => 2.0]);
        $g = $this->blockMetrics($measurements, $ease, $params);

        $strap = (float) $this->param($params, 'strap_width', 2.5);
        $frontDrop = (float) $this->param($params, 'top_drop', 6);
        $backDrop = (float) $this->param($params, 'back_drop', 14);

        $shared = [
            'shape' => $this->fitShape($params),
            'length' => $this->bodyLength($params, $g, 4),
            'grow' => $grow,
            'bottom_tag' => 'hem',
            'waist_dart' => true,
            // نقطهٔ زیر بغل روی جلو و پشت باید یکی باشد، وگرنه دو درز پهلو
            // هم‌اندازه درنمی‌آیند
            'armhole_drop' => 2.0,
        ];

        // بند هالتر کنار گردن بالا می‌رود: نوک سرشانه تا لبهٔ یقه عقب می‌آید
        $front = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'front',
            'code' => 'halter-front',
            'name' => 'هالتر جلو',
            'bust_dart' => $this->flag($params, 'bust_dart', true),
            'shoulder_extra' => ($g['neck_width'] + $strap) - $g['shoulder_half'],
            'neck_depth_extra' => $frontDrop,
            // کتفِ باز هالتر یعنی منحنی حلقه باید هم تو بیاید و هم پایین برود
            'across_extra' => -min(6.0, 3.0 + ($frontDrop * 0.25)),
        ]));

        $back = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'back',
            'code' => 'halter-back',
            'name' => 'هالتر پشت',
        ]));

        /*
         * جلو بریده نمی‌شود (حلقه دارد)، پس خط بالای پشت باید طوری بریده شود که
         * درز پهلویش هم‌اندازهٔ جلو دربیاید.
         *
         * پیش‌تر این‌جا ارتفاعِ زیر بغلِ *جلو* را می‌گرفتیم و همان را روی پشت
         * می‌بریدیم. ولی هر پنل بعد از ساخته‌شدن روی مبدأ خودش می‌نشیند، و
         * بالای جلو (سرشانه) با بالای پشت (خط یقه) یکی نیست — پس آن عدد در
         * قابِ پشت جای دیگری می‌افتد. روی سایز جدولی این جابه‌جایی یک‌دهم
         * سانتی‌متر بود و کسی نمی‌دید؛ روی اندامِ گلابی به دو سانتی‌متر می‌رسید
         * و دو درزِ پهلو دیگر به هم نمی‌رسیدند.
         *
         * حالا به‌جای حدس زدنِ ارتفاع، خودِ طول را می‌سنجیم: خطِ برش با تنصیف
         * آن‌قدر بالا و پایین می‌رود تا درزِ پهلوی پشت به درزِ پهلوی جلو برسد.
         * همان کاری که پیش‌بند از قبل می‌کرد؛ حالا هر دو از یک جا می‌آید.
         */
        $backTop = (float) ($back['meta']['bust_y'] ?? 20) - $backDrop;
        $back = $this->matchSideSeam($back, $front, (float) ($front['meta']['bust_y'] ?? $backTop), [
            'center' => $backTop,
            'shape' => 'straight',
        ]);

        $pieces = [$front, $back];

        $tie = (string) $this->param($params, 'tie', 'tie');

        if ($tie === 'tie') {
            $pieces[] = $this->strapPiece(
                (float) $this->param($params, 'tie_length', 42),
                $strap,
                ['code' => 'halter-tie', 'name' => 'بند گره هالتر', 'cut' => 2],
            );
        }

        $notes = [
            $this->finishNote($params, ['حلقه', 'خط بالای پشت']),
            ['type' => 'info', 'text' => match ($tie) {
                'button' => 'بند پشت گردن با دکمه یا قزن بسته می‌شود؛ یک عدد در صورت مواد حساب شده است.',
                'fixed' => 'بند دوخته و ثابت است؛ پیش از دوخت نهایی حتماً روی تن اندازه بگیرید، چون دیگر تنظیم نمی‌شود.',
                default => 'دو بند گره‌خور پشت گردن؛ بلندی‌شان عمداً زیاد گرفته شده تا در پرو کوتاه شود.',
            }],
            ['type' => 'warning', 'text' => 'همهٔ وزن جلوی لباس روی گردن می‌افتد؛ بند باریک‌تر از دو سانتی‌متر روی گردن می‌بُرد.'],
        ];

        return $this->finishBlock($this->noted($pieces, $notes), $g, $grow);
    }
}
