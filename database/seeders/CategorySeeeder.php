<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CategorySeeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Category::create([
            'name' => 'Chi phí công cụ dụng cụ',
        ]);

        Category::create([
            'name' => 'Category 2',
            'description' => 'Description 2',
        ]);
    }
}
