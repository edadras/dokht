<?php

namespace Database\Factories;

use App\Models\GarmentType;
use App\Models\PatternTemplate;
use App\Models\Workshop;
use App\Services\Pattern\GeneratorRegistry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatternTemplate>
 */
class PatternTemplateFactory extends Factory
{
    public function definition(): array
    {
        return array_merge([
            'workshop_id' => null,
            'garment_type_id' => GarmentType::factory(),
            'code' => 'template-'.fake()->unique()->numberBetween(1, 999999),
            'description' => 'الگوی پایه آزمایشی.',
            'is_public' => true,
            'sort' => 10,
        ], $this->generatorAttributes('bodice_block', 'بالاتنه پایه'));
    }

    /** الگوی پایه با تولیدکننده دلخواه. */
    public function generator(string $key): static
    {
        return $this->state(fn () => $this->generatorAttributes($key, GeneratorRegistry::make($key)->label()));
    }

    /** الگوی اختصاصی یک کارگاه (نه سراسری). */
    public function forWorkshop(Workshop|int $workshop): static
    {
        return $this->state(fn () => [
            'workshop_id' => $workshop instanceof Workshop ? $workshop->id : $workshop,
            'is_public' => false,
        ]);
    }

    /** @return array<string, mixed> */
    protected function generatorAttributes(string $key, string $name): array
    {
        $generator = GeneratorRegistry::make($key);

        return [
            'generator' => $key,
            'name_fa' => $name,
            'default_params' => $generator->defaultParams(),
            'params_schema' => $generator->paramsSchema(),
        ];
    }
}
