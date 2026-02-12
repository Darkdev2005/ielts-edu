<?php

namespace App\Http\Controllers\Billing;

use App\Contracts\PaymentGateway;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BillingPortalController extends Controller
{
    public function redirect(Request $request, PaymentGateway $gateway)
    {
        $user = $request->user();

        if (!$user || !$user->subscription) {
            return redirect()->route('pricing')->with('status', __('app.upgrade_required'));
        }

        $url = $gateway->createBillingPortalSession($user, route('dashboard'));

        return redirect()->away($url);
    }
}
