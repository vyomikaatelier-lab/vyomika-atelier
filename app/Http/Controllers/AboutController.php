<?php

namespace App\Http\Controllers;

use App\Support\AboutContent;
use App\Support\Seo\StaticPageSeo;

class AboutController extends Controller
{
    public function index()
    {
        return view('pages.about', [
            'page' => AboutContent::all(),
            'pageSeo' => StaticPageSeo::forSlug('about'),
        ]);
    }
}
