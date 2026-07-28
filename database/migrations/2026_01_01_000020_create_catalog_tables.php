<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // بانک پایه پارچه: شناسنامه کامل هر نوع پارچه (سراسری، مشترک بین همه کارگاه‌ها)
        Schema::create('fabric_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name_fa');
            $table->string('name_en')->nullable();
            $table->string('category', 32)->default('natural'); // natural | synthetic | blend
            $table->string('family', 48)->nullable();           // woven | knit | lace | non_woven
            $table->json('fiber_composition')->nullable();      // [{fiber: cotton, percent: 70}, ...]
            $table->json('profile');                            // مشخصات فیزیکی کامل (FabricProfile)
            $table->json('care')->nullable();                   // شست‌وشو، اتو، خشک‌کن
            $table->json('sewing')->nullable();                 // سوزن، کوک، درزبندی پیشنهادی
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['category', 'is_active']);
        });

        // انواع لباس و اجزای آن‌ها
        Schema::create('garment_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name_fa');
            $table->string('name_en')->nullable();
            $table->string('category', 32); // top | bottom | one_piece | outer | formal
            $table->json('parts');          // ['front_bodice', 'back_bodice', ...]
            $table->json('default_ease')->nullable();
            $table->json('required_measurements')->nullable();
            $table->json('fabric_preferences')->nullable(); // معیار سازگاری پارچه
            $table->string('icon', 32)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['category', 'is_active']);
        });

        // کتابخانه الگوهای پایه (بلوک‌های پارامتریک)
        Schema::create('pattern_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('garment_type_id')->constrained()->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('name_fa');
            $table->string('generator', 64);              // کلید کلاس تولیدکننده الگو
            $table->json('default_params')->nullable();
            $table->json('params_schema')->nullable();
            $table->text('description')->nullable();
            $table->text('preview_svg')->nullable();
            $table->boolean('is_public')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['workshop_id', 'code']);
        });

        // موتور قواعد: قواعد به صورت داده ذخیره می‌شوند نه کد
        Schema::create('design_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('name');
            $table->string('scope', 32);      // fabric | pattern | cutting | fit | production
            $table->json('conditions');       // {all: [{field, op, value}], any: [...]}
            $table->json('actions');          // [{type, ...payload}]
            $table->string('severity', 16)->default('suggestion'); // info | suggestion | warning
            $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['workshop_id', 'code']);
            $table->index(['scope', 'is_active']);
        });

        // بخش آموزش: راهنمای برش و دوخت برای هر پارچه یا هر مدل لباس
        Schema::create('guides', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 32)->default('general'); // fabric_type | garment_type | general
            $table->foreignId('fabric_type_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('garment_type_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('summary')->nullable();
            $table->longText('body')->nullable();
            $table->json('tips')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->index(['scope', 'fabric_type_id']);
            $table->index(['scope', 'garment_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guides');
        Schema::dropIfExists('design_rules');
        Schema::dropIfExists('pattern_templates');
        Schema::dropIfExists('garment_types');
        Schema::dropIfExists('fabric_types');
    }
};
