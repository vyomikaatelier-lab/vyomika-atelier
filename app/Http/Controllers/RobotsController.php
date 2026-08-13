<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $sitemap = rtrim((string) config('app.url'), '/').'/sitemap.xml';

        $content = view('robots', compact('sitemap'))->render();

        return response($content, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
