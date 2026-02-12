<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MockSection;
use App\Models\MockTest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MockTestController extends Controller
{
    public function index()
    {
        $module = request('module');

        $query = MockTest::query()
            ->withCount(['sections', 'attempts'])
            ->orderByDesc('id');

        if (in_array($module, ['reading', 'listening'], true)) {
            $query->where('module', $module);
        }

        return view('admin.mock-tests.index', [
            'tests' => $query->paginate(20)->withQueryString(),
            'module' => $module,
        ]);
    }

    public function create()
    {
        return view('admin.mock-tests.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['is_active'] = $request->boolean('is_active');

        $test = MockTest::create($data);

        return redirect()
            ->route('admin.mock-tests.edit', $test)
            ->with('status', __('app.saved'));
    }

    public function edit(MockTest $mockTest)
    {
        $mockTest->load([
            'sections' => fn ($query) => $query
                ->withCount('questions')
                ->orderBy('section_number'),
        ]);

        return view('admin.mock-tests.edit', [
            'test' => $mockTest,
        ]);
    }

    public function update(Request $request, MockTest $mockTest)
    {
        $data = $this->validated($request);
        $data['is_active'] = $request->boolean('is_active');

        $mockTest->update($data);

        return redirect()
            ->route('admin.mock-tests.edit', $mockTest)
            ->with('status', __('app.saved'));
    }

    public function destroy(MockTest $mockTest)
    {
        $mockTest->load('sections');
        foreach ($mockTest->sections as $section) {
            $this->deleteSectionAudio($section);
        }

        $mockTest->delete();

        return redirect()
            ->route('admin.mock-tests.index')
            ->with('status', __('app.deleted'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'module' => ['required', 'in:reading,listening'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'time_limit' => ['required', 'integer', 'min:60', 'max:7200'],
            'total_questions' => ['required', 'integer', 'min:1', 'max:200'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function deleteSectionAudio(MockSection $section): void
    {
        if ($section->audio_disk && $section->audio_path) {
            Storage::disk($section->audio_disk)->delete($section->audio_path);
            return;
        }

        if (!$section->audio_url) {
            return;
        }

        $path = parse_url($section->audio_url, PHP_URL_PATH);
        if (!is_string($path) || !str_starts_with($path, '/storage/')) {
            return;
        }

        $relative = ltrim(substr($path, strlen('/storage/')), '/');
        if ($relative !== '') {
            Storage::disk('public')->delete($relative);
        }
    }
}
