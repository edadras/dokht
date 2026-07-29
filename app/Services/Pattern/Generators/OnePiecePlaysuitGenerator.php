<?php

namespace App\Services\Pattern\Generators;

/**
 * پلی‌سوت.
 *
 * کوتاه‌ترین عضو این خانواده: بالاتنه تابستانی با یقه باز و شورت کوتاه که روی
 * خط کمر به هم دوخته می‌شوند. چون از سر پوشیده نمی‌شود، جلوی بالاتنه درز مرکزی
 * دارد و با پاتلت دکمه بسته می‌شود؛ همین پاتلت جدا (نه اضافه روی خود تنه) است
 * که نمی‌گذارد لبه کمر بالاتنه از لبه کمر شورت بلندتر شود.
 *
 * شورت از دور ران گرفته می‌شود نه از دور مچ پا، و اگر برگردان بخواهید نوارِ
 * برگردان جدا بریده می‌شود تا دم پاچه سنگین بماند و بالا نزند.
 */
class OnePiecePlaysuitGenerator extends OnePieceBaseGenerator
{
    public static function key(): string
    {
        return 'one_playsuit';
    }

    public function label(): string
    {
        return 'پلی‌سوت';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->onePieceSchema(['front_neck_depth_extra' => 4, 'armhole_depth_extra' => 2]),
            $this->fitParam('regular'),
            $this->sleeveParam('none', 16, [
                'none' => 'بی‌آستین (نوار حلقه)',
                'set_in' => 'آستین کوتاه',
            ]),
            $this->riseSchema(2, 2),
            $this->shortLegSchema(14, 8),
            [
                'buttons' => [
                    'label' => 'تعداد دکمه جلو', 'min' => 3, 'max' => 10, 'step' => 1, 'default' => 5,
                ],
                'button_stand' => [
                    'label' => 'پهنای پاتلت', 'min' => 1.5, 'max' => 5, 'step' => 0.5,
                    'default' => 2, 'unit' => 'سانتی‌متر',
                ],
                'hem_cuff' => [
                    'label' => 'برگردان دم پاچه', 'type' => 'toggle', 'default' => true,
                ],
                'cuff_height' => [
                    'label' => 'بلندی برگردان', 'min' => 2, 'max' => 7, 'step' => 0.5,
                    'default' => 4, 'unit' => 'سانتی‌متر',
                ],
                'sash' => [
                    'label' => 'بند کمر', 'type' => 'toggle', 'default' => true,
                ],
            ],
            $this->pocketParam(false, 12, 13),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $params = $this->withRise($params);
        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = $this->fitGrow($params, ['fitted' => 0.0, 'regular' => 1.5, 'loose' => 3.0]);

        $pieces = $this->onePieceBody($measurements, $ease, $params, $g, [
            'prefix' => 'playsuit-',
            'grow' => $grow,
            'short' => true,
            'panel' => ['bust_dart' => true],
            'front' => [
                // درز مرکز جلو لازم است تا پاتلت رویش بنشیند؛ اضافه جای دکمه
                // عمداً روی تنه نیست، وگرنه کمر بالاتنه از کمر شورت بلندتر می‌شود
                'on_fold' => false,
                'cut' => 2,
                'mirror' => true,
                'name' => 'بالاتنه جلو (درز مرکزی)',
            ],
            'leg_front_name' => 'شورت جلو',
            'leg_back_name' => 'شورت پشت',
        ]);

        $pieces = $this->frontClosureSet($pieces, $g, $params, [
            'kind' => 'button',
            'prefix' => 'playsuit-',
        ]);

        $front = $pieces[0];
        $back = $pieces[1];

        if ((string) $this->param($params, 'sleeve_style', 'none') === 'none') {
            $pieces[] = $this->armholeBindingPiece($this->armholeOf([$front, $back]), ['prefix' => 'playsuit-']);
        }

        $pieces[] = $this->bandPiece('playsuit-neck-binding', 'نوار یقه', $this->neckOf([$front, $back]) + 5, 3, [
            'cut' => 2, 'part' => 'facing',
            'meta' => ['bias' => true, 'girth_role' => 'trim', 'notes' => ['نوار اریب، چون یقه باز منحنی است.']],
        ]);

        if ($this->flag($params, 'hem_cuff', true)) {
            $height = (float) $this->param($params, 'cuff_height', 4);
            $hem = 0.0;

            foreach ($pieces as $piece) {
                if (($piece['meta']['girth_role'] ?? '') === 'bottom') {
                    $hem += (float) ($piece['meta']['hem_width'] ?? 0);
                }
            }

            $pieces[] = $this->bandPiece('playsuit-leg-cuff', 'برگردان دم پاچه', max(14.0, $hem), $height * 2, [
                'cut' => 2, 'part' => 'cuff', 'fold_line' => true,
                'meta' => [
                    'girth_role' => 'trim',
                    'notes' => ['برای هر پاچه یک نوار؛ دولا دوخته و به دم پاچه برگردانده می‌شود.'],
                ],
            ]);
        }

        if ($this->flag($params, 'sash', true)) {
            $pieces[] = $this->bandPiece('playsuit-sash', 'بند کمر', ($this->m($measurements, 'waist', 74) / 2) + 60, 7, [
                'cut' => 2, 'part' => 'belt', 'fold_line' => true,
                'meta' => ['notes' => ['دو بند روی درز پهلوی خط کمر دوخته و پشت گره می‌شود.']],
            ]);
        }

        $pieces = array_merge($pieces, $this->pocketSet($params, ['prefix' => 'playsuit-']));

        return $this->finishBlock($pieces, $g, $grow);
    }
}
