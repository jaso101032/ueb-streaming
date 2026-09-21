<?php

namespace App\Console\Commands;

use App\Models\Transmision;
use App\Services\RecordingProcessor;
use Illuminate\Console\Command;

class PrepareRecordings extends Command
{
    protected $signature = 'streaming:prepare-recordings';
    protected $description = 'Prepara y publica las grabaciones cerradas; recupera trabajos interrumpidos.';

    public function handle(RecordingProcessor $processor): int
    {
        // Local advisory lock survives long encodes and is released on process exit.
        $lock = fopen(storage_path('framework/recordings-worker.lock'), 'c');
        if (!$lock) return self::FAILURE;
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            return self::SUCCESS;
        }
        try {
            Transmision::whereIn('recording_status', ['recording', 'waiting', 'processing', 'failed'])
                ->where(fn ($query) => $query->where('recording_attempts', '<', 3)->orWhere('recording_status', 'processing'))->orderBy('id')->chunkById(50, function ($streams) use ($processor) {
                    foreach ($streams as $stream) $processor->process($stream->id);
                });
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        return self::SUCCESS;
    }
}
