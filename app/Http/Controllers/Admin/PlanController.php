<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function update(Request $request, Plan $plan)
    {
        $data = $request->validate([
            'price_monthly' => ['required', 'integer', 'min:0'],
        ]);

        $plan->update([
            'price_monthly' => $data['price_monthly'],
        ]);

        return redirect()
            ->back()
            ->with('status', __('app.plan_price_updated'));
    }
}
