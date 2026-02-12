<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ArrayExport;
use App\Http\Controllers\Controller;
use App\Models\VocabItem;
use App\Models\VocabList;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Illuminate\Support\Facades\Storage;

class VocabularyController extends Controller
{
    public function index()
    {
        $lists = VocabList::withCount('items')->latest()->paginate(20);

        return view('admin.vocabulary.index', compact('lists'));
    }

    public function exportAll(Request $request)
    {
        $items = VocabItem::with('list')->orderBy('vocab_list_id')->orderBy('term')->get();

        $format = strtolower((string) $request->query('format', 'csv'));
        if ($format === 'xlsx') {
            $headings = [
                'list',
                'term',
                'pronunciation',
                'definition',
                'definition_uz',
                'definition_ru',
                'part_of_speech',
                'example',
            ];

            $rows = $items->map(function (VocabItem $item) {
                return [
                    $item->list?->title,
                    $item->term,
                    $item->pronunciation,
                    $item->definition,
                    $item->definition_uz,
                    $item->definition_ru,
                    $item->part_of_speech,
                    $item->example,
                ];
            })->all();

            return Excel::download(
                new ArrayExport($headings, $rows),
                'vocab-all-'.now()->format('Ymd-His').'.xlsx',
                ExcelWriter::XLSX
            );
        }

        $filename = 'vocab-all-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($items) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'list',
                'term',
                'definition',
                'definition_uz',
                'definition_ru',
                'part_of_speech',
                'example',
            ]);

            foreach ($items as $item) {
                fputcsv($handle, [
                    $item->list?->title,
                    $item->term,
                    $item->pronunciation,
                    $item->definition,
                    $item->definition_uz,
                    $item->definition_ru,
                    $item->part_of_speech,
                    $item->example,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function create()
    {
        return view('admin.vocabulary.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateList($request);
        $list = VocabList::create($data + ['created_by' => $request->user()->id]);

        return redirect()->route('admin.vocabulary.edit', $list);
    }

    public function edit(VocabList $list)
    {
        $list->load('items');

        return view('admin.vocabulary.edit', compact('list'));
    }

    public function downloadSample(Request $request, VocabList $list)
    {
        $format = strtolower((string) $request->query('format', 'xlsx'));
        $filename = $format === 'csv' ? 'vocabulary_items_sample.csv' : 'vocabulary_items_sample.xlsx';
        $path = 'samples/'.$filename;

        if (!Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return response()->download(Storage::disk('local')->path($path), $filename);
    }

    public function update(Request $request, VocabList $list)
    {
        $data = $this->validateList($request);
        $list->update($data);

        return redirect()->route('admin.vocabulary.edit', $list);
    }

    public function storeItem(Request $request, VocabList $list)
    {
        $data = $this->validateItem($request);
        $list->items()->create($data);

        return redirect()->route('admin.vocabulary.edit', $list);
    }

    public function destroyItem(VocabList $list, VocabItem $item)
    {
        if ($item->vocab_list_id !== $list->id) {
            abort(404);
        }

        $item->delete();

        return redirect()->route('admin.vocabulary.edit', $list);
    }

    public function exportItems(Request $request, VocabList $list)
    {
        $items = $list->items()->orderBy('term')->get();

        $format = strtolower((string) $request->query('format', 'csv'));
        if ($format === 'xlsx') {
            $headings = [
                'term',
                'pronunciation',
                'definition',
                'definition_uz',
                'definition_ru',
                'part_of_speech',
                'example',
            ];

            $rows = $items->map(function (VocabItem $item) {
                return [
                    $item->term,
                    $item->pronunciation,
                    $item->definition,
                    $item->definition_uz,
                    $item->definition_ru,
                    $item->part_of_speech,
                    $item->example,
                ];
            })->all();

            return Excel::download(
                new ArrayExport($headings, $rows),
                'vocab-'.$list->id.'-'.now()->format('Ymd-His').'.xlsx',
                ExcelWriter::XLSX
            );
        }

        $filename = 'vocab-'.$list->id.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($items) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'term',
                'pronunciation',
                'definition',
                'definition_uz',
                'definition_ru',
                'part_of_speech',
                'example',
            ]);

            foreach ($items as $item) {
                fputcsv($handle, [
                    $item->term,
                    $item->pronunciation,
                    $item->definition,
                    $item->definition_uz,
                    $item->definition_ru,
                    $item->part_of_speech,
                    $item->example,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function importItems(Request $request, VocabList $list)
    {
        $data = $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt,xls,xlsx', 'max:5120'],
        ]);

        $file = $data['csv'];
        if (!$file->isValid()) {
            return redirect()->route('admin.vocabulary.edit', $list)
                ->with('status', 'import-failed');
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $created = 0;
        $updated = 0;
        $errors = [];
        $lineNumber = 1;

        $processRow = function (array $row, array $mapped) use (&$created, &$updated, &$errors, &$lineNumber, $list) {
            $lineNumber += 1;
            $rowData = $this->mapRow($row, $mapped);
            $term = trim((string) ($rowData['term'] ?? ''));
            if ($term === '') {
                $errors[] = __('app.import_error_term', ['line' => $lineNumber]);
                return;
            }

            $payload = [
                'term' => $term,
                'pronunciation' => $rowData['pronunciation'] ?? null,
                'definition' => $rowData['definition'] ?? null,
                'definition_uz' => $rowData['definition_uz'] ?? null,
                'definition_ru' => $rowData['definition_ru'] ?? null,
                'example' => $rowData['example'] ?? null,
                'part_of_speech' => $rowData['part_of_speech'] ?? null,
            ];

            $item = $list->items()->where('term', $term)->first();
            if ($item) {
                $item->update(array_filter($payload, fn ($v) => $v !== null && $v !== ''));
                $updated++;
            } else {
                $list->items()->create($payload);
                $created++;
            }
        };

        if (in_array($extension, ['xls', 'xlsx'], true)) {
            $rows = Excel::toArray([], $file)[0] ?? [];
            if (empty($rows)) {
                return redirect()->route('admin.vocabulary.edit', $list)
                    ->with('status', 'import-failed');
            }

            $header = $rows[0] ?? [];
            $mapped = $this->mapHeader($header);
            $hasHeader = array_key_exists('term', $mapped);
            $startIndex = $hasHeader ? 1 : 0;
            $lineNumber = 1;

            for ($i = $startIndex; $i < count($rows); $i++) {
                $processRow($rows[$i], $mapped);
            }
        } else {
            $path = $file->getRealPath();
            $handle = fopen($path, 'r');

            if (!$handle) {
                return redirect()->route('admin.vocabulary.edit', $list)
                    ->with('status', 'import-failed');
            }

            $header = fgetcsv($handle);
            $mapped = $this->mapHeader($header);

            $lineNumber = 1;
            while (($row = fgetcsv($handle)) !== false) {
                $processRow($row, $mapped);
            }

            fclose($handle);
        }

        return redirect()->route('admin.vocabulary.edit', $list)
            ->with('status', "imported:{$created}:{$updated}")
            ->with('import_errors', $errors);
    }

    private function validateList(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'level' => ['nullable', 'in:A1,A2,B1,B2'],
            'description' => ['nullable', 'string'],
        ]);
    }

    private function validateItem(Request $request): array
    {
        return $request->validate([
            'term' => ['required', 'string', 'max:255'],
            'pronunciation' => ['nullable', 'string', 'max:120'],
            'definition' => ['nullable', 'string'],
            'definition_uz' => ['nullable', 'string'],
            'definition_ru' => ['nullable', 'string'],
            'example' => ['nullable', 'string'],
            'part_of_speech' => ['nullable', 'string', 'max:30'],
        ]);
    }

    private function mapHeader(?array $header): array
    {
        if (!$header) {
            return [];
        }

        $normalized = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
        $aliases = [
            'word' => 'term',
            'term' => 'term',
            'pronunciation' => 'pronunciation',
            'pronounce' => 'pronunciation',
            'ipa' => 'pronunciation',
            'definition' => 'definition',
            'meaning' => 'definition',
            'meaning_en' => 'definition',
            'definition_en' => 'definition',
            'translation' => 'definition',
            'definition_uz' => 'definition_uz',
            'translation_uz' => 'definition_uz',
            'uz' => 'definition_uz',
            'definition_ru' => 'definition_ru',
            'translation_ru' => 'definition_ru',
            'ru' => 'definition_ru',
            'part_of_speech' => 'part_of_speech',
            'partofspeech' => 'part_of_speech',
            'pos' => 'part_of_speech',
            'example' => 'example',
            'example_en' => 'example',
        ];
        $allowed = [
            'term',
            'pronunciation',
            'definition',
            'definition_uz',
            'definition_ru',
            'part_of_speech',
            'example',
        ];
        $map = [];
        foreach ($normalized as $index => $key) {
            $canonical = $aliases[$key] ?? $key;
            if (in_array($canonical, $allowed, true)) {
                $map[$canonical] = $index;
            }
        }

        return $map;
    }

    private function mapRow(array $row, array $map): array
    {
        if (empty($map)) {
            $usePronunciation = count($row) >= 7;
            return [
                'term' => $row[0] ?? null,
                'pronunciation' => $usePronunciation ? ($row[1] ?? null) : null,
                'definition' => $usePronunciation ? ($row[2] ?? null) : ($row[1] ?? null),
                'definition_uz' => $usePronunciation ? ($row[3] ?? null) : ($row[2] ?? null),
                'definition_ru' => $usePronunciation ? ($row[4] ?? null) : ($row[3] ?? null),
                'part_of_speech' => $usePronunciation ? ($row[5] ?? null) : ($row[4] ?? null),
                'example' => $usePronunciation ? ($row[6] ?? null) : ($row[5] ?? null),
            ];
        }

        return [
            'term' => $row[$map['term'] ?? -1] ?? null,
            'pronunciation' => $row[$map['pronunciation'] ?? -1] ?? null,
            'definition' => $row[$map['definition'] ?? -1] ?? null,
            'definition_uz' => $row[$map['definition_uz'] ?? -1] ?? null,
            'definition_ru' => $row[$map['definition_ru'] ?? -1] ?? null,
            'part_of_speech' => $row[$map['part_of_speech'] ?? -1] ?? null,
            'example' => $row[$map['example'] ?? -1] ?? null,
        ];
    }
}
