<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('garment_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('measurement_set_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fabric_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lining_fabric_id')->nullable()->constrained('fabrics')->nullOnDelete();
            $table->foreignId('pattern_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('size', 8)->nullable();
            $table->string('status', 24)->default('draft'); // draft|in_progress|done|archived
            $table->unsignedSmallInteger('step')->default(1);
            $table->json('ease')->nullable();
            $table->json('settings')->nullable();
            $table->json('scorecard')->nullable(); // امتیاز کیفیت طراحی
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['workshop_id', 'status']);
        });

        // نتیجه شبیه‌سازی و تست لباس روی مانکن
        Schema::create('simulations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('pattern_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fabric_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('measurement_set_id')->nullable()->constrained()->nullOnDelete();
            $table->string('pose', 24)->default('stand'); // stand|walk|arm_raise|bend|sit|turn
            $table->json('params')->nullable();           // پارامترهای فیزیکی پارچه
            $table->json('zones')->nullable();            // نقشه فشار/کشش هر ناحیه
            $table->json('warnings')->nullable();
            $table->json('suggestions')->nullable();
            $table->unsignedSmallInteger('fit_score')->nullable();
            $table->timestamps();
            $table->index(['workshop_id', 'project_id']);
        });

        // چیدمان برش روی پارچه
        Schema::create('cutting_layouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('pattern_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fabric_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('fabric_width_cm', 6, 1);
            $table->decimal('required_length_cm', 8, 1)->default(0);
            $table->decimal('waste_percent', 5, 2)->default(0);
            $table->decimal('efficiency', 5, 2)->default(0);
            $table->boolean('respect_nap')->default(false);
            $table->boolean('match_stripes')->default(false);
            $table->boolean('folded')->default(true);
            $table->json('placements')->nullable();
            $table->json('warnings')->nullable();
            $table->timestamps();
            $table->index(['workshop_id', 'project_id']);
        });

        // کارت فنی / Tech Pack
        Schema::create('tech_packs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->json('data');
            $table->timestamps();
            $table->index(['workshop_id', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tech_packs');
        Schema::dropIfExists('cutting_layouts');
        Schema::dropIfExists('simulations');
        Schema::dropIfExists('projects');
    }
};
