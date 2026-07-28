<?php

namespace Database\Seeders;

use App\Models\GarmentType;
use Illuminate\Database\Seeder;

/**
 * فهرست انواع لباس.
 *
 * برای هر لباس اجزای تشکیل‌دهنده، آزادی پیش‌فرض، اندازه‌های لازم و «سلیقه پارچه»
 * (معیارهایی که ماژول سازگاری پارچه با آن امتیاز می‌دهد) ثبت می‌شود.
 */
class GarmentTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->garments() as $index => $garment) {
            GarmentType::updateOrCreate(
                ['code' => $garment['code']],
                array_merge($garment, ['sort' => ($index + 1) * 10, 'is_active' => true]),
            );
        }
    }

    /** @return array<int, array<string, mixed>> */
    protected function garments(): array
    {
        return [
            [
                'code' => 'shirt',
                'name_fa' => 'پیراهن',
                'name_en' => 'Shirt',
                'category' => 'top',
                'icon' => 'shirt',
                'parts' => ['front_bodice', 'back_bodice', 'yoke', 'sleeve', 'collar', 'cuff', 'placket', 'interfacing'],
                'default_ease' => ['bust' => 10, 'waist' => 10, 'hip' => 10, 'bicep' => 6],
                'required_measurements' => ['bust', 'waist', 'hip', 'shoulder_width', 'arm_length', 'neck', 'back_length'],
                'fabric_preferences' => $this->preferences(0.6, 0.3, 90, 200, 0, 15, 0.25, 0.4, 0.3),
            ],
            [
                'code' => 'blouse',
                'name_fa' => 'بلوز',
                'name_en' => 'Blouse',
                'category' => 'top',
                'icon' => 'shirt',
                'parts' => ['front_bodice', 'back_bodice', 'sleeve', 'collar', 'facing'],
                'default_ease' => ['bust' => 8, 'waist' => 8, 'hip' => 8, 'bicep' => 5],
                'required_measurements' => ['bust', 'waist', 'shoulder_width', 'arm_length', 'back_length'],
                'fabric_preferences' => $this->preferences(0.7, 0.3, 70, 180, 0, 25, 0.35, 0.35, 0.3),
            ],
            [
                'code' => 'shomiz',
                'name_fa' => 'شومیز',
                'name_en' => 'Tunic blouse',
                'category' => 'top',
                'icon' => 'shirt',
                'parts' => ['front_bodice', 'back_bodice', 'sleeve', 'collar', 'cuff', 'placket', 'facing'],
                'default_ease' => ['bust' => 12, 'waist' => 14, 'hip' => 14, 'bicep' => 7],
                'required_measurements' => ['bust', 'waist', 'hip', 'shoulder_width', 'arm_length', 'back_length'],
                'fabric_preferences' => $this->preferences(0.75, 0.25, 70, 170, 0, 20, 0.35, 0.3, 0.3),
            ],
            [
                'code' => 'top',
                'name_fa' => 'تاپ',
                'name_en' => 'Top',
                'category' => 'top',
                'icon' => 'shirt',
                'parts' => ['front_bodice', 'back_bodice', 'facing'],
                'default_ease' => ['bust' => 2, 'waist' => 2, 'hip' => 4, 'bicep' => 0],
                'required_measurements' => ['bust', 'waist', 'shoulder_width'],
                'fabric_preferences' => $this->preferences(0.7, 0.3, 60, 200, 5, 60, 0.3, 0.25, 0.3),
            ],
            [
                'code' => 'tshirt',
                'name_fa' => 'تی‌شرت',
                'name_en' => 'T-shirt',
                'category' => 'top',
                'icon' => 'shirt',
                'parts' => ['front_bodice', 'back_bodice', 'sleeve', 'collar'],
                'default_ease' => ['bust' => 4, 'waist' => 6, 'hip' => 6, 'bicep' => 3],
                'required_measurements' => ['bust', 'waist', 'shoulder_width', 'arm_length'],
                'fabric_preferences' => $this->preferences(0.55, 0.3, 140, 260, 25, 90, 0.15, 0.3, 0.3),
            ],
            [
                'code' => 'blazer',
                'name_fa' => 'کت',
                'name_en' => 'Blazer',
                'category' => 'outer',
                'icon' => 'layers',
                'parts' => ['front_bodice', 'back_bodice', 'sleeve', 'lapel', 'collar', 'facing', 'lining', 'interfacing', 'pocket'],
                'default_ease' => ['bust' => 12, 'waist' => 12, 'hip' => 12, 'bicep' => 8],
                'required_measurements' => ['bust', 'waist', 'hip', 'shoulder_width', 'arm_length', 'back_length', 'bicep'],
                'fabric_preferences' => $this->preferences(0.35, 0.25, 220, 420, 0, 12, 0.05, 0.7, 0.25),
            ],
            [
                'code' => 'cardigan',
                'name_fa' => 'ژاکت',
                'name_en' => 'Cardigan',
                'category' => 'outer',
                'icon' => 'layers',
                'parts' => ['front_bodice', 'back_bodice', 'sleeve', 'facing', 'pocket'],
                'default_ease' => ['bust' => 10, 'waist' => 10, 'hip' => 10, 'bicep' => 7],
                'required_measurements' => ['bust', 'waist', 'shoulder_width', 'arm_length'],
                'fabric_preferences' => $this->preferences(0.5, 0.35, 180, 400, 15, 80, 0.15, 0.4, 0.35),
            ],
            [
                'code' => 'manteau',
                'name_fa' => 'مانتو',
                'name_en' => 'Manteau',
                'category' => 'outer',
                'icon' => 'layers',
                'parts' => ['front_bodice', 'back_bodice', 'sleeve', 'collar', 'cuff', 'facing', 'lining', 'pocket', 'belt'],
                'default_ease' => ['bust' => 14, 'waist' => 16, 'hip' => 16, 'bicep' => 8],
                'required_measurements' => ['bust', 'waist', 'hip', 'shoulder_width', 'arm_length', 'back_length'],
                'fabric_preferences' => $this->preferences(0.45, 0.3, 180, 380, 0, 20, 0.1, 0.55, 0.3),
            ],
            [
                'code' => 'coat',
                'name_fa' => 'کاپشن و پالتو',
                'name_en' => 'Coat',
                'category' => 'outer',
                'icon' => 'layers',
                'parts' => ['front_bodice', 'back_bodice', 'sleeve', 'collar', 'hood', 'facing', 'lining', 'interfacing', 'pocket', 'belt'],
                'default_ease' => ['bust' => 18, 'waist' => 20, 'hip' => 20, 'bicep' => 12],
                'required_measurements' => ['bust', 'waist', 'hip', 'shoulder_width', 'arm_length', 'back_length', 'bicep'],
                'fabric_preferences' => $this->preferences(0.3, 0.25, 260, 600, 0, 15, 0.05, 0.8, 0.25),
            ],
            [
                'code' => 'skirt_straight',
                'name_fa' => 'دامن راسته',
                'name_en' => 'Straight skirt',
                'category' => 'bottom',
                'icon' => 'box',
                'parts' => ['skirt_front', 'skirt_back', 'waistband', 'facing', 'lining'],
                'default_ease' => ['waist' => 2, 'hip' => 4],
                'required_measurements' => ['waist', 'hip', 'waist_to_hip'],
                'fabric_preferences' => $this->preferences(0.45, 0.3, 180, 380, 0, 20, 0.1, 0.55, 0.3),
            ],
            [
                'code' => 'skirt_gored',
                'name_fa' => 'دامن ترک',
                'name_en' => 'Gored skirt',
                'category' => 'bottom',
                'icon' => 'box',
                'parts' => ['front_panel', 'back_panel', 'waistband', 'lining'],
                'default_ease' => ['waist' => 2, 'hip' => 5],
                'required_measurements' => ['waist', 'hip', 'waist_to_hip'],
                'fabric_preferences' => $this->preferences(0.55, 0.3, 150, 350, 0, 20, 0.1, 0.45, 0.3),
            ],
            [
                'code' => 'skirt_circle',
                'name_fa' => 'دامن کلوش',
                'name_en' => 'Circle skirt',
                'category' => 'bottom',
                'icon' => 'box',
                'parts' => ['skirt_front', 'skirt_back', 'waistband', 'lining'],
                'default_ease' => ['waist' => 2, 'hip' => 8],
                'required_measurements' => ['waist', 'hip'],
                'fabric_preferences' => $this->preferences(0.85, 0.2, 80, 220, 0, 20, 0.3, 0.2, 0.25),
            ],
            [
                'code' => 'pants',
                'name_fa' => 'شلوار',
                'name_en' => 'Trousers',
                'category' => 'bottom',
                'icon' => 'box',
                'parts' => ['front_leg', 'back_leg', 'waistband', 'pocket', 'facing'],
                'default_ease' => ['waist' => 2, 'hip' => 6],
                'required_measurements' => ['waist', 'hip', 'inseam', 'outseam', 'thigh', 'knee', 'ankle', 'waist_to_hip'],
                'fabric_preferences' => $this->preferences(0.4, 0.3, 200, 420, 0, 30, 0.05, 0.6, 0.3),
            ],
            [
                'code' => 'shorts',
                'name_fa' => 'شلوارک',
                'name_en' => 'Shorts',
                'category' => 'bottom',
                'icon' => 'box',
                'parts' => ['front_leg', 'back_leg', 'waistband', 'pocket'],
                'default_ease' => ['waist' => 2, 'hip' => 6],
                'required_measurements' => ['waist', 'hip', 'thigh', 'waist_to_hip'],
                'fabric_preferences' => $this->preferences(0.4, 0.3, 160, 380, 0, 35, 0.05, 0.55, 0.3),
            ],
            [
                'code' => 'jumpsuit',
                'name_fa' => 'سرهمی (اورال)',
                'name_en' => 'Jumpsuit',
                'category' => 'one_piece',
                'icon' => 'cube',
                'parts' => ['front_bodice', 'back_bodice', 'front_leg', 'back_leg', 'sleeve', 'waistband', 'facing', 'pocket'],
                'default_ease' => ['bust' => 10, 'waist' => 8, 'hip' => 10, 'bicep' => 6],
                'required_measurements' => ['bust', 'waist', 'hip', 'shoulder_width', 'arm_length', 'inseam', 'back_length', 'waist_to_hip'],
                'fabric_preferences' => $this->preferences(0.55, 0.3, 150, 340, 0, 30, 0.1, 0.4, 0.3),
            ],
            [
                'code' => 'dress',
                'name_fa' => 'پیراهن یک‌تکه',
                'name_en' => 'Dress',
                'category' => 'one_piece',
                'icon' => 'cube',
                'parts' => ['front_bodice', 'back_bodice', 'sleeve', 'collar', 'facing', 'lining'],
                'default_ease' => ['bust' => 8, 'waist' => 6, 'hip' => 8, 'bicep' => 5],
                'required_measurements' => ['bust', 'waist', 'hip', 'shoulder_width', 'arm_length', 'back_length', 'front_length', 'waist_to_hip'],
                'fabric_preferences' => $this->preferences(0.65, 0.3, 90, 260, 0, 30, 0.25, 0.35, 0.3),
            ],
            [
                'code' => 'evening_dress',
                'name_fa' => 'لباس شب',
                'name_en' => 'Evening gown',
                'category' => 'formal',
                'icon' => 'sparkles',
                'parts' => ['front_bodice', 'back_bodice', 'skirt_front', 'skirt_back', 'facing', 'lining', 'interfacing'],
                'default_ease' => ['bust' => 2, 'waist' => 1, 'hip' => 3, 'bicep' => 3],
                'required_measurements' => ['bust', 'under_bust', 'waist', 'hip', 'shoulder_width', 'back_length', 'front_length', 'waist_to_hip'],
                'fabric_preferences' => $this->preferences(0.85, 0.2, 60, 200, 0, 25, 0.4, 0.25, 0.25),
            ],
            [
                'code' => 'cocktail_dress',
                'name_fa' => 'لباس مجلسی',
                'name_en' => 'Cocktail dress',
                'category' => 'formal',
                'icon' => 'sparkles',
                'parts' => ['front_bodice', 'back_bodice', 'skirt_front', 'skirt_back', 'sleeve', 'facing', 'lining'],
                'default_ease' => ['bust' => 4, 'waist' => 3, 'hip' => 4, 'bicep' => 4],
                'required_measurements' => ['bust', 'waist', 'hip', 'shoulder_width', 'back_length', 'front_length'],
                'fabric_preferences' => $this->preferences(0.7, 0.25, 80, 260, 0, 30, 0.3, 0.35, 0.3),
            ],
            [
                'code' => 'bridal_dress',
                'name_fa' => 'لباس عروس',
                'name_en' => 'Bridal gown',
                'category' => 'formal',
                'icon' => 'star',
                'parts' => ['front_bodice', 'back_bodice', 'skirt_front', 'skirt_back', 'sleeve', 'facing', 'lining', 'interfacing'],
                'default_ease' => ['bust' => 0, 'waist' => 0, 'hip' => 2, 'bicep' => 3],
                'required_measurements' => ['bust', 'under_bust', 'waist', 'hip', 'shoulder_width', 'back_length', 'front_length', 'shoulder_to_bust', 'waist_to_hip'],
                'fabric_preferences' => $this->preferences(0.5, 0.35, 90, 320, 0, 20, 0.35, 0.5, 0.35),
            ],
        ];
    }

    /**
     * سلیقه پارچه یک لباس، در همان قالبی که امتیازدهی سازگاری پارچه می‌خواند.
     *
     * @return array<string, array<string, float|int>>
     */
    protected function preferences(
        float $drape,
        float $drapeTolerance,
        int $minGsm,
        int $maxGsm,
        int $minStretch,
        int $maxStretch,
        float $maxTransparency,
        float $stiffness,
        float $stiffnessTolerance,
    ): array {
        return [
            'drape' => ['ideal' => $drape, 'tolerance' => $drapeTolerance],
            'weight_gsm' => ['min' => $minGsm, 'max' => $maxGsm],
            'stretch_weft' => ['min' => $minStretch, 'max' => $maxStretch],
            'transparency' => ['max' => $maxTransparency],
            'stiffness' => ['ideal' => $stiffness, 'tolerance' => $stiffnessTolerance],
        ];
    }
}
