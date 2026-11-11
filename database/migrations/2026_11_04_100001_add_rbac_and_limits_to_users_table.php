<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            // ربط المستخدم بالفرع
            $table->foreignId('branch_id')->nullable()->constrained('branches')->onDelete('set null');
            // الحد اليومي للحوالات
            $table->decimal('daily_transfer_limit', 15, 2)->default(0)->comment('0 = بدون حد');
            $table->integer('daily_transfer_count_limit')->default(0)->comment('0 = بدون حد');
            // إضافة دور Supervisor
            // role الموجودة: Admin, User - نضيف Supervisor
        });
    }
    public function down(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn(['branch_id', 'daily_transfer_limit', 'daily_transfer_count_limit']);
        });
    }
};
