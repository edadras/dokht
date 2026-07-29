<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * پرو: آنچه خیاط هنگام امتحان لباس روی تن مشتری دید و اصلاحی که از آن درآمد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fittings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pattern_id')->nullable()->constrained()->nullOnDelete();
            $table->date('fitted_on');
            $table->unsignedTinyInteger('round')->default(1); // پرو اول، دوم…
            $table->text('notes')->nullable();
            $table->json('adjustments')->nullable();   // [{key, value, note}]
            $table->json('applied')->nullable();       // آنچه واقعاً روی الگو نشست
            $table->timestamp('applied_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['workshop_id', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fittings');
    }
};
