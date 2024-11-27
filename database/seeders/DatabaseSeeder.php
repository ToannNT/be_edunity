<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Models\Lecture;
use App\Models\ProgressLecture;
use App\Models\ProgressQuiz;
use App\Models\Section;
use App\Models\Wishlist;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(UserSeeder::class);
        $this->call(CategorySeeder::class);
        $this->call(LanguageSeeder::class);
        $this->call(CourseLevelSeeder::class);
        $this->call(VoucherSeeder::class);
        $this->call(CourseSeeder::class);
        $this->call(SectionSeeder::class);
        $this->call(LectureSeeder::class);
        $this->call(ReviewSeeder::class);
        $this->call(OrderSeeder::class);
        $this->call(OrderItemSeeder::class);
        $this->call(WishlistSeeder::class);
        $this->call(QuizSeeder::class);
        $this->call(QuestionSeeder::class);
        $this->call(ProgressLecture::class);
        $this->call(ProgressQuiz::class);
    }
}
