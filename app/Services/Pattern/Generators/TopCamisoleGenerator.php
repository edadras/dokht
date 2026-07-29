<?php

namespace App\Services\Pattern\Generators;

/**
 * تاپ بندی (کمیزول).
 *
 * تن ساده با خط بالای صاف یا قلبی، و دو بند باریک که جدا بریده و روی خط بالا
 * دوخته می‌شوند. بند جدا بودن تصمیم است نه ساده‌کاری: بند سرخود یعنی راستای
 * پارچه‌اش با تن یکی است و زیر وزن تاپ کش می‌آید؛ بند جدا را می‌شود از اریب یا
 * از نوار کشباف برید و در پرو کوتاه کرد.
 *
 * چون بالای سینه تکیه‌گاهی ندارد، خط بالا باید به تن بچسبد؛ برای همین آزادی
 * سینه در این مدل کمتر از بلوک معمولی گرفته می‌شود و ساسون سینه پیش‌فرض روشن
 * است تا خط بالا زیر سینه باز نماند.
 */
class TopCamisoleGenerator extends TopBaseGenerator
{
    public static function key(): string
    {
        return 'top_camisole';
    }

    public function label(): string
    {
        return 'تاپ بندی (کمیزول)';
    }

    public function paramsSchema(): array
    {
        return $this->topSchema(array_merge(
            $this->topLineParam(9, 'گودی خط بالای جلو از زیر بغل'),
            [
                'back_drop' => [
                    'label' => 'گودی خط بالای پشت', 'min' => -2, 'max' => 26, 'step' => 0.5,
                    'default' => 12, 'unit' => 'سانتی‌متر',
                    'hint' => 'پشت معمولاً از جلو بازتر است.',
                ],
                'top_shape' => [
                    'label' => 'شکل خط بالای جلو', 'type' => 'select', 'default' => 'straight',
                    'options' => ['straight' => 'صاف', 'sweetheart' => 'قلبی', 'scoop' => 'گرد'],
                ],
            ],
            $this->strapParam(1.5),
            [
                'adjustable' => [
                    'label' => 'بند قابل تنظیم (سگک)', 'type' => 'toggle', 'default' => true,
                ],
                'bust_dart' => [
                    'label' => 'ساسون سینه', 'type' => 'toggle', 'default' => true,
                ],
            ],
        ), length: 6);
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $grow = $this->fitGrow($params, ['fitted' => -0.5, 'regular' => 0.5, 'loose' => 2.5]);
        $g = $this->blockMetrics($measurements, $ease, $params);

        $length = $this->bodyLength($params, $g, 6);
        $frontDrop = (float) $this->param($params, 'top_drop', 9);
        $backDrop = (float) $this->param($params, 'back_drop', 12);
        $strapWidth = (float) $this->param($params, 'strap_width', 1.5);

        $shared = [
            'shape' => $this->fitShape($params),
            'length' => $length,
            'grow' => $grow,
            'bottom_tag' => 'hem',
            'waist_dart' => true,
        ];

        $front = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'front',
            'code' => 'cami-front',
            'name' => 'تاپ جلو',
            'bust_dart' => $this->flag($params, 'bust_dart', true),
        ]));

        $back = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'back',
            'code' => 'cami-back',
            'name' => 'تاپ پشت',
        ]));

        // خط بالا از خط زیر بغل به بالا اندازه می‌شود، نه از بالای کادر.
        // جای برخورد خط بالا با درز پهلو روی جلو و پشت یکی است، وگرنه دو درز
        // پهلو هم‌اندازه نمی‌شوند و لباس دوخته نمی‌شود.
        $frontTop = (float) ($front['meta']['bust_y'] ?? 20) - $frontDrop;
        $backTop = (float) ($back['meta']['bust_y'] ?? 20) - $backDrop;
        $sideTop = min($frontTop, $backTop) + 1.5;

        $front = $this->cutTop($front, [
            'center' => $frontTop,
            'side' => $sideTop,
            'shape' => (string) $this->param($params, 'top_shape', 'straight'),
            'apex' => 0.6,
        ]);

        $back = $this->cutTop($back, [
            'center' => $backTop,
            'side' => $sideTop,
            'shape' => 'straight',
        ]);

        $strapLength = $this->strapLength($g, $frontDrop + 4, $backDrop + 4, extra: 8);

        $pieces = [$front, $back, $this->strapPiece($strapLength, $strapWidth, [
            'code' => 'cami-strap',
            'name' => 'بند تاپ',
            'cut' => 2,
            'meta' => ['adjustable' => $this->flag($params, 'adjustable', true)],
        ])];

        $notes = [
            $this->finishNote($params, ['خط بالا', 'حلقه']),
            ['type' => 'info', 'text' => 'بند '.$this->fa($strapLength).' سانتی‌متر بریده شده که عمداً بلندتر است؛ اندازهٔ نهایی را در پرو ببندید.'],
        ];

        if ($this->flag($params, 'adjustable', true)) {
            $notes[] = ['type' => 'info', 'text' => 'برای بند قابل تنظیم، دو سگک و دو حلقه به ازای هر بند لازم است.'];
        }

        return $this->finishBlock($this->noted($pieces, $notes), $g, $grow);
    }
}
