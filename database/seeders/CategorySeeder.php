<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Electronics',
                'description' => 'Electronic devices and components',
                'slug' => 'electronics',
            ],
            [
                'name' => 'Office Supplies',
                'description' => 'General office supplies and stationery',
                'slug' => 'office-supplies',
            ],
            [
                'name' => 'Hardware',
                'description' => 'Tools and hardware equipment',
                'slug' => 'hardware',
            ],
            [
                'name' => 'Software',
                'description' => 'Software licenses and digital products',
                'slug' => 'software',
            ],
            [
                'name' => 'Furniture',
                'description' => 'Office and workplace furniture',
                'slug' => 'furniture',
            ],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}
