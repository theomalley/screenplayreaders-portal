<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class SpacesStorageService
{
    public function store(string $path, string $contents, string $mimeType = 'application/pdf'): string
    {
        Storage::disk('do_spaces')->put($path, $contents, [
            'ContentType' => $mimeType,
        ]);

        return $path;
    }

    public function get(string $path): string
    {
        return Storage::disk('do_spaces')->get($path);
    }

    public function delete(string $path): void
    {
        Storage::disk('do_spaces')->delete($path);
    }

    public function exists(string $path): bool
    {
        return Storage::disk('do_spaces')->exists($path);
    }
}
