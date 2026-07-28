<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('gender', 16)->default('female'); // female | male | child
            $table->date('birth_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['workshop_id', 'name']);
            $table->index(['workshop_id', 'phone']);
        });

        // دفترچه اندازه: هر مشتری می‌تواند چند مجموعه اندازه داشته باشد (تاریخچه)
        Schema::create('measurement_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('unit', 8)->default('cm');
            $table->string('base_size', 8)->nullable(); // 34 .. 48
            $table->json('values');                     // {height: 170, bust: 92, ...}
            $table->boolean('is_default')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['workshop_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measurement_sets');
        Schema::dropIfExists('customers');
    }
};
