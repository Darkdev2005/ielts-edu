<?php

namespace App\Console\Commands;

use App\Models\MockSection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupOrphanMockAudio extends Command
{
    protected $signature = 'mock:cleanup-orphan-audio {--disk=} {--prefix=}';
    protected $description = 'Delete mock listening audio files that are no longer referenced by sections.';

    public function handle(): int
    {
        $disk = (string) ($this->option('disk') ?: config('mock.audio_disk', 'public'));
        $prefix = trim((string) ($this->option('prefix') ?: config('mock.audio_prefix', 'mock-audio')), '/');

        $storage = Storage::disk($disk);
        if (!$storage->exists($prefix)) {
            $this->info("No files found under {$disk}:{$prefix}");
            return Command::SUCCESS;
        }

        $allFiles = collect($storage->allFiles($prefix));
        $usedFiles = MockSection::query()
            ->where('audio_disk', $disk)
            ->whereNotNull('audio_path')
            ->pluck('audio_path')
            ->filter()
            ->values();

        $orphans = $allFiles->diff($usedFiles)->values();
        $deleted = 0;

        foreach ($orphans as $path) {
            if ($storage->delete($path)) {
                $deleted++;
            }
        }

        $this->info("Checked: {$allFiles->count()}, used: {$usedFiles->count()}, deleted orphans: {$deleted}");

        return Command::SUCCESS;
    }
}
