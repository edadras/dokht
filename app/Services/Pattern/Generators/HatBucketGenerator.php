<?php

namespace App\Services\Pattern\Generators;

/** کلاهِ باکت: تاجِ گرد، دیوارهٔ نواری و لبهٔ دورتادور. */
class HatBucketGenerator extends AccessoryBaseGenerator
{
    public static function key(): string
    {
        return 'hat_bucket';
    }

    protected function accessory(): array
    {
        return [
            'prefix' => 'hat_bucket',
            'title' => 'کلاه باکت',
            'kind' => 'hat',
            'parts' => [
                ['form' => 'disc', 'code' => 'crown', 'name' => 'تاج کلاه', 'r' => 'head/6.6', 'cut' => 1, 'fold' => true],
                ['form' => 'rect', 'code' => 'side', 'name' => 'دیواره کلاه', 'w' => 'head+2', 'h' => 9.0, 'cut' => 1],
                ['form' => 'ring', 'code' => 'brim', 'name' => 'لبه کلاه', 'r' => 'head/6.3', 'width' => 6.0, 'cut' => 4],
            ],
            'notes' => ['لبه در چهار تکه بریده می‌شود: دو نیمه برای رو و دو نیمه برای زیر.'],
        ];
    }
}
