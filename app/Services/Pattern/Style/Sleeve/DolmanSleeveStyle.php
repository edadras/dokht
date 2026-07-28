<?php

namespace App\Services\Pattern\Style\Sleeve;

use App\Support\Format;

/**
 * آستین دولمان.
 *
 * همان آستین یکی‌بریده کیمونو است ولی با حلقه‌ای بسیار گودتر: نقطه زیر بغل تا
 * نزدیک خط کمر پایین می‌آید و درز زیر آستین یک منحنی بلند و نرم از مچ تا پهلو
 * می‌شود. چون گودی حلقه خودش پارچه لازم برای بالا بردن دست را تأمین می‌کند،
 * دولمان به لوزی زیربغل نیاز ندارد؛ در عوض زیر بازو پارچه جمع می‌شود و همین چین
 * ملایم، شکل شناخته‌شده دولمان است.
 */
class DolmanSleeveStyle extends GrownOnSleeveStyle
{
    public static function key(): string
    {
        return 'sleeve_dolman';
    }

    public function label(): string
    {
        return 'آستین دولمان';
    }

    public function description(): string
    {
        return 'آستین یکی‌بریده با حلقه بسیار گود؛ زیر بازو آزاد و افتاده می‌ایستد.';
    }

    public function paramsSchema(): array
    {
        return $this->grownFields(32, 12, 5, 10);
    }

    protected function shapeNote(array $p, array $plans): string
    {
        $front = $plans['front'] ?? reset($plans);
        $curve = $front['underarm_final'] - $front['underarm_chord'];

        return 'دولمان: زیر بغل '.Format::cm($front['drop'], 1).' پایین‌تر از حلقه بلوک نشست و درز '
            .'زیر آستین '.Format::cm($curve, 1).' بلندتر از خط راست کشیده شد تا زیر بازو نرم بیفتد. '
            .'با زاویه '.Format::number($front['angle']).' درجه، آستین همراه بالا رفتن دست بالا می‌آید و '
            .'لباس از پهلو کشیده نمی‌شود؛ به همین دلیل دولمان لوزی زیربغل نمی‌خواهد.';
    }
}
