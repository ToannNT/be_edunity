<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Order;
use App\Models\User;
use App\Models\Voucher;
use Faker\Factory as Faker;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();

        // Lấy danh sách ID của users và vouchers
        $userIds = User::pluck('id')->toArray();
        $voucherIds = Voucher::pluck('id')->toArray();

        // Tạo 50 đơn hàng ngẫu nhiên
        for ($i = 0; $i < 100; $i++) {
            Order::create([
                'user_id' => $faker->randomElement($userIds),
                'voucher_id' => $faker->optional()->randomElement($voucherIds), // Có thể null hoặc chọn voucher ngẫu nhiên
                'order_code' => $faker->unique()->numerify('ORD-#####'),
                'total_price' => $faker->randomFloat(2, 50, 1000), // Giá trị ngẫu nhiên từ 50 đến 1000
                'payment_method' => $faker->randomElement(['credit_card', 'paypal', 'cash']),
                'payment_status' => $faker->randomElement(['paid', 'pending', 'cancelled']),
                'payment_code' => $faker->regexify('[A-Z0-9]{10}'), // Mã thanh toán ngẫu nhiên hoặc null
                'status' => $faker->randomElement(['active']),
                // 'deleted_by' => $faker->optional()->randomElement($userIds), // Ngẫu nhiên chọn người xóa hoặc null
                'created_by' => $faker->randomElement($userIds), // Ngẫu nhiên chọn người tạo
                'updated_by' => $faker->randomElement($userIds), // Ngẫu nhiên chọn người cập nhật
            ]);
        }
    }
}
