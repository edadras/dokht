<?php

namespace App\Services\Pattern\Style\Sleeve;

use App\Support\Format;

/**
 * آستین کیمونو (یک‌سره).
 *
 * ساده‌ترین و قدیمی‌ترین آستین دنیا: آستین جدا نیست، از خود تنه در می‌آید. چون
 * حلقه‌ای در کار نیست، تنها چیزی که فرم و راحتی را تعیین می‌کند زاویه آستین است.
 *
 * دو حالت دارد:
 *   ـ بدون لوزی زیربغل: زاویه باید نزدیک افق بماند تا دست بالا برود؛ زیر بغل چین
 *     می‌افتد و لباس نرم و آزاد می‌ایستد.
 *   ـ با لوزی زیربغل: زاویه می‌تواند تندتر و آستین قالب‌تر باشد، چون لوزی همان
 *     پارچه‌ای را که برای بالا بردن دست لازم است سر جای خودش می‌گذارد.
 */
class KimonoSleeveStyle extends GrownOnSleeveStyle
{
    public static function key(): string
    {
        return 'sleeve_kimono';
    }

    public function label(): string
    {
        return 'آستین کیمونو (یک‌سره)';
    }

    public function description(): string
    {
        return 'آستین از خود تنه در می‌آید و حلقه‌ای دوخته نمی‌شود؛ با یا بدون لوزی زیربغل.';
    }

    public function paramsSchema(): array
    {
        return $this->grownFields(45, 3, 2.5, 8) + [
            'gusset' => [
                'label' => 'لوزی زیربغل داشته باشد', 'type' => 'toggle', 'default' => true,
                'hint' => 'بدون لوزی، بالا بردن دست کل لباس را بالا می‌کشد.',
            ],
            'gusset_size' => [
                'label' => 'ضلع لوزی زیربغل', 'min' => 5, 'max' => 16, 'step' => 0.5, 'default' => 9,
                'unit' => 'سانتی‌متر',
                'hint' => 'چاک زیر بغل هم به همین اندازه زده می‌شود؛ بزرگ‌تر یعنی آزادی حرکت بیشتر.',
            ],
        ];
    }

    protected function shapeNote(array $p, array $plans): string
    {
        $angle = (float) $p['sleeve_angle'];

        if ($this->hasGusset($p)) {
            return 'کیمونو با زاویه '.Format::number($angle).' درجه درفت شد؛ چون لوزی زیربغل کار آزادی '
                .'حرکت را می‌کند، درز زیر آستین صاف و بدون گودی اضافه بریده شد و آستین قالب می‌ایستد.';
        }

        return $angle <= 35
            ? 'زاویه '.Format::number($angle).' درجه آستین را به افق نزدیک کرده است: دست راحت بالا می‌رود '
                .'ولی زیر بغل پارچه جمع می‌شود و لباس آزادتر دیده می‌شود.'
            : 'زاویه '.Format::number($angle).' درجه برای کیمونوی بدون لوزی تند است؛ لباس قالب‌تر دیده '
                .'می‌شود ولی دست کمتر بالا می‌رود. اگر آستین را قالب می‌خواهید، لوزی زیربغل را روشن کنید.';
    }
}
