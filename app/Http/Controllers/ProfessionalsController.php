<?php

namespace App\Http\Controllers;

use App\Support\ProfessionalsContent;

class ProfessionalsController extends Controller
{
    public function index()
    {
        return view('pages.professionals', [
            'page' => ProfessionalsContent::all(),
            'featuredProjects' => ProfessionalsContent::featuredProjects(),
        ]);
    }
}
