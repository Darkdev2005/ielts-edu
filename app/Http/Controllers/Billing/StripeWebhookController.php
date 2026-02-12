<?php

namespace App\Http\Controllers\Billing;

use App\Contracts\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;

class StripeWebhookController extends Controller
{
    public function handle(Request $request, PaymentGateway $gateway)
    {
        $payload = $gateway->parseWebhook($request);
        if (!$payload) {
            return response()->json(['status' => 'ignored']);
        }

        $subscriptionId = $payload['provider_subscription_id'] ?? null;
        $customerId = $payload['provider_customer_id'] ?? null;
        $email = $payload['customer_email'] ?? null;
        $planSlug = $payload['plan_slug'] ?? null;
        $status = $payload['status'] ?? null;

        if (!$subscriptionId) {
            return response()->json(['status' => 'ignored']);
        }

        $user = $email ? User::where('email', $email)->first() : null;
        if (!$user && $customerId) {
            $existing = Subscription::where('provider_customer_id', $customerId)->first();
            $user = $existing?->user;
        }
        if (!$user) {
            return response()->json(['status' => 'ignored']);
        }

        $plan = $planSlug ? Plan::where('slug', $planSlug)->first() : null;
        $freePlan = Plan::where('slug', 'free')->first();

        $subscription = Subscription::updateOrCreate(
            ['user_id' => $user->id, 'provider' => 'stripe'],
            [
                'plan_id' => $plan?->id ?? $freePlan?->id,
                'provider_customer_id' => $customerId,
                'provider_subscription_id' => $subscriptionId,
                'status' => $status ?? 'inactive',
                'current_period_end' => $payload['current_period_end'] ?? null,
                'cancel_at_period_end' => $payload['cancel_at_period_end'] ?? false,
            ]
        );

        if (in_array($status, ['active', 'trialing'], true) && $plan) {
            $user->current_plan_id = $plan->id;
        } else {
            $user->current_plan_id = $freePlan?->id;
        }
        $user->save();

        return response()->json(['status' => 'ok', 'subscription_id' => $subscription->id]);
    }
}
