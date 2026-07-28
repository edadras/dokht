<?php

namespace App\Services\Pattern\Style\Sleeve;

use App\Support\Format;

/**
 * آستین بت‌وینگ (بال خفاشی).
 *
 * تندروترین حالت آستین یکی‌بریده: زیر بغل تا خط کمر پایین می‌آید و درز زیر آستین
 * یک کمان بسیار گود از مچ تا پهلو می‌شود. دم آستین تنگ می‌ماند، پس همه گشادی بین
 * زیر بازو و کمر جمع می‌شود و با باز کردن دست «بال» درست می‌کند.
 *
 * چون گودی حلقه به بیشترین حد خود رسیده، لباس هیچ محدودیتی برای بالا بردن دست
 * ندارد؛ در عوض زیر بغل حجم زیادی پارچه هست و لباس زیر آن قالب نمی‌ایستد.
 */
class BatwingSleeveStyle extends GrownOnSleeveStyle
{
    public static function key(): string
    {
        return 'sleeve_batwing';
    }

    public function label(): string
    {
        return 'آستین بت‌وینگ';
    }

    public function description(): string
    {
        return 'زیر بغل تا کمر گود می‌شود و دم آستین تنگ می‌ماند؛ با باز کردن دست بال درست می‌کند.';
    }

    public function paramsSchema(): array
    {
        return $this->grownFields(20, 18, 10, 3);
    }

    protected function shapeNote(array $p, array $plans): string
    {
        $front = $plans['front'] ?? reset($plans);

        return 'بت‌وینگ: زیر بغل '.Format::cm($front['drop'], 1).' پایین آمد — یعنی تا نزدیک خط کمر — و '
            .'درز زیر آستین با کمان بلند بریده شد. دم آستین '.Format::cm($front['hem_half'] * 2, 1)
            .' (دور کامل) ماند تا همه گشادی زیر بازو جمع شود و بال بسازد. برای پارچه نرم و افتاده مثل '
            .'ویسکوز یا جرسی درفت شده است؛ با پارچه سفت زیر بغل باد می‌کند.';
    }
}
