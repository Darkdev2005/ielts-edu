<?php

namespace App\Http\Middleware;

use App\Exceptions\LimitExceededException;
use App\Models\Lesson;
use App\Services\FeatureGate;
use App\Services\UsageLimiter;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceLimit
{
    public function __construct(private UsageLimiter $usageLimiter, private FeatureGate $featureGate)
    {
    }

    public function handle(Request $request, Closure $next, string $limitKey): Response
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }
        if ($user->is_admin || $user->is_super_admin) {
            return $next($request);
        }

        $plan = $this->featureGate->currentPlan($user);
        if ($plan && $plan->slug !== 'free') {
            return $next($request);
        }

        if (!$this->shouldApply($request, $limitKey)) {
            return $next($request);
        }

        try {
            $this->usageLimiter->assertWithinLimit($user, $limitKey);
        } catch (LimitExceededException $exception) {
            if ($request->expectsJson()) {
                abort(429);
            }

            return redirect()->route('pricing')->with('status', __('app.daily_limit_reached'));
        }

        $response = $next($request);
        $this->usageLimiter->increment($user, $limitKey);

        return $response;
    }

    protected function shouldApply(Request $request, string $limitKey): bool
    {
        $lesson = $request->route('lesson');
        if ($lesson instanceof Lesson) {
            if ($limitKey === 'reading_daily') {
                return $lesson->type === 'reading';
            }

            if ($limitKey === 'listening_daily') {
                return $lesson->type === 'listening';
            }
        }

        return true;
    }
}
