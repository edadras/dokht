<?php

namespace App\Services\Pattern\Generators;

/**
 * بیکینی ورزشی (تاپ برالت).
 *
 * تاپی که مثل سوتین ورزشی می‌پوشند: از سر رد می‌شود، بند ندارد که باز شود، و
 * نگه‌داشتنش کار نوار زیرسینه است نه بندها.
 *
 * همان نوار زیرسینه مهم‌ترین عدد این الگوست: باید محسوس‌تر از بقیهٔ لبه‌ها
 * کوتاه‌تر بریده شود، چون تمام وزن روی آن است. برای همین کش زیرسینه این‌جا
 * جدا از بقیهٔ لبه‌ها حساب می‌شود و کوتاه‌تر است.
 */
class SwimSportBraGenerator extends SwimBaseGenerator
{
    public static function key(): string
    {
        return 'swim_sport_bra';
    }

    public function label(): string
    {
        return 'بیکینی ورزشی (برالت)';
    }

    public function paramsSchema(): array
    {
        return $this->swimSchema([
            'top_height' => [
                'label' => 'بلندی تاپ', 'min' => 12, 'max' => 30, 'step' => 0.5,
                'default' => 20, 'unit' => 'سانتی‌متر',
            ],
            'band_height' => [
                'label' => 'بلندی نوار زیرسینه', 'min' => 2, 'max' => 8, 'step' => 0.5,
                'default' => 4, 'unit' => 'سانتی‌متر',
            ],
            'band_ratio' => [
                'label' => 'کوتاهی کش زیرسینه', 'min' => 0.7, 'max' => 0.95, 'step' => 0.01,
                'default' => 0.82,
                'hint' => 'کوتاه‌تر از بقیهٔ لبه‌ها، چون همهٔ وزن روی همین نوار است.',
            ],
            'neck_drop' => [
                'label' => 'گودی یقه جلو', 'min' => 2, 'max' => 20, 'step' => 0.5,
                'default' => 8, 'unit' => 'سانتی‌متر',
            ],
            'racer_back' => [
                'label' => 'پشت‌کمانی', 'type' => 'toggle', 'default' => true,
            ],
        ], stretch: 0.8);
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $ease = $this->swimEase($ease, $measurements, $params);
        $g = $this->blockMetrics($measurements, $ease, $params);

        $strap = $this->flag($params, 'racer_back', true) ? 3.5 : 5.0;

        $shared = [
            'shape' => 'straight',
            'length' => -max(0.0, (float) ($g['side_waist_y'] ?? 0) - (float) ($g['bust_y'] ?? 0) - (float) $this->param($params, 'top_height', 20)),
            'bottom_tag' => 'hem',
            'waist_dart' => false,
            'shoulder_extra' => ($g['neck_width'] + $strap) - $g['shoulder_half'],
            'across_extra' => -3.0,
            'armhole_drop' => 3.0,
        ];

        $front = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'front', 'code' => 'bralette-front', 'name' => 'برالت جلو',
            'neck_depth_extra' => (float) $this->param($params, 'neck_drop', 8),
        ]));

        $back = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'back', 'code' => 'bralette-back', 'name' => 'برالت پشت',
            'neck_depth_extra' => 6,
        ]));

        $pieces = [];

        foreach ([$front, $back] as $piece) {
            $piece = $this->elastic($piece, 'neck', 'کش یقه', $params);
            $piece = $this->elastic($piece, 'armhole', 'کش حلقه', $params);
            $pieces[] = $piece;
        }

        $under = ((float) ($measurements['underbust'] ?? ($measurements['bust'] ?? 92) * 0.86)) * $this->stretch($params);
        $ratio = (float) $this->param($params, 'band_ratio', 0.82);

        $band = $this->bandPiece('bralette-band', 'نوار زیرسینه', $under * $ratio, (float) $this->param($params, 'band_height', 4), [
            'cut' => 1, 'on_fold' => false, 'fold_line' => true, 'part' => 'binding',
            'meta' => [
                'girth_role' => 'trim',
                'notions' => [[
                    'type' => 'elastic',
                    'label' => 'کش زیرسینه',
                    'length' => round($under * $ratio, 1),
                ]],
                'notes' => [
                    'کش زیرسینه '.$this->fa(round((1 - $ratio) * 100)).' درصد کوتاه‌تر از دور زیرسینه است؛'
                        .' همهٔ وزن تاپ روی همین نوار می‌افتد.',
                ],
            ],
        ]);

        $pieces[] = $band;
        $pieces[] = $this->cupPocketPiece();

        $notes = array_merge($this->swimNotes($params), [
            'این تاپ از سر پوشیده می‌شود و بندی ندارد که باز شود؛ نگه‌داشتنش کار نوار زیرسینه است.',
        ]);

        return $this->finish($this->noted(
            $this->withLining($pieces, $params),
            array_map(fn (string $t) => ['type' => 'info', 'text' => $t], $notes),
        ));
    }

    protected function cupPocketPiece(): array
    {
        return $this->bandPiece('bralette-cup-pocket', 'جیب فنجان سینه', 32, 8, [
            'cut' => 1, 'part' => 'lining',
            'meta' => [
                'girth_role' => 'lining',
                'notes' => ['یک طرفش باز می‌ماند تا فنجان درآید و تاپ شسته شود.'],
            ],
        ]);
    }
}
