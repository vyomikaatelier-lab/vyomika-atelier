<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class LeadController extends Controller
{
    public function create()
    {
        return view('leads.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'subject' => 'nullable|string|max:255',
            'message' => 'required|string|max:5000',
            'budget' => 'nullable|string|max:100',
            'preferred_contact' => 'nullable|in:email,phone,whatsapp',
            'service_slug' => 'nullable|string|max:100',
            'design_slug' => 'nullable|string|max:100',
            'calculated_price' => 'nullable|numeric|min:0',
            'dimensions' => 'nullable|string|max:255',
            'unit_type' => 'nullable|string|max:50',
            'type' => 'nullable|in:custom_order,service_inquiry,order_now',
        ]);

        $type = $validated['type'] ?? ($request->filled('calculated_price') ? 'order_now' : 'custom_order');
        unset($validated['type']);

        $lead = Lead::create([
            ...$validated,
            'type' => $type,
            'status' => 'new',
        ]);

        $adminEmail = config('services.admin_email');
        if ($adminEmail) {
            $details = "New {$lead->typeLabel()} from {$lead->name} ({$lead->email}).";
            if ($lead->service_slug) {
                $details .= "\nService: {$lead->service_slug}";
            }
            if ($lead->calculated_price) {
                $details .= "\nEstimated price: ₹" . number_format($lead->calculated_price, 0);
            }
            $details .= "\n\n{$lead->message}";

            Mail::raw(
                $details,
                fn ($message) => $message->to($adminEmail)->subject("New {$lead->typeLabel()} — VYOMIKA ATELIER")
            );
        }

        return back()->with('success', 'Thank you! We received your request and will contact you soon.');
    }
}
