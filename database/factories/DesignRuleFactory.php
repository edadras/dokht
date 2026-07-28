<?php

namespace Database\Factories;

use App\Models\DesignRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DesignRule>
 */
class DesignRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workshop_id' => null,
            'code' => 'rule-'.fake()->unique()->numberBetween(1, 999999),
            'name' => 'قاعده آزمایشی '.fake()->numberBetween(1, 99),
            'scope' => 'fabric',
            'conditions' => ['all' => [
                ['field' => 'fabric.transparency', 'op' => '>', 'value' => 0.6],
            ]],
            'actions' => [
                ['type' => 'note', 'text' => 'یادداشت آزمایشی'],
            ],
            'severity' => 'suggestion',
            'priority' => 100,
            'is_active' => true,
        ];
    }

    /** قاعده مخصوص یک کارگاه. */
    public function forWorkshop(int $workshopId): static
    {
        return $this->state(fn () => ['workshop_id' => $workshopId]);
    }

    public function scope(string $scope): static
    {
        return $this->state(fn () => ['scope' => $scope]);
    }

    /** یک شرط دلخواه با کنش یادداشت. */
    public function condition(string $field, string $op, mixed $value): static
    {
        return $this->state(fn () => [
            'conditions' => ['all' => [['field' => $field, 'op' => $op, 'value' => $value]]],
        ]);
    }

    public function actions(array $actions): static
    {
        return $this->state(fn () => ['actions' => $actions]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
