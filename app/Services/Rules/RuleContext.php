<?php

namespace App\Services\Rules;

use App\Models\Fabric;
use App\Models\FabricType;
use App\Models\GarmentType;
use App\Models\Pattern;
use App\Support\FabricProfile;

/**
 * مجموعه «واقعیت‌ها» که موتور قواعد روی آن‌ها شرط می‌گذارد.
 *
 * واقعیت‌ها به شکل مسیرهای نقطه‌دار نگه داشته می‌شوند تا یک قاعده ذخیره‌شده در
 * دیتابیس بتواند بدون هیچ کدی به آن‌ها اشاره کند؛ مثلاً fabric.stretch_weft یا
 * garment.category. کلید ناشناس هرگز خطا نمی‌دهد و فقط «نامعلوم» شمرده می‌شود.
 */
class RuleContext
{
    /** @var array<string, mixed> */
    protected array $facts;

    public function __construct(array $facts = [])
    {
        $this->facts = static::flatten($facts);
    }

    public static function make(array $facts = []): static
    {
        return new static($facts);
    }

    /** ساخت واقعیت‌ها از روی مدل‌های پروژه. */
    public static function for(
        ?Fabric $fabric = null,
        ?GarmentType $garmentType = null,
        ?Pattern $pattern = null,
        array $extra = [],
    ): static {
        return new static(static::build($fabric, $garmentType, $pattern, $extra));
    }

    /** آرایه تودرتوی واقعیت‌ها (پیش از تخت‌شدن). */
    public static function build(
        ?Fabric $fabric = null,
        ?GarmentType $garmentType = null,
        ?Pattern $pattern = null,
        array $extra = [],
    ): array {
        $facts = array_filter([
            'fabric' => static::fabricFacts($fabric),
            'garment' => static::garmentFacts($garmentType),
            'pattern' => static::patternFacts($pattern),
            'fit' => static::fitFacts($garmentType, $pattern),
        ]);

        return array_replace_recursive($facts, $extra);
    }

    /** @return array<string, mixed> */
    public static function fabricFacts(?Fabric $fabric): array
    {
        if ($fabric === null) {
            return [];
        }

        $type = $fabric->fabricType;

        return array_merge(static::profileFacts($fabric->profile()), [
            'id' => $fabric->id,
            'name' => $fabric->name,
            'color' => $fabric->color,
            'type_code' => $type?->code,
            'type_name' => $type?->name_fa,
            'category' => $type?->category,
            'family' => $type?->family,
            'surface_pattern' => $fabric->surface_pattern,
            'pattern_repeat_cm' => (float) ($fabric->pattern_repeat_cm ?? 0),
            'width_cm' => $fabric->effectiveWidth(),
            'stock_meters' => (float) $fabric->stock_meters,
            'price_per_meter' => (float) ($fabric->price_per_meter ?? 0),
            'is_directional' => $fabric->isDirectional(),
            'needs_pattern_matching' => $fabric->needsPatternMatching(),
        ]);
    }

    /** واقعیت‌های یک نوع پارچه از بانک پایه (وقتی پارچه کارگاه در دست نیست). */
    public static function fabricTypeFacts(?FabricType $type): array
    {
        if ($type === null) {
            return [];
        }

        return array_merge(static::profileFacts($type->fabricProfile()), [
            'type_code' => $type->code,
            'type_name' => $type->name_fa,
            'name' => $type->name_fa,
            'category' => $type->category,
            'family' => $type->family,
            'surface_pattern' => 'plain',
            'width_cm' => 140.0,
            'is_directional' => (bool) $type->fabricProfile()->get('has_nap'),
            'needs_pattern_matching' => false,
        ]);
    }

    /** مشخصات فیزیکی به‌همراه چند مقدار محاسبه‌شده پرکاربرد. */
    public static function profileFacts(FabricProfile $profile): array
    {
        return array_merge($profile->toArray(), [
            'max_stretch' => $profile->maxStretch(),
            'difficulty' => $profile->difficulty(),
            'is_sheer' => $profile->isSheer(),
            'is_stretchy' => $profile->isStretchy(),
            'drape_label' => $profile->drapeLabel(),
            'weight_label' => $profile->weightLabel(),
            'thickness_label' => $profile->thicknessLabel(),
        ]);
    }

    /** @return array<string, mixed> */
    public static function garmentFacts(?GarmentType $garmentType): array
    {
        if ($garmentType === null) {
            return [];
        }

        return [
            'id' => $garmentType->id,
            'code' => $garmentType->code,
            'name' => $garmentType->name_fa,
            'category' => $garmentType->category,
            'parts' => array_values($garmentType->parts ?? []),
            'silhouette' => (string) ($garmentType->fabric_preferences['silhouette'] ?? ''),
        ];
    }

    /** @return array<string, mixed> */
    public static function patternFacts(?Pattern $pattern): array
    {
        if ($pattern === null) {
            return [];
        }

        return [
            'id' => $pattern->id,
            'size' => (string) $pattern->base_size,
            'cut' => (string) ($pattern->params['cut'] ?? 'straight'),
            'version' => (int) $pattern->version,
            'pieces' => array_keys($pattern->seam_allowances ?? []),
        ];
    }

    /** آزادی مؤثر به شکل fit.bust_ease و مانند آن. */
    public static function fitFacts(?GarmentType $garmentType, ?Pattern $pattern): array
    {
        $ease = array_merge($garmentType?->ease() ?? [], $pattern?->ease ?? []);

        $out = [];

        foreach ($ease as $key => $value) {
            if (is_numeric($value)) {
                $out[$key.'_ease'] = (float) $value;
            }
        }

        return $out;
    }

    public function has(string $field): bool
    {
        return array_key_exists($field, $this->facts);
    }

    public function get(string $field, mixed $default = null): mixed
    {
        return $this->facts[$field] ?? $default;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->facts;
    }

    public function with(array $extra): static
    {
        return new static(array_replace($this->facts, static::flatten($extra)));
    }

    /**
     * تبدیل آرایه تودرتو به کلیدهای نقطه‌دار.
     *
     * فهرست‌ها (آرایه با کلید عددی) دست‌نخورده می‌مانند تا عملگرهای in و contains
     * بتوانند روی آن‌ها کار کنند.
     *
     * @return array<string, mixed>
     */
    protected static function flatten(array $values, string $prefix = ''): array
    {
        $out = [];

        foreach ($values as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $out[$path] = $value;

                if (! array_is_list($value)) {
                    $out = array_merge($out, static::flatten($value, $path));
                }

                continue;
            }

            $out[$path] = $value;
        }

        return $out;
    }
}
