<?php

namespace App\Services\Pattern\Generators;

/** دامن ترومپت: مثل دامن ماهی ولی کلوش آن از بالای زانو و ملایم‌تر باز می‌شود. */
class SkirtTrumpetGenerator extends SkirtMermaidGenerator
{
    public static function key(): string
    {
        return 'skirt_trumpet';
    }

    public function label(): string
    {
        return 'دامن ترومپت';
    }

    protected function defaults(): array
    {
        return ['length' => 78, 'start' => 0.52, 'taper' => 1.5, 'flare' => 14.0];
    }
}
