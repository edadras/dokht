<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * کت رسمی (کت‌وشلوار).
 *
 * برش پنلی چهارتکه، آستین دوتکه، یقهٔ انگلیسی (برگردانِ نازک‌دار)، دو جیب فیلتاب
 * پهلو با درپوش، جیب سینهٔ فیلتاب، سجاف جلو، سجاف یقهٔ پشت، چاک پشت و آستر کامل.
 *
 * چیزی که این درفت را از کتِ سادهٔ کاتالوگ جدا می‌کند: کاهش کمر به‌جای ساسون در
 * دو درز پنلی حل شده، سرآستین دوتکه دقیقاً به حلقهٔ اندازه‌گرفته‌شده می‌خورد، و
 * لبهٔ خط یقه سالم می‌ماند تا هر یقهٔ دیگری از لایهٔ سبک‌ها هم روی آن بنشیند.
 */
class SuitJacketGenerator extends SuitBaseGenerator
{
    public static function key(): string
    {
        return 'suit_jacket';
    }

    public function label(): string
    {
        return 'کت رسمی';
    }

    /** کد و نام قطعه‌ها را با یک پیشوند از هم جدا نگه می‌داریم. */
    protected function prefix(): string
    {
        return 'suit-';
    }

    public function paramsSchema(): array
    {
        return $this->suitSchema([], [
            'pocket_opening' => [
                'label' => 'دهانه جیب پهلو', 'min' => 10, 'max' => 20, 'step' => 0.5,
                'default' => 15, 'unit' => 'سانتی‌متر',
            ],
            'pocket_flap' => [
                'label' => 'درپوش جیب پهلو', 'type' => 'toggle', 'default' => true,
            ],
            'chest_pocket' => [
                'label' => 'جیب سینه فیلتاب', 'type' => 'toggle', 'default' => true,
            ],
        ]);
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = $this->fitGrow($params, ['fitted' => 1.5, 'regular' => 3.0, 'loose' => 5.0]);

        $length = (float) $this->param($params, 'length', 24);
        $stand = (float) $this->param($params, 'button_stand', 2);
        $lapel = (float) $this->param($params, 'lapel_width', 8.5);
        $bottom = $g['side_waist_y'] + $length;
        $breakY = max($g['bust_y'] + 6, min($bottom - 8, $g['side_waist_y'] + (float) $this->param($params, 'lapel_break', 0)));

        $shell = $this->suitShell($g, $params, [
            'prefix' => $this->prefix(),
            'length' => $length,
            'grow' => $grow,
            'stand' => $stand,
        ]);

        $shell[0] = $this->markLapelAndButtons($shell[0], $params, $g, $stand, $breakY, $lapel);
        $shell[2] = $this->markBackVent($shell[2], (float) $this->param($params, 'back_vent', 20));

        $armhole = $this->armholeTotal($shell);
        $halfNeck = $this->neckTotal($shell);

        $pieces = array_merge($shell, $this->suitSleeve($measurements, $ease, $params, $armhole, [
            'prefix' => $this->prefix(),
            'length' => (float) $this->param($params, 'sleeve_length', 60),
        ]));

        $pieces = array_merge($pieces, $this->collarPieces($g, $params, $halfNeck, $stand, $bottom, $lapel, $breakY));

        $pieces = array_merge($pieces, $this->jettedPocketSet((float) $this->param($params, 'pocket_opening', 15), [
            'prefix' => $this->prefix(),
            'key' => 'hip',
            'name' => 'جیب پهلو',
            'flap' => $this->flag($params, 'pocket_flap', true),
            'depth' => 30.0,
        ]));

        if ($this->flag($params, 'chest_pocket', true)) {
            $pieces = array_merge($pieces, $this->jettedPocketSet(10.5, [
                'prefix' => $this->prefix(),
                'key' => 'chest',
                'name' => 'جیب سینه',
                'cut' => 2,
                'flap' => false,
                'depth' => 14.0,
            ]));
        }

        $pieces = array_merge($pieces, $this->suitLining($g, $params, [
            'prefix' => $this->prefix(),
            'length' => $length,
            'grow' => $grow,
        ]));

        $pieces[0]['meta']['notes'] = array_merge($pieces[0]['meta']['notes'] ?? [], [
            'کاهش کمر در دو درز پنلی حل شده؛ رویهٔ کت هیچ ساسونی ندارد.',
            'جای جیب پهلو: هفت تا هشت سانتی‌متر پایین‌تر از خط کمر، روی تنهٔ جلو و پنل پهلوی جلو.',
            'سرآستین '.$this->fa(round((float) $this->param($params, 'cap_ease', 3), 1))
                .' سانتی‌متر از حلقه بلندتر است؛ همین اضافه با جذب‌دادن، سرِ آستین را گرد می‌کند.',
        ]);

        return $this->finishBlock($pieces, $g, $grow);
    }

