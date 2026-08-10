<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->string('trans_number')->unique();
            $table->string('account_number');
            $table->string('receiver_name');
            $table->string('receiver_email')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('currency');
            $table->string('company_type');
            $table->string('destination');
            $table->string('created_by');         // اسم المرسل (username)
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('status', ['pending', 'paid'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
