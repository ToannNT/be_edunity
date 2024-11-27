<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Course;
use App\Models\Section;
use App\Models\User;
use Faker\Factory as Faker;

class SectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();

        // Lấy tất cả ID từ bảng courses
        $courseIds = Course::pluck('id')->toArray();

        // Nếu không có dữ liệu trong courses, hiển thị thông báo
        if (empty($courseIds)) {
            $this->command->info('Không có dữ liệu trong bảng courses.');
            return;
        }

        // Lấy tất cả ID từ bảng users
        $userIds = User::pluck('id')->toArray();

        // Khởi tạo mảng để theo dõi thứ tự order cho từng course_id
        $orderForCourse = [];

        // Tạo 100 section cho các khóa học
        for ($i = 0; $i < 100; $i++) {
            // Chọn ngẫu nhiên course_id từ mảng courseIds
            $courseId = $faker->randomElement($courseIds);

            // Nếu course_id này chưa có trong mảng orderForCourse, khởi tạo giá trị order ban đầu là 1
            if (!isset($orderForCourse[$courseId])) {
                $orderForCourse[$courseId] = 1;
            }

            // Tạo section với thứ tự tăng dần không trùng trong từng khóa học
            Section::create([
                'course_id' => $courseId,
                'title' => $faker->sentence(2),                             // Tên section với 2 từ
                'status' => $faker->randomElement(['active']), // Trạng thái ngẫu nhiên
                'order' => $orderForCourse[$courseId],                     // Thứ tự tăng dần trong course
                'deleted_by' => null,                                      // Giá trị mặc định là null
                'created_by' => $faker->randomElement($userIds),           // Chọn ngẫu nhiên ID từ danh sách user
                'updated_by' => $faker->optional()->randomElement($userIds), // Người cập nhật ngẫu nhiên hoặc null
            ]);

            // Tăng thứ tự order cho course_id này
            $orderForCourse[$courseId]++;
        }
    }
}
