<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $names = [
            'Code Assistants',
            'Image Generation',
            'Writing',
            'Data & Analytics',
            'Productivity',
        ];

        foreach ($names as $name) {
            $slug = Str::slug($name);

            Category::updateOrCreate(['slug' => $slug], ['name' => $name, 'slug' => $slug]);
        }
    }
}
