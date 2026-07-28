<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * بازارچه الگو: آگهی فروش الگو و سفارش‌های خرید میان کارگاه‌ها.
 *
 * پرداخت بیرون از سامانه انجام می‌شود؛ این جدول‌ها فقط دفترِ روشنِ «چه کسی چه چیزی
 * را از چه کسی سفارش داد و کِی تحویل گرفت» هستند، نه حساب پول.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pattern_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_workshop_id')->constrained('workshops')->cascadeOnDelete();
            $table->foreignId('pattern_id')->constrained()->cascadeOnDelete();
            $table->foreignId('garment_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->string('currency', 16)->default('تومان'); // فقط برچسب نمایشی
            $table->boolean('is_active')->default(true);
            // نگهبان دیتابیسی «هر الگو حداکثر یک آگهی فعال»: با غیرفعال یا حذف‌شدن null می‌شود
            $table->unsignedBigInteger('active_pattern_id')->nullable()->unique();
            $table->json('preview')->nullable();            // چکیده بی‌خطر الگو برای ویترین
            $table->json('tags')->nullable();
            $table->unsignedInteger('sales_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['seller_workshop_id', 'is_active']);
            $table->index('is_active');
            $table->index('garment_type_id');
        });

        Schema::create('pattern_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pattern_listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_workshop_id')->constrained('workshops')->cascadeOnDelete();
            $table->foreignId('buyer_workshop_id')->constrained('workshops')->cascadeOnDelete();
            $table->foreignId('buyer_user_id')->nullable()->constrained('users')->nullOnDelete();
            // نسخه تحویل‌شده در کارگاه خریدار؛ تا وقتی کپی نگرفته null است
            $table->foreignId('delivered_pattern_id')->nullable()->constrained('patterns')->nullOnDelete();
            $table->decimal('price', 12, 2)->default(0);   // قیمت لحظه سفارش، جدا از قیمت امروز آگهی
            $table->string('currency', 16)->default('تومان');
            $table->string('status', 16)->default('pending'); // pending|paid|delivered|cancelled
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('paid_at')->nullable();      // یعنی «فروشنده دریافت وجه را تأیید کرد»
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('seller_note')->nullable();
            $table->text('buyer_note')->nullable();
            $table->timestamps();
            $table->index(['buyer_workshop_id', 'status']);
            $table->index(['seller_workshop_id', 'status']);
            $table->index(['pattern_listing_id', 'buyer_workshop_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pattern_purchases');
        Schema::dropIfExists('pattern_listings');
    }
};
