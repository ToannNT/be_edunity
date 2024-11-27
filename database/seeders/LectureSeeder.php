<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Lecture;
use App\Models\Section;
use App\Models\User;
use Faker\Factory as Faker;
use getID3;
use setasign\Fpdi\Fpdi;

class LectureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();

        // Lấy tất cả ID từ bảng sections
        $sectionIds = Section::pluck('id')->toArray();

        // Nếu không có dữ liệu trong sections, hiển thị thông báo
        if (empty($sectionIds)) {
            $this->command->info('Không có dữ liệu trong bảng sections.');
            return;
        }

        // Lấy tất cả ID từ bảng users
        $userIds = User::pluck('id')->toArray();

        // Mảng để theo dõi thứ tự order cho từng section_id
        $orderForSection = [];

        // Danh sách URL mẫu
        $videoUrls = [
            'https://edunity.s3.amazonaws.com/category-image/KGHdw454hmIeLk0SJMlHK7NmzbRqxtfxlUR3xHUd.mp4',
        ];
        $pdfUrls = [
            'https://edunity.s3.amazonaws.com/category-image/phWjG4plbyagFvWbWYpqewzXtUodBqummZPSGUsA.pdf',
        ];

        // Tạo 500 lectures
        for ($i = 0; $i < 500; $i++) {
            // Chọn ngẫu nhiên section_id từ mảng sectionIds
            $sectionId = $faker->randomElement($sectionIds);

            // Nếu section_id này chưa có trong mảng orderForSection, khởi tạo giá trị order ban đầu là 1
            if (!isset($orderForSection[$sectionId])) {
                $orderForSection[$sectionId] = 1;
            }

            // Xác định loại nội dung và chọn đường dẫn ngẫu nhiên
            $type = $faker->randomElement(['video', 'file']);
            $contentLink = $type === 'video'
                ? $faker->randomElement($videoUrls)   // Chọn ngẫu nhiên video từ danh sách videoUrls
                : $faker->randomElement($pdfUrls);    // Chọn ngẫu nhiên file PDF từ danh sách pdfUrls

                $duration = $type === 'video'
                ? 37   // Chọn ngẫu nhiên video từ danh sách videoUrls
                : 4;

            // Tạo lecture
            Lecture::create([
                'section_id' => $sectionId,                                  // section_id ngẫu nhiên
                'type' => $type,                                             // Loại bài giảng (video hoặc file)
                'title' => $faker->sentence(4),                              // Tiêu đề với 4 từ ngẫu nhiên
                'content_link' => $contentLink,                              // Đường dẫn nội dung
                'duration' => $duration,                               // Thời lượng dựa theo loại nội dung
                'order' => $orderForSection[$sectionId],                     // Thứ tự tăng dần trong section
                'preview' => $faker->randomElement(['can', 'cant']),         // Trạng thái preview
                'status' => $faker->randomElement(['active']),   // Trạng thái bài giảng
                'deleted_by' => null,                                        // Giá trị mặc định là null
                'created_by' => $faker->randomElement($userIds),             // ID người tạo
                'updated_by' => $faker->optional()->randomElement($userIds), // Người cập nhật hoặc null
            ]);

            // Tăng thứ tự order cho section_id này
            $orderForSection[$sectionId]++;
        }
    }

    /**
     * Lấy thời lượng video bằng getID3
     */
   



}
