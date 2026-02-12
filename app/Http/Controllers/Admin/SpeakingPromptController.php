<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SpeakingPrompt;
use Illuminate\Http\Request;

class SpeakingPromptController extends Controller
{
    public function index()
    {
        $mode = request('mode');
        $query = SpeakingPrompt::orderBy('part')->orderBy('id');
        if (in_array($mode, ['practice', 'mock'], true)) {
            $query->where('mode', $mode);
        }
        $prompts = $query->paginate(30)->withQueryString();

        return view('admin.speaking-prompts.index', [
            'prompts' => $prompts,
            'mode' => $mode,
        ]);
    }

    public function create()
    {
        return view('admin.speaking-prompts.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['is_active'] = $request->boolean('is_active');
        $data['created_by'] = $request->user()->id;
        $data['mode'] = $data['mode'] ?? 'practice';

        SpeakingPrompt::create($data);

        return redirect()->route('admin.speaking-prompts.index')->with('status', __('app.saved'));
    }

    public function edit(SpeakingPrompt $prompt)
    {
        return view('admin.speaking-prompts.edit', [
            'prompt' => $prompt,
        ]);
    }

    public function update(Request $request, SpeakingPrompt $prompt)
    {
        $data = $this->validated($request);
        $data['is_active'] = $request->boolean('is_active');
        $data['mode'] = $data['mode'] ?? 'practice';
        $prompt->update($data);

        return redirect()->route('admin.speaking-prompts.index')->with('status', __('app.saved'));
    }

    public function destroy(SpeakingPrompt $prompt)
    {
        $prompt->delete();

        return redirect()->route('admin.speaking-prompts.index')->with('status', __('app.deleted'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'part' => ['required', 'integer', 'min:1', 'max:3'],
            'prompt' => ['required', 'string'],
            'difficulty' => ['nullable', 'string', 'max:10'],
            'is_active' => ['nullable', 'boolean'],
            'mode' => ['nullable', 'in:practice,mock'],
        ]);
    }
}
