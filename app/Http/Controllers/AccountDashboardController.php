<?php

namespace App\Http\Controllers;

use App\Models\CustomerAddress;
use App\Models\Lead;
use App\Models\Order;
use App\Services\AddressValidationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AccountDashboardController extends Controller
{
    public function __construct(private AddressValidationService $addresses) {}

    public function index()
    {
        $user = Auth::user();

        $orders = Order::query()
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere('customer_email', $user->email);
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

        try {
            $validated = $this->addresses->validate($request->all());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $snapshot = $this->addresses->toSnapshot($validated);
        $addressLine2 = CustomerAddress::encodeLine2($validated['company'] ?? null, $snapshot['country']);

        if ($request->boolean('is_default')) {
            $user->addresses()->update(['is_default' => false]);
        }

        $user->addresses()->create([
            'label' => $validated['label'],
            'name' => $snapshot['full_name'],
            'phone' => $snapshot['phone'],
            'alt_mobile' => $snapshot['alt_mobile'],
            'email' => $snapshot['email'] ?: $user->email,
            'address_line1' => $snapshot['formatted_line'],
            'address_line2' => $addressLine2,
            'house_building' => $snapshot['house_building'],
            'street' => $snapshot['street'],
            'locality' => $snapshot['locality'],
            'landmark' => $snapshot['landmark'],
            'city' => $snapshot['city'],
            'state' => $snapshot['state'],
            'pincode' => $snapshot['pincode'],
            'country' => $snapshot['country'],
            'address_type' => $snapshot['address_type'],
            'floor' => $snapshot['floor'],
            'lift_available' => $snapshot['lift_available'],
            'delivery_instructions' => $snapshot['delivery_instructions'],
            'billing_same_as_shipping' => $snapshot['billing_same_as_shipping'],
            'pin_lookup_status' => $snapshot['pin_lookup_status'],
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
