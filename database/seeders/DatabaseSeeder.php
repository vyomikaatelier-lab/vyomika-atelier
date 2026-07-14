<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Admin',
            'email' => 'admin@vyomikaatelier.com',
            'password' => Hash::make('changeme123'),
            'is_admin' => true,
        ]);

        $categories = [
            ['name' => 'Ready to Wear', 'slug' => 'ready-to-wear'],
            ['name' => 'Occasion Wear', 'slug' => 'occasion-wear'],
            ['name' => 'Accessories', 'slug' => 'accessories'],
        ];

        foreach ($categories as $cat) {
            Category::create([...$cat, 'is_active' => true]);
        }

        $products = [
            ['name' => 'Ivory Silk Kurta', 'category' => 'ready-to-wear', 'price' => 8900, 'featured' => true, 'image' => 'https://images.unsplash.com/photo-1595777457583-95e059d581b8?w=800&q=80'],
            ['name' => 'Handwoven Stole', 'category' => 'accessories', 'price' => 3200, 'featured' => true, 'image' => 'https://images.unsplash.com/photo-1601924994987-69f26d75c78e?w=800&q=80'],
            ['name' => 'Embroidered Lehenga', 'category' => 'occasion-wear', 'price' => 45000, 'featured' => true, 'image' => 'https://images.unsplash.com/photo-1583391734527-7e944b2cbdce?w=800&q=80'],
            ['name' => 'Linen Co-ord Set', 'category' => 'ready-to-wear', 'price' => 6800, 'featured' => false, 'image' => 'https://images.unsplash.com/photo-1496747611176-843222e1e57c?w=800&q=80'],
        ];

        foreach ($products as $item) {
            $category = Category::where('slug', $item['category'])->first();
            Product::create([
                'category_id' => $category?->id,
                'name' => $item['name'],
                'slug' => Str::slug($item['name']),
                'description' => 'A beautifully crafted piece from VYOMIKA ATELIER, made with attention to detail and timeless design.',
                'price' => $item['price'],
                'stock' => 10,
                'image' => $item['image'],
                'is_featured' => $item['featured'],
                'is_active' => true,
            ]);
        }
    }
}
