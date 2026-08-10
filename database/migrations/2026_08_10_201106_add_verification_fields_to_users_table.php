<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('user_number', 6)->unique()->nullable()->after('email');
            
            $table->string('email_otp')->nullable()->after('email_verified_at');
            $table->timestamp('email_otp_expires_at')->nullable()->after('email_otp');
            
            $table->string('phone')->nullable()->after('user_number');
            $table->enum('phone_status', ['pending', 'confirmed', 'rejected'])->default('pending')->after('phone');
            
            $table->boolean('is_active')->default(false)->after('phone_status');
            
            $table->foreignId('parent_user_id')->nullable()->constrained('users')->nullOnDelete()->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['parent_user_id']);
            $table->dropColumn([
                'user_number', 
                'email_otp', 
                'email_otp_expires_at', 
                'phone', 
                'phone_status', 
                'is_active', 
                'parent_user_id'
            ]);
        });
    }
};
