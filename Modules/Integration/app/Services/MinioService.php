<?php

declare(strict_types=1);

namespace Modules\Integration\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * MinioService
 *
 * Provides a typed interface over the Laravel Storage disks backed by MinIO
 * (S3-compatible object storage).  Handles uploads, signed-URL generation,
 * deletion and directory listing for artifact buckets:
 *
 *   minio          → documents / catch-all
 *   minio_renders  → PNG/JPEG/EXR product renders
 *   minio_models   → 3D model files (.glb / .gltf)
 *   minio_nesting  → SVG/DXF nesting files (CNC/laser-cutting)
 */
final class MinioService
{
    /**
     * Upload a render file (PNG/JPEG/EXR/WebP) to the renders bucket.
     *
     * @param  string  $localPath   Absolute path to the local source file.
     * @param  string  $remotePath  Destination key inside the bucket (e.g. "tenant/42/render.png").
     * @return string               Public URL of the uploaded object.
     *
     * @throws RuntimeException When the upload fails.
     */
    public function uploadRender(string $localPath, string $remotePath): string
    {
        return $this->uploadFile('minio_renders', $localPath, $remotePath);
    }

    /**
     * Upload a 3D model file (.glb / .gltf) to the models bucket.
     *
     * @param  string  $localPath   Absolute path to the local source file.
     * @param  string  $remotePath  Destination key inside the bucket (e.g. "tenant/42/product.glb").
     * @return string               Public URL of the uploaded object.
     *
     * @throws RuntimeException When the upload fails.
     */
    public function uploadModel(string $localPath, string $remotePath): string
    {
        return $this->uploadFile('minio_models', $localPath, $remotePath);
    }

    /**
     * Upload an SVG or DXF nesting file to the nesting bucket.
     *
     * @param  string  $localPath   Absolute path to the local source file.
     * @param  string  $remotePath  Destination key inside the bucket (e.g. "tenant/42/layout.svg").
     * @return string               Public URL of the uploaded object.
     *
     * @throws RuntimeException When the upload fails.
     */
    public function uploadNestingFile(string $localPath, string $remotePath): string
    {
        return $this->uploadFile('minio_nesting', $localPath, $remotePath);
    }

    /**
     * Generate a temporary signed (pre-signed) URL for a private object.
     *
     * @param  string  $disk              Filesystem disk name (e.g. 'minio_models').
     * @param  string  $path              Object key inside the bucket.
     * @param  int     $expiresInMinutes  Validity window in minutes (default 60).
     * @return string                     Signed URL valid for the requested duration.
     */
    public function getSignedUrl(string $disk, string $path, int $expiresInMinutes = 60): string
    {
        $expiry = now()->addMinutes($expiresInMinutes);

        /** @var \Illuminate\Filesystem\FilesystemAdapter $storage */
        $storage = Storage::disk($disk);

        return $storage->temporaryUrl($path, $expiry);
    }

    /**
     * Delete a single object from the specified disk/bucket.
     *
     * @param  string  $disk  Filesystem disk name (e.g. 'minio_renders').
     * @param  string  $path  Object key inside the bucket.
     * @return bool           True on success, false if the object did not exist.
     */
    public function deleteFile(string $disk, string $path): bool
    {
        return Storage::disk($disk)->delete($path);
    }

    /**
     * List all objects under a given prefix in a disk/bucket.
     *
     * @param  string  $disk    Filesystem disk name.
     * @param  string  $prefix  Key prefix to filter results (empty string = all).
     * @return array<int, string>  Array of object keys relative to the bucket root.
     */
    public function listFiles(string $disk, string $prefix = ''): array
    {
        return Storage::disk($disk)->allFiles($prefix);
    }

    // ─── Internal helpers ──────────────────────────────────────────────────────

    /**
     * Core upload helper: reads a local file and streams it to the given disk.
     *
     * @param  string  $disk        Filesystem disk name.
     * @param  string  $localPath   Absolute local file path.
     * @param  string  $remotePath  Destination key inside the bucket.
     * @return string               Public URL returned by the disk adapter.
     *
     * @throws RuntimeException When the file cannot be read or the upload fails.
     */
    private function uploadFile(string $disk, string $localPath, string $remotePath): string
    {
        if (! file_exists($localPath)) {
            throw new RuntimeException("Source file not found: {$localPath}");
        }

        $stream = fopen($localPath, 'r');

        if ($stream === false) {
            throw new RuntimeException("Unable to open file for reading: {$localPath}");
        }

        try {
            $success = Storage::disk($disk)->put($remotePath, $stream);
        } finally {
            fclose($stream);
        }

        if (! $success) {
            throw new RuntimeException(
                "Failed to upload file to disk '{$disk}' at path '{$remotePath}'."
            );
        }

        return Storage::disk($disk)->url($remotePath);
    }
}
