<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Quiz;
use App\Models\Section;
use App\Models\User;
use Faker\Factory as Faker;

class QuizSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();

        // Lấy tất cả sections với course_id
        $sections = Section::select('id', 'course_id')->get();

        // Nếu không có dữ liệu trong sections, hiển thị thông báo
        if ($sections->isEmpty()) {
            $this->command->info('Không có dữ liệu trong bảng sections.');
            return;
        }

        // Lấy tất cả ID từ bảng users
        $userIds = User::pluck('id')->toArray();

        // Khởi tạo mảng để theo dõi thứ tự order cho từng section_id
        $orderForSection = [];

        // Tạo 100 quiz cho các sections
        for ($i = 0; $i < 200; $i++) {
            // Chọn ngẫu nhiên một section
            $section = $faker->randomElement($sections);

            // Nếu section_id này chưa có trong mảng orderForSection, khởi tạo giá trị order ban đầu là 1
            if (!isset($orderForSection[$section->id])) {
                $orderForSection[$section->id] = 1;
            }

            // Quyết định chỉ nhập course_id hoặc section_id
            $useCourseId = $faker->boolean;

            Quiz::create([
                'section_id' => $useCourseId ? null : $section->id,      // Chỉ nhập section_id hoặc null
                'title' => $faker->sentence(3),                          // Tên quiz với 3 từ
                'status' => $faker->randomElement(['active']), // Trạng thái ngẫu nhiên
                'order' => $orderForSection[$section->id],                // Thứ tự tăng dần trong section
                'deleted_by' => null,                                    // Giá trị mặc định là null
                'created_by' => $faker->randomElement($userIds),         // Chọn ngẫu nhiên ID từ danh sách user
                'updated_by' => $faker->optional()->randomElement($userIds), // Người cập nhật ngẫu nhiên hoặc null
            ]);

            // Tăng thứ tự order cho section_id này
            $orderForSection[$section->id]++;
        }
    }
}
