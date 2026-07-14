<?php

namespace Database\Seeders;

use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Product;
use App\Models\Project;
use App\Models\Service;
use App\Models\ServiceDesign;
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
            ['name' => 'Coffee Tables', 'slug' => 'coffee-tables'],
            ['name' => 'Corner Tables', 'slug' => 'corner-tables'],
            ['name' => 'Glass Tables', 'slug' => 'glass-tables'],
            ['name' => 'Door Handles', 'slug' => 'door-handles'],
            ['name' => 'Home Decor', 'slug' => 'home-decor'],
        ];

        foreach ($categories as $cat) {
            Category::create([...$cat, 'is_active' => true]);
        }

        $products = [
            ['name' => 'Aurora Glass Coffee Table', 'category' => 'glass-tables', 'price' => 18900, 'featured' => true, 'image' => 'https://images.unsplash.com/photo-1615529182904-896166571fac?w=800&q=80', 'desc' => 'Tempered glass top with brushed metal legs. A statement piece for modern living rooms.'],
            ['name' => 'Nordic Corner Table', 'category' => 'corner-tables', 'price' => 12500, 'featured' => true, 'image' => 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?w=800&q=80', 'desc' => 'Space-saving corner design in warm walnut finish. Perfect for compact spaces and reading nooks.'],
            ['name' => 'Crystal Top Coffee Table', 'category' => 'coffee-tables', 'price' => 22400, 'featured' => true, 'image' => 'https://images.unsplash.com/photo-1532372320572-127d86b32558?w=800&q=80', 'desc' => 'Elegant coffee table with crystal-clear glass surface and geometric metal base.'],
            ['name' => 'Minimal Marble Corner Table', 'category' => 'corner-tables', 'price' => 15800, 'featured' => false, 'image' => 'https://images.unsplash.com/photo-1555041469-a586c61e9bc9?w=800&q=80', 'desc' => 'Italian marble top corner table that adds luxury to any room corner.'],
            ['name' => 'Zen Glass Side Table', 'category' => 'glass-tables', 'price' => 8900, 'featured' => false, 'image' => 'https://images.unsplash.com/photo-1598300042247-d088f8ab3a91?w=800&q=80', 'desc' => 'Compact glass side table with minimalist silhouette. Ideal beside sofas and beds.'],
            ['name' => 'Heritage Wooden Coffee Table', 'category' => 'coffee-tables', 'price' => 19600, 'featured' => true, 'image' => 'https://images.unsplash.com/photo-1551292831-023188e78222?w=800&q=80', 'desc' => 'Solid wood coffee table with hand-finished edges. Timeless craftsmanship for your home.'],
            ['name' => 'Brushed Brass Pull Handle', 'category' => 'door-handles', 'price' => 2400, 'featured' => true, 'image' => 'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?w=800&q=80', 'desc' => 'Slim profile brass pull handle with PVD coating for lasting finish.'],
            ['name' => 'Matte Black Lever Handle', 'category' => 'door-handles', 'price' => 1800, 'featured' => true, 'image' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=800&q=80', 'desc' => 'Contemporary lever handle in matte black PVD finish.'],
            ['name' => 'Rose Gold Flush Pull', 'category' => 'door-handles', 'price' => 3200, 'featured' => false, 'image' => 'https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?w=800&q=80', 'desc' => 'Flush-mounted pull handle in rose gold PVD for sliding doors.'],
            ['name' => 'Stainless Knob Set', 'category' => 'door-handles', 'price' => 1500, 'featured' => false, 'image' => 'https://images.unsplash.com/photo-1600210492486-724fe5c67fb0?w=800&q=80', 'desc' => 'Precision-machined stainless steel knob set with satin finish.'],
        ];

        foreach ($products as $item) {
            $category = Category::where('slug', $item['category'])->first();
            Product::create([
                'category_id' => $category?->id,
                'name' => $item['name'],
                'slug' => Str::slug($item['name']),
                'description' => $item['desc'],
                'price' => $item['price'],
                'stock' => 10,
                'image' => $item['image'],
                'is_featured' => $item['featured'],
                'is_active' => true,
            ]);
        }

        $services = [
            [
                'name' => 'Partitions',
                'slug' => 'partitions',
                'summary' => 'Custom glass and aluminium partitions for offices, homes, and commercial spaces.',
                'content' => '<p>Our partition systems combine structural precision with refined aesthetics. Choose from frameless glass, aluminium-framed, sliding, or folding configurations — each engineered for durability and seamless integration.</p>',
                'image' => 'https://images.unsplash.com/photo-1497366216548-37526070297c?w=1200&q=80',
                'has_calculator' => true,
                'has_designs' => true,
                'lead_form' => 'popup',
                'designs' => [
                    ['name' => 'Frameless Glass Partition', 'slug' => 'frameless-glass', 'description' => 'Clean sightlines with minimal hardware for open, light-filled interiors.'],
                    ['name' => 'Aluminium Frame Partition', 'slug' => 'aluminium-frame', 'description' => 'Robust aluminium profiles with glass infill for commercial-grade performance.'],
                    ['name' => 'Sliding Partition', 'slug' => 'sliding-partition', 'description' => 'Space-saving sliding panels that divide rooms without sacrificing flow.'],
                    ['name' => 'Folding Partition', 'slug' => 'folding-partition', 'description' => 'Accordion-style folding systems for flexible multi-use spaces.'],
                ],
            ],
            [
                'name' => 'Corten Steel Façade',
                'slug' => 'corten-steel-facade',
                'summary' => 'Weathering steel façades that develop a rich patina while protecting your structure.',
                'content' => '<p>Corten steel façades bring raw, architectural character to buildings. We fabricate custom panels, screens, and cladding systems that age beautifully and require minimal maintenance.</p><p>Ideal for residential entrances, commercial exteriors, and landscape features.</p>',
                'image' => 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?w=1200&q=80',
                'has_calculator' => false,
                'has_designs' => false,
                'lead_form' => 'inline',
            ],
            [
                'name' => 'Slim Profile Door System',
                'slug' => 'slim-profile-door-system',
                'summary' => 'Ultra-slim aluminium door frames with premium glass and hardware.',
                'content' => '<p>Our slim profile door systems maximise glass area while maintaining structural integrity. Available as pivot, sliding, and hinged configurations with concealed hardware.</p>',
                'image' => 'https://images.unsplash.com/photo-1600607687644-c7171b42498f?w=1200&q=80',
                'has_calculator' => true,
                'has_designs' => true,
                'lead_form' => 'popup',
                'designs' => [
                    ['name' => 'Minimalist Frame Door', 'slug' => 'minimalist-frame', 'description' => 'Ultra-slim frame with floor-to-ceiling glass for seamless indoor-outdoor flow.'],
                    ['name' => 'Pivot Door System', 'slug' => 'pivot-door', 'description' => 'Statement pivot doors with concealed pivot hardware and premium seals.'],
                    ['name' => 'Sliding Door System', 'slug' => 'sliding-door', 'description' => 'Smooth-gliding slim-track sliding doors for patios and room dividers.'],
                ],
            ],
            [
                'name' => 'Bespoke Metal Furniture',
                'slug' => 'bespoke-metal-furniture',
                'summary' => 'Custom metal furniture crafted to your specifications.',
                'content' => '<p>From console tables to shelving and display units — we design and fabricate bespoke metal furniture with precision welding, powder coating, and PVD finishes.</p>',
                'image' => 'https://images.unsplash.com/photo-1532372320572-127d86b32558?w=1200&q=80',
                'has_calculator' => true,
                'has_designs' => false,
                'lead_form' => 'popup',
            ],
            [
                'name' => 'Main Entrance PVD Doors',
                'slug' => 'main-entrance-pvd-doors',
                'summary' => 'Grand entrance doors with PVD-coated metal finishes.',
                'content' => '<p>Make a lasting first impression with custom main entrance doors. PVD coating ensures scratch resistance and lasting colour — available in brass, black, bronze, and rose gold tones.</p>',
                'image' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=1200&q=80',
                'has_calculator' => true,
                'has_designs' => false,
                'lead_form' => 'popup',
            ],
            [
                'name' => 'Rack Systems, Metal PVD',
                'slug' => 'rack-systems-metal-pvd',
                'summary' => 'Display and storage rack systems in premium metal PVD finishes.',
                'content' => '<p>Modular rack systems for retail, wine storage, and residential display. Multiple design options available on a single bespoke fabrication platform.</p>',
                'image' => 'https://images.unsplash.com/photo-1616486338812-3dadae4b4ace?w=1200&q=80',
                'has_calculator' => true,
                'has_designs' => true,
                'lead_form' => 'popup',
                'designs' => [
                    ['name' => 'Wall-Mounted Display Rack', 'slug' => 'wall-display', 'description' => 'Floating wall racks for art, wine, or retail display.'],
                    ['name' => 'Freestanding Shelf Unit', 'slug' => 'freestanding-shelf', 'description' => 'Modular freestanding shelving in PVD metal finish.'],
                    ['name' => 'Wine Storage Rack', 'slug' => 'wine-rack', 'description' => 'Custom wine rack systems with horizontal bottle storage.'],
                ],
            ],
        ];

        foreach ($services as $data) {
            $designs = $data['designs'] ?? [];
            unset($data['designs']);

            $service = Service::create([...$data, 'is_active' => true, 'rate_per_sqft' => 1800]);

            foreach ($designs as $design) {
                ServiceDesign::create([
                    ...$design,
                    'service_id' => $service->id,
                    'is_active' => true,
                ]);
            }
        }

        $projects = [
            [
                'title' => 'Corporate Office Partitions',
                'slug' => 'corporate-office-partitions',
                'summary' => 'Frameless glass partitions for a 12,000 sq ft corporate headquarters.',
                'location' => 'Mumbai',
                'image' => 'https://images.unsplash.com/photo-1497366216548-37526070297c?w=1200&q=80',
                'gallery' => [
                    'https://images.unsplash.com/photo-1497366754035-f200968a6e72?w=800&q=80',
                    'https://images.unsplash.com/photo-1497215842964-222b430dc094?w=800&q=80',
                ],
                'is_featured' => true,
            ],
            [
                'title' => 'Corten Façade Residence',
                'slug' => 'corten-facade-residence',
                'summary' => 'Weathering steel screen façade for a luxury villa entrance.',
                'location' => 'Pune',
                'image' => 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?w=1200&q=80',
                'gallery' => [
                    'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=800&q=80',
                ],
                'is_featured' => true,
            ],
            [
                'title' => 'Slim Profile Patio Doors',
                'slug' => 'slim-profile-patio-doors',
                'summary' => 'Floor-to-ceiling sliding doors opening to a landscaped terrace.',
                'location' => 'Bangalore',
                'image' => 'https://images.unsplash.com/photo-1600607687644-c7171b42498f?w=1200&q=80',
                'gallery' => [
                    'https://images.unsplash.com/photo-1600210492486-724fe5c67fb0?w=800&q=80',
                ],
                'is_featured' => false,
            ],
            [
                'title' => 'PVD Entrance Door — Villa',
                'slug' => 'pvd-entrance-door-villa',
                'summary' => 'Custom main entrance door in brushed brass PVD finish.',
                'location' => 'Delhi NCR',
                'image' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=1200&q=80',
                'gallery' => [],
                'is_featured' => true,
            ],
        ];

        foreach ($projects as $project) {
            Project::create([
                ...$project,
                'completed_at' => now()->subMonths(rand(2, 18)),
                'is_active' => true,
            ]);
        }

        $posts = [
            [
                'title' => 'Why Corten Steel Is Perfect for Modern Façades',
                'slug' => 'corten-steel-modern-facades',
                'excerpt' => 'Discover how weathering steel adds character and durability to architectural exteriors.',
                'content' => '<p>Corten steel develops a protective rust-like patina that eliminates the need for painting. This makes it an ideal material for façades, screens, and landscape features that must withstand the elements while looking striking.</p><p>At VYOMIKA ATELIER, we fabricate custom Corten panels tailored to your building\'s geometry and design language.</p>',
                'image' => 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?w=1200&q=80',
                'meta_title' => 'Corten Steel Façades — VYOMIKA ATELIER',
                'meta_description' => 'Learn why Corten steel is the material of choice for modern architectural façades.',
            ],
            [
                'title' => 'Glass Partitions: Open Plan Without Compromise',
                'slug' => 'glass-partitions-open-plan',
                'excerpt' => 'How frameless glass partitions create privacy without blocking natural light.',
                'content' => '<p>Modern offices and homes demand flexible spaces. Glass partitions deliver acoustic separation and visual openness in one solution.</p><p>Our frameless systems use minimal hardware and premium toughened glass for a clean, contemporary look.</p>',
                'image' => 'https://images.unsplash.com/photo-1497366216548-37526070297c?w=1200&q=80',
                'meta_title' => 'Glass Partitions Guide — VYOMIKA ATELIER',
                'meta_description' => 'A guide to choosing glass partition systems for offices and homes.',
            ],
            [
                'title' => 'PVD Coating Explained: Durable Metal Finishes',
                'slug' => 'pvd-coating-explained',
                'excerpt' => 'What PVD coating is and why it matters for doors, handles, and metal furniture.',
                'content' => '<p>Physical Vapour Deposition (PVD) creates an ultra-hard, corrosion-resistant surface on metal. Unlike paint, PVD bonds at the molecular level — resulting in finishes that resist scratching, fading, and wear.</p><p>We offer PVD in brass, black, bronze, and rose gold tones across our door and hardware range.</p>',
                'image' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=1200&q=80',
                'meta_title' => 'PVD Coating Guide — VYOMIKA ATELIER',
                'meta_description' => 'Understanding PVD metal coating for doors, handles, and bespoke furniture.',
            ],
        ];

        foreach ($posts as $post) {
            BlogPost::create([
                ...$post,
                'published_at' => now()->subDays(rand(5, 60)),
                'is_active' => true,
            ]);
        }
    }
}
