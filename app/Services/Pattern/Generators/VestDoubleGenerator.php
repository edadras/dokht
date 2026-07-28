<?php

namespace App\Services\Pattern\Generators;

/**
 * جلیقه دوطرفه‌دکمه.
 *
 * همان درفت جلیقه است با یک تفاوت مهم: لبه جلو به اندازه هم‌پوشانی از خط مرکز
 * جلو بیرون می‌زند و دو رج دکمه می‌خورد. این اضافه روی هم می‌افتد، پس در دور
 * تمام‌شده حساب نمی‌شود؛ فقط سجاف جلو باید به همان اندازه پهن‌تر بریده شود.
 */
class VestDoubleGenerator extends VestSingleGenerator
{
    public static function key(): string
    {
        return 'vest_double';
    }

    public function label(): string
    {
        return 'جلیقه دوطرفه‌دکمه';
    }

    protected function overlap(array $params): float
    {
        return max(3.0, (float) $this->param($params, 'button_stand', 6.5));
    }

    protected function rows(): int
    {
        return 2;
    }

    public function paramsSchema(): array
    {
        $schema = parent::paramsSchema();
        $schema['button_stand']['label'] = 'هم‌پوشانی جلو از خط مرکز';
        $schema['button_stand']['default'] = 6.5;
        $schema['button_stand']['min'] = 3;
        $schema['buttons']['label'] = 'تعداد دکمه در هر رج';
        $schema['buttons']['default'] = 3;
        $schema['front_neck_depth_extra']['default'] = 8;

        return $schema;
    }
}
