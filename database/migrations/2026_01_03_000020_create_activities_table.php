<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ردّ کار: چه کسی، چه وقت، روی چه چیزی چه کرد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 16);            // created | updated | deleted | restored
            $table->string('subject_type', 64);      // نام کوتاه مدل
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label', 160)->nullable();
            $table->json('changes')->nullable();     // فقط نام فیلدهای عوض‌شده و مقدار پیش و پس
            $table->timestamp('created_at')->nullable();

            $table->index(['workshop_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
