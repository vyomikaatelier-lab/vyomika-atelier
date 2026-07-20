<?php

namespace App\Http\Controllers;

use App\Models\CatalogueDownload;
use App\Services\LeadProtectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CatalogueRequestController extends Controller
{
  public function __construct(
    private LeadProtectionService $leadProtection,
  ) {}

  public function show()
  {
    return view('catalogue.index');
  }

  public function store(Request $request)
  {
    if ($response = $this->leadProtection->guard($request, 'catalogue_request', false)) {
      return $response;
    }

    $validated = $request->validate([
      'name' => 'required|string|max:255',
      'email' => 'required|email|max:255',
      'phone' => 'required|string|max:20',
      'profession' => 'required|string|max:120',
      'city' => 'required|string|max:120',
      'message' => 'nullable|string|max:2000',
    ]);

    $message = $validated['message'] ?? "Catalogue request from {$validated['profession']} in {$validated['city']}.";

    $result = $this->leadProtection->finalizeLead($request, 'catalogue_request', [
      'name' => $validated['name'],
      'email' => $validated['email'],
      'phone' => $validated['phone'],
      'type' => 'catalogue_request',
      'subject' => 'Catalogue Request',
      'message' => $message,
      'status' => 'unverified',
      'metadata' => [
        'profession' => $validated['profession'],
        'city' => $validated['city'],
        'project_type' => ['catalogue'],
      ],
    ]);

    $lead = $result['lead'];
    $token = Str::random(48);
    $ttl = (int) config('lead_qualification.catalogue.download_ttl_hours', 72);

    CatalogueDownload::create([
      'lead_id' => $lead->id,
      'email' => $lead->email,
      'phone' => $lead->phone,
      'profession' => $validated['profession'],
      'city' => $validated['city'],
      'download_token' => hash('sha256', $token),
      'expires_at' => now()->addHours($ttl),
      'ip_fingerprint' => $lead->ip_fingerprint,
    ]);

    if ($result['notify']) {
      $this->leadProtection->notifyAdmin(
        $lead,
        "Catalogue request: {$validated['profession']} in {$validated['city']}\n{$lead->name} ({$lead->email})",
        'Catalogue Request — Vyomika Atelier'
      );
    }

    return back()
      ->with('success', $result['success_message'])
      ->with('catalogue_download_url', route('catalogue.download', ['token' => $token]));
  }

  public function download(string $token): StreamedResponse
  {
    $hash = hash('sha256', $token);
    $record = CatalogueDownload::query()->where('download_token', $hash)->first();
    abort_unless($record && ! $record->isExpired(), 403);

    $path = config('lead_qualification.catalogue.catalogue_path');
    abort_unless($path && Storage::disk('local')->exists($path), 404);

    $record->update(['downloaded_at' => now()]);

    return Storage::disk('local')->download($path, 'vyomika-atelier-catalogue.pdf');
  }
}
