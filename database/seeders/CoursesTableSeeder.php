<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseLevel;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;


class CoursesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();
        $totalCategories = Category::all()->count();
        for ($i = 0; $i < 10; $i++) {
            Course::create([
                'category_id' => $faker->numberBetween(1, $totalCategories),
                'level_id' => $faker->numberBetween(1, 10),
                'title' => $faker->sentence(3),
                'description' => $faker->paragraph,
                'short_description' => $faker->sentence,
                'thumbnail' => $faker->imageUrl(640, 480, 'education', true, 'Faker'),
                'price' => $faker->randomFloat(2, 100000, 1000000),
                'type_sale' => $faker->randomElement(['percent', 'price']),
                'sale_value' => $faker->randomFloat(2, 0, 100),
                'status' => $faker->randomElement(['active', 'inactive']),
                'created_by' => $faker->numberBetween(1, 10),
                'updated_by' => $faker->numberBetween(1, 10),
            ]);
        }
    }
}
