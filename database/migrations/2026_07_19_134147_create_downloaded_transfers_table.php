<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('downloaded_transfers', function (Blueprint $table) {
            $table->id();
            // المفتاح الأجنبي الذي يربط بجدول الحوالات مباشرة
            $table->foreignId('transfer_id')->unique()->constrained('transfers')->onDelete('cascade');
            // المفتاح الأجنبي الذي يربط بالمستخدم الذي استلم الحوالة
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('downloaded_transfers');
    }
};
