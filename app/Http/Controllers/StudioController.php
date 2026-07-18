<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\View\View;

class StudioController extends Controller
{
    public function index(): View
    {
        $services = Service::query()
            ->where('is_active', true)
            ->whereIn('slug', array_values(\App\Support\StorefrontRoutes::studioServiceMap()))
            ->orderBy('name')
            ->get();

        return view('studio.index', compact('services'));
    }

    public function show(string $slug)
    {
        if (\App\Support\StorefrontRoutes::isShopCategory($slug)) {
            if ($slug === 'mirror-frames') {
                return redirect()->route('shop.mirror-frames.index', [], 301);
            }

            return redirect()->route('shop.show', $slug, 301);
        }

        $serviceSlug = \App\Support\StorefrontRoutes::serviceSlugForStudioUrl($slug);

        abort_unless($serviceSlug, 404);

        return app(ServiceController::class)->show($serviceSlug);
    }
}
