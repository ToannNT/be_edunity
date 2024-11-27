<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\OrderItem;
use App\Models\Order;
use App\Models\Course;
use App\Models\User;
use Faker\Factory as Faker;

class OrderItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();

        // Lấy danh sách ID của orders, courses và users
        $orderIds = Order::pluck('id')->toArray();
        $courseIds = Course::pluck('id')->toArray();
        $userIds = User::pluck('id')->toArray();

        // Tạo 50 mục order item ngẫu nhiên
        for ($i = 0; $i < 100; $i++) {
            OrderItem::create([
                'order_id' => $faker->randomElement($orderIds),
                'course_id' => $faker->randomElement($courseIds),
                'price' => $faker->randomFloat(2, 10, 500), // Giá ngẫu nhiên từ 10 đến 500
                'status' => $faker->randomElement(['active']),
                // 'deleted_by' => $faker->optional()->randomElement($userIds), // Ngẫu nhiên chọn người xóa hoặc null
                'created_by' => $faker->randomElement($userIds), // Ngẫu nhiên chọn người tạo
                'updated_by' => $faker->randomElement($userIds), // Ngẫu nhiên chọn người cập nhật
            ]);
        }
    }
}
