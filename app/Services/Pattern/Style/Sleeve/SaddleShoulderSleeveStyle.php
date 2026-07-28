<?php

namespace App\Services\Pattern\Style\Sleeve;

use App\Support\Format;

/**
 * آستین ساقه‌دار (سدل).
 *
 * همان برش رگلان است ولی خیلی کوتاه‌تر: به‌جای آنکه خط برش از یقه تا زیر بغل
 * برود، تنها یک بند باریک از بالای حلقه آستین جدا می‌شود و همان بند از روی
 * سرشانه تا سرگردن ادامه پیدا می‌کند. نتیجه، بندی از جنس آستین است که روی شانه
 * می‌نشیند و حلقه آستین — برخلاف رگلان — تقریباً سر جای خودش می‌ماند.
 *
 * پهنای بند روی یقه و فاصله برش از نوک سرشانه دو پارامتر اصلی‌اند؛ بند پهن‌تر
 * شانه را بزرگ‌تر و ورزشی‌تر نشان می‌دهد.
 */
class SaddleShoulderSleeveStyle extends RaglanCutStyle
{
    public static function key(): string
    {
        return 'sleeve_saddle';
    }

    public function label(): string
    {
        return 'آستین ساقه‌دار (سدل)';
    }

    public function description(): string
    {
        return 'بندی از خود آستین از روی سرشانه تا یقه می‌رود؛ حلقه آستین تقریباً سر جایش می‌ماند.';
    }

    protected function seamName(): string
    {
        return 'درز بند سرشانه';
    }

    protected function sleeveName(): string
    {
        return 'آستین ساقه‌دار';
    }

    protected function sleeveCode(): string
    {
        return 'saddle-sleeve';
    }

    public function paramsSchema(): array
    {
        return [
            'strap_width' => [
                'label' => 'پهنای بند روی خط یقه', 'min' => 2, 'max' => 10, 'step' => 0.5, 'default' => 4.5,
                'unit' => 'سانتی‌متر',
                'hint' => 'از سرگردن به سمت مرکز؛ همین اندازه پهنای بند روی شانه است.',
            ],
            'strap_drop' => [
                'label' => 'فاصله برش از نوک سرشانه', 'min' => 2, 'max' => 12, 'step' => 0.5, 'default' => 4,
                'unit' => 'سانتی‌متر',
                'hint' => 'روی حلقه آستین به پایین اندازه گرفته می‌شود؛ بیشتر یعنی بند پهن‌تر.',
            ],
            'head' => [
                'label' => 'سر آستین', 'type' => 'select', 'default' => 'one_piece',
                'options' => [
                    'one_piece' => 'یک‌تکه ساده',
                    'dart' => 'یک‌تکه با ساسون سرشانه',
                ],
            ],
        ] + $this->sharedFields(1.5);
    }

    protected function cutPlan(array $anchors, array $params): array
    {
        $drop = max(2.0, min($anchors['armhole_length'] * 0.45, (float) $params['strap_drop']));

        return [
            'neck' => (float) $params['strap_width'],
            'armhole' => $anchors['armhole_length'] - $drop,
        ];
    }

    public function apply(array $pieces, array $context): array
    {
        $result = parent::apply($pieces, $context);
        $p = $this->params($context);

        $result['notes'][] = 'بند سرشانه به پهنای '.Format::cm($p['strap_width'], 1).' روی یقه و '
            .Format::cm($p['strap_drop'], 1).' روی حلقه بریده شد؛ چون برش نزدیک نوک سرشانه می‌افتد، '
            .'بیشترِ گودی حلقه آستین سر جایش می‌ماند و لباس قالب‌تر از رگلان می‌ایستد.';

        return $result;
    }
}
