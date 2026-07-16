<?php

namespace App\Http\Controllers;

use App\Support\RailingsContent;

class RailingsController extends Controller
{
    public function index()
    {
        return view('studio.railings', [
            'page' => RailingsContent::all(),
        ]);
    }
}
