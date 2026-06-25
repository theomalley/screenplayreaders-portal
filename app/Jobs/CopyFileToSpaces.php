<?php

namespace App\Jobs;

use App\Services\GoogleDriveService;
use App\Services\GoogleDocsService;
use App\Services\SpacesStorageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CopyFileToSpaces implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $backoff = 60;

    public function __construct(
        public readonly string $modelClass,
        public readonly int $modelId,
        public readonly string $driveIdColumn,
        public readonly string $spacesPathColumn,
        public readonly string $spacesPath,
    ) {}

    public function handle(SpacesStorageService $spaces): void
    {
        $model = $this->modelClass::findOrFail($this->modelId);

        $driveId = $model->{$this->driveIdColumn};
        if (! $driveId) {
            return;
        }

        if ($model->{$this->spacesPathColumn}) {
            return;
        }

        $bytes = app(GoogleDriveService::class)->downloadContents($driveId);
        $spaces->store($this->spacesPath, $bytes);

        $model->update([$this->spacesPathColumn => $this->spacesPath]);

        Log::info('CopyFileToSpaces: copied', [
            'model' => $this->modelClass,
            'id' => $this->modelId,
            'spaces_path' => $this->spacesPath,
        ]);
    }
}
