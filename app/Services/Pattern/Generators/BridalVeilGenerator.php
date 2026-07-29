<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * تور عروس.
 *
 * تنها قطعهٔ این خانواده که لباس نیست، ولی بدون آن مجموعه کامل نیست.
 *
 * تور نیم‌دایره است، نه مستطیل: اگر مستطیل بریده شود، دو گوشه‌اش روی زمین
 * می‌ماند و لبه‌اش موج نمی‌خورد. شعاع نیم‌دایره همان بلندی خواسته‌شدهٔ تور
 * است.
 *
 * دو نکته که همیشه فراموش می‌شود و هر دو در الگو هستند: لبهٔ بالای تور باید
 * چین بخورد تا روی شانه بیفتد نه اینکه صاف بایستد، و اگر تور از یک‌ونیم متر
 * بلندتر باشد باید دو لایه شود — لایهٔ کوتاه روی صورت و لایهٔ بلند پشت.
 */
class BridalVeilGenerator extends EveningBaseGenerator
{
    public static function key(): string
    {
        return 'bridal_veil';
    }

    public function label(): string
    {
        return 'تور عروس';
    }

    public function paramsSchema(): array
    {
        return [
            'length' => [
                'label' => 'بلندی تور', 'min' => 40, 'max' => 350, 'step' => 10,
                'default' => 180, 'unit' => 'سانتی‌متر',
                'hint' => 'تا ۷۵ کوتاه، ۱۲۰ تا کمر، ۱۸۰ تا زمین، بالای ۲۵۰ کاتدرال.',
            ],
            'gather' => [
                'label' => 'نسبت چین لبهٔ بالا', 'min' => 1.5, 'max' => 5, 'step' => 0.25,
                'default' => 3,
                'hint' => 'لبهٔ بالا باید چین بخورد تا تور روی شانه بیفتد، نه اینکه صاف بایستد.',
            ],
            'blusher' => [
                'label' => 'لایهٔ روی صورت', 'type' => 'toggle', 'default' => true,
            ],
            'blusher_length' => [
                'label' => 'بلندی لایهٔ روی صورت', 'min' => 30, 'max' => 90, 'step' => 5,
                'default' => 55, 'unit' => 'سانتی‌متر',
            ],
            'comb' => [
                'label' => 'شانهٔ سر', 'type' => 'toggle', 'default' => true,
            ],
        ];
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $length = (float) $this->param($params, 'length', 180);
        $gather = max(1.5, (float) $this->param($params, 'gather', 3));

        $pieces = [$this->veilPiece($length, 'veil-main', 'تور اصلی', $gather)];

        if ($this->flag($params, 'blusher', true)) {
            $pieces[] = $this->veilPiece(
                (float) $this->param($params, 'blusher_length', 55),
                'veil-blusher',
                'لایهٔ روی صورت',
                $gather,
            );
        }

        $notes = [
            'تور نیم‌دایره است نه مستطیل؛ مستطیل دو گوشه‌اش روی زمین می‌ماند و لبه‌اش موج نمی‌خورد.',
            'لبهٔ بالا '.$this->fa($gather).' برابر چین می‌خورد تا تور روی شانه بیفتد.',
            'لبهٔ تور اگر دوخته نشود ریش نمی‌شود؛ برش تمیز روی تور کافی است.',
        ];

        if ($length > 150 && ! $this->flag($params, 'blusher', true)) {
            $notes[] = 'هشدار: تور بلندتر از یک‌ونیم متر بهتر است دو لایه باشد؛ یک لایه سنگین می‌شود و از سر می‌افتد.';
        }

        if ($this->flag($params, 'comb', true)) {
            $pieces[0]['meta']['notions'][] = ['type' => 'other', 'label' => 'شانهٔ سر تور', 'count' => 1];
            $notes[] = 'چین لبهٔ بالا روی شانهٔ سر دوخته می‌شود.';
        }

        return $this->finish($this->noted($pieces, array_map(
            fn (string $t) => ['type' => 'info', 'text' => $t],
            $notes,
        )));
    }

    /** نیم‌دایره‌ای به شعاع بلندی تور. */
    protected function veilPiece(float $radius, string $code, string $name, float $gather): array
    {
        $radius = max(20.0, $radius);

        // نقطهٔ کنترل به نقطه‌ای می‌چسبد که به آن می‌رسیم، پس کنترلِ لبهٔ
        // بسته‌شونده روی نقطهٔ اول می‌نشیند و نیم‌دایره با سه نقطه ساخته می‌شود
        $outline = [
            Geometry::curve(0, 0, 0, $radius * 1.1),
            Geometry::point($radius * 2, 0),
            Geometry::curve($radius, $radius, $radius * 2, $radius * 1.1),
        ];

        return $this->piece([
            'code' => $code,
            'name' => $name,
            'cut_quantity' => 1,
            'outline' => $outline,
            'grainline' => $this->grainline($radius, 1, $radius - 1),
            'markers' => [$this->marker('centre', 'مرکز تور', $radius, 0, $radius, $radius)],
            'meta' => [
                'part' => 'veil',
                'edges' => ['waist', 'default', 'default'],
                'fold_edges' => [],
                'girth_role' => 'trim',
                'gathers' => [[
                    'edge' => 0,
                    'amount' => round(($radius * 2) - (($radius * 2) / $gather), 2),
                    'label' => 'چین لبهٔ بالای تور',
                ]],
                'notes' => [
                    'لبهٔ صاف بالا چین می‌خورد و روی شانهٔ سر می‌نشیند؛ لبهٔ منحنی آزاد می‌ماند.',
                ],
            ],
        ]);
    }
}
