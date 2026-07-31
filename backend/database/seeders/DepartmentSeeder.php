<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
            'marketing' => 'Маркетинг',
            'accounting' => 'Счетоводство',
            'it' => 'ИТ',
            'projects' => 'Проекти',
            'commercial' => 'Търговски',
            'sales' => 'Продажби',
            'network' => 'Магазинна мрежа',
            'production' => 'Производство',
            'customer_support' => 'Обслужване на клиенти',
            'administration' => 'Администрация',
            'tender' => 'Обществени поръчки',
            'telesales' => 'Продажби по телефона',
        ];

        foreach ($departments as $slug => $name) {
            Department::updateOrCreate(['slug' => $slug], ['name' => $name, 'slug' => $slug]);
        }
    }
}
