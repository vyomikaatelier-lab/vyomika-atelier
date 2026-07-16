<?php

namespace App\Console\Commands;

use App\Http\Controllers\HomeController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

class StorefrontDiagnose extends Command
{
    protected $signature = 'storefront:diagnose';

    protected $description = 'Diagnose public storefront 500 errors (run on server via SSH)';

    public function handle(): int
    {
        $this->info('=== Storefront diagnose ===');

        $files = [
            'app/Support/StorefrontUrl.php',
            'app/Support/SiteContent.php',
            'config/site.php',
            'database/data/projects-catalog.php',
            'resources/views/layouts/store.blade.php',
            'resources/views/home.blade.php',
            'public/css/amerce.css',
            'public/index.php',
        ];

        foreach ($files as $file) {
            $path = base_path($file);
            $this->line((is_file($path) ? '[OK]' : '[MISSING]')." {$file}");
        }

        $routes = [
            'home', 'shop.index', 'shop.show', 'legal.privacy', 'studio.railings',
            'collections.mirror-frames.index', 'professionals.index', 'account',
            'leads.store', 'blog.index',
        ];

        $this->newLine();
        $this->info('Named routes:');
        foreach ($routes as $name) {
            $this->line((Route::has($name) ? '[OK]' : '[MISSING]')." {$name}");
        }

        $this->newLine();
        $this->info('Config:');
        $this->line('site.nav items: '.count(config('site.nav', [])));
        $this->line('legal.footer_links: '.count(config('legal.footer_links', [])));

        if (! class_exists(\App\Support\StorefrontUrl::class)) {
            $this->error('StorefrontUrl class not found — run git pull origin main');

            return 1;
        }

        if (! View::exists('layouts.store') || ! View::exists('home')) {
            $this->error('Store views missing — run git pull origin main');

            return 1;
        }

        $this->newLine();
        $this->info('Rendering homepage...');

        try {
            $view = app(HomeController::class)->index();
            $html = $view->render();
            $this->info('Homepage rendered successfully ('.strlen($html).' bytes)');

            return 0;
        } catch (\Throwable $e) {
            $this->error('Homepage render FAILED');
            $this->error($e->getMessage());
            $this->line($e->getFile().':'.$e->getLine());
            $this->newLine();
            $this->line('Recent log entries:');
            $log = storage_path('logs/laravel.log');
            if (is_readable($log)) {
                $lines = array_slice(file($log, FILE_IGNORE_NEW_LINES) ?: [], -30);
                foreach ($lines as $line) {
                    $this->line($line);
                }
            } else {
                $this->warn('No readable laravel.log');
            }

            return 1;
        }
    }
}
