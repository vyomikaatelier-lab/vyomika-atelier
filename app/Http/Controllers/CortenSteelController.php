<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Support\CortenContent;

class CortenSteelController extends Controller
{
    public function show()
    {
        $service = Service::query()
            ->where('slug', 'corten-steel-facade')
            ->where('is_active', true)
            ->first()
            ?? new Service([
                'slug' => 'corten-steel-facade',
                'name' => 'Corten Steel',
                'summary' => data_get(CortenContent::all(), 'hero.subtitle'),
                'image' => data_get(CortenContent::all(), 'hero.image'),
                'meta_title' => CortenContent::metaTitle(),
                'meta_description' => CortenContent::metaDescription(),
            ]);

        return view('services.corten-steel', [
            'service' => $service,
            'page' => CortenContent::all(),
        ]);
    }
}
