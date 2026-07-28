<?php

namespace Tests\Unit;

use App\Support\FabricProfile;
use PHPUnit\Framework\TestCase;

class FabricProfileTest extends TestCase
{
    public function test_defaults_cover_every_documented_property(): void
    {
        $defaults = FabricProfile::defaults();

        $this->assertSame(array_keys(FabricProfile::SCHEMA), array_keys($defaults));

        foreach (FabricProfile::SCHEMA as $key => $definition) {
            $this->assertArrayHasKey('label', $definition, $key);
            $this->assertArrayHasKey('group', $definition, $key);
            $this->assertArrayHasKey(
                $definition['group'],
                FabricProfile::GROUPS,
                "گروه ناشناخته برای {$key}"
            );
        }
    }

    public function test_it_normalises_values_by_type(): void
    {
        $profile = FabricProfile::make([
            'drape' => 5,             // نسبت: به ۱ محدود می‌شود
            'stiffness' => -2,        // نسبت: به ۰ محدود می‌شود
            'weight_gsm' => '240',    // رشته عددی
            'stretch_weft' => 500,    // درصد: به بیشینه محدود می‌شود
            'has_nap' => '1',
            'pressing' => 'nonsense', // گزینه نامعتبر: پیش‌فرض
            'unknown' => 'x',
        ]);

        $this->assertSame(1.0, $profile->get('drape'));
        $this->assertSame(0.0, $profile->get('stiffness'));
        $this->assertSame(240.0, $profile->get('weight_gsm'));
        $this->assertSame(200.0, $profile->get('stretch_weft'));
        $this->assertTrue($profile->get('has_nap'));
        $this->assertSame('medium', $profile->get('pressing'));
        $this->assertArrayNotHasKey('unknown', $profile->toArray());
    }

    public function test_overrides_are_merged_over_the_base_profile(): void
    {
        $base = FabricProfile::make(['weight_gsm' => 120, 'drape' => 0.8])->toArray();

        $merged = FabricProfile::merge($base, ['drape' => 0.2, 'stiffness' => null, 'transparency' => '']);

        $this->assertSame(120.0, $merged->get('weight_gsm'), 'مقدار پایه باید حفظ شود');
        $this->assertSame(0.2, $merged->get('drape'), 'بازنویسی باید اعمال شود');
        $this->assertSame(
            FabricProfile::SCHEMA['stiffness']['default'],
            $merged->get('stiffness'),
            'مقدار خالی نباید مقدار پایه را پاک کند'
        );
    }

    public function test_it_describes_fabric_behaviour_in_persian(): void
    {
        $silk = FabricProfile::make(['drape' => 0.92, 'weight_gsm' => 60, 'thickness_mm' => 0.12]);
        $denim = FabricProfile::make(['drape' => 0.12, 'weight_gsm' => 340, 'thickness_mm' => 1.1]);

        $this->assertSame('خیلی لخت', $silk->drapeLabel());
        $this->assertSame('بسیار سبک', $silk->weightLabel());
        $this->assertSame('بسیار نازک', $silk->thicknessLabel());

        $this->assertSame('بسیار سفت و ایستا', $denim->drapeLabel());
        $this->assertSame('سنگین', $denim->weightLabel());
        $this->assertSame('ضخیم', $denim->thicknessLabel());
    }

    public function test_it_recognises_stretchy_and_sheer_fabrics(): void
    {
        $jersey = FabricProfile::make(['stretch_weft' => 45, 'stretch_warp' => 10]);
        $poplin = FabricProfile::make(['stretch_weft' => 2, 'stretch_warp' => 0]);
        $chiffon = FabricProfile::make(['transparency' => 0.7]);

        $this->assertTrue($jersey->isStretchy());
        $this->assertSame(45.0, $jersey->maxStretch());
        $this->assertFalse($poplin->isStretchy());
        $this->assertTrue($chiffon->isSheer());
        $this->assertFalse($poplin->isSheer());
    }

    public function test_difficult_fabrics_score_higher_than_easy_ones(): void
    {
        $chiffon = FabricProfile::make([
            'slippage' => 0.9, 'fraying' => 0.8, 'distortion' => 0.7, 'transparency' => 0.7,
        ]);

        $cotton = FabricProfile::make([
            'slippage' => 0.1, 'fraying' => 0.2, 'distortion' => 0.1, 'transparency' => 0.0,
        ]);

        $this->assertGreaterThan($cotton->difficulty(), $chiffon->difficulty());
        $this->assertLessThanOrEqual(1.0, $chiffon->difficulty());
        $this->assertGreaterThanOrEqual(0.0, $cotton->difficulty());
    }

    public function test_it_converts_the_profile_into_simulation_parameters(): void
    {
        $physics = FabricProfile::make([
            'weight_gsm' => 180,
            'thickness_mm' => 0.4,
            'stiffness' => 0.5,
            'drape' => 0.7,
            'roughness' => 0.4,
            'stretch_weft' => 22,
            'recovery' => 90,
        ])->physics();

        $this->assertSame(0.18, $physics['weight']);
        $this->assertSame(0.22, $physics['stretch_weft']);
        $this->assertSame(0.9, $physics['recovery']);

        // سفت‌تر بودن پارچه باید مقاومت خمشی بیشتری بدهد
        $soft = FabricProfile::make(['stiffness' => 0.05])->physics();
        $stiff = FabricProfile::make(['stiffness' => 0.95])->physics();

        $this->assertGreaterThan($soft['bending'], $stiff['bending']);
        $this->assertGreaterThan($soft['shear'], $stiff['shear']);
    }
}
