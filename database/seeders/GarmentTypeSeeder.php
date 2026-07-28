<?php

namespace Database\Seeders;

use App\Models\GarmentType;
use Illuminate\Database\Seeder;

/**
 * فهرست انواع لباس.
 *
 * برای هر لباس اجزای تشکیل‌دهنده، آزادی پیش‌فرض، اندازه‌های لازم و «سلیقه پارچه»
 * (معیارهایی که ماژول سازگاری پارچه با آن امتیاز می‌دهد) ثبت می‌شود.
 *
 * کدها پایدارند: پروژه‌های ساخته‌شده و سرویس سازگاری پارچه به همین کدها ارجاع
 * می‌دهند، پس کد یک ردیف هرگز عوض نمی‌شود؛ فقط ردیف تازه اضافه می‌شود.
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
        return array_merge(
            $this->tops(),
            $this->outerwear(),
            $this->bottoms(),
            $this->onePiece(),
            $this->formal(),
            $this->children(),
        );
    }

    /** بالاتنه‌ها. @return array<int, array<string, mixed>> */
    protected function tops(): array
    {
        return [
            [
                'code' => 'bodice_block',
                'name_fa' => 'بلوک بالاتنه',
                'name_en' => 'Bodice block',
                'category' => 'top',
                'icon' => 'shirt',
                'parts' => ['front_bodice', 'back_bodice'],
                'default_ease' => ['bust' => 6, 'waist' => 4, 'hip' => 6, 'bicep' => 4],
                'required_measurements' => ['bust', 'waist', 'hip', 'shoulder_width', 'neck', 'back_length', 'front_length', 'shoulder_to_bust'],
                'fabric_preferences' => $this->preferences(0.5, 0.35, 100, 260, 0, 20, 0.2, 0.4, 0.35),
            ],
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
                'code' => 'knit_top',
                'name_fa' => 'بلوز کشباف',
                'name_en' => 'Knit top',
                'category' => 'top',
                'icon' => 'shirt',
                'parts' => ['front_bodice', 'back_bodice', 'sleeve', 'collar', 'cuff'],
                'default_ease' => ['bust' => -4, 'waist' => -4, 'hip' => -2, 'bicep' => 0],
                'required_measurements' => ['bust', 'waist', 'hip', 'shoulder_width', 'arm_length', 'neck'],
                'fabric_preferences' => $this->preferences(0.6, 0.3, 130, 300, 30, 110, 0.15, 0.25, 0.3),
            ],
            [
                'code' => 'tunic',
                'name_fa' => 'تونیک',
                'name_en' => 'Tunic',
                'category' => 'top',
                'icon' => 'shirt',
                'parts' => ['front_bodice', 'back_bodice', 'sleeve', 'collar', 'placket', 'facing'],
                'default_ease' => ['bust' => 10, 'waist' => 12, 'hip' => 14, 'bicep' => 6],
                'required_measurements' => ['bust', 'waist', 'hip', 'shoulder_width', 'arm_length', 'back_length', 'waist_to_hip'],
                'fabric_preferences' => $this->preferences(0.7, 0.3, 90, 220, 0, 25, 0.25, 0.3, 0.3),
            ],
            [
                'code' => 'peplum_top',
                'name_fa' => 'بلوز پپلوم‌دار',
                'name_en' => 'Peplum top',
                'category' => 'top',
                'icon' => 'shirt',
                'parts' => ['front_bodice', 'back_bodice', 'skirt_front', 'skirt_back', 'sleeve', 'facing', 'lining'],
                'default_ease' => ['bust' => 5, 'waist' => 3, 'hip' => 8, 'bicep' => 4],
                'required_measurements' => ['bust', 'waist', 'hip', 'shoulder_width', 'back_length', 'front_length', 'waist_to_hip'],
                'fabric_preferences' => $this->preferences(0.6, 0.3, 110, 280, 0, 20, 0.2, 0.45, 0.3),
            ],
            [
                'code' => 'wrap_top',
                'name_fa' => 'بلوز راپ (کراس)',
                'name_en' => 'Wrap top',
                'category' => 'top',
                'icon' => 'shirt',
                'parts' => ['front_bodice', 'back_bodice', 'sleeve', 'facing', 'belt'],
                'default_ease' => ['bust' => 6, 'waist' => 4, 'hip' => 6, 'bicep' => 4],
                'required_measurements' => ['bust', 'waist', 'hip', 'shoulder_width', 'arm_length', 'front_length', 'back_length'],
                'fabric_preferences' => $this->preferences(0.8, 0.22, 70, 190, 5, 45, 0.3, 0.2, 0.28),
            ],
            [
                'code' => 'sweatshirt',
                'name_fa' => 'سویشرت',
                'name_en' => 'Sweatshirt',
                'category' => 'top',
                'icon' => 'shirt',
                'parts' => ['front_bodice', 'back_bodice', 'sleeve', 'collar', 'cuff', 'waistband', 'pocket'],
                'default_ease' => ['bust' => 16, 'waist' => 18, 'hip' => 18, 'bicep' => 10],
                'required_measurements' => ['bust', 'waist', 'hip', 'shoulder_width', 'arm_length', 'wrist', 'back_length'],
                'fabric_preferences' => $this->preferences(0.45, 0.3, 220, 400, 15, 70, 0.05, 0.4, 0.3),
            ],
            [
                'code' => 'hoodie',
                'name_fa' => 'هودی',
                'name_en' => 'Hoodie',
                'category' => 'top',
                'icon' => 'shirt',
                'parts' => ['front_bodice', 'back_bodice', 'sleeve', 'hood', 'cuff', 'waistband', 'pocket'],
                'default_ease' => ['bust' => 18, 'waist' => 20, 'hip' => 20, 'bicep' => 11],
                'required_measurements' => ['bust', 'waist', 'hip', 'shoulder_width', 'arm_length', 'wrist', 'neck', 'back_length'],
                'fabric_preferences' => $this->preferences(0.45, 0.3, 240, 430, 15, 70, 0.05, 0.4, 0.3),
            ],
        ];
    }

    /** لباس‌های رویی. @return array<int, array<string, mixed>> */
    protected function outerwear(): array
    {
        return [
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
                'name_fa' => 'ژاکت و کاردیگان',
                'name_en' => 'Cardigan',
                'category' => 'outer',
                'icon' => 'layers',
                'parts' => ['front_bodice', 'back_bodice', 'sleeve', 'placket', 'facing', 'pocket'],
                'default_ease' => ['bust' => 10, 'waist' => 10, 'hip' => 10, 'bicep' => 7],
                'required_measurements' => ['bust', 'waist', 'shoulder_width', 'arm_length'],
                'fabric_preferences' => $this->preferences(0.5, 0.35, 180, 400, 15, 80, 0.15, 0.4, 0.35),
            ],
            [
                'code' => 'vest',
                'name_fa' => 'جلیقه',
                'name_en' => 'Vest',
                'category' => 'outer',
                'icon' => 'layers',
                'parts' => ['front_bodice', 'back_bodice', 'facing', 'lining', 'interfacing', 'pocket'],
                'default_ease' => ['bust' => 6, 'waist' => 6, 'hip' => 8, 'bicep' => 0],
                'required_measurements' => ['bust', 'waist', 'hip', 'shoulder_width', 'back_length', 'front_length'],
                'fabric_preferences' => $this->preferences(0.35, 0.28, 200, 420, 0, 15, 0.05, 0.65, 0.28),
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
                'code' => 'trench',
                'name_fa' => 'ترنچ‌کت',
                'name_en' => 'Trench coat',
                'category' => 'outer',
                'icon' => 'layers',
                'parts' => ['front_bodice', 'back_bodice', 'sleeve', 'collar', 'lapel', 'facing', 'lining', 'interfacing', 'pocket', 'belt'],
                'default_ease' => ['bust' => 18, 'waist' => 18, 'hip' => 20, 'bicep' => 12],
                'required_measurements' => ['bust', 'waist', 'hip', 'shoulder_width', 'arm_length', 'back_length', 'bicep', 'wrist'],
                'fabric_preferences' => $this->preferences(0.35, 0.25, 220, 440, 0, 15, 0.03, 0.7, 0.25),
            ],
            [
                'code' => 'raincoat',
                'name_fa' => 'بارانی',
                'name_en' => 'Raincoat',
                'category' => 'outer',
                'icon' => 'layers',
                'parts' => ['front_bodice', 'back_bodice', 'sleeve', 'hood', 'collar', 'facing', 'placket', 'pocket'],
                'default_ease' => ['bust' => 22, 'waist' => 24, 'hip' => 24, 'bicep' => 14],
                'required_measurements' => ['bust', 'waist', 'hip', 'shoulder_width', 'arm_length', 'back_length', 'bicep', 'neck'],
                'fabric_preferences' => $this->preferences(0.3, 0.25, 120, 320, 0, 15, 0.03, 0.7, 0.3),
            ],
            [
                'code' => 'bomber',
                'name_fa' => 'کاپشن بمبر',
                'name_en' => 'Bomber jacket',
                'category' => 'outer',
                'icon' => 'layers',
                'parts' => ['front_bodice', 'back_bodice', 'sleeve', 'collar', 'cuff', 'waistband', 'facing', 'lining', 'pocket'],
                'default_ease' => ['bust' => 20, 'waist' => 22, 'hip' => 22, 'bicep' => 13],
                'required_measurements' => ['bust', 'waist', 'hip', 'shoulder_width', 'arm_length', 'wrist', 'bicep'],
                'fabric_preferences' => $this->preferences(0.4, 0.28, 180, 400, 0, 25, 0.05, 0.55, 0.3),
            ],
            [
                'code' => 'abaya',
                'name_fa' => 'عبا',
                'name_en' => 'Abaya',
                'category' => 'outer',
                'icon' => 'layers',
                'parts' => ['front_bodice', 'back_bodice', 'facing', 'belt'],
                'default_ease' => ['bust' => 28, 'waist' => 32, 'hip' => 32, 'bicep' => 16],
                'required_measurements' => ['bust', 'hip', 'shoulder_width', 'height', 'back_length', 'neck'],
                'fabric_preferences' => $this->preferences(0.9, 0.18, 80, 240, 0, 20, 0.08, 0.15, 0.25),
            ],
            [
                'code' => 'kimono_robe',
                'name_fa' => 'کیمونو و روب',
                'name_en' => 'Kimono robe',
                'category' => 'outer',
                'icon' => 'layers',
                'parts' => ['front_bodice', 'back_bodice', 'placket', 'belt', 'pocket'],
                'default_ease' => ['bust' => 22, 'waist' => 26, 'hip' => 26, 'bicep' => 14],
                'required_measurements' => ['bust', 'waist', 'hip', 'shoulder_width', 'back_length', 'front_length'],
                'fabric_preferences' => $this->preferences(0.85, 0.2, 90, 260, 0, 25, 0.2, 0.2, 0.28),
            ],
        ];
    }

    /** پایین‌تنه‌ها. @return array<int, array<string, mixed>> */
    protected function bottoms(): array
    {
        return [
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
                'code' => 'skirt_pleated',
                'name_fa' => 'دامن پیلی‌دار',
                'name_en' => 'Pleated skirt',
                'category' => 'bottom',
                'icon' => 'box',
                'parts' => ['skirt_front', 'skirt_back', 'waistband', 'facing', 'lining'],
                'default_ease' => ['waist' => 2, 'hip' => 6],
                'required_measurements' => ['waist', 'hip', 'waist_to_hip'],
                'fabric_preferences' => $this->preferences(0.4, 0.3, 140, 320, 0, 12, 0.1, 0.6, 0.28),
            ],
            [
                'code' => 'skirt_wrap',
                'name_fa' => 'دامن پاکتی (راپ)',
                'name_en' => 'Wrap skirt',
                'category' => 'bottom',
                'icon' => 'box',
                'parts' => ['skirt_front', 'skirt_back', 'waistband', 'facing', 'belt'],
                'default_ease' => ['waist' => 2, 'hip' => 5],
                'required_measurements' => ['waist', 'hip', 'waist_to_hip'],
                'fabric_preferences' => $this->preferences(0.6, 0.3, 130, 320, 0, 15, 0.08, 0.4, 0.3),
            ],
            [
                'code' => 'skirt_mermaid',
                'name_fa' => 'دامن ماهی',
                'name_en' => 'Mermaid skirt',
                'category' => 'bottom',
                'icon' => 'box',
                'parts' => ['skirt_front', 'skirt_back', 'waistband', 'lining', 'facing'],
                'default_ease' => ['waist' => 1, 'hip' => 3],
                'required_measurements' => ['waist', 'hip', 'waist_to_hip', 'knee', 'height'],
                'fabric_preferences' => $this->preferences(0.55, 0.28, 160, 360, 5, 35, 0.08, 0.45, 0.28),
            ],
            [
                'code' => 'skirt_tiered',
                'name_fa' => 'دامن طبقه‌ای',
                'name_en' => 'Tiered skirt',
                'category' => 'bottom',
                'icon' => 'box',
                'parts' => ['skirt_front', 'skirt_back', 'waistband', 'lining'],
                'default_ease' => ['waist' => 2, 'hip' => 10],
                'required_measurements' => ['waist', 'hip', 'height'],
                'fabric_preferences' => $this->preferences(0.8, 0.22, 70, 200, 0, 20, 0.3, 0.2, 0.28),
            ],
            [
                'code' => 'skirt_asymmetric',
                'name_fa' => 'دامن نامتقارن',
                'name_en' => 'Asymmetric skirt',
                'category' => 'bottom',
                'icon' => 'box',
                'parts' => ['skirt_front', 'skirt_back', 'waistband', 'lining'],
                'default_ease' => ['waist' => 2, 'hip' => 6],
                'required_measurements' => ['waist', 'hip', 'waist_to_hip', 'height'],
                'fabric_preferences' => $this->preferences(0.8, 0.22, 70, 220, 0, 20, 0.3, 0.2, 0.28),
            ],
            [
                'code' => 'skirt_yoke',
                'name_fa' => 'دامن یوک‌دار',
                'name_en' => 'Yoke skirt',
                'category' => 'bottom',
                'icon' => 'box',
                'parts' => ['yoke', 'skirt_front', 'skirt_back', 'waistband', 'lining', 'interfacing'],
                'default_ease' => ['waist' => 2, 'hip' => 5],
                'required_measurements' => ['waist', 'hip', 'waist_to_hip'],
                'fabric_preferences' => $this->preferences(0.55, 0.3, 150, 340, 0, 15, 0.1, 0.45, 0.3),
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
                'code' => 'pants_wide',
                'name_fa' => 'شلوار گشاد',
                'name_en' => 'Wide-leg trousers',
                'category' => 'bottom',
                'icon' => 'box',
                'parts' => ['front_leg', 'back_leg', 'waistband', 'pocket', 'facing'],
                'default_ease' => ['waist' => 2, 'hip' => 10],
                'required_measurements' => ['waist', 'hip', 'inseam', 'outseam', 'thigh', 'waist_to_hip'],
                'fabric_preferences' => $this->preferences(0.7, 0.25, 150, 340, 0, 20, 0.08, 0.35, 0.3),
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
                'code' => 'leggings',
                'name_fa' => 'ساپورت و لگینگ',
                'name_en' => 'Leggings',
                'category' => 'bottom',
                'icon' => 'box',
                'parts' => ['front_leg', 'back_leg', 'waistband'],
                'default_ease' => ['waist' => -4, 'hip' => -4],
                'required_measurements' => ['waist', 'hip', 'inseam', 'thigh', 'knee', 'ankle', 'waist_to_hip'],
                'fabric_preferences' => $this->preferences(0.5, 0.3, 180, 340, 55, 130, 0.03, 0.25, 0.3),
            ],
            [
                'code' => 'culottes',
                'name_fa' => 'کولوت',
                'name_en' => 'Culottes',
                'category' => 'bottom',
                'icon' => 'box',
                'parts' => ['front_leg', 'back_leg', 'waistband', 'pocket', 'facing'],
                'default_ease' => ['waist' => 2, 'hip' => 12],
                'required_measurements' => ['waist', 'hip', 'inseam', 'thigh', 'waist_to_hip'],
                'fabric_preferences' => $this->preferences(0.7, 0.25, 140, 320, 0, 20, 0.08, 0.35, 0.3),
            ],
            [
                'code' => 'joggers',
                'name_fa' => 'شلوار جاگر',
                'name_en' => 'Joggers',
                'category' => 'bottom',
                'icon' => 'box',
                'parts' => ['front_leg', 'back_leg', 'waistband', 'cuff', 'pocket'],
                'default_ease' => ['waist' => 4, 'hip' => 10],
                'required_measurements' => ['waist', 'hip', 'inseam', 'thigh', 'ankle', 'waist_to_hip'],
                'fabric_preferences' => $this->preferences(0.5, 0.3, 200, 400, 15, 70, 0.05, 0.35, 0.3),
            ],
            [
                'code' => 'cargo_pants',
                'name_fa' => 'شلوار کارگو',
                'name_en' => 'Cargo trousers',
                'category' => 'bottom',
                'icon' => 'box',
                'parts' => ['front_leg', 'back_leg', 'waistband', 'pocket', 'facing', 'interfacing'],
                'default_ease' => ['waist' => 3, 'hip' => 10],
                'required_measurements' => ['waist', 'hip', 'inseam', 'thigh', 'knee', 'ankle', 'waist_to_hip'],
                'fabric_preferences' => $this->preferences(0.3, 0.25, 220, 460, 0, 20, 0.03, 0.7, 0.28),
            ],
        ];
    }

    /** لباس‌های یک‌تکه. @return array<int, array<string, mixed>> */
    protected function onePiece(): array
    {
        return [
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
                'code' => 'overall',
                'name_fa' => 'اورال بنددار',
                'name_en' => 'Dungarees',
                'category' => 'one_piece',
                'icon' => 'cube',
                'parts' => ['front_panel', 'back_panel', 'front_leg', 'back_leg', 'waistband', 'belt', 'pocket', 'interfacing'],
                'default_ease' => ['waist' => 6, 'hip' => 12],
                'required_measurements' => ['waist', 'hip', 'inseam', 'thigh', 'shoulder_width', 'waist_to_hip', 'front_length'],
                'fabric_preferences' => $this->preferences(0.3, 0.28, 240, 480, 0, 20, 0.03, 0.7, 0.3),
            ],
            [
                'code' => 'romper',
                'name_fa' => 'رامپر',
                'name_en' => 'Romper',
                'category' => 'one_piece',
                'icon' => 'cube',
                'parts' => ['front_bodice', 'back_bodice', 'front_leg', 'back_leg', 'sleeve', 'waistband', 'facing'],
                'default_ease' => ['bust' => 8, 'waist' => 8, 'hip' => 10, 'bicep' => 5],
                'required_measurements' => ['bust', 'waist', 'hip', 'shoulder_width', 'thigh', 'back_length', 'waist_to_hip'],
                'fabric_preferences' => $this->preferences(0.7, 0.28, 100, 260, 0, 35, 0.15, 0.3, 0.3),
            ],
            [
                'code' => 'kaftan',
                'name_fa' => 'کافتان',
                'name_en' => 'Kaftan',
                'category' => 'one_piece',
                'icon' => 'cube',
                'parts' => ['front_bodice', 'back_bodice', 'facing', 'belt'],
                'default_ease' => ['bust' => 24, 'waist' => 28, 'hip' => 28, 'bicep' => 14],
                'required_measurements' => ['bust', 'hip', 'shoulder_width', 'height', 'neck', 'front_length'],
                'fabric_preferences' => $this->preferences(0.85, 0.2, 70, 220, 0, 20, 0.25, 0.2, 0.28),
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
                'code' => 'maxi_dress',
                'name_fa' => 'پیراهن ماکسی',
                'name_en' => 'Maxi dress',
                'category' => 'one_piece',
                'icon' => 'cube',
                'parts' => ['front_bodice', 'back_bodice', 'sleeve', 'facing', 'lining'],
                'default_ease' => ['bust' => 6, 'waist' => 5, 'hip' => 8, 'bicep' => 5],
                'required_measurements' => ['bust', 'waist', 'hip', 'shoulder_width', 'height', 'back_length', 'front_length', 'waist_to_hip'],
                'fabric_preferences' => $this->preferences(0.8, 0.22, 70, 220, 0, 30, 0.25, 0.25, 0.28),
            ],
        ];
    }

    /** لباس‌های مجلسی. @return array<int, array<string, mixed>> */
    protected function formal(): array
    {
        return [
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
                'code' => 'mermaid_dress',
                'name_fa' => 'پیراهن ماهی',
                'name_en' => 'Mermaid dress',
                'category' => 'formal',
                'icon' => 'sparkles',
                'parts' => ['front_panel', 'back_panel', 'skirt_front', 'skirt_back', 'facing', 'lining', 'interfacing'],
                'default_ease' => ['bust' => 2, 'waist' => 1, 'hip' => 2, 'bicep' => 3],
                'required_measurements' => ['bust', 'under_bust', 'waist', 'hip', 'shoulder_width', 'height', 'knee', 'waist_to_hip', 'shoulder_to_bust'],
                'fabric_preferences' => $this->preferences(0.55, 0.3, 120, 320, 5, 40, 0.2, 0.45, 0.3),
            ],
            [
                'code' => 'corset',
                'name_fa' => 'کرست چندتکه',
                'name_en' => 'Corset bodice',
                'category' => 'formal',
                'icon' => 'sparkles',
                'parts' => ['front_panel', 'back_panel', 'lining', 'interfacing', 'facing'],
                'default_ease' => ['bust' => 0, 'waist' => -2, 'hip' => 2],
                'required_measurements' => ['bust', 'under_bust', 'waist', 'hip', 'back_length', 'front_length', 'shoulder_to_bust'],
                'fabric_preferences' => $this->preferences(0.25, 0.25, 200, 480, 0, 8, 0.03, 0.8, 0.22),
            ],
            [
                'code' => 'bridal_dress',
                'name_fa' => 'لباس عروس',
                'name_en' => 'Bridal gown',
                'category' => 'formal',
                'icon' => 'star',
                'parts' => ['front_panel', 'back_panel', 'skirt_front', 'skirt_back', 'sleeve', 'facing', 'lining', 'interfacing', 'waistband'],
                'default_ease' => ['bust' => 0, 'waist' => 0, 'hip' => 2, 'bicep' => 3],
                'required_measurements' => ['bust', 'under_bust', 'waist', 'hip', 'shoulder_width', 'back_length', 'front_length', 'shoulder_to_bust', 'waist_to_hip'],
                'fabric_preferences' => $this->preferences(0.5, 0.35, 90, 320, 0, 20, 0.35, 0.5, 0.35),
            ],
        ];
    }

    /** لباس کودک. @return array<int, array<string, mixed>> */
    protected function children(): array
    {
        return [
            [
                'code' => 'child_dress',
                'name_fa' => 'پیراهن دخترانه',
                'name_en' => "Girl's dress",
                'category' => 'one_piece',
                'icon' => 'cube',
                'parts' => ['front_bodice', 'back_bodice', 'skirt_front', 'skirt_back', 'sleeve', 'facing', 'belt'],
                'default_ease' => ['bust' => 6, 'waist' => 5, 'hip' => 6, 'bicep' => 4],
                'required_measurements' => ['bust', 'waist', 'hip', 'shoulder_width', 'height', 'back_length', 'front_length'],
                'fabric_preferences' => $this->preferences(0.7, 0.3, 80, 220, 0, 30, 0.15, 0.3, 0.3),
            ],
            [
                'code' => 'child_top',
                'name_fa' => 'بلوز و تی‌شرت بچگانه',
                'name_en' => "Child's top",
                'category' => 'top',
                'icon' => 'shirt',
                'parts' => ['front_bodice', 'back_bodice', 'sleeve', 'collar'],
                'default_ease' => ['bust' => 6, 'waist' => 8, 'hip' => 8, 'bicep' => 4],
                'required_measurements' => ['bust', 'waist', 'shoulder_width', 'arm_length', 'height', 'neck'],
                'fabric_preferences' => $this->preferences(0.55, 0.3, 130, 260, 25, 90, 0.1, 0.3, 0.3),
            ],
            [
                'code' => 'child_hoodie',
                'name_fa' => 'هودی و سویشرت بچگانه',
                'name_en' => "Child's hoodie",
                'category' => 'top',
                'icon' => 'shirt',
                'parts' => ['front_bodice', 'back_bodice', 'sleeve', 'hood', 'cuff', 'waistband', 'pocket'],
                'default_ease' => ['bust' => 12, 'waist' => 14, 'hip' => 14, 'bicep' => 8],
                'required_measurements' => ['bust', 'waist', 'hip', 'shoulder_width', 'arm_length', 'wrist', 'neck', 'height'],
                'fabric_preferences' => $this->preferences(0.45, 0.3, 200, 380, 15, 70, 0.05, 0.4, 0.3),
            ],
            [
                'code' => 'child_pants',
                'name_fa' => 'شلوار بچگانه',
                'name_en' => "Child's trousers",
                'category' => 'bottom',
                'icon' => 'box',
                'parts' => ['front_leg', 'back_leg', 'waistband', 'pocket', 'cuff'],
                'default_ease' => ['waist' => 4, 'hip' => 8],
                'required_measurements' => ['waist', 'hip', 'inseam', 'thigh', 'ankle', 'height', 'waist_to_hip'],
                'fabric_preferences' => $this->preferences(0.45, 0.3, 160, 380, 5, 45, 0.05, 0.5, 0.3),
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
