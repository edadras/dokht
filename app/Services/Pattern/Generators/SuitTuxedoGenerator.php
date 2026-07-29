<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * کت اسموکینگ (تاکسیدو).
 *
 * همان سازهٔ کت رسمی است — چهار پنل، آستین دوتکه، جیب فیلتاب — با چهار تفاوتِ
 * قاعده‌مندِ لباس شب:
 *
 *   ۱. یقه شالی (یا نوک‌تیز) است و از پارچهٔ براق (ساتن یا گروگرن) بریده می‌شود؛
 *      برای همین یقه و سجاف یک قطعهٔ یک‌سره‌اند، نه یقهٔ زیر + سجاف.
 *   ۲. یک دکمه، و شکست یقه پایین‌تر — نزدیک خط کمر. کتِ اسموکینگ با دو دکمه
 *      وجود ندارد.
 *   ۳. جیب پهلو درپوش ندارد؛ فیلتابِ ساده، چون درپوش خطِ صافِ سینه را می‌شکند.
 *   ۴. چاک پشت ندارد. اسموکینگ کتِ نشستن و ایستادن است، نه کتِ سوارکاری.
 */
class SuitTuxedoGenerator extends SuitJacketGenerator
{
    public static function key(): string
    {
        return 'suit_tuxedo';
    }

    public function label(): string
    {
        return 'کت اسموکینگ';
    }

    protected function prefix(): string
    {
        return 'tuxedo-';
    }

    public function paramsSchema(): array
    {
        $schema = $this->suitSchema([], [
            'collar_style' => [
                'label' => 'گونه یقه', 'type' => 'select', 'default' => 'shawl',
                'options' => ['shawl' => 'شالی', 'peak' => 'نوک‌تیز'],
                'hint' => 'هر دو از پارچهٔ براق بریده می‌شوند؛ یقهٔ انگلیسیِ ساده روی اسموکینگ نمی‌نشیند.',
            ],
            'pocket_opening' => [
                'label' => 'دهانه جیب پهلو', 'min' => 10, 'max' => 20, 'step' => 0.5,
                'default' => 15, 'unit' => 'سانتی‌متر',
            ],
            'chest_pocket' => [
                'label' => 'جیب سینه فیلتاب', 'type' => 'toggle', 'default' => true,
            ],
        ]);

        // یک دکمه، شکست یقهٔ پایین‌تر، بدون چاک پشت، بدون درپوش جیب
        $schema['buttons']['default'] = 1;
        $schema['buttons']['max'] = 2;
        $schema['lapel_break']['default'] = 3;
        $schema['lapel_width']['default'] = 10;
        $schema['back_vent']['default'] = 0;
        $schema['length']['default'] = 26;

        return $schema;
    }

    /** اسموکینگ درپوش جیب ندارد. */
    protected function paramsWithoutFlap(array $params): array
    {
        $params['pocket_flap'] = false;

        return $params;
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $pieces = parent::generate($measurements, $ease, $this->paramsWithoutFlap($params));

        $pieces[0]['meta']['notes'] = array_merge($pieces[0]['meta']['notes'] ?? [], [
            'یقه و سجاف از پارچهٔ براق (ساتن یا گروگرن) بریده می‌شوند و بقیهٔ کت از پارچهٔ مات.',
            'دکمهٔ جلو و دکمه‌های سر آستین هم با همان پارچهٔ براق روکش می‌شوند.',
        ]);

        return $pieces;
    }

    /**
     * یقهٔ اسموکینگ: شالی یا نوک‌تیز، یک‌سره با سجاف.
     *
     * @param  array<string, float>  $g
     * @return array<int, array<string, mixed>>
     */
    protected function collarPieces(array $g, array $params, float $halfNeck, float $stand, float $bottom, float $lapel, float $breakY): array
    {
        $style = (string) $this->param($params, 'collar_style', 'shawl');

        if ($style === 'peak') {
            // نوک‌تیز: همان برگردانِ نازک‌دار ولی با نوکِ بالا رفته، پس یقهٔ زیر جدا لازم است
            $collar = $this->underCollarPiece($halfNeck, (float) $this->param($params, 'collar_height', 7.5), [
                'prefix' => $this->prefix(),
                'point' => 5.0,
            ]);
            $collar['meta']['notes'][] = 'از پارچهٔ براق بریده می‌شود؛ نوکِ یقه رو به بالا و به سمت سرشانه می‌ایستد.';

            $facing = $this->notchedFacingPiece($g, $stand, $bottom, $lapel, $breakY, [
                'prefix' => $this->prefix(),
                'width' => max(8.0, $lapel * 0.95),
            ]);
            $facing['name'] = 'سجاف جلو با برگردان نوک‌تیز';
            $facing['meta']['notes'][] = 'از پارچهٔ براق بریده می‌شود.';

            return [$collar, $facing, $this->backNeckFacingPiece($g, ['prefix' => $this->prefix(), 'width' => 7.5])];
        }

        $shawl = $this->shawlFacingPiece($g, $stand, $bottom, [
            'prefix' => $this->prefix(),
            'width' => max(8.0, $lapel),
            'break_y' => $breakY,
            'name' => 'سجاف و یقه شالی (یک‌سره)',
        ]);

        $shawl['markers'][] = $this->marker(
            'gorge',
            'نشانه مرکز پشت یقه',
            0,
            0,
            min(Geometry::bounds($shawl['outline'])[2], $g['neck_width'] + $stand),
            0,
        );
        $shawl['meta']['notes'] = [
            'یقهٔ شالی و سجاف یک قطعه‌اند: از دم لباس بالا می‌آید، دور گردن می‌چرخد و درزش روی مرکز پشت می‌افتد.',
            'از پارچهٔ براق بریده می‌شود و تا خط برگردان لایی می‌خورد.',
            'راستای پارچه در ناحیهٔ گردن باید اریب شود تا یقه دور گردن بخوابد؛ اگر پارچه اجازه نداد، یقه را جدا و اریب ببرید.',
        ];

        return [$shawl, $this->backNeckFacingPiece($g, ['prefix' => $this->prefix(), 'width' => 7.5])];
    }
}
