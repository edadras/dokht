<?php

namespace Tests\Unit;

use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\SeasonClassifier;
use Tests\TestCase;

/**
 * فصلِ مدل‌ها.
 *
 * این آزمون فهرستِ فصل‌ها را حفظ نمی‌کند؛ چند موردی را می‌سنجد که اگر برعکس
 * دربیایند، قاعده شکسته است: پالتو تابستانی نیست و مایو زمستانی.
 */
class SeasonClassifierTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        SeasonClassifier::flush();
    }

    public function test_the_swimwear_is_summer_only(): void
    {
        $this->assertSame(['summer'], SeasonClassifier::of('swim_onepiece'));
        $this->assertSame(['summer'], SeasonClassifier::of('beach_sarong'));
    }

    public function test_the_outer_layers_never_land_in_summer(): void
    {
        foreach (['coat_overcoat', 'coat_trench', 'jacket_puffer', 'mens_parka'] as $key) {
            $this->assertNotContains(
                'summer',
                SeasonClassifier::of($key),
                $key.' نباید تابستانی شمرده شود.',
            );
        }
    }

    public function test_a_sleeveless_top_is_summer(): void
    {
        $this->assertContains('summer', SeasonClassifier::of('top_tank'));
        $this->assertContains('summer', SeasonClassifier::of('active_singlet'));
    }

    public function test_a_long_sleeved_layered_garment_is_cold_weather(): void
    {
        $seasons = SeasonClassifier::of('active_base_layer');

        $this->assertContains('winter', $seasons);
        $this->assertNotContains('summer', $seasons);
    }

    public function test_every_model_lands_in_at_least_one_season(): void
    {
        $problems = [];

        foreach (array_keys(GeneratorRegistry::all()) as $key) {
            $seasons = SeasonClassifier::of($key);

            if ($seasons === []) {
                $problems[] = $key;

                continue;
            }

            foreach ($seasons as $season) {
                if (! array_key_exists($season, SeasonClassifier::SEASONS)) {
                    $problems[] = $key.' فصل ناشناخته دارد: '.$season;
                }
            }
        }

        $this->assertSame([], $problems, 'هر مدل باید دست‌کم در یک فصل بنشیند.');
    }

    public function test_each_season_has_models(): void
    {
        foreach (array_keys(SeasonClassifier::SEASONS) as $season) {
            $this->assertNotEmpty(
                SeasonClassifier::inSeason($season),
                'فصل '.$season.' هیچ مدلی ندارد.',
            );
        }
    }
}
