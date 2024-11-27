<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Wishlist;
use App\Models\User;
use App\Models\Course;
use Faker\Factory as Faker;

class WishlistSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();

        // Lấy danh sách ID của users và courses
        $userIds = User::pluck('id')->toArray();
        $courseIds = Course::pluck('id')->toArray();

        // Tạo 50 wishlist ngẫu nhiên
        for ($i = 0; $i < 1000; $i++) {
            Wishlist::create([
                'user_id' => $faker->randomElement($userIds),
                'course_id' => $faker->randomElement($courseIds),
                'deleted_by' => $faker->optional()->randomElement($userIds), // Ngẫu nhiên chọn người xóa hoặc null
                'created_by' => $faker->randomElement($userIds), // Ngẫu nhiên chọn người tạo
                'updated_by' => $faker->randomElement($userIds), // Ngẫu nhiên chọn người cập nhật
            ]);
        }
    }
}
