<?php

namespace App\Http\Controllers;

use App\Services\FeatureGate;
use App\Models\SpeakingPrompt;
use Illuminate\Support\Facades\Auth;

class SpeakingController extends Controller
{
    public function index(FeatureGate $featureGate)
    {
        $user = Auth::user();
        $canSpeaking = $user && ($user->is_admin || $featureGate->userCan($user, 'speaking_ai'));
        $prompts = SpeakingPrompt::query()
            ->where('is_active', true)
            ->where('mode', 'practice')
            ->orderBy('part')
            ->orderBy('id')
            ->get()
            ->groupBy('part');

        if ($prompts->isEmpty()) {
            $prompts = collect([
                1 => collect([__('app.speaking_preview_prompt_2')]),
                2 => collect([__('app.speaking_preview_prompt_1')]),
            ]);
        }

        $recentSubmissions = collect();
        if ($user) {
            $recentSubmissions = \App\Models\SpeakingSubmission::query()
                ->where('user_id', $user->id)
                ->whereHas('prompt', fn ($query) => $query->where('mode', 'practice'))
                ->latest()
                ->limit(5)
                ->get();
        }

        return view('speaking.index', [
            'canSpeaking' => $canSpeaking,
            'promptsByPart' => $prompts,
            'recentSubmissions' => $recentSubmissions,
        ]);
    }
}
