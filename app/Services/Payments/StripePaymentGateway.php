<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class StripePaymentGateway implements PaymentGateway
{
    public function createCheckoutSession(User $user, Plan $plan, string $successUrl, string $cancelUrl): string
    {
        if (!class_exists('Stripe\\StripeClient')) {
            throw new \RuntimeException('Stripe SDK is not installed.');
        }

        $priceId = config("subscriptions.plans.{$plan->slug}.stripe_price_id");
        if (!$priceId) {
            throw new \RuntimeException('Missing Stripe price ID for plan.');
        }

        $client = new \Stripe\StripeClient(config('services.stripe.secret'));

        $session = $client->checkout->sessions->create([
            'mode' => 'subscription',
            'customer_email' => $user->email,
            'success_url' => $successUrl . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
            'line_items' => [
                [
                    'price' => $priceId,
                    'quantity' => 1,
                ],
            ],
            'metadata' => [
                'user_id' => $user->id,
                'plan_slug' => $plan->slug,
            ],
        ]);

        return $session->url;
    }

    public function createBillingPortalSession(User $user, string $returnUrl): string
    {
        if (!class_exists('Stripe\\StripeClient')) {
            throw new \RuntimeException('Stripe SDK is not installed.');
        }

        $subscription = $user->subscription;
        if (!$subscription || !$subscription->provider_customer_id) {
            throw new \RuntimeException('No Stripe customer found for this user.');
        }

        $client = new \Stripe\StripeClient(config('services.stripe.secret'));

        $session = $client->billingPortal->sessions->create([
            'customer' => $subscription->provider_customer_id,
            'return_url' => $returnUrl,
        ]);

        return $session->url;
    }

    public function parseWebhook(Request $request): ?array
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret = config('services.stripe.webhook_secret');

        if (class_exists('Stripe\\Webhook') && $secret) {
            try {
                $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $secret);
            } catch (\Throwable $e) {
                return null;
            }
        } else {
            $event = json_decode($payload, true);
        }

        if (!is_array($event) || empty($event['type'])) {
            return null;
        }

        $type = $event['type'];
        $data = $event['data']['object'] ?? [];

        if ($type === 'checkout.session.completed') {
            return [
                'type' => $type,
                'provider_subscription_id' => $data['subscription'] ?? null,
                'provider_customer_id' => $data['customer'] ?? null,
                'status' => 'active',
                'plan_slug' => $data['metadata']['plan_slug'] ?? null,
                'current_period_end' => null,
                'cancel_at_period_end' => false,
                'customer_email' => $data['customer_details']['email'] ?? $data['customer_email'] ?? null,
            ];
        }

        if (Str::startsWith($type, 'customer.subscription')) {
            $priceId = Arr::get($data, 'items.data.0.price.id');
            $planSlug = $this->planSlugFromPriceId($priceId);

            return [
                'type' => $type,
                'provider_subscription_id' => $data['id'] ?? null,
                'provider_customer_id' => $data['customer'] ?? null,
                'status' => $data['status'] ?? null,
                'plan_slug' => $planSlug,
                'current_period_end' => isset($data['current_period_end']) ? \Carbon\Carbon::createFromTimestamp($data['current_period_end']) : null,
                'cancel_at_period_end' => (bool) ($data['cancel_at_period_end'] ?? false),
                'customer_email' => Arr::get($data, 'customer_email'),
            ];
        }

        if ($type === 'invoice.payment_failed') {
            return [
                'type' => $type,
                'provider_subscription_id' => $data['subscription'] ?? null,
                'provider_customer_id' => $data['customer'] ?? null,
                'status' => 'past_due',
                'plan_slug' => null,
                'current_period_end' => null,
                'cancel_at_period_end' => false,
                'customer_email' => $data['customer_email'] ?? null,
            ];
        }

        return null;
    }

    protected function planSlugFromPriceId(?string $priceId): ?string
    {
        if (!$priceId) {
            return null;
        }

        foreach ((array) config('subscriptions.plans') as $slug => $plan) {
            if (($plan['stripe_price_id'] ?? null) === $priceId) {
                return $slug;
            }
        }

        return null;
    }
}
