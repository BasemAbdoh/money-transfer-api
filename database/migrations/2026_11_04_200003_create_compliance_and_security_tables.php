<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('blacklisted_entities')) {
            Schema::create('blacklisted_entities', function (Blueprint $table) {
                $table->id();
                $table->enum('type', ['name', 'phone', 'account_number'])->default('name');
                $table->string('value');
                $table->string('reason')->nullable();
                $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('transfer_requests')) {
            Schema::create('transfer_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('transfer_id')->constrained('transfers')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->enum('request_type', ['edit_receiver', 'cancel_refund'])->default('edit_receiver');
                $table->string('new_receiver_name')->nullable();
                $table->text('reason')->nullable();
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_requests');
        Schema::dropIfExists('blacklisted_entities');
    }
};

