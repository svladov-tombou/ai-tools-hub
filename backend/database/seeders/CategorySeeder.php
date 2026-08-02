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
        // Keyed on the English slug, which is frozen (ADR-26): it is the wire vocabulary
        // spoken by ToolSeeder, by ?category= and by the frontend filter option values.
        $categories = [
            'code-assistants' => [
                'bg' => 'Асистенти за код',
                'en' => 'Code Assistants',
                'fr' => 'Assistants de code',
            ],
            'image-generation' => [
                'bg' => 'Генериране на изображения',
                'en' => 'Image Generation',
                'fr' => "Génération d'images",
            ],
            'writing' => [
                'bg' => 'Писане и текстове',
                'en' => 'Writing',
                'fr' => 'Rédaction',
            ],
            'data-analytics' => [
                'bg' => 'Данни и анализи',
                'en' => 'Data & Analytics',
                'fr' => 'Données et analyses',
            ],
            'productivity' => [
                'bg' => 'Продуктивност',
                'en' => 'Productivity',
                'fr' => 'Productivité',
            ],
        ];

        foreach ($categories as $slug => $name) {
            // firstOrCreate, NOT updateOrCreate: categories are editable through Settings now,
            // and updateOrCreate would overwrite an admin's renamed category — all three
            // languages at once — on the next db:seed. The open question from ADR-26/27.
            // The cost is that changing a name here no longer reaches an existing database;
            // that is a deliberate trade of seed authority for user data.
            Category::firstOrCreate(['slug' => $slug], ['name' => $name, 'slug' => $slug]);
        }
    }
}
