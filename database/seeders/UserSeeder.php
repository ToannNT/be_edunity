<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Faker\Factory as Faker;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();
        $faker->unique($reset = true, $maxRetries = 100000);
        // Tạo 20 người dùng ngẫu nhiên
        for ($i = 0; $i < 20; $i++) {
            User::create([
                'email' => $faker->unique()->safeEmail,
                'password' => Hash::make('password'), // Mật khẩu mặc định là 'password'
                'provider' => 'google',
                'provider_id' => $faker->optional()->uuid,
                'first_name' => $faker->firstName,
                'last_name' => $faker->lastName,
                'avatar' => $faker->unique()->randomElement([
                    'https://randomuser.me/api/portraits/men/' . $faker->numberBetween(1, 99) . '.jpg',
                    'https://randomuser.me/api/portraits/women/' . $faker->numberBetween(1, 99) . '.jpg'
                ]),
                'gender' => $faker->randomElement(['male', 'female', 'unknown']),
                'date_of_birth' => $faker->optional()->date(),
                'email_verified' => $faker->boolean(80), // 80% cơ hội là email đã được xác minh
                'reset_token' => $faker->optional()->sha256,
                'verification_token' => $faker->optional()->sha256,
                'role' => $faker->randomElement(['admin', 'instructor', 'student']),
                'status' => $faker->randomElement(['active', 'inactive']),
                'deleted_by' => null,                              // Giá trị mặc định là null
                'created_by' => null, // Chọn ngẫu nhiên ID từ danh sách user
                'updated_by' => null,
            ]);
        }
    }
}
