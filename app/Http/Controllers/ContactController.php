<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function index()
    {
        return view('contact.index');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
        ]);

        $lead = Lead::create([
            ...$validated,
            'type' => 'contact',
            'status' => 'new',
        ]);

        $adminEmail = config('services.admin_email');
        if ($adminEmail) {
            Mail::raw(
                "Contact form: {$lead->subject}\nFrom: {$lead->name} ({$lead->email})\n\n{$lead->message}",
                fn ($message) => $message->to($adminEmail)->subject("Contact: {$lead->subject}")
            );
        }

        return back()->with('success', 'Message sent! We will get back to you shortly.');
    }
}
