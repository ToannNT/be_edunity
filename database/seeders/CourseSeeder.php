<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;
use App\Models\Course;
use App\Models\Language;
use App\Models\Category;
use App\Models\CourseLevel;
use App\Models\User;


class CourseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    protected $thumbnailUrls = [
        // Công nghệ & Lập trình
        'https://images.unsplash.com/photo-1461749280684-dccba630e2f6',  // Coding trên màn hình
        'https://images.unsplash.com/photo-1498050108023-c5249f4df085',  // Laptop với code
        'https://images.unsplash.com/photo-1537432376769-00f5c2f4c8d2',  // THiết kế web
        'https://images.unsplash.com/photo-1607799279861-4dd421887fb3',  // Mobile Development
        'https://images.unsplash.com/photo-1555949963-aa79dcee981c',     // Programming setup
        'https://images.unsplash.com/photo-1517694712202-14dd9538aa97',  // Coding on laptop
        
        // Kinh doanh & Marketing
        'https://images.unsplash.com/photo-1460925895917-afdab827c52f',  // Business meeting
        'https://images.unsplash.com/photo-1552664730-d307ca884978',     // Marketing strategy
        'https://images.unsplash.com/photo-1542744173-8e7e53415bb0',     // Team discussion
        'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40',  // Business analysis
        
        // Thiết kế & Nghệ thuật
        'https://images.unsplash.com/photo-1526649661456-89c7ed4d00b8',  // Design work
        'https://images.unsplash.com/photo-1509395062183-67c5ad6faff9',  // Art supplies
        'https://images.unsplash.com/photo-1558655146-d09347e92766',     // Digital art
        'https://images.unsplash.com/photo-1509395176047-4a66953fd231',  // Creative desktop
        
        // Giáo dục & Học tập
        'https://images.unsplash.com/photo-1523050854058-8df90110c9f1',  // Library study
        'https://images.unsplash.com/photo-1513258496099-48168024aec0',  // Learning space
        'https://images.unsplash.com/photo-1501504905252-473c47e087f8',  // Study environment
        'https://images.unsplash.com/photo-1434030216411-0b793f4b4173',  // Workspace
        
        // Khoa học & Công nghệ
        'https://images.unsplash.com/photo-1532094349884-543bc11b234d',  // Science lab
        'https://images.unsplash.com/photo-1517976487492-5750f3195933',  // Technology
        'https://images.unsplash.com/photo-1517976384346-3136801d605d',  // Research
        'https://images.unsplash.com/photo-1516321318423-f06f85e504b3',  // Innovation
        
        // Phát triển cá nhân
        'https://images.unsplash.com/photo-1552581234-26160f608093',     // Personal growth
        'https://images.unsplash.com/photo-1486312338219-ce68d2c6f44d',  // Productivity
        'https://images.unsplash.com/photo-1434626881859-194d67b2b86f',  // Meditation
        'https://images.unsplash.com/photo-1519389950473-47ba0277781c',  // Goal setting
        
        // Ngôn ngữ & Giao tiếp
        'https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8',  // Communication
        'https://images.unsplash.com/photo-1522202176988-66273c2fd55f',  // Group discussion
        'https://images.unsplash.com/photo-1522151272973-9c6e5b8b4830',  // Language learning
        'https://images.unsplash.com/photo-1523240795612-9a054b0db644',  // Study materials
        
        // Sức khỏe & Thể thao
        'https://images.unsplash.com/photo-1517836357463-d25dfeac3438',  // Fitness
        'https://images.unsplash.com/photo-1518611012118-696072aa579a',  // Yoga
        'https://images.unsplash.com/photo-1511632765486-a01980e01a18',  // Health
        'https://images.unsplash.com/photo-1517838277536-f5f99be501cd',  // Wellness
        
        // Âm nhạc & Nghệ thuật biểu diễn
        'https://images.unsplash.com/photo-1511379938547-c1f69419868d',  // Music
        'https://images.unsplash.com/photo-1514320291840-2e0a9bf2a9ae',  // Performance
        'https://images.unsplash.com/photo-1506157786151-b8491531f063',  // Musical instruments
        'https://images.unsplash.com/photo-1517722014278-c256a91a6fba',  // Studio
        
        // Nhiếp ảnh & Video
        'https://images.unsplash.com/photo-1516035069371-29a1b244cc32',  // Photography
        'https://images.unsplash.com/photo-1492691527719-9d1e07e534b4',  // Camera equipment
        'https://images.unsplash.com/photo-1492691527719-9d1e07e534b4',  // Video production
        'https://images.unsplash.com/photo-1496559249665-c7e2874707ea',  // Photo editing
    ];

    public function run(): void
    {
        $faker = Faker::create();

        // Lấy tất cả ID từ bảng categories và course_levels
        $categoryIds = Category::pluck('id')->toArray();
        $languageIds = Language::pluck('id')->toArray();
        $levelIds = CourseLevel::pluck('id')->toArray();

        // Nếu không có dữ liệu trong categories hoặc course_levels, hiển thị thông báo
        if (empty($categoryIds) || empty($levelIds) || empty($languageIds)) {
            $this->command->info('Không có dữ liệu trong bảng categories hoặc course_levels hoặc language.');
            return;
        }
        $userIds = User::pluck('id')->toArray();
        // Tạo 50 khóa học
        for ($i = 0; $i < 50; $i++) {
            Course::create([
                'category_id' => $faker->randomElement($categoryIds), // Chọn ngẫu nhiên category_id từ mảng categoryIds
                'level_id' => $faker->randomElement($levelIds), 
                'language_id' => $faker->randomElement($languageIds),       // Chọn ngẫu nhiên level_id từ mảng levelIds
                'title' => $faker->sentence(3),                      // Tạo tiêu đề với 3 từ
                'description' => $faker->paragraph(),                // Mô tả ngẫu nhiên
                'short_description' => $faker->sentence(6),          // Mô tả ngắn ngẫu nhiên
                'thumbnail' => $faker->randomElement($this->thumbnailUrls), // URL hình ảnh ngẫu nhiên
                'price' => round($faker->numberBetween(1000000, 10000000) / 100000) * 100000,        // Giá ngẫu nhiên từ 100 đến 1000
                'type_sale' => $faker->randomElement(['percent', 'price']), // Loại giảm giá ngẫu nhiên
                'sale_value' => $faker->randomFloat(0, 0, 89),      // Giá trị giảm giá ngẫu nhiên
                'status' => $faker->randomElement(['active', 'inactive']), // Trạng thái ngẫu nhiên
                'deleted_by' => null,                                // Giá trị mặc định là null
                'created_by' => $faker->randomElement($userIds), // Chọn ngẫu nhiên ID từ danh sách user
                'updated_by' => $faker->optional()->randomElement($userIds), // Người cập nhật ngẫu nhiên hoặc null
            ]);
        }
    }
}
