<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\Request;

class AISettingsController extends Controller
{
    public function edit()
    {
        $defaults = [
            'retry_interval_minutes' => config('services.ai.retry_interval_minutes', 5),
            'retry_limit' => config('services.ai.retry_limit', 20),
            'retry_min_age_minutes' => config('services.ai.retry_min_age_minutes', 10),
            'retry_max_attempts' => config('services.ai.retry_max_attempts', 3),
        ];

        $values = [
            'retry_interval_minutes' => AppSetting::getValue('ai_retry_interval_minutes', $defaults['retry_interval_minutes']),
            'retry_limit' => AppSetting::getValue('ai_retry_limit', $defaults['retry_limit']),
            'retry_min_age_minutes' => AppSetting::getValue('ai_retry_min_age_minutes', $defaults['retry_min_age_minutes']),
            'retry_max_attempts' => AppSetting::getValue('ai_retry_max_attempts', $defaults['retry_max_attempts']),
        ];

        return view('admin.ai-settings.edit', compact('values'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'retry_interval_minutes' => ['required', 'integer', 'min:1', 'max:60'],
            'retry_limit' => ['required', 'integer', 'min:1', 'max:200'],
            'retry_min_age_minutes' => ['required', 'integer', 'min:1', 'max:120'],
            'retry_max_attempts' => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        AppSetting::setValue('ai_retry_interval_minutes', $data['retry_interval_minutes']);
        AppSetting::setValue('ai_retry_limit', $data['retry_limit']);
        AppSetting::setValue('ai_retry_min_age_minutes', $data['retry_min_age_minutes']);
        AppSetting::setValue('ai_retry_max_attempts', $data['retry_max_attempts']);

        return redirect()
            ->route('admin.ai-settings.edit')
            ->with('status', __('app.saved'));
    }
}
