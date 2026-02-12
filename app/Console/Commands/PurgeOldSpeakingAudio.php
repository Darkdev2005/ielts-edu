<?php

namespace App\Console\Commands;

use App\Models\SpeakingSubmission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgeOldSpeakingAudio extends Command
{
    protected $signature = 'speaking:purge-audio {--days=30}';
    protected $description = 'Delete old speaking audio files and clear references.';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $submissions = SpeakingSubmission::query()
            ->whereNotNull('audio_path')
            ->where('created_at', '<', $cutoff)
            ->get();

        $deleted = 0;
        foreach ($submissions as $submission) {
            $path = (string) $submission->audio_path;
            if ($path !== '' && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
                $deleted++;
            }

            $submission->update([
                'audio_path' => null,
                'has_audio' => false,
            ]);
        }

        $this->info("Deleted {$deleted} audio file(s).");
        return Command::SUCCESS;
    }
}
