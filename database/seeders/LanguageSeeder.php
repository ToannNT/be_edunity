<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Language;
use App\Models\User;
use Faker\Factory as Faker;

class LanguageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();

        // Lấy tất cả user IDs hiện có
        $userIds = User::pluck('id')->toArray();
        $randomUserId = function() use ($userIds) {
            return $userIds[array_rand($userIds)];
        };

        // Tạo các bản ghi cụ thể cho Tiếng Việt, Tiếng Anh, Tiếng Pháp
        $languages = [
            [
                'name' => 'Tiếng Việt',
                'description' => 'Ngôn ngữ chính thức của Việt Nam.',
                'status' => 'active',
                'created_by' => $randomUserId(),
                'updated_by' => null,
                'deleted_by' => null,
            ],
            [
                'name' => 'Tiếng Anh',
                'description' => 'Ngôn ngữ quốc tế được sử dụng rộng rãi.',
                'status' => 'active',
                'created_by' => $randomUserId(),
                'updated_by' => null,
                'deleted_by' => null,
            ],
            [
                'name' => 'Tiếng Pháp',
                'description' => 'Ngôn ngữ phổ biến tại nhiều quốc gia.',
                'status' => 'active',
                'created_by' => $randomUserId(),
                'updated_by' => null,
                'deleted_by' => null,
            ]
        ];

        // Chèn các bản ghi vào bảng languages
        foreach ($languages as $language) {
            Language::create($language);
        }
    }
}
