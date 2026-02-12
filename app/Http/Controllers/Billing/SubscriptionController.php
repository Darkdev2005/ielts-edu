<?php

namespace App\Http\Controllers\Billing;

use App\Contracts\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function subscribePlus(Request $request, PaymentGateway $gateway)
    {
        if (config('subscriptions.demo_mode')) {
            return redirect()
                ->route('pricing')
                ->with('status', __('app.payment_disabled'));
        }

        $plan = Plan::where('slug', 'plus')->firstOrFail();
        if (!$plan->is_active) {
            abort(404);
        }
        if (!config('subscriptions.plans.plus.purchasable', false)) {
            abort(404);
        }

        $user = $request->user();
        $checkoutUrl = $gateway->createCheckoutSession(
            $user,
            $plan,
            route('billing.success'),
            route('pricing')
        );

        return redirect()->away($checkoutUrl);
    }

    public function success()
    {
        return redirect()->route('dashboard')->with('status', __('app.subscription_success'));
    }
}
