<?php

namespace App\Services\Pattern\Generators;

/** پیراهنِ مجلسیِ بچگانه: بالاتنهٔ جذب و دامنِ پرچین از خطِ کمر. */
class ChildPartyDressGenerator extends ChildGarmentBaseGenerator
{
    public static function key(): string
    {
        return 'child_party_dress';
    }

    protected function child(): array
    {
        return [
            'prefix' => 'child_party_dress',
            'title' => 'پیراهن مجلسی بچگانه',
            'form' => 'dress',
            'use' => 'party',
            'play' => 1,
            'length' => 38,
            'length_max' => 100,
            'shape' => 'straight',
            'opening' => 'button',
            'buttons' => 6,
            'sleeve_length' => 16,
            'hem_flare' => 6,
            'notes' => ['دامن از خط کمر باز می‌شود؛ اگر پارچه سنگین است باز شدن را کمتر بگیر.'],
        ];
    }
}
