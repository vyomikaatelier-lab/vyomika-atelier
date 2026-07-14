<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\ServiceDesign;

class ServiceController extends Controller
{
    public function index()
    {
        $services = Service::where('is_active', true)->orderBy('name')->get();

        return view('services.index', compact('services'));
    }

    public function show(string $slug)
    {
        $service = Service::where('slug', $slug)->where('is_active', true)
            ->with(['designs' => fn ($q) => $q->where('is_active', true)])
            ->firstOrFail();

        return view('services.show', compact('service'));
    }

    public function design(string $serviceSlug, string $designSlug)
    {
        $service = Service::where('slug', $serviceSlug)->where('is_active', true)->firstOrFail();
        $design = ServiceDesign::where('service_id', $service->id)
            ->where('slug', $designSlug)
            ->where('is_active', true)
            ->firstOrFail();

        return view('services.design', compact('service', 'design'));
    }
}
