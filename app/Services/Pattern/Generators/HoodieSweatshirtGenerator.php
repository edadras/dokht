<?php

namespace App\Services\Pattern\Generators;

/**
 * سویشرت.
 *
 * همان درفت هودی بدون کلاه: به‌جای کلاه یک نوار کشباف دور یقه می‌نشیند و جیب
 * کانگورویی به‌طور پیش‌فرض برداشته می‌شود. تنه گشاد، سرشانه افتاده و لبه پایین
 * و مچ با نوار کشباف بسته می‌شود.
 */
class HoodieSweatshirtGenerator extends HoodieGenerator
{
    public static function key(): string
    {
        return 'sweatshirt';
    }

    public function label(): string
    {
        return 'سویشرت';
    }

    protected function hasHood(array $params): bool
    {
        return false;
    }

    public function paramsSchema(): array
    {
        $schema = parent::paramsSchema();
        unset($schema['hood'], $schema['hood_height']);
        $schema['kangaroo']['default'] = false;
        $schema['neck_width_extra']['default'] = 2;

        return $schema;
    }
}
