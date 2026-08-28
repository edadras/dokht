<?php

namespace App\Services\Pattern\Generators;

/** پیراهن فلانل: گشاد، دو جیبِ سینهٔ دردار و آستینِ بلند؛ روی تی‌شرت پوشیده می‌شود. */
class MensShirtFlannelGenerator extends MensShirtBaseGenerator
{
    public static function key(): string
    {
        return 'mens_shirt_flannel';
    }

    public function label(): string
    {
        return 'پیراهن مردانه پشمی (فلانل)';
    }

    protected function mens(): array
    {
        return ['prefix' => 'mens-flannel', 'title' => 'پیراهن فلانل', 'fit' => 'loose',
            'collar' => 'shirt', 'cuff' => 'button', 'use' => 'daily', 'pocket' => true,
            'body_length' => 20, 'armhole' => 5];
    }
}
