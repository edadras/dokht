<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Simulation;
use App\Models\Workshop;
use App\Support\FabricProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Simulation>
 */
class SimulationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workshop_id' => Workshop::factory(),
            'project_id' => Project::factory(),
            'pose' => 'stand',
            'params' => FabricProfile::make()->physics(),
            'zones' => $this->zones('good'),
            'warnings' => [],
            'suggestions' => [],
            'fit_score' => 86,
        ];
    }

    /** نتیجه‌ای که چند ناحیه‌ی تنگ و هشدار دارد. */
    public function tight(): static
    {
        return $this->state(fn () => [
            'zones' => $this->zones('tight'),
            'warnings' => ['ناحیه «دور سینه» تنگ است.'],
            'suggestions' => [[
                'text' => '۲ سانتی‌متر به آزادی «دور سینه» اضافه شود.',
                'action' => ['type' => 'increase_ease', 'key' => 'bust', 'value' => 2.0],
            ]],
            'fit_score' => 44,
        ]);
    }

    public function forPose(string $pose): static
    {
        return $this->state(fn () => ['pose' => $pose]);
    }

    /** نواحی نمونه با یک وضعیت مشخص. */
    protected function zones(string $level): array
    {
        $colors = ['tight' => '#dc2626', 'snug' => '#f59e0b', 'good' => '#16a34a', 'loose' => '#2563eb'];

        return collect([
            'bust' => ['دور سینه', 92.0],
            'waist' => ['دور کمر', 74.0],
            'hip' => ['دور باسن', 98.0],
        ])->map(fn ($row, $key) => [
            'key' => $key,
            'label' => $row[0],
            'garment_cm' => $row[1] + ($level === 'tight' ? -5 : 6),
            'body_cm' => $row[1],
            'ease_cm' => $level === 'tight' ? -5.0 : 6.0,
            'level' => $level,
            'color' => $colors[$level] ?? '#16a34a',
            'pressure' => $level === 'tight' ? 0.85 : 0.3,
            'note' => 'ناحیه‌ی نمونه برای آزمون.',
        ])->values()->all();
    }
}
