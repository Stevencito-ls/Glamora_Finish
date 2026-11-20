<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Concerns;

use App\Services\ImageOptimizerService;
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
        bool $allowIcons = false,
        int $maxDimension = 1200,
        int $quality = 82,
    ): ?string {
        if (! $request->hasFile($field)) {
            return null;
        }

        $file = $request->file($field);
        if (! $file instanceof UploadedFile) {
            return null;
        }

        return app(ImageOptimizerService::class)->processAndStore(
            file: $file,
            folder: $folder,
            maxDimension: $maxDimension,
            quality: $quality,
            allowIcons: $allowIcons,
        );
    }

    /**
     * @return list<string>
     */
    protected function storeSafePublicImages(
        Request $request,
        string $field,
        string $folder,
        int $maxDimension = 1200,
        int $quality = 82,
    ): array {
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

        $optimizer = app(ImageOptimizerService::class);
        $storedPaths = [];

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $stored = $optimizer->processAndStore(
                file: $file,
                folder: $folder,
                maxDimension: $maxDimension,
                quality: $quality,
                allowIcons: false,
            );

            if ($stored !== null) {
                $storedPaths[] = $stored;
            }
        }

        return $storedPaths;
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

    protected function sanitizeSafeImagePath(?string $path, bool $allowIcons = false): ?string
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

        $withoutQuery = strtolower(trim((string) strtok($clean, '?#')));
        if (str_ends_with($withoutQuery, '.svg') || str_ends_with($withoutQuery, '.svgz')) {
            return null;
        }

        if (! $allowIcons && str_ends_with($withoutQuery, '.ico')) {
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
