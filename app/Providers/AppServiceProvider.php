<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Service;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        View::composer('layouts.app', function ($view) {
            $navServices = collect();
            $navFurnitureCategories = collect();
            $navDoorHandles = null;

            if (Schema::hasTable('services')) {
                $navServices = Service::where('is_active', true)->orderBy('name')->get(['name', 'slug', 'has_designs']);
            }
            if (Schema::hasTable('categories')) {
                $navFurnitureCategories = Category::where('is_active', true)
                    ->whereIn('slug', ['coffee-tables', 'corner-tables', 'glass-tables'])
                    ->orderBy('name')
                    ->get(['name', 'slug']);
                $navDoorHandles = Category::where('slug', 'door-handles')->where('is_active', true)->first();
            }

            $view->with(compact('navServices', 'navFurnitureCategories', 'navDoorHandles'));
        });
    }
}
