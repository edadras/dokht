<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** داده‌های پایه سامانه و در محیط توسعه، یک کارگاه نمونه. */
    public function run(): void
    {
        $this->call([
            FabricTypeSeeder::class,
            GarmentTypeSeeder::class,
            PatternTemplateSeeder::class,
            DesignRuleSeeder::class,
            GuideSeeder::class,
        ]);

        if (! app()->environment('production')) {
            $this->call(DemoWorkshopSeeder::class);
        }
    }
}
