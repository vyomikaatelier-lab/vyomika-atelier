<?php

namespace App\Http\Controllers;

use App\Support\RailingsContent;
use App\Support\Seo\LandingPageSeo;

class RailingsController extends Controller
{
    public function index()
    {
        $page = RailingsContent::all();

        return view('studio.railings', [
            'page' => $page,
            'pageSeo' => LandingPageSeo::forSlug('railings'),
        ]);
    }
}
