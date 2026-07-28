<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // پارچه‌های کارگاه: بر پایه نوع پارچه، با امکان بازنویسی مشخصات
        Schema::create('fabrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fabric_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('color')->nullable();
            $table->string('color_hex', 9)->nullable();
            $table->string('surface_pattern', 32)->default('plain'); // plain|stripe|plaid|print|textured
            $table->decimal('pattern_repeat_cm', 6, 2)->nullable();  // اندازه تکرار طرح
            $table->decimal('width_cm', 6, 1)->nullable();
            $table->unsignedSmallInteger('gsm')->nullable();
            $table->decimal('price_per_meter', 12, 2)->nullable();
            $table->decimal('stock_meters', 8, 2)->default(0);
            $table->json('profile_overrides')->nullable(); // فقط تفاوت‌ها با نوع پارچه
            $table->string('texture_path')->nullable();
            $table->string('normal_map_path')->nullable();
            $table->string('roughness_map_path')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['workshop_id', 'name']);
        });

        // متعلقات: نخ، دکمه، زیپ، لایی، آستر، کشی
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('kind', 32)->default('other'); // thread|button|zipper|interfacing|lining|elastic|other
            $table->string('spec')->nullable();
            $table->string('unit', 16)->default('عدد');
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('stock', 10, 2)->default(0);
            $table->string('color')->nullable();
            $table->timestamps();
            $table->index(['workshop_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materials');
        Schema::dropIfExists('fabrics');
    }
};
