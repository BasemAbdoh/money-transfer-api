<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('login_attempts')) {
            return;
        }
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('identifier'); // user_number أو email
            $table->string('ip_address', 45)->nullable();
            $table->boolean('success')->default(false);
            $table->timestamp('attempted_at')->useCurrent();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('login_attempts');
    }
};
