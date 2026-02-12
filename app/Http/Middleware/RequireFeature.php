<?php

namespace App\Http\Middleware;

use App\Services\FeatureGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireFeature
{
    public function __construct(private FeatureGate $featureGate)
    {
    }

    public function handle(Request $request, Closure $next, string $featureKey): Response
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }
        if ($user->is_admin || $user->is_super_admin) {
            return $next($request);
        }

        if ($this->featureGate->userCan($user, $featureKey)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403);
        }

        return redirect()->route('pricing')->with('status', __('app.upgrade_required'));
    }
}
