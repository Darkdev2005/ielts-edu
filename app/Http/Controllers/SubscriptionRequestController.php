<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\SubscriptionRequest;
use Illuminate\Http\Request;

class SubscriptionRequestController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $plan = Plan::where('id', $data['plan_id'])->where('is_active', true)->first();
        if (!$plan) {
            return redirect()->route('pricing')->with('status', __('app.plan_unavailable'));
        }

        $existing = SubscriptionRequest::where('user_id', $user->id)
            ->where('plan_id', $plan->id)
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        if ($existing) {
            return redirect()->route('pricing')->with('status', __('app.subscription_request_exists'));
        }

        SubscriptionRequest::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'amount' => $plan->price_monthly,
            'currency' => 'UZS',
            'status' => 'pending',
            'message' => $data['message'] ?? null,
        ]);

        return redirect()->route('pricing')->with('status', __('app.subscription_request_submitted'));
    }
}
