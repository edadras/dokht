<?php

namespace App\Services\Pattern\Generators;

/**
 * مایو دامنی.
 *
 * یک‌تکهٔ کامل با یک دامن کوتاه که از خط کمر یا باسن آویزان می‌شود.
 *
 * دامن مایو یک قاعده دارد که با دامن معمولی فرق می‌کند: باید سبک و کوتاه باشد
 * و از پارچهٔ خودِ مایو بریده شود، نه پارچهٔ بافته. دامنِ سنگین در آب باد
 * می‌کند و بالا می‌آید — دقیقاً برعکس کاری که قرار است بکند.
 */
class SwimSkirtedGenerator extends SwimOnePieceGenerator
{
    public static function key(): string
    {
        return 'swim_skirted';
    }

    public function label(): string
    {
        return 'مایو دامنی';
    }

    public function paramsSchema(): array
    {
        return $this->swimSchema($this->onePieceParams([
            'skirt_length' => [
                'label' => 'بلندی دامن', 'min' => 8, 'max' => 40, 'step' => 1,
                'default' => 20, 'unit' => 'سانتی‌متر',
            ],
            'skirt_flare' => [
                'label' => 'گشادی دامن', 'min' => 0, 'max' => 30, 'step' => 1,
                'default' => 10, 'unit' => 'سانتی‌متر',
            ],
            'skirt_from' => [
                'label' => 'دامن از کجا آویزان شود', 'type' => 'select', 'default' => 'waist',
                'options' => ['waist' => 'خط کمر', 'hip' => 'خط باسن'],
            ],
        ]));
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $pieces = $this->onePieceBody($measurements, $ease, $params, ['prefix' => 'skirted']);

        $stretch = $this->stretch($params);
        $fromWaist = $this->param($params, 'skirt_from', 'waist') === 'waist';
        $girth = ((float) ($measurements[$fromWaist ? 'waist' : 'hip'] ?? ($fromWaist ? 74 : 98))) * $stretch;

        $length = (float) $this->param($params, 'skirt_length', 20);
        $flare = (float) $this->param($params, 'skirt_flare', 10);

        $pieces[] = $this->bandPiece('swim-skirt', 'دامن مایو', ($girth + $flare) / 2, $length, [
            'cut' => 2, 'part' => 'skirt_panel',
            'meta' => [
                'girth_role' => 'trim',
                'notes' => [
                    'از پارچهٔ خودِ مایو بریده می‌شود، نه پارچهٔ بافته؛ دامن سنگین در آب باد می‌کند و بالا می‌آید.',
                    'دم دامن با کش یا دوخت دولا تمام می‌شود.',
                ],
            ],
        ]);

        return $this->finish($this->noted(
            $this->withLining($pieces, $params),
            array_map(fn (string $t) => ['type' => 'info', 'text' => $t], $this->swimNotes($params)),
        ));
    }
}
