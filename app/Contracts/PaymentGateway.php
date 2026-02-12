<?php

namespace App\Contracts;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;

interface PaymentGateway
{
    public function createCheckoutSession(User $user, Plan $plan, string $successUrl, string $cancelUrl): string;

    public function createBillingPortalSession(User $user, string $returnUrl): string;

    /**
     * Returns a normalized payload or null if the event should be ignored.
     *
     * Expected keys: type, provider_subscription_id, provider_customer_id, status,
     * plan_slug, current_period_end, cancel_at_period_end, customer_email.
     */
    public function parseWebhook(Request $request): ?array;
}
