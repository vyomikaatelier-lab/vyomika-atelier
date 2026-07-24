<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerAdminController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query()
            ->where('is_admin', false)
            ->latest();

        if ($request->filled('q')) {
            $q = $request->string('q');
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('mobile', 'like', "%{$q}%");
            });
        }

        if ($request->filled('account_type')) {
            $query->where('account_type', $request->account_type);
        }

        if ($request->filled('verified')) {
            $request->verified === 'yes'
                ? $query->whereNotNull('phone_verified_at')
                : $query->whereNull('phone_verified_at');
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $customers = $query->paginate(20)->withQueryString();

        return view('admin.customers.index', [
            'customers' => $customers,
            'accountTypes' => User::ACCOUNT_TYPES,
        ]);
    }

    public function show(User $customer)
    {
        abort_if($customer->is_admin, 404);

        $orders = Order::query()->where('customer_email', $customer->email)->latest()->limit(10)->get();
        $leads = Lead::query()->where('email', $customer->email)->latest()->limit(10)->get();
        $applications = Lead::query()
            ->where('email', $customer->email)
            ->where('type', 'professional_application')
            ->latest()
            ->limit(10)
            ->get();

        return view('admin.customers.show', [
            'customer' => $customer,
            'orders' => $orders,
            'leads' => $leads,
            'applications' => $applications,
            'accountTypes' => User::ACCOUNT_TYPES,
        ]);
    }

    public function update(Request $request, User $customer)
    {
        abort_if($customer->is_admin, 404);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($customer->id)],
            'mobile' => ['required', 'string', 'max:20', Rule::unique('users', 'mobile')->ignore($customer->id)],
            'account_type' => ['required', Rule::in(array_keys(User::ACCOUNT_TYPES))],
            'is_active' => 'required|boolean',
        ]);

        $customer->update($validated);

        return back()->with('success', 'Customer account updated.');
    }
}
