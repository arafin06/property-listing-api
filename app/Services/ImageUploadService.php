<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ImageUploadService
{
    /**
     * The storage disk used for property images.
     */
    private const DISK = 'public';

    /**
     * Store the uploaded file under the given property's image directory.
     *
     * @return string The stored file's path relative to the disk root.
     */
    public function handle(UploadedFile $file, int $propertyId): string
    {
        $path = $file->store("properties/{$propertyId}", self::DISK);

        if ($path === false) {
            throw new RuntimeException('Failed to store the uploaded image.');
        }

        return $path;
    }

    /**
     * Remove the file at the given path from disk.
     */
    public function delete(string $path): void
    {
        Storage::disk(self::DISK)->delete($path);
    }
}
