<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1. جدول قواعد العمولات
        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            $table->string('company_type')->nullable()->comment('null = ينطبق على كل الشركات');
            $table->string('currency', 20)->nullable()->comment('null = ينطبق على كل العملات');
            $table->decimal('min_amount', 15, 2)->default(0);
            $table->decimal('max_amount', 15, 2)->nullable()->comment('null = بلا سقف أعلى');
            $table->enum('fee_type', ['fixed', 'percentage'])->default('percentage');
            $table->decimal('fee_value', 10, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. جدول أسعار الصرف
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('currency_code', 10)->unique(); // SAR, USD, YER
            $table->string('currency_name', 50);
            $table->decimal('buy_rate', 15, 4)->default(1);
            $table->decimal('sell_rate', 15, 4)->default(1);
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 3. إضافة حقول العمولة والصافي لجدول الحوالات
        Schema::table('transfers', function (Blueprint $table) {
            $table->decimal('commission_amount', 15, 2)->default(0)->after('amount');
            $table->decimal('net_amount', 15, 2)->default(0)->after('commission_amount');
        });

        // 4. إضافة صندوق الفرع وسقف الائتمان/السيولة الدنيا للفروع
        Schema::table('branches', function (Blueprint $table) {
            $table->decimal('balance', 15, 2)->default(0)->after('city')->comment('رصيد سيولة صندوق الفرع');
            $table->decimal('credit_limit', 15, 2)->default(0)->after('balance')->comment('الحد الأدنى للتنبيه بانخفاض السيولة');
        });

        // بذر بيانات افتراضية لأسعار الصرف وقواعد العمولات
        DB::table('exchange_rates')->insert([
            [
                'currency_code' => 'YER',
                'currency_name' => 'ريال يمني',
                'buy_rate'      => 1.0000,
                'sell_rate'     => 1.0000,
                'is_active'     => true,
                'created_at'    => now(),
                'updated_at'    => now(),
            ],
            [
                'currency_code' => 'SAR',
                'currency_name' => 'ريال سعودي',
                'buy_rate'      => 425.0000,
                'sell_rate'     => 428.0000,
                'is_active'     => true,
                'created_at'    => now(),
                'updated_at'    => now(),
            ],
            [
                'currency_code' => 'USD',
                'currency_name' => 'دولار أمريكي',
                'buy_rate'      => 1620.0000,
                'sell_rate'     => 1635.0000,
                'is_active'     => true,
                'created_at'    => now(),
                'updated_at'    => now(),
            ],
        ]);

        DB::table('commission_rules')->insert([
            [
                'company_type' => null,
                'currency'     => null,
                'min_amount'   => 1,
                'max_amount'   => 100000,
                'fee_type'     => 'percentage',
                'fee_value'    => 1.0000, // 1%
                'is_active'    => true,
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
            [
                'company_type' => null,
                'currency'     => null,
                'min_amount'   => 100000.01,
                'max_amount'   => null,
                'fee_type'     => 'percentage',
                'fee_value'    => 0.5000, // 0.5%
                'is_active'    => true,
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['balance', 'credit_limit']);
        });
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropColumn(['commission_amount', 'net_amount']);
        });
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('commission_rules');
    }
};

