<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workshops', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('phone', 32)->nullable();
            $table->string('city')->nullable();
            $table->string('address')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('currency', 8)->default('IRT');
            $table->decimal('default_seam_allowance', 4, 1)->default(1.0);
            $table->decimal('default_hem_allowance', 4, 1)->default(3.0);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('workshop_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('role', 32)->default('owner')->after('password');
            $table->boolean('is_admin')->default(false)->after('role');
            $table->string('phone', 32)->nullable()->after('is_admin');
            $table->string('avatar_path')->nullable()->after('phone');
            $table->index(['workshop_id', 'role']);
        });

        Schema::create('workshop_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role', 32)->default('tailor');
            $table->string('token', 64)->unique();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->index(['workshop_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_invites');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['workshop_id']);
            $table->dropIndex(['workshop_id', 'role']);
            $table->dropColumn(['workshop_id', 'role', 'is_admin', 'phone', 'avatar_path']);
        });

        Schema::dropIfExists('workshops');
    }
};
