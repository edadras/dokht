<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\GeneratorRegistry;
use InvalidArgumentException;

/**
 * خانواده‌ای که روی درفت‌های *موجود* سوار می‌شود.
 *
 * خیلی از مدل‌های یک کاتالوگ واقعی، درفتِ تازه نیستند: «دامنِ راستهٔ میدی» و
 * «دامنِ راستهٔ کوتاه» یک درفت‌اند با یک عدد فرق. تا این‌جا برای هرکدام یک کلاسِ
 * بیست‌خطی می‌نوشتیم که فقط defaultParams را عوض می‌کرد؛ این‌جا همان کار در یک
 * ردیفِ جدول انجام می‌شود.
 *
 * هر ردیف می‌گوید روی کدام درفت سوار است (base) و چه چیزهایی را عوض می‌کند
 * (params). باقی — پارامترها، قطعه‌ها، بازرسی — همان درفتِ پایه است، پس مدلِ
 * تازه هیچ ریسکِ هندسیِ تازه‌ای نمی‌آورد.
 *
 * پیش‌فرضِ فرم هم عوض می‌شود، نه فقط ساخت: کاربر که فرمِ «دامنِ راستهٔ میدی» را
 * باز می‌کند باید عدد ۷۲ ببیند، نه ۶۰ را که پایه دارد.
 */
abstract class CatalogVariantBase extends BaseGenerator implements VariantAware
{
    use HasVariants;

    public function label(): string
    {
        return (string) ($this->spec()['title'] ?? 'مدل');
    }

    public function paramsSchema(): array
    {
        $schema = $this->inner()->paramsSchema();

        foreach ((array) ($this->spec()['params'] ?? []) as $name => $value) {
            if (isset($schema[$name])) {
                $schema[$name]['default'] = $value;
            }
        }

        return $schema;
    }

    public function defaultParams(): array
    {
        $inner = $this->inner();
        $defaults = $inner->defaultParams();
        $own = (array) ($this->spec()['params'] ?? []);

        /*
         * پارامتری که درفتِ پایه نمی‌شناسد، بی‌صدا گم می‌شود — و با آن، یک محورِ
         * کاملِ جدول.
         *
         * این واقعاً پیش آمد: به خانوادهٔ پوشاک بیرونی محورِ «قد» داده شد و
         * کتِ تک اصلاً پارامترِ قد ندارد. سه ردیفِ «کوتاه»، «تا باسن» و «تا ران»
         * ساخته شد که هر سه دقیقاً یک الگو بودند با سه نامِ متفاوت. نه خطایی،
         * نه هشداری — فقط سه ردیفِ بی‌معنا در فهرست.
         *
         * پس این‌جا سخت می‌گیریم: ردیفی که پارامترِ ناشناخته بدهد اصلاً ساخته
         * نمی‌شود و نامِ پارامتر را می‌گوید.
         */
        $unknown = array_diff(array_keys($own), array_keys($defaults));

        if ($unknown !== []) {
            throw new InvalidArgumentException(
                'درفت «'.(string) ($this->spec()['base'] ?? '?').'» پارامتر «'
                    .implode('، ', $unknown).'» را نمی‌شناسد (ردیف '.$this->variantKey().').'
            );
        }

        return array_merge($defaults, $own);
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $pieces = $this->inner()->generate($measurements, $ease, array_merge($this->defaultParams(), $params));

        foreach ($pieces as $index => $piece) {
            $pieces[$index]['meta']['variant'] = [
                'model' => $this->variantKey(),
                'base' => (string) ($this->spec()['base'] ?? ''),
            ];
        }

        return $pieces;
    }

    /** درفتی که این ردیف رویش سوار است. */
    protected function inner(): PatternGenerator
    {
        return GeneratorRegistry::make((string) ($this->spec()['base'] ?? ''));
    }
}
