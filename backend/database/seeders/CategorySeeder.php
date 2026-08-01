<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'code-assistants' => 'Асистенти за код',
            'image-generation' => 'Генериране на изображения',
            'writing' => 'Писане и текстове',
            'data-analytics' => 'Данни и анализи',
            'productivity' => 'Продуктивност',
        ];

        foreach ($categories as $slug => $name) {
            Category::updateOrCreate(['slug' => $slug], ['name' => $name, 'slug' => $slug]);
        }
    }
}
