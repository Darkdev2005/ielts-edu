<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MockSection;
use App\Models\MockTest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MockSectionController extends Controller
{
    public function store(Request $request, MockTest $mockTest)
    {
        $data = $this->validated($request, $mockTest);
        $data['mock_test_id'] = $mockTest->id;
        if ($mockTest->module === 'listening') {
            $audio = $this->resolveAudioInput($request);
            $data['audio_url'] = $audio['audio_url'];
            $data['audio_disk'] = $audio['audio_disk'];
            $data['audio_path'] = $audio['audio_path'];
            $data['passage_text'] = null;
        } else {
            $data['audio_url'] = null;
            $data['audio_disk'] = null;
            $data['audio_path'] = null;
        }

        MockSection::create($data);

        return redirect()
            ->route('admin.mock-tests.edit', $mockTest)
            ->with('status', __('app.saved'));
    }

    public function update(Request $request, MockTest $mockTest, MockSection $mockSection)
    {
        $this->ensureTestMatch($mockTest, $mockSection);

        $data = $this->validated($request, $mockTest, $mockSection->id, $mockSection);
        if ($mockTest->module === 'listening') {
            $oldAudio = $mockSection->audio_url;
            $oldDisk = $mockSection->audio_disk;
            $oldPath = $mockSection->audio_path;
            if ($request->hasFile('audio_file') || $request->filled('audio_url')) {
                $audio = $this->resolveAudioInput($request);
                $data['audio_url'] = $audio['audio_url'];
                $data['audio_disk'] = $audio['audio_disk'];
                $data['audio_path'] = $audio['audio_path'];

                if ($audio['audio_url'] !== $oldAudio || $audio['audio_disk'] !== $oldDisk || $audio['audio_path'] !== $oldPath) {
                    $this->deleteStoredAudio($oldDisk, $oldPath, $oldAudio);
                }
            } else {
                $data['audio_url'] = $oldAudio;
                $data['audio_disk'] = $oldDisk;
                $data['audio_path'] = $oldPath;
            }

            $data['passage_text'] = null;
        } else {
            $data['audio_url'] = null;
            $data['audio_disk'] = null;
            $data['audio_path'] = null;
        }

        $mockSection->update($data);

        return redirect()
            ->route('admin.mock-tests.edit', $mockTest)
            ->with('status', __('app.saved'));
    }

    public function destroy(MockTest $mockTest, MockSection $mockSection)
    {
        $this->ensureTestMatch($mockTest, $mockSection);
        $this->deleteStoredAudio($mockSection->audio_disk, $mockSection->audio_path, $mockSection->audio_url);
        $mockSection->delete();

        return redirect()
            ->route('admin.mock-tests.edit', $mockTest)
            ->with('status', __('app.deleted'));
    }

    private function ensureTestMatch(MockTest $mockTest, MockSection $mockSection): void
    {
        if ($mockSection->mock_test_id !== $mockTest->id) {
            abort(404);
        }
    }

    private function validated(Request $request, MockTest $mockTest, ?int $ignoreId = null, ?MockSection $existingSection = null): array
    {
        $module = $mockTest->module;
        $noYoutubeRule = function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            $host = strtolower((string) parse_url((string) $value, PHP_URL_HOST));
            if (str_contains($host, 'youtube.com') || str_contains($host, 'youtu.be')) {
                $fail(__('app.mock_listening_audio_direct_only'));
            }
        };

        $rules = [
            'section_number' => [
                'required',
                'integer',
                'min:1',
                'max:'.($module === 'listening' ? '4' : '3'),
                Rule::unique('mock_sections', 'section_number')
                    ->where('mock_test_id', $mockTest->id)
                    ->ignore($ignoreId),
            ],
            'title' => ['nullable', 'string', 'max:255'],
            'audio_url' => [
                'nullable',
                Rule::when($module === 'listening', ['url', $noYoutubeRule]),
            ],
            'audio_file' => [
                'nullable',
                Rule::when($module === 'listening', [
                    'file',
                    'mimes:mp3,m4a,wav,ogg,webm,aac',
                    'max:'.$this->audioMaxKb(),
                ]),
            ],
            'passage_text' => [
                Rule::requiredIf($module === 'reading'),
                'nullable',
                'string',
            ],
        ];

        $validator = Validator::make($request->all(), $rules);
        $validator->after(function ($validator) use ($request, $module, $existingSection): void {
            if ($module !== 'listening') {
                return;
            }

            $hasUrl = trim((string) $request->input('audio_url', '')) !== '';
            $hasFile = $request->hasFile('audio_file');
            $hasExisting = $existingSection && !empty($existingSection->audio_url);

            if (!$hasUrl && !$hasFile && !$hasExisting) {
                $validator->errors()->add('audio_url', __('app.mock_listening_audio_required'));
            }
        });

        return $validator->validate();
    }

    /**
     * @return array{audio_url:?string,audio_disk:?string,audio_path:?string}
     */
    private function resolveAudioInput(Request $request): array
    {
        if ($request->hasFile('audio_file')) {
            $disk = $this->audioDisk();
            $path = $request->file('audio_file')->store($this->audioPrefix(), $disk);

            return [
                'audio_url' => Storage::disk($disk)->url($path),
                'audio_disk' => $disk,
                'audio_path' => $path,
            ];
        }

        $audioUrl = trim((string) $request->input('audio_url', ''));

        return [
            'audio_url' => $audioUrl !== '' ? $audioUrl : null,
            'audio_disk' => null,
            'audio_path' => null,
        ];
    }

    private function deleteStoredAudio(?string $audioDisk, ?string $audioPath, ?string $audioUrl): void
    {
        if ($audioDisk && $audioPath) {
            Storage::disk($audioDisk)->delete($audioPath);
            return;
        }

        if (!$audioUrl) {
            return;
        }

        $path = parse_url($audioUrl, PHP_URL_PATH);
        if (!is_string($path) || !str_starts_with($path, '/storage/')) {
            return;
        }

        $relative = ltrim(substr($path, strlen('/storage/')), '/');
        if ($relative === '') {
            return;
        }

        Storage::disk('public')->delete($relative);
    }

    private function audioDisk(): string
    {
        return (string) config('mock.audio_disk', 'public');
    }

    private function audioPrefix(): string
    {
        return (string) config('mock.audio_prefix', 'mock-audio');
    }

    private function audioMaxKb(): int
    {
        $mb = (int) config('mock.audio_max_mb', 15);
        $mb = max(1, $mb);

        return $mb * 1024;
    }
}
