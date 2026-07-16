<?php

namespace App\Http\Controllers;

use App\Support\AboutContent;

class AboutController extends Controller
{
    public function index()
    {
        return view('pages.about', [
            'page' => AboutContent::all(),
        ]);
    }
}
