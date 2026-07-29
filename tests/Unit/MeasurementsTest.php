<?php

namespace Tests\Unit;

use App\Support\Measurements;
use PHPUnit\Framework\TestCase;

class MeasurementsTest extends TestCase
{
    public function test_only_six_measurements_are_essential(): void
    {
        $essential = array_keys(Measurements::essential());

        $this->assertSame(
            ['height', 'bust', 'waist', 'hip', 'shoulder_width', 'arm_length'],
            $essential
        );
    }

    public function test_it_estimates_the_missing_measurements_from_the_essentials(): void
    {
        $completed = Measurements::complete([
            'height' => 170,
            'bust' => 92,
            'waist' => 74,
            'hip' => 98,
            'shoulder_width' => 39,
            'arm_length' => 58,
        ]);

        // هیچ اندازه‌ای خالی نمی‌ماند. اصلاح‌های پوسچر از این قاعده بیرون‌اند:
        // مقدارشان روی بدنِ راست درست همان صفر است، نه یک اندازه تخمینی.
        foreach (Measurements::FIELDS as $key => $field) {
            $this->assertArrayHasKey($key, $completed);

            if (! empty($field['posture'])) {
                $this->assertSame(0.0, (float) $completed[$key], "پیش‌فرض «{$key}» باید صفر باشد.");

                continue;
            }

            $this->assertGreaterThan(0, $completed[$key]);
        }

        // مقادیر ورودی دست‌نخورده می‌مانند
        $this->assertSame(92.0, $completed['bust']);

        // تخمین‌ها منطقی‌اند
        $this->assertEqualsWithDelta(78, $completed['under_bust'], 0.1);
        $this->assertEqualsWithDelta(90, $completed['high_hip'], 0.1);
        $this->assertEqualsWithDelta(36.8, $completed['neck'], 0.2);
        $this->assertLessThan($completed['bicep'], $completed['wrist']);
        $this->assertLessThan($completed['outseam'], $completed['inseam']);
    }

    public function test_it_clamps_values_to_the_allowed_range(): void
    {
        $cleaned = Measurements::clean(['height' => 500, 'bust' => 10, 'unknown_field' => 42]);

        $this->assertSame(220.0, $cleaned['height']);
        $this->assertSame(50.0, $cleaned['bust']);
        $this->assertArrayNotHasKey('unknown_field', $cleaned);
    }

    public function test_it_ignores_empty_values(): void
    {
        $cleaned = Measurements::clean(['height' => 170, 'bust' => '', 'waist' => null]);

        $this->assertSame(['height' => 170.0], $cleaned);
    }

    public function test_it_guesses_the_closest_standard_size(): void
    {
        $this->assertSame('40', Measurements::guessSize(['bust' => 92]));
        $this->assertSame('34', Measurements::guessSize(['bust' => 79]));
        $this->assertSame('48', Measurements::guessSize(['bust' => 112]));
        $this->assertSame('40', Measurements::guessSize([]));
    }

    public function test_standard_sizes_grow_monotonically(): void
    {
        $previous = null;

        foreach (Measurements::SIZE_TABLE as $size => $row) {
            if ($previous !== null) {
                $this->assertGreaterThan($previous['bust'], $row['bust'], "size {$size}");
                $this->assertGreaterThan($previous['waist'], $row['waist'], "size {$size}");
                $this->assertGreaterThan($previous['hip'], $row['hip'], "size {$size}");
            }

            $previous = $row;
        }
    }

    public function test_it_builds_a_complete_measurement_set_from_a_size(): void
    {
        $values = Measurements::fromSize('42');

        $this->assertSame(96.0, $values['bust']);
        $this->assertCount(count(Measurements::FIELDS), $values);
    }
}
