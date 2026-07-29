<?php

namespace App\Services\Pattern\Generators;

/**
 * سارافون کودک.
 *
 * سارافون پیراهن بی‌آستین نیست؛ لباسی است که *روی* پیراهن یا بلوز پوشیده
 * می‌شود، و همین سه چیز را در الگو عوض می‌کند:
 *
 *   ۱. حلقه آستین باید بازتر و گودتر باشد تا آستین لباس زیر در آن گیر نکند.
 *   ۲. دور سینه آزادی بیشتری می‌خواهد، چون یک لایه پارچه زیرش هست.
 *   ۳. سرشانه باریک و دور از گردن است تا یقه پیراهنِ زیر دیده شود.
 *
 * بسته شدن از سرشانه است، نه از پشت: دو دکمه روی سرشانه، هم برای کودک راحت‌تر
 * است و هم درز مرکز پشت را دست‌نخورده می‌گذارد تا لبه کمر بالاتنه و لبه کمر
 * دامن هم‌اندازه بمانند. با این حال یقه هم آن‌قدر باز می‌شود که اگر دکمه‌ها
 * بسته باشند لباس از سر رد شود.
 */
class ChildPinaforeDressGenerator extends ChildBaseGenerator
{
    public static function key(): string
    {
        return 'child_pinafore_dress';
    }

    public function label(): string
    {
        return 'سارافون کودک';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->childSchema([
                'shoulder_slope' => 2.5,
                'neck_width_extra' => 3,
                'front_neck_depth_extra' => 2,
                'back_neck_depth' => 2.5,
                'armhole_depth_extra' => 3.5,
            ]),
            // آزادی بازی این مدل از بقیه بیشتر است چون روی لباس دیگر پوشیده می‌شود
            $this->childEaseSchema(3, 2),
            [
                'skirt_length' => [
                    'label' => 'بلندی دامن از کمر', 'min' => 14, 'max' => 60, 'step' => 1,
                    'default' => 28, 'unit' => 'سانتی‌متر',
                ],
                'gather_ratio' => [
                    'label' => 'نسبت پُری دامن', 'min' => 1, 'max' => 2.4, 'step' => 0.1,
                    'default' => 1.5,
                    'hint' => 'یک یعنی دامن ترپز بدون چین؛ عدد بزرگ‌تر یعنی چین بیشتر روی خط کمر.',
                ],
                'flare' => [
                    'label' => 'باز شدن لبه دامن', 'min' => 0, 'max' => 24, 'step' => 1,
                    'default' => 9, 'unit' => 'سانتی‌متر',
                ],
                'shoulder_button' => [
                    'label' => 'دکمه سرشانه', 'type' => 'toggle', 'default' => true,
                ],
                'pockets' => [
                    'label' => 'جیب رودوزی جلوی دامن', 'type' => 'toggle', 'default' => true,
                ],
                'pocket_width' => [
                    'label' => 'پهنای جیب', 'min' => 7, 'max' => 16, 'step' => 0.5,
                    'default' => 10, 'unit' => 'سانتی‌متر',
                ],
                'pocket_height' => [
                    'label' => 'بلندی جیب', 'min' => 7, 'max' => 16, 'step' => 0.5,
                    'default' => 10, 'unit' => 'سانتی‌متر',
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = $this->childGrow($params);
        $clearance = $this->headClearance($g, $measurements, [
            'margin' => 2.0,
            'max_depth' => 4.0,
            'shoulder_extra' => -1.0,
        ]);

        $shared = [
            'shape' => 'waist',
            'grow' => $grow,
            'waist_dart' => false,
            'bust_dart' => false,
            'neck_width_extra' => $clearance['width_extra'],
            // سرشانه سارافون باریک است تا یقه لباس زیر بیرون بماند
            'shoulder_extra' => -1.0,
        ];

        $front = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'front',
            'neck_depth_extra' => $clearance['front_depth_extra'],
            'code' => 'child-pinafore-bodice-front',
            'name' => 'بالاتنه جلو',
        ]));

        $back = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'back',
            'code' => 'child-pinafore-bodice-back',
            'name' => 'بالاتنه پشت',
        ]));

        [$front, $back] = $this->walkSideSeams($front, $back);

        if ($this->flag($params, 'shoulder_button', true)) {
            $front['meta']['notions'][] = [
                'type' => 'button',
                'label' => 'دکمه سرشانه',
                'count' => 2,
            ];
            $front['meta']['notes'] = array_merge($front['meta']['notes'] ?? [], [
                'سرشانه جلو روی سرشانه پشت می‌افتد و با یک دکمه بسته می‌شود؛ برای هر سرشانه یکی.',
            ]);
        }

        $pieces = [$front, $back];

        $length = (float) $this->param($params, 'skirt_length', 28);
        $ratio = max(1.0, (float) $this->param($params, 'gather_ratio', 1.5));
        $waistQuarter = $g['quarter_waist'] + $grow;
        $gather = round($waistQuarter * ($ratio - 1), 2);

        foreach (['front', 'back'] as $side) {
            // چین را خودمان روی لبه ثبت می‌کنیم، پس لبه بالا را پهن‌تر می‌بریم و
            // به پنل نمی‌گوییم خودش چین بگذارد؛ وگرنه یک چین دو بار شمرده می‌شود
            $panel = $this->lowerPanel($g, [
                'side' => $side,
                'shape' => 'flare',
                'grow' => $grow,
                'top_width' => $waistQuarter + $gather,
                'top_y' => $g['side_waist_y'],
                'length' => $length,
                'gather' => 0.0,
                'flare' => (float) $this->param($params, 'flare', 9),
                'top_tag' => 'waist',
                'code' => 'child-pinafore-skirt-'.$side,
                'name' => $side === 'front' ? 'دامن جلو' : 'دامن پشت',
                'meta' => [
                    'gather_ratio' => $ratio,
                    'notes' => [$gather > 0.5
                        ? 'لبه کمر این پنل '.$this->fa($ratio).' برابر لبه کمر بالاتنه است.'
                        : 'لبه کمر این پنل دقیقاً هم‌اندازه لبه کمر بالاتنه است.'],
                ],
            ]);

            $pieces[] = $this->gatheredWaist($panel, $gather, 'چین کمر دامن');
        }

        $pieces[] = $this->armholeBindingPiece($this->armholeOf([$front, $back]), [
            'prefix' => 'child-pinafore-', 'height' => 3,
        ]);

        $pieces[] = $this->bandPiece('child-pinafore-neck-binding', 'نوار یقه', $this->neckOf([$front, $back]) + 4, 3, [
            'cut' => 2, 'part' => 'facing',
            'meta' => ['bias' => true, 'girth_role' => 'trim', 'notes' => ['نوار اریب دور یقه باز.']],
        ]);

        if ($this->flag($params, 'pockets', true)) {
            $pieces[] = $this->patchPocketPiece(
                (float) $this->param($params, 'pocket_width', 10),
                (float) $this->param($params, 'pocket_height', 10),
                ['prefix' => 'child-pinafore-', 'name' => 'جیب رودوزی دامن', 'cut' => 2],
            );
        }

        $pieces = $this->stampHeadClearance($pieces, $clearance, $g, ['notion' => 'button']);

        return $this->finishBlock($this->childNoted($pieces, [
            'سارافون روی پیراهن پوشیده می‌شود؛ حلقه آستین آن عمداً گودتر و سرشانه‌اش باریک‌تر از پیراهن است.',
        ]), $g, $grow);
    }
}
