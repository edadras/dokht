<?php

namespace App\Services\Pattern\Generators;

/**
 * بالاتنه پرنسسی از سرشانه.
 *
 * همان درفت پرنسسی است با یک تفاوت: درز به‌جای حلقه آستین از وسط سرشانه شروع
 * می‌شود. این خط روی تن بلندتر دیده می‌شود و قد را کشیده‌تر نشان می‌دهد؛ در کت،
 * پالتو و لباس مجلسی بیشتر همین را می‌دوزند.
 */
class BodicePrincessShoulderGenerator extends BodicePrincessArmholeGenerator
{
    public static function key(): string
    {
        return 'bodice_princess_shoulder';
    }

    public function label(): string
    {
        return 'بالاتنه پرنسسی از سرشانه';
    }

    protected function origin(): string
    {
        return 'shoulder';
    }
}
