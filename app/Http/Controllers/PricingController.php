<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\SubscriptionRequest;
use Illuminate\Http\Request;

class PricingController extends Controller
{
    public function index(Request $request)
    {
        $plans = Plan::with('features')
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->get();

        $currentPlan = $request->user()?->currentPlan;
        $pendingRequest = null;
        if ($request->user()) {
            $pendingRequest = SubscriptionRequest::with('plan')
                ->where('user_id', $request->user()->id)
                ->where('status', 'pending')
                ->latest('id')
                ->first();
        }

        return view('pricing.index', [
            'plans' => $plans,
            'currentPlan' => $currentPlan,
            'pendingRequest' => $pendingRequest,
        ]);
    }
}
