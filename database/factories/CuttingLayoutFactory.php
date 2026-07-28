<?php

namespace Database\Factories;

use App\Models\CuttingLayout;
use App\Models\Fabric;
use App\Models\Pattern;
use App\Models\Project;
use App\Models\Workshop;
use App\Services\Cutting\NestingService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CuttingLayout>
 */
class CuttingLayoutFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workshop_id' => Workshop::factory(),
            'fabric_width_cm' => 140,
            'required_length_cm' => 220,
            'waste_percent' => 18.5,
            'efficiency' => 81.5,
            'respect_nap' => false,
            'match_stripes' => false,
            'folded' => true,
            'placements' => [],
            'warnings' => [],
        ];
    }

    /** چیدمان واقعی: با اجرای سرویس چیدمان روی الگو ساخته می‌شود. */
    public function nested(?Pattern $pattern = null, ?Fabric $fabric = null, array $options = []): static
    {
        return $this->state(function (array $attributes) use ($pattern, $fabric, $options) {
            $pattern ??= Pattern::factory()->withSimplePieces()->create([
                'workshop_id' => $attributes['workshop_id'] ?? null,
            ]);

            $result = app(NestingService::class)->nest($pattern, $fabric, $options);

            return [
                'pattern_id' => $pattern->id,
                'fabric_id' => $fabric?->id,
                'fabric_width_cm' => $result['fabric_width_cm'],
                'required_length_cm' => $result['required_length_cm'],
                'waste_percent' => $result['waste_percent'],
                'efficiency' => $result['efficiency'],
                'respect_nap' => $result['options']['respect_nap'],
                'match_stripes' => $result['options']['match_stripes'],
                'folded' => $result['options']['folded'],
                'placements' => $result['placements'],
                'warnings' => $result['warnings'],
            ];
        });
    }

    public function forProject(Project $project): static
    {
        return $this->state(fn () => [
            'workshop_id' => $project->workshop_id,
            'project_id' => $project->id,
            'pattern_id' => $project->pattern_id,
            'fabric_id' => $project->fabric_id,
        ]);
    }
}
