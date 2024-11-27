<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Faker\Factory as Faker;

class QuestionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();

        // Lấy tất cả ID từ bảng quizzes
        $quizIds = Quiz::pluck('id')->toArray();

        // Nếu không có dữ liệu trong quizzes, hiển thị thông báo
        if (empty($quizIds)) {
            $this->command->info('Không có dữ liệu trong bảng quizzes.');
            return;
        }

        // Lấy tất cả ID từ bảng users
        $userIds = User::pluck('id')->toArray();

        // Khởi tạo mảng để theo dõi thứ tự order cho từng quiz_id
        $orderForQuiz = [];

        // Tạo 200 câu hỏi cho các quizzes
        for ($i = 0; $i < 1000; $i++) {
            // Chọn ngẫu nhiên quiz_id từ mảng quizIds
            $quizId = $faker->randomElement($quizIds);

            // Nếu quiz_id này chưa có trong mảng orderForQuiz, khởi tạo giá trị order ban đầu là 1
            if (!isset($orderForQuiz[$quizId])) {
                $orderForQuiz[$quizId] = 1;
            }

            // Tạo danh sách các tùy chọn
            $options = [
                $faker->word,
                $faker->word,
                $faker->word,
                $faker->word,
            ];

            // Tạo câu hỏi với thứ tự tăng dần không trùng trong từng quiz
            Question::create([
                'quiz_id' => $quizId,
                'question' => $faker->sentence(6),                         // Câu hỏi với 6 từ
                'options' => $options,                        // Các tùy chọn trả lời dạng JSON
                'answer' => $faker->randomElement($options),               // Đáp án là một trong các tùy chọn
                'status' => $faker->randomElement(['active']), // Trạng thái ngẫu nhiên
                'order' => $orderForQuiz[$quizId],                         // Thứ tự tăng dần trong quiz
                'deleted_by' => null,                                      // Giá trị mặc định là null
                'created_by' => $faker->randomElement($userIds),           // Chọn ngẫu nhiên ID từ danh sách user
                'updated_by' => $faker->optional()->randomElement($userIds), // Người cập nhật ngẫu nhiên hoặc null
            ]);

            // Tăng thứ tự order cho quiz_id này
            $orderForQuiz[$quizId]++;
        }
    }
}
