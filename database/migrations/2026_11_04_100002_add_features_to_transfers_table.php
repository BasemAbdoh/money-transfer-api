<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('transfers', function (Blueprint $table) {
            // ملاحظات الحوالة
            $table->text('notes')->nullable()->after('status'); // ملاحظة المرسل
            $table->text('internal_notes')->nullable()->after('notes'); // ملاحظات داخلية (مدير/مشرف)
            // ربط بالفرع
            $table->foreignId('branch_id')->nullable()->constrained('branches')->onDelete('set null');
            // الموافقة المزدوجة
            $table->enum('approval_status', ['not_required', 'pending_approval', 'approved', 'rejected'])
                  ->default('not_required');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            // حالة إلغاء
            $table->text('cancel_reason')->nullable();
        });

        // جدول سجل تعديلات الحوالة
        Schema::create('transfer_history_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained('transfers')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('action'); // مثل: created, approved, rejected, paid, cancelled, notes_updated
            $table->text('description')->nullable();
            $table->json('old_data')->nullable();
            $table->json('new_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('transfer_history_logs');
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['notes', 'internal_notes', 'branch_id', 'approval_status', 'approved_by', 'approved_at', 'cancel_reason']);
        });
    }
};
