<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\CourseLevel;
use App\Models\User;
use Faker\Factory as Faker;

class CourseLevelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();

        // Lấy tất cả user IDs hiện có từ bảng users
        $userIds = User::pluck('id')->toArray();
        $randomUserId = function() use ($userIds) {
            return $userIds[array_rand($userIds)];
        };

        // Nếu không có user ID nào, dừng việc seed
        if (empty($userIds)) {
            $this->command->info('No users found in the users table. Please seed the users table first.');
            return;
        }

        // Tạo các bản ghi cụ thể cho các cấp độ khóa học
        $courseLevels = [
            [
                'name' => 'Mới bắt đầu',
                'status' => 'active',
                'created_by' => $randomUserId(),
                'updated_by' => null,
                'deleted_by' => null,
            ],
            [
                'name' => 'Trung cấp',
                'status' => 'active',
                'created_by' => $randomUserId(),
                'updated_by' => null,
                'deleted_by' => null,
            ],
            [
                'name' => 'Nâng cao',
                'status' => 'active',
                'created_by' => $randomUserId(),
                'updated_by' => null,
                'deleted_by' => null,
            ]
        ];

        // Chèn các bản ghi vào bảng course_levels
        foreach ($courseLevels as $level) {
            CourseLevel::create($level);
        }
    }
}
