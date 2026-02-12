<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $users = User::with(['currentPlan', 'subscription'])
            ->orderBy('id')
            ->paginate(20);

        $planMap = Plan::pluck('name', 'id');
        $requests = SubscriptionRequest::with(['user', 'plan'])
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->get();

        return view('admin.subscriptions.index', [
            'users' => $users,
            'planMap' => $planMap,
            'requests' => $requests,
        ]);
    }

    public function approveRequest(Request $request, SubscriptionRequest $subscriptionRequest)
    {
        if ($subscriptionRequest->status !== 'pending') {
            return redirect()->route('admin.subscriptions.index');
        }

        $defaultMonths = (int) config('subscriptions.manual_payment.default_months', 1);
        $data = $request->validate([
            'months' => ['nullable', 'integer', 'min:1', 'max:24'],
        ]);
        $months = (int) ($data['months'] ?? $defaultMonths);
        if ($months < 1) {
            $months = $defaultMonths;
        }

        $subscriptionRequest->loadMissing(['user', 'plan']);
        $user = $subscriptionRequest->user;
        $plan = $subscriptionRequest->plan;
        if (!$user || !$plan) {
            return redirect()->route('admin.subscriptions.index');
        }

        DB::transaction(function () use ($subscriptionRequest, $user, $plan, $months): void {
            $subscriptionRequest->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => auth()->id(),
            ]);

            $user->update([
                'current_plan_id' => $plan->id,
            ]);

            Subscription::updateOrCreate(
                ['user_id' => $user->id, 'provider' => 'manual'],
                [
                    'plan_id' => $plan->id,
                    'status' => 'active',
                    'current_period_end' => now()->addMonths($months),
                    'cancel_at_period_end' => false,
                    'provider_subscription_id' => 'manual-'.$subscriptionRequest->id,
                ]
            );
        });

        return redirect()->route('admin.subscriptions.index')->with('status', __('app.subscription_request_approved'));
    }

    public function rejectRequest(Request $request, SubscriptionRequest $subscriptionRequest)
    {
        if ($subscriptionRequest->status !== 'pending') {
            return redirect()->route('admin.subscriptions.index');
        }

        $data = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $subscriptionRequest->update([
            'status' => 'rejected',
            'admin_note' => $data['admin_note'] ?? null,
            'approved_by' => auth()->id(),
        ]);

        return redirect()->route('admin.subscriptions.index')->with('status', __('app.subscription_request_rejected'));
    }
}
