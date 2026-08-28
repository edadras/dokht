<?php

namespace App\Services\Pattern\Generators;

/** کتِ چرمِ کوتاهِ زنانه با زیپِ اریب. */
class JacketLeatherCroppedGenerator extends JacketBikerGenerator
{
    public static function key(): string
    {
        return 'jacket_leather_cropped';
    }

    public function label(): string
    {
        return 'کت چرم کوتاه';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'length' => 6,
            'ease_extra' => 2,
        ]);
    }
}
