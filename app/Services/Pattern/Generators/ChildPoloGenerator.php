<?php

namespace App\Services\Pattern\Generators;

/** پولوشرتِ بچگانه: کشباف، یقهٔ ایستادهٔ کوتاه و جای‌دکمهٔ کوتاهِ جلو. */
class ChildPoloGenerator extends ChildGarmentBaseGenerator
{
    public static function key(): string
    {
        return 'child_polo';
    }

    protected function child(): array
    {
        return [
            'prefix' => 'child_polo',
            'title' => 'پولوشرت بچگانه',
            'form' => 'top',
            'use' => 'school',
            'knit' => true,
            'length' => 12,
            'shape' => 'straight',
            'opening' => 'closed',
            'collar' => 'turn',
            'collar_height' => 4,
            'sleeve_length' => 18,
            'notes' => ['جای‌دکمهٔ کوتاهِ مرکز جلو جدا دوخته می‌شود و در سنجش رد شدن یقه از سر حساب نشده است.'],
        ];
    }
}
