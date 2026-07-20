<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Exhibition;
use App\Models\Lead;
use App\Models\MediaFile;
use App\Models\Order;
use App\Models\Product;
use App\Models\Project;
use App\Models\User;
use App\Support\LeadStatus;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'products' => Product::count(),
            'categories' => Category::count(),
            'orders' => Order::count(),
            'projects' => Project::count(),
            'blog_posts' => BlogPost::count(),
            'exhibitions' => Schema::hasTable('exhibitions') ? Exhibition::count() : 0,
            'new_leads' => Lead::where('status', LeadStatus::NEW)->where('enquiry_type', '!=', 'vendor_marketing')->count(),
            'hot_leads' => Lead::where('priority', 'hot')->count(),
            'overdue_followups' => Lead::whereNotNull('next_follow_up_at')->where('next_follow_up_at', '<', now())->count(),
            'vendor_leads' => Lead::where('enquiry_type', 'vendor_marketing')->count(),
            'professional_applications' => Lead::where('type', 'professional_application')->where('status', 'new')->count(),
            'railing_quotes' => Lead::where('type', 'railing_quotation')->where('status', 'new')->count(),
            'customers' => User::where('is_admin', false)->count(),
            'media_files' => Schema::hasTable('media_files') ? MediaFile::count() : 0,
            'revenue' => Order::whereIn('status', ['paid', 'processing', 'shipped', 'delivered'])->sum('total'),
        ];

        $recentOrders = Order::latest()->take(5)->get();
        $recentLeads = Lead::latest()->take(5)->get();

        return view('admin.dashboard', compact('stats', 'recentOrders', 'recentLeads'));
    }
}
