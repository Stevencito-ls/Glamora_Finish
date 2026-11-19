<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

trait HandlesSafeMediaUploads
{
    protected function storeSafePublicImage(
        Request $request,
        string $field,
        string $folder,
    ): ?string {
        if (! $request->hasFile($field)) {
            return null;
        }

        $file = $request->file($field);
        if (! $file instanceof UploadedFile || $this->isDisallowedMediaUpload($file)) {
            return null;
        }

        $stored = $file->store($folder, 'public');

        return is_string($stored) && $stored !== ''
            ? 'storage/' . ltrim($stored, '/')
            : null;
    }

    /**
     * @return list<string>
     */
    protected function storeSafePublicImages(Request $request, string $field, string $folder): array
    {
        if (! $request->hasFile($field)) {
            return [];
        }

        $files = $request->file($field);
        if ($files instanceof UploadedFile) {
            $files = [$files];
        }

        if (! is_array($files)) {
            return [];
        }

        $storedPaths = [];

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || $this->isDisallowedMediaUpload($file)) {
                continue;
            }

            $stored = $file->store($folder, 'public');
            if (is_string($stored) && $stored !== '') {
                $storedPaths[] = 'storage/' . ltrim($stored, '/');
            }
        }

        return $storedPaths;
    }

    private function isDisallowedMediaUpload(UploadedFile $file): bool
    {
        if (! $file->isValid()) {
            return true;
        }

        $extension = strtolower(trim((string) $file->getClientOriginalExtension()));
        $guessedExtension = strtolower(trim((string) ($file->guessExtension() ?? '')));
        $mimeType = strtolower(trim((string) $file->getMimeType()));
        $clientMimeType = strtolower(trim((string) $file->getClientMimeType()));

        return in_array($extension, ['svg', 'svgz'], true)
            || in_array($guessedExtension, ['svg', 'svgz'], true)
            || str_contains($mimeType, 'svg')
            || str_contains($clientMimeType, 'svg');
    }

    protected function deleteSafePublicImage(?string $publicPath): void
    {
        if ($publicPath === null) {
            return;
        }

        $normalized = ltrim(trim($publicPath), '/');
        if (! str_starts_with($normalized, 'storage/')) {
            return;
        }

        $relativePath = ltrim(substr($normalized, strlen('storage/')), '/');
        if ($relativePath !== '' && ! str_contains($relativePath, '..')) {
            Storage::disk('public')->delete($relativePath);
        }
    }

    protected function sanitizeSafeImagePath(?string $path): ?string
    {
        $clean = trim((string) $path);
        if (
            $clean === ''
            || preg_match('/[\x00-\x1F\x7F]/', $clean) === 1
            || str_contains($clean, '..')
            || str_contains($clean, '\\')
            || str_starts_with($clean, '//')
        ) {
            return null;
        }

        $withoutQuery = (string) strtok($clean, '?#');
        if (str_ends_with(strtolower(trim($withoutQuery)), '.svg')) {
            return null;
        }

        if (
            preg_match('/^[a-z][a-z0-9+.-]*:/i', $clean) === 1
            && preg_match('/^https?:\/\/\S+$/i', $clean) !== 1
        ) {
            return null;
        }

        return $clean;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function runWithMediaCleanup(callable $callback, ?string ...$uploadedPaths): mixed
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            foreach ($uploadedPaths as $path) {
                $this->deleteSafePublicImage($path);
            }

            throw $e;
        }
    }
}
