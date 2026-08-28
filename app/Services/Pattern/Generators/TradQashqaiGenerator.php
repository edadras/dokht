<?php

namespace App\Services\Pattern\Generators;

/**
 * لباس قشقایی.
 *
 * تنهٔ کوتاه و دامنِ چند طبقه: هر طبقه از طبقهٔ بالای خودش پُرتر بریده می‌شود و
 * روی آن چین می‌خورد، پس دامن هرچه پایین‌تر می‌رود پُرتر می‌شود. همین طبقه‌بندی
 * است که به آن حجمِ بی‌ساسون می‌دهد.
 */
class TradQashqaiGenerator extends RegionalDressBaseGenerator
{
    public static function key(): string
    {
        return 'trad_qashqai';
    }

    public function label(): string
    {
        return 'لباس قشقایی';
    }

    protected function regional(): array
    {
        return [
            'prefix' => 'qashqai-',
            'title' => 'لباس قشقایی',
            'length' => 118,
            'sleeve' => 58,
            'cuff_flare' => 8,
            'fullness' => 2.4,
            'tiers' => 3,
            'tier_grow' => 1.45,
            'waist_drop' => 34,
            'slit' => 16,
            'notes' => [
                'سه طبقهٔ دامن هرکدام روی طبقهٔ بالای خود چین می‌خورد.',
                'تنه کوتاه است و خط کمر بالاتر از کمرِ طبیعی می‌نشیند.',
            ],
        ];
    }
}
