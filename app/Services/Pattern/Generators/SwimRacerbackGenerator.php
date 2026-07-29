<?php

namespace App\Services\Pattern\Generators;

/**
 * مایو یک‌تکه ورزشی (پشت‌کمانی).
 *
 * همان یک‌تکه است با دو تفاوت که هر دو کاربردی‌اند، نه ظاهری:
 *
 *   ۱. بند به مرکز پشت نزدیک می‌شود تا هنگام حرکت دست از روی شانه نیفتد.
 *   ۲. پارچه پرکشش‌تر و الگو تنگ‌تر بریده می‌شود؛ مایو مسابقه‌ای باید در آب
 *      هیچ چین و بادی نداشته باشد.
 *
 * یقهٔ جلو هم بالاتر از مایو معمولی است، به همان دلیل: هرچه بازتر، در آب
 * بیشتر جابه‌جا می‌شود.
 */
class SwimRacerbackGenerator extends SwimOnePieceGenerator
{
    public static function key(): string
    {
        return 'swim_racerback';
    }

    public function label(): string
    {
        return 'مایو ورزشی (پشت‌کمانی)';
    }

    public function paramsSchema(): array
    {
        $schema = $this->swimSchema($this->onePieceParams([
            'racer_width' => [
                'label' => 'پهنای بند در مرکز پشت', 'min' => 2, 'max' => 12, 'step' => 0.5,
                'default' => 5, 'unit' => 'سانتی‌متر',
            ],
        ]), stretch: 0.78);

        $schema['neck_drop']['default'] = 4;
        $schema['back_drop']['default'] = 16;
        $schema['strap_width']['default'] = 3;
        $schema['bust_cups']['default'] = false;

        return $schema;
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $pieces = $this->onePieceBody($measurements, $ease, $params, ['prefix' => 'racer']);
        $racer = (float) $this->param($params, 'racer_width', 5);

        foreach ($pieces as $index => $piece) {
            if (($piece['meta']['part'] ?? '') !== 'back_bodice') {
                continue;
            }

            $pieces[$index]['meta']['racerback'] = true;
            $pieces[$index]['meta']['notes'][] = 'بند پشت به '.$this->fa($racer)
                .' سانتی‌متری مرکز پشت می‌رسد؛ همین است که بند را روی شانه نگه می‌دارد.';
        }

        $notes = array_merge($this->swimNotes($params), [
            'مایو مسابقه‌ای باید در آب هیچ چین و بادی نداشته باشد؛ الگو عمداً تنگ‌تر از مایو معمولی است.',
        ]);

        return $this->finish($this->noted(
            $this->withLining($pieces, $params),
            array_map(fn (string $t) => ['type' => 'info', 'text' => $t], $notes),
        ));
    }
}