    /**
     * یقه و سجاف این مدل: یقهٔ انگلیسی با برگردانِ نازک‌دار.
     *
     * @param  array<string, float>  $g
     * @return array<int, array<string, mixed>>
     */
    protected function collarPieces(array $g, array $params, float $halfNeck, float $stand, float $bottom, float $lapel, float $breakY): array
    {
        return [
            $this->underCollarPiece($halfNeck, (float) $this->param($params, 'collar_height', 7.5), [
                'prefix' => $this->prefix(),
            ]),
            $this->notchedFacingPiece($g, $stand, $bottom, $lapel, $breakY, [
                'prefix' => $this->prefix(),
                'width' => max(7.0, $lapel * 0.9),
            ]),
            $this->backNeckFacingPiece($g, ['prefix' => $this->prefix(), 'width' => 7.5]),
        ];
    }

    /**
     * خط برگردان یقه و جای دکمه‌های جلو روی تنهٔ جلو.
     *
     * سرِ بیرونیِ خط برگردان به کادر خودِ قطعه مهار می‌شود؛ روی بدنی با گردن پهن
     * و سینهٔ باریک، نقطهٔ سرگردن از پهنای پنل میانی بیرون می‌زند.
     *
     * @param  array<string, mixed>  $piece
     * @param  array<string, float>  $g
     * @return array<string, mixed>
     */
    protected function markLapelAndButtons(array $piece, array $params, array $g, float $stand, float $breakY, float $lapel): array
    {
        [$minX, $minY, $maxX, $maxY] = Geometry::bounds($piece['outline']);

        $topX = min($maxX, $stand + $g['neck_width']);
        $break = max($minY + 1, min($maxY - 1, $breakY));

        $piece['markers'][] = $this->marker('roll_line', 'خط برگردان یقه', max($minX, 0.0), $break, $topX, max($minY, 0.0));

        $buttons = (int) $this->param($params, 'buttons', 2);
        $to = min($maxY - 6, $break + (8.0 * max(1, $buttons - 1)));

        if ($buttons > 0 && $to > $break + 1) {
            $piece = $this->markButtons($piece, min($maxX, $stand), $break, $to, $buttons, 'جای دکمه جلو');
        }

        $piece['meta']['lapel_width'] = round($lapel, 2);
        $piece['meta']['lapel_break_y'] = round($break, 2);
        $piece['meta']['notes'] = array_merge($piece['meta']['notes'] ?? [], [
            'برگردان یقه با سجاف بریده می‌شود؛ روی تنه فقط خط برگردان علامت می‌خورد و پارچه از همان خط به پشت تا می‌شود.',
            'لبهٔ جلو، حلقهٔ آستین و خط یقه نواردوزی (تیپ) می‌شوند تا در دوخت کشیده نشوند.',
        ]);

        return $piece;
    }

    /**
     * چاک پشت روی درز مرکز پشت.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function markBackVent(array $piece, float $vent): array
    {
        if ($vent < 1) {
            return $piece;
        }

        [$minX, , , $maxY] = Geometry::bounds($piece['outline']);
        $length = min($vent, max(4.0, $maxY * 0.4));

        $piece['markers'][] = $this->marker('vent', 'چاک مرکز پشت', $minX, $maxY - $length, $minX, $maxY);
        $piece['meta']['vent'] = round($length, 2);
        $piece['meta']['notes'] = array_merge($piece['meta']['notes'] ?? [], [
            'چاک مرکز پشت به بلندی '.$this->fa(round($length, 1)).' سانتی‌متر باز می‌ماند؛'
                .' برای رویهم‌آمدن چاک، سه سانتی‌متر پارچه به هر دو لبهٔ مرکز پشت اضافه کنید.',
        ]);

        return $piece;
    }
}
