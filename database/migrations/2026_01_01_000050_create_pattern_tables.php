<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patterns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('garment_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pattern_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('measurement_set_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('base_size', 8)->default('38');
            $table->json('measurements')->nullable();      // عکس لحظه‌ای اندازه‌های استفاده‌شده
            $table->json('ease')->nullable();              // آزادی: {bust: 6, waist: 4, hip: 6}
            $table->json('seam_allowances')->nullable();   // {default: 1, hem: 3, neck: 0.7, ...}
            $table->json('params')->nullable();            // پارامترهای تولیدکننده الگو
            $table->json('sewing_relations')->nullable();  // دوخت مجازی: کدام لبه به کدام لبه
            $table->unsignedInteger('version')->default(1);
            $table->text('notes')->nullable();
            $table->boolean('is_published')->default(false); // اشتراک در کتابخانه عمومی
            $table->unsignedInteger('copies_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['workshop_id', 'name']);
            $table->index('is_published');
        });

        Schema::create('pattern_pieces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pattern_id')->constrained()->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('name');
            $table->string('layer', 32)->default('outer'); // outer|lining|interfacing|padding|underlining
            $table->unsignedSmallInteger('cut_quantity')->default(1);
            $table->boolean('on_fold')->default(false);
            $table->boolean('mirror')->default(false);
            $table->json('outline');                      // [{x,y,type:line|curve,cx,cy}, ...]
            $table->json('grainline')->nullable();        // {from:{x,y}, to:{x,y}}
            $table->json('darts')->nullable();
            $table->json('notches')->nullable();
            $table->json('drills')->nullable();
            $table->json('pleats')->nullable();
            $table->json('markers')->nullable();          // خط سینه/کمر/باسن، CF، CB و ...
            $table->json('edge_allowances')->nullable();  // جای دوخت هر لبه
            $table->json('meta')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->index(['pattern_id', 'code']);
        });

        Schema::create('pattern_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pattern_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['pattern_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pattern_versions');
        Schema::dropIfExists('pattern_pieces');
        Schema::dropIfExists('patterns');
    }
};
