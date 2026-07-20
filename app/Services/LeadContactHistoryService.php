<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Collection;

class LeadContactHistoryService
{
  /**
   * @return array{
   *   previous_leads: Collection<int, Lead>,
   *   orders: Collection<int, Order>,
   *   customer: User|null,
   *   duplicate_count: int
   * }
   */
  public function forLead(Lead $lead): array
  {
    $email = strtolower(trim($lead->email));
    $phone = $this->normalizePhone($lead->phone);

    $previousLeads = Lead::query()
      ->where('id', '!=', $lead->id)
      ->where(function ($query) use ($email, $phone) {
        if ($email) {
          $query->where('email', $email);
        }
        if ($phone) {
          $query->orWhere('phone', 'like', '%' . substr($phone, -10));
        }
      })
      ->latest()
      ->limit(20)
      ->get();

    $customer = null;
    if ($email) {
      $customer = User::query()->where('is_admin', false)->where('email', $email)->first();
    }
    if (! $customer && $phone) {
      $customer = User::query()
        ->where('is_admin', false)
        ->where('mobile', 'like', '%' . substr($phone, -10))
        ->first();
    }

    $orders = collect();
    if ($customer) {
      $orders = Order::query()->where('user_id', $customer->id)->latest()->limit(10)->get();
    } elseif ($email) {
      $orders = Order::query()->where('customer_email', $email)->latest()->limit(10)->get();
    }

    $duplicateCount = Lead::query()
      ->where('duplicate_of_id', $lead->id)
      ->count();

    return [
      'previous_leads' => $previousLeads,
      'orders' => $orders,
      'customer' => $customer,
      'duplicate_count' => $duplicateCount + (int) $lead->duplicate_count,
    ];
  }

  private function normalizePhone(?string $phone): string
  {
    return preg_replace('/\D/', '', (string) $phone);
  }
}
