<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Quiz;
use App\Models\User;
use App\Models\ProgressQuiz;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class ProgressQuizSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();

        // Lấy tất cả quizzes
        $quizzes = Quiz::select('id')->get();

        // Nếu không có dữ liệu trong quizzes, hiển thị thông báo
        if ($quizzes->isEmpty()) {
            $this->command->info('Không có dữ liệu trong bảng quizzes.');
            return;
        }

        // Lấy tất cả ID từ bảng users
        $userIds = User::pluck('id')->toArray();

        // Tạo dữ liệu progress cho mỗi quiz và user
        foreach ($quizzes as $quiz) {
            // Lấy tổng số câu hỏi của quiz
            $questionsCount = DB::table('questions')->where('quiz_id', $quiz->id)->count();

            // Nếu quiz không có câu hỏi, bỏ qua
            if ($questionsCount === 0) {
                continue;
            }

            // Tạo progress cho một số người dùng ngẫu nhiên
            $assignedUsers = $faker->randomElements($userIds, $faker->numberBetween(1, count($userIds)));

            foreach ($assignedUsers as $userId) {
                // Lấy số câu hỏi đã làm ngẫu nhiên: tổng, tổng -1 hoặc tổng -2
                $questionsDone = $faker->randomElement([
                    $questionsCount,
                    max(0, $questionsCount - 1),
                    max(0, $questionsCount - 2)
                ]);

                // Tính phần trăm hoàn thành dựa trên số câu đã làm
                $percent = $questionsCount > 0 ? ($questionsDone / $questionsCount) * 100 : 0;

                // Tạo bản ghi ProgressQuiz
                ProgressQuiz::create([
                    'user_id' => $userId,
                    'quiz_id' => $quiz->id,
                    'questions_done' => $questionsDone,
                    'percent' => $percent,
                    'status' => $faker->randomElement(['active']),
                    'created_by' => $faker->randomElement($userIds),
                    'updated_by' => $faker->optional()->randomElement($userIds),
                    'deleted_by' => null,
                ]);
            }
        }

        $this->command->info('ProgressQuizSeeder: Dữ liệu seed thành công.');
    }
}
