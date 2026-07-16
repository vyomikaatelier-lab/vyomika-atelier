<?php

namespace App\Http\Controllers;

use App\Models\CustomerAddress;
use App\Models\Lead;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountDashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $orders = Order::query()
            ->where(function ($q) use ($user) {
                $q->where('customer_email', $user->email);
                if ($user->mobile) {
                    $q->orWhere('customer_phone', 'like', '%' . $user->mobile);
                }
            })
            ->latest()
            ->take(10)
            ->get();

        $leads = Lead::query()
            ->where(function ($q) use ($user) {
                $q->where('email', $user->email);
                if ($user->mobile) {
                    $q->orWhere('phone', 'like', '%' . $user->mobile);
                }
            })
            ->latest()
            ->take(20)
            ->get();

        $enquiries = $leads->whereIn('type', ['contact', 'service_inquiry', 'inquiry']);
        $quotations = $leads->whereIn('type', ['custom_order', 'order_now', 'railing_quotation']);
        $professional = $leads->where('type', 'professional_application')->first();

        $addresses = $user->addresses()->orderByDesc('is_default')->orderBy('label')->get();

        return view('account.dashboard', compact(
            'user',
            'orders',
            'enquiries',
            'quotations',
            'professional',
            'addresses',
        ));
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'whatsapp' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:100',
        ]);

        $user->update($validated);

        return back()->with('success', 'Profile updated.');
    }

    public function storeAddress(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'label' => 'required|string|max:60',
            'first_name' => 'required|string|max:60',
            'last_name' => 'required|string|max:60',
            'name' => 'nullable|string|max:120',
            'phone' => 'required|string|max:20',
            'address_line1' => 'required|string|max:255',
            'company' => 'nullable|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'required|string|max:100',
            'state' => 'nullable|string|max:100',
            'pincode' => 'required|string|max:20',
            'country' => 'required|string|max:100',
            'country_other' => 'nullable|required_if:country,Other|string|max:100',
            'is_default' => 'nullable|boolean',
        ]);

        $name = trim($validated['first_name'] . ' ' . $validated['last_name']);
        $country = $validated['country'] === 'Other'
            ? trim((string) ($validated['country_other'] ?? ''))
            : $validated['country'];
        $addressLine2 = CustomerAddress::encodeLine2($validated['company'] ?? null, $country);

        if ($request->boolean('is_default')) {
            $user->addresses()->update(['is_default' => false]);
        }

        $user->addresses()->create([
            'label' => $validated['label'],
            'name' => $name,
            'phone' => $validated['phone'],
            'address_line1' => $validated['address_line1'],
            'address_line2' => $addressLine2,
            'city' => $validated['city'],
            'state' => $validated['state'],
            'pincode' => $validated['pincode'],
            'is_default' => $request->boolean('is_default') || $user->addresses()->count() === 0,
        ]);

        return back()->with('success', 'Address saved.');
    }

    public function destroyAddress(CustomerAddress $address)
    {
        if ($address->user_id !== Auth::id()) {
            abort(403);
        }

        $address->delete();

        return back()->with('success', 'Address removed.');
    }
}
