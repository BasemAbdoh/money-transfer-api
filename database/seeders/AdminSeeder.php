<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@company.com')],
            [
                'username'          => 'admin',
                'password'          => Hash::make(env('ADMIN_PASSWORD', 'Change@123!')),
                'role'              => 'Admin',
                'balance'           => 100000.00,
                'user_number'       => env('ADMIN_USER_NUMBER', '000001'),
                'email_verified_at' => now(),
                'phone'             => env('ADMIN_PHONE', '771000000'),
                'phone_status'      => 'confirmed',
                'is_active'         => true,
                'parent_user_id'    => null,
            ]
        );
    }
}
