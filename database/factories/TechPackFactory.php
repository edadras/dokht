<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\TechPack;
use App\Models\Workshop;
use App\Services\TechPack\TechPackBuilder;
use App\Support\Jalali;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TechPack>
 */
class TechPackFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workshop_id' => Workshop::factory(),
            'project_id' => Project::factory(),
            'version' => 1,
            'data' => [
                'garment' => ['name' => 'پیراهن نمونه', 'size' => '40'],
                'fabric' => ['name' => 'پارچه نمونه', 'consumption_m' => 2.4],
                'pattern' => ['piece_count' => 2, 'pieces' => []],
                'measurements' => ['rows' => []],
                'construction' => [],
                'materials' => [],
                'cutting' => ['width' => 140, 'length' => 220, 'waste' => 18.5, 'efficiency' => 81.5, 'warnings' => []],
                'fit' => null,
                'quality' => null,
                'notes' => [],
                'missing' => [],
                'generated_at' => Jalali::dateTime(now()),
            ],
        ];
    }

    /** داده واقعی: با سازنده کارت فنی از روی پروژه ساخته می‌شود. */
    public function built(Project $project): static
    {
        return $this->state(fn () => [
            'workshop_id' => $project->workshop_id,
            'project_id' => $project->id,
            'version' => TechPack::nextVersion($project),
            'data' => app(TechPackBuilder::class)->build($project),
        ]);
    }
}
