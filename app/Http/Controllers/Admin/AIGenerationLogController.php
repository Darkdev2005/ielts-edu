<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiRequest;
use App\Jobs\ProcessAiRequestJob;
use Illuminate\Http\Request;

class AIGenerationLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AiRequest::with('user')->latest();

        if ($status = $request->string('status')->trim()->value()) {
            $query->where('status', $status);
        }
        if ($type = $request->string('type')->trim()->value()) {
            $query->where('input_json->task', $type);
        }
        if ($provider = $request->string('provider')->trim()->value()) {
            $query->where('provider', $provider);
        }
        if ($q = $request->string('q')->trim()->value()) {
            $query->where('input_json->prompt', 'like', '%'.$q.'%');
        }

        $logs = $query->paginate(25)->appends($request->query());

        return view('admin.ai-logs.index', compact('logs'));
    }

    public function show(AiRequest $log)
    {
        $log->load('user');

        return view('admin.ai-logs.show', compact('log'));
    }

    public function retry(AiRequest $log)
    {
        if (!in_array($log->status, ['failed', 'failed_quota'], true)) {
            return redirect()
                ->route('admin.ai-logs.show', $log)
                ->with('status', __('app.ai_retry_failed'));
        }

        $log->update([
            'status' => 'pending',
            'error_text' => null,
            'started_at' => null,
            'finished_at' => null,
        ]);

        ProcessAiRequestJob::dispatch($log->id);

        return redirect()
            ->route('admin.ai-logs.show', $log)
            ->with('status', __('app.ai_retry_queued'));
    }
}
