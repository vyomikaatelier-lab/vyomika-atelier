<?php

namespace App\Http\Controllers;

use App\Support\RailingsContent;
use App\Support\Seo\PageSeo;

class RailingsController extends Controller
{
    public function index()
    {
        $page = RailingsContent::all();

        return view('studio.railings', [
            'page' => $page,
            'pageSeo' => PageSeo::make([
                'title' => $page['meta_title'] ?? 'Railings — Vyomika Atelier',
                'description' => $page['meta_description'] ?? '',
                'canonical' => route('railings.index'),
                'og_image' => data_get($page, 'hero.image'),
                'primary_keyword' => $page['primary_keyword'] ?? null,
            ]),
        ]);
    }
}
