<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;
use App\Models\Review;
use App\Models\Course;
use App\Models\User;

class ReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();
        
        // Lấy danh sách id của user và course
        $userIds = User::pluck('id')->toArray(); // Lấy tất cả user_id
        $courseIds = Course::pluck('id')->toArray(); // Lấy tất cả course_id

        // Tạo 50 đánh giá ngẫu nhiên
        for ($i = 0; $i < 500; $i++) {
            Review::create([
                'user_id' => $faker->randomElement($userIds), // Chọn ngẫu nhiên user_id
                'course_id' => $faker->randomElement($courseIds), // Chọn ngẫu nhiên course_id
                'rating' => $faker->numberBetween(1, 5), // Đánh giá từ 1 đến 5
                'comment' => $faker->sentence(10), // Bình luận với 10 từ
                'status' => $faker->randomElement(['active', 'inactive']), // Trạng thái
                'created_by' => $faker->randomElement($userIds), // Chọn ngẫu nhiên user_id cho created_by
                'updated_by' => $faker->randomElement($userIds), // Chọn ngẫu nhiên user_id cho updated_by
            ]);
        }
    }
}
