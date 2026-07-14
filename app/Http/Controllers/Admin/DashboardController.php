<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Product;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'products' => Product::count(),
            'orders' => Order::count(),
            'new_leads' => Lead::where('status', 'new')->count(),
            'revenue' => Order::whereIn('status', ['paid', 'processing', 'shipped', 'delivered'])->sum('total'),
        ];

        $recentOrders = Order::latest()->take(5)->get();
        $recentLeads = Lead::latest()->take(5)->get();

        return view('admin.dashboard', compact('stats', 'recentOrders', 'recentLeads'));
    }
}
