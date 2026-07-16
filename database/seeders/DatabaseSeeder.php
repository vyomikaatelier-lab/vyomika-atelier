<?php

namespace Database\Seeders;

use App\Models\BlogPost;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CatalogSyncSeeder::class);

        if (Project::count() === 0 && BlogPost::count() === 0) {
            $this->seedProjectsAndBlog();
        }

        $this->seedAdminUser();

        $this->call(CmsContentSeeder::class);
    }

    private function seedAdminUser(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@vyomikaatelier.com');
        $user = User::query()->where('email', $email)->first();

        if ($user) {
            if (! $user->is_admin) {
                $user->update(['is_admin' => true]);
            }

            if (filled(env('ADMIN_PASSWORD'))) {
                $user->update(['password' => Hash::make(env('ADMIN_PASSWORD'))]);
            }

            return;
        }

        $password = env('ADMIN_PASSWORD');
        if (! filled($password)) {
            if (app()->environment('local')) {
                $password = 'changeme123';
            } else {
                $this->command?->warn('ADMIN_PASSWORD not set — admin user not created. Set ADMIN_PASSWORD in .env and run db:seed again.');

                return;
            }
        }

        User::create([
            'email' => $email,
            'name' => 'Vyomika Atelier LLP Admin',
            'password' => Hash::make($password),
            'is_admin' => true,
            'is_active' => true,
        ]);
    }

    private function seedProjectsAndBlog(): void
    {
        $projects = require database_path('data/projects-catalog.php');

        foreach ($projects as $project) {
            Project::create([...$project, 'completed_at' => now()->subMonths(rand(2, 24)), 'is_active' => true]);
        }

        $posts = require database_path('data/blog-catalog.php');

        foreach ($posts as $post) {
            $published = ! empty($post['published_at'])
                ? \Carbon\Carbon::parse($post['published_at'])
                : now()->subDays(rand(5, 60));

            BlogPost::create([
                ...$post,
                'published_at' => $published,
                'is_active' => true,
                'status' => 'published',
                'reading_time_minutes' => \App\Support\BlogContent::readingTimeMinutes(
                    $post['content'] ?? '',
                    $post['reading_time_minutes'] ?? null
                ),
            ]);
        }
    }
}
