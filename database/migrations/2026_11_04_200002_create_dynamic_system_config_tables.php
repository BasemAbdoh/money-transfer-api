<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('remittance_companies')) {
            Schema::create('remittance_companies', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('prefix', 10)->default('TRN');
                $table->boolean('is_active')->default(true);
                $table->string('logo_url')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('destinations')) {
            Schema::create('destinations', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('province')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('system_settings')) {
            Schema::create('system_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->string('description')->nullable();
                $table->timestamps();
            });
        }

        $now = now();

        if (DB::table('remittance_companies')->count() === 0) {
            DB::table('remittance_companies')->insert([
                ['name' => 'بنك الكريمي', 'prefix' => 'KRM', 'is_active' => true, 'logo_url' => null, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'بنك الأمل', 'prefix' => 'AML', 'is_active' => true, 'logo_url' => null, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'بنك عدن', 'prefix' => 'ADN', 'is_active' => true, 'logo_url' => null, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'النجم', 'prefix' => 'NJM', 'is_active' => true, 'logo_url' => null, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'دادية', 'prefix' => 'DDA', 'is_active' => true, 'logo_url' => null, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'قطيبي لحظات', 'prefix' => 'QTB', 'is_active' => true, 'logo_url' => null, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'يمن اكسبرس', 'prefix' => 'YEX', 'is_active' => true, 'logo_url' => null, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'الشبكة الموحدة', 'prefix' => 'UNN', 'is_active' => true, 'logo_url' => null, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }

        if (DB::table('destinations')->count() === 0) {
            DB::table('destinations')->insert([
                ['name' => 'صنعاء - المركز الرئيسي', 'province' => 'صنعاء', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'عدن - فرع المنصورة', 'province' => 'عدن', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'تعز - شارع جمال', 'province' => 'تعز', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'حضرموت - المكلا', 'province' => 'حضرموت', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'إب - الدائري', 'province' => 'إب', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'الحديدة - شارع صنعاء', 'province' => 'الحديدة', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'مأرب - المجمع', 'province' => 'مأرب', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'ذمار - الشارع العام', 'province' => 'ذمار', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }

        if (DB::table('system_settings')->count() === 0) {
            DB::table('system_settings')->insert([
                [
                    'key' => 'approval_threshold_amount',
                    'value' => '50000',
                    'description' => 'السقف المالي الذي يتطلب موافقة مشرف أو مدير للحوالة (بالريال)',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'key' => 'max_login_attempts',
                    'value' => '5',
                    'description' => 'الحد الأقصى لمحاولات تسجيل الدخول الفاشلة قبل الحظر المؤقت',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'key' => 'lockout_minutes',
                    'value' => '15',
                    'description' => 'مدة حظر الحساب بالدقائق بعد تجاوز محاولات الدخول المسموحة',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'key' => 'system_maintenance_mode',
                    'value' => '0',
                    'description' => 'وضع الصيانة المؤقت (إيقاف إرسال الحوالات مؤقتاً لجميع المستخدمين)',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('destinations');
        Schema::dropIfExists('remittance_companies');
    }
};

