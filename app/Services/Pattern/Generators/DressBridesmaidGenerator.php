<?php

namespace App\Services\Pattern\Generators;

/**
 * لباس ساقدوش.
 *
 * این لباس یک مسئلهٔ فنی دارد که هیچ لباس مجلسی دیگری ندارد: **قرار است چند نفر
 * با چند بدن متفاوت آن را بپوشند و همه یک شکل دیده شوند.** پس الگویش با لباس شبِ
 * معمولی سه فرق دارد و هر سه عمدی‌اند:
 *
 *   بند پشت      به‌جای زیپِ ثابت، بندِ پارچه‌ای بلند که دور کمر می‌پیچد و پشت
 *                گره می‌خورد. همین یک تکه لباس را روی دو سایز اختلاف جواب می‌دهد.
 *   جای رهاشده   درز پهلو و درز مرکز پشت با جای دوختِ پهن‌تر بریده می‌شوند تا
 *                هنگام پرو بشود لباس را باز یا تنگ کرد؛ عددش در الگو نوشته است.
 *   دامن نرم     دامن خط A از پارچهٔ نرم (شیفون یا کرپ) که روی بدن‌های مختلف
 *                یک‌جور می‌ریزد؛ دامن جذب روی هر بدن یک شکل دیگر است.
 *
 * بست پشتش زیپ می‌ماند، چون لباس باید بسته شود؛ بند فقط اندازه را تنظیم می‌کند.
 */
class DressBridesmaidGenerator extends EveningBaseGenerator
{
    public static function key(): string
    {
        return 'dress_bridesmaid';
    }

    public function label(): string
    {
        return 'لباس ساقدوش';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->fitParam(),
            $this->eveningSchema(
                array_merge($this->gownLengthParam(104), [
                    'skirt_flare' => [
                        'label' => 'گشادی دم دامن در هر پهلو', 'min' => 8, 'max' => 45, 'step' => 1,
                        'default' => 22, 'unit' => 'سانتی‌متر',
                    ],
                    'sash' => [
                        'label' => 'بند کمرِ پشت‌گره', 'type' => 'toggle', 'default' => true,
                    ],
                    'sash_width' => [
                        'label' => 'پهنای بند کمر', 'min' => 3, 'max' => 12, 'step' => 0.5,
                        'default' => 7, 'unit' => 'سانتی‌متر',
                    ],
                    'fit_allowance' => [
                        'label' => 'جای دوخت رهاشده روی درزها', 'min' => 0, 'max' => 4, 'step' => 0.5,
                        'default' => 2, 'unit' => 'سانتی‌متر',
                        'hint' => 'روی درز پهلو و مرکز پشت اضافه می‌ماند تا در پرو بشود لباس را تنگ یا گشاد کرد.',
                    ],
                ]),
                ['neckline' => 'strap', 'strap_width' => 4, 'boning' => false, 'bust_cups' => false, 'lining' => 'bodice'],
            ),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $ease = $this->gownEase($ease, $params);

        [$bodice, $waist] = $this->eveningBodice($measurements, $ease, $params, [
            'prefix' => 'bridesmaid',
            'neck_drop' => 6,
            'back_drop' => 7,
        ]);

        $skirt = $this->gownSkirt('skirt_a_line', $measurements, $ease, [
            'length' => (float) $this->param($params, 'skirt_length', 104),
            'flare' => (float) $this->param($params, 'skirt_flare', 22),
        ], 'bridesmaid');

        [$skirt, $waistNotes] = $this->joinWaist($skirt, $waist);

        $pieces = array_merge($bodice, $skirt);

        // جای دوختِ رهاشده روی درزهایی که در پرو تنگ یا گشاد می‌شوند
        $allowance = max(0.0, (float) $this->param($params, 'fit_allowance', 2));

        if ($allowance > 0.1) {
            foreach ($pieces as $index => $piece) {
                if (! in_array($piece['meta']['girth_role'] ?? '', ['shell', ''], true)) {
                    continue;
                }

                $pieces[$index]['meta']['fit_allowance'] = round($allowance, 2);
                $pieces[$index]['meta']['notes'] = array_merge($piece['meta']['notes'] ?? [], [
                    'روی درز پهلو و مرکز پشت '.$this->fa(round($allowance, 1))
                        .' سانتی‌متر بیشتر از جای دوخت معمول بریده می‌شود و تا پس از پرو بریده نمی‌گردد.',
                ]);
            }
        }

        if ($this->flag($params, 'sash', true)) {
            $width = (float) $this->param($params, 'sash_width', 7);
            $body = $this->m($measurements, 'waist', 74);

            $pieces[] = $this->bandPiece('bridesmaid-sash', 'بند کمرِ پشت‌گره', ($body * 0.9) + 60, $width * 2, [
                'cut' => 2, 'part' => 'belt', 'fold_line' => true,
                'meta' => [
                    'girth_role' => 'trim',
                    'finished_width' => round($width, 2),
                    'notes' => [
                        'دو تکه از پهلو به پشت می‌رود و پشت گره می‌خورد؛ همین بند است که یک الگو را روی چند بدن جواب می‌دهد.',
                        'عمداً بلند بریده شده تا برای بدن بزرگ‌تر هم برسد؛ کوتاه کردنش در پرو ساده است، بلند کردنش نه.',
                    ],
                ],
            ]);

            $pieces[] = $this->bandPiece('bridesmaid-sash-loop', 'حلقهٔ بند روی درز پهلو', $width + 4, 3.0, [
                'cut' => 2, 'part' => 'belt',
                'meta' => ['girth_role' => 'trim', 'notes' => ['روی درز پهلو، هم‌تراز خط کمر دوخته می‌شود تا بند سر نخورد.']],
            ]);
        }

        [$pieces, $closureNotes] = $this->gownClosure($pieces, $params, $measurements);
        $pieces = $this->gownLining($pieces, $params);

        $notes = array_merge($waistNotes, $closureNotes, $this->gownNotes($params), [
            'این لباس قرار است چند نفر با چند بدن بپوشند؛ دامنش عمداً نرم و خط A است تا روی بدن‌های مختلف یک‌جور بیفتد.',
            'بالاتنه آستر دارد ولی دامن نه: دامن شیفون روی آستر جدا می‌افتد و اگر با آستر یکی دوخته شود، ریزشش را از دست می‌دهد.',
            'پیش از برشِ چند دست، یک نمونهٔ آزمایشی از پارچهٔ ارزان بدوزید و روی بلندقدترین و کوتاه‌ترین نفرِ گروه پرو کنید؛ بلندی دامن روی هر قد جای دیگری می‌ایستد.',
        ]);

        return $this->finish($this->noted($pieces, array_map(
            fn (string $t) => ['type' => 'info', 'text' => $t],
            $notes,
        )));
    }
}
