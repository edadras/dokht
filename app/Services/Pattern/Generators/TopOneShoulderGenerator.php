<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * تاپ یک‌شانه (نامتقارن).
 *
 * یک طرف بند دارد و طرف دیگر بی‌بند است. تنها تاپی از این گروه که نمی‌شود روی
 * تای پارچه بریدش: قطعهٔ جلو باید کامل و باز باشد، چون دو نیمه‌اش با هم فرق
 * دارند. همین یک نکته کل الگو را عوض می‌کند — راستای پارچه از مرکز جلو رد
 * می‌شود، نه از لبهٔ تا، و قطعه یک بار بریده می‌شود نه دو بار.
 *
 * سمت بی‌بند هیچ تکیه‌گاهی ندارد، پس همان قواعد بندو آن‌جا هم برقرار است: خط
 * بالا باید بچسبد و بهتر است کش یا لایی داشته باشد.
 */
class TopOneShoulderGenerator extends TopBaseGenerator
{
    public static function key(): string
    {
        return 'top_one_shoulder';
    }

    public function label(): string
    {
        return 'تاپ یک‌شانه (نامتقارن)';
    }

    public function paramsSchema(): array
    {
        return $this->topSchema(array_merge(
            [
                'shoulder' => [
                    'label' => 'بند روی کدام شانه', 'type' => 'select', 'default' => 'right',
                    'options' => ['right' => 'شانهٔ راست', 'left' => 'شانهٔ چپ'],
                ],
            ],
            $this->strapParam(5, 'پهنای بند روی شانه'),
            [
                'bare_drop' => [
                    'label' => 'گودی خط بالا در سمت بی‌بند', 'min' => 0, 'max' => 20, 'step' => 0.5,
                    'default' => 5, 'unit' => 'سانتی‌متر',
                    'hint' => 'از خط زیر بغل به بالا؛ صفر یعنی درست روی زیر بغل.',
                ],
                'bust_dart' => [
                    'label' => 'ساسون سینه', 'type' => 'toggle', 'default' => true,
                ],
            ],
        ), length: 6);
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $grow = $this->fitGrow($params, ['fitted' => -0.5, 'regular' => 0.5, 'loose' => 2.0]);
        $g = $this->blockMetrics($measurements, $ease, $params);

        $strap = (float) $this->param($params, 'strap_width', 5);
        $bare = (float) $this->param($params, 'bare_drop', 5);
        $right = $this->param($params, 'shoulder', 'right') !== 'left';

        $shared = [
            'shape' => $this->fitShape($params),
            /*
             * یک‌شانه هم کفِ قدِ خودش را دارد.
             *
             * برشِ «سمتِ بی‌بند» از لبهٔ بیرونی مورب بالا می‌رود؛ روی پنلِ بلند
             * این برش در ناحیهٔ حلقه می‌ماند و کاری به درزِ پهلو ندارد. روی پنلِ
             * کوتاه ولی وارد خودِ درزِ پهلو می‌شود، و چون پشت دو سانتی‌متر
             * پایین‌تر بریده می‌شود، درزِ پهلوی پشت از جلو کوتاه‌تر درمی‌آید و
             * دو لبه‌ای که به هم دوخته می‌شوند دیگر هم‌اندازه نیستند.
             */
            'length' => $this->bodyLength($params, $g, 6, clearance: 16.0),
            'grow' => $grow,
            'bottom_tag' => 'hem',
            'waist_dart' => true,
            'shoulder_extra' => ($g['neck_width'] + $strap) - $g['shoulder_half'],
            'across_extra' => -min(4.0, $strap * 0.5),
            'armhole_drop' => 2.0,
        ];

        $front = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'front',
            'code' => 'one-shoulder-front',
            'name' => 'تاپ یک‌شانه — جلو',
            'bust_dart' => $this->flag($params, 'bust_dart', true),
        ]));

        $back = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'back',
            'code' => 'one-shoulder-back',
            'name' => 'تاپ یک‌شانه — پشت',
        ]));

        $front = $this->bareOneSide($this->unfoldPanel($front), $bare, $right);
        $back = $this->bareOneSide($this->unfoldPanel($back), $bare + 2, $right);

        $notes = [
            $this->finishNote($params, ['خط بالا', 'حلقهٔ سمت بند']),
            ['type' => 'info', 'text' => 'قطعه‌های جلو و پشت باز (کامل) بریده شده‌اند و هرکدام فقط یک بار بریده می‌شوند؛ روی تای پارچه نگذارید، وگرنه لباس قرینه می‌شود و یک‌شانه بودنش از بین می‌رود.'],
            ['type' => 'warning', 'text' => 'سمت بی‌بند تکیه‌گاهی ندارد؛ لبه‌اش را لایی یا کش بزنید تا نیفتد.'],
        ];

        return $this->finishBlock($this->noted([$front, $back], $notes), $g, $grow);
    }

    /**
     * برداشتن بند از یک سمت قطعهٔ باز.
     *
     * برش از لبهٔ بیرونیِ سمت بی‌بند شروع می‌شود و مورب بالا می‌رود تا زیر بند
     * سمت دیگر؛ پس یک شانه می‌ماند و یکی برداشته می‌شود.
     */
    protected function bareOneSide(array $piece, float $drop, bool $rightShoulder): array
    {
        $bustY = (float) ($piece['meta']['bust_y'] ?? 20);
        [, $minY] = Geometry::bounds($piece['outline']);

        $bareY = max($minY + 1.0, $bustY - $drop);
        $strapY = $minY + 0.4;

        // «مرکز» خط برش همان لبهٔ چپ کادر است؛ اگر بند روی شانهٔ راست باشد،
        // سمت چپِ الگو باید باز شود
        return $this->cutTop($piece, [
            'center' => $rightShoulder ? $bareY : $strapY,
            'side' => $rightShoulder ? $strapY : $bareY,
            'shape' => 'straight',
        ]);
    }
}
