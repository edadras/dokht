<?php

namespace Database\Seeders;

use App\Models\GarmentType;
use App\Models\PatternPiece;
use App\Models\PatternTemplate;
use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\Generators\PatternGenerator;
use App\Services\Pattern\SeamAllowanceService;
use App\Services\Pattern\SvgRenderer;
use App\Support\Measurements;
use Illuminate\Database\Seeder;

/**
 * کتابخانه الگوهای پایه (سراسری، بدون کارگاه).
 *
 * پارامترهای پیش‌فرض و توضیح پارامترها از خود تولیدکننده خوانده می‌شود تا همیشه با
 * کد هم‌خوان باشد؛ برای هر الگو یک پیش‌نمایش کوچک هم ساخته و ذخیره می‌شود.
 */
class PatternTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $garments = GarmentType::pluck('id', 'code');
        $renderer = new SvgRenderer(new SeamAllowanceService);

        foreach ($this->templates() as $index => $template) {
            $garmentId = $garments[$template['garment']] ?? $garments->first();

            if ($garmentId === null) {
                continue;
            }

            $generator = GeneratorRegistry::make($template['generator']);
            $params = array_merge($generator->defaultParams(), $template['params'] ?? []);

            PatternTemplate::updateOrCreate(
                ['workshop_id' => null, 'code' => $template['code']],
                [
                    'garment_type_id' => $garmentId,
                    'name_fa' => $template['name_fa'],
                    'generator' => $template['generator'],
                    'default_params' => $params,
                    'params_schema' => $generator->paramsSchema(),
                    'description' => $template['description'],
                    'preview_svg' => $this->preview($renderer, $generator, $params, $template['ease'] ?? []),
                    'is_public' => true,
                    'sort' => ($index + 1) * 10,
                ],
            );
        }
    }

    /** @return array<int, array<string, mixed>> */
    protected function templates(): array
    {
        return [
            [
                'code' => 'bodice-block',
                'name_fa' => 'بالاتنه پایه',
                'garment' => 'blouse',
                'generator' => 'bodice_block',
                'ease' => ['bust' => 6, 'waist' => 4, 'hip' => 6],
                'description' => 'بلوک پایه بالاتنه با ساسون سینه و کمر؛ مادر همه مدل‌های بالاتنه است و برای گرفتن قالب تن استفاده می‌شود.',
            ],
            [
                'code' => 'sleeve-basic',
                'name_fa' => 'آستین ساده',
                'garment' => 'blouse',
                'generator' => 'sleeve',
                'ease' => ['bicep' => 4],
                'description' => 'آستین یک‌تکه با سرآستین متناسب با حلقه آستین؛ با یا بدون مچ‌بند.',
            ],
            [
                'code' => 'skirt-a-line',
                'name_fa' => 'دامن A',
                'garment' => 'skirt_gored',
                'generator' => 'skirt_a_line',
                'ease' => ['waist' => 2, 'hip' => 5],
                'description' => 'دامن ساده که از باسن به پایین باز می‌شود؛ آسان‌ترین دامن برای شروع.',
            ],
            [
                'code' => 'skirt-pencil',
                'name_fa' => 'دامن راسته',
                'garment' => 'skirt_straight',
                'generator' => 'skirt_pencil',
                'ease' => ['waist' => 2, 'hip' => 4],
                'description' => 'دامن مدادی با دو ساسون پشت، کمربند و چاک پشت.',
            ],
            [
                'code' => 'pants-straight',
                'name_fa' => 'شلوار راسته',
                'garment' => 'pants',
                'generator' => 'pants_straight',
                'ease' => ['waist' => 2, 'hip' => 6],
                'description' => 'شلوار کلاسیک راسته با منحنی فاق استاندارد و کمربند.',
            ],
            [
                'code' => 'pants-wide-leg',
                'name_fa' => 'شلوار گشاد',
                'garment' => 'pants',
                'generator' => 'pants_wide_leg',
                'ease' => ['waist' => 2, 'hip' => 8],
                'description' => 'شلوار دم‌گشاد با پیلی جلو؛ برای پارچه‌های خوش‌ریزش مناسب است.',
            ],
            [
                'code' => 'shirt-classic',
                'name_fa' => 'پیراهن کلاسیک',
                'garment' => 'shirt',
                'generator' => 'shirt_classic',
                'ease' => ['bust' => 10, 'waist' => 10, 'hip' => 10, 'bicep' => 6],
                'description' => 'پیراهن جلوباز با یوک پشت، یقه، پاتلت، آستین و مچ‌بند.',
            ],
            [
                'code' => 'dress-basic',
                'name_fa' => 'پیراهن یک‌تکه',
                'garment' => 'dress',
                'generator' => 'dress',
                'ease' => ['bust' => 8, 'waist' => 6, 'hip' => 8, 'bicep' => 5],
                'description' => 'پیراهن یک‌تکه با بالاتنه گرفته و دامن باز؛ درز پهلو یک‌سره از زیر بغل تا لبه دامن.',
            ],
            [
                'code' => 'blazer-basic',
                'name_fa' => 'کت',
                'garment' => 'blazer',
                'generator' => 'blazer',
                'ease' => ['bust' => 12, 'waist' => 12, 'hip' => 12, 'bicep' => 8],
                'description' => 'کت با برگردان یقه، آستین دوتکه، سجاف و آستر؛ برای پارچه‌های بدن‌دار.',
            ],
            [
                'code' => 'tshirt-knit',
                'name_fa' => 'تی‌شرت کشی',
                'garment' => 'tshirt',
                'generator' => 'tshirt',
                'ease' => ['bust' => 4, 'waist' => 6, 'hip' => 6, 'bicep' => 3],
                'description' => 'بلوک پارچه کشی بدون ساسون با نوار یقه؛ آزادی منفی هم می‌پذیرد.',
            ],
        ];
    }

    /** پیش‌نمایش کوچک الگو برای نمایش در کتابخانه. */
    protected function preview(SvgRenderer $renderer, PatternGenerator $generator, array $params, array $ease): string
    {
        $pieces = collect($generator->generate(Measurements::fromSize('38'), $ease, $params))
            ->take(3)
            ->map(fn (array $piece) => new PatternPiece($piece));

        return $renderer->renderPieces($pieces, [
            'width' => 260,
            'labels' => false,
            'seam_allowance' => false,
            'gap' => 3,
        ]);
    }
}
