<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageOptimizerService
{
    /**
     * @var list<string>
     */
    private const array BLOCKED_EXTENSIONS = [
        'svg',
        'svgz',
        'html',
        'htm',
        'xhtml',
        'shtml',
        'mhtml',
        'php',
        'phtml',
        'php3',
        'php4',
        'php5',
        'php7',
        'php8',
        'phps',
        'phar',
        'xml',
        'js',
        'mjs',
        'cjs',
        'css',
        'txt',
        'asp',
        'aspx',
        'jsp',
        'sh',
        'bat',
        'exe',
    ];

    /**
     * @var list<string>
     */
    private const array BLOCKED_MIME_FRAGMENTS = [
        'svg',
        'html',
        'php',
        'xml',
        'javascript',
        'ecmascript',
        'text/plain',
    ];

    /**
     * @var list<string>
     */
    private const array DANGEROUS_BYTE_PATTERNS = [
        '<svg',
        '<?php',
        '<?=',
        '<script',
        '<!doctype',
        '<html',
        '<body',
        '<iframe',
        '<object',
        '<embed',
        '<foreignobject',
    ];

    /**
     * Inspects uploaded image binary safely without requiring ext-gd.
     *
     * @return array{
     *     valid: bool,
     *     error: ?string,
     *     extension: ?string,
     *     mime: ?string,
     *     is_icon: bool,
     *     width: ?int,
     *     height: ?int
     * }
     */
    public function inspectBinary(UploadedFile $file, bool $allowIcons = false): array
    {
        if (! $file->isValid()) {
            return $this->fail('El archivo cargado no es válido o está incompleto.');
        }

        $realPath = $file->getRealPath();
        if (! is_string($realPath) || $realPath === '' || ! is_file($realPath)) {
            return $this->fail('No se pudo acceder al archivo temporal cargado.');
        }

        $binary = @file_get_contents($realPath);
        if (! is_string($binary) || $binary === '') {
            return $this->fail('El archivo de imagen está vacío.');
        }

        $clientExtension = strtolower(trim((string) $file->getClientOriginalExtension()));
        $guessedExtension = strtolower(trim((string) ($file->guessExtension() ?? '')));
        $mimeType = strtolower(trim((string) ($file->getMimeType() ?? '')));
        $clientMimeType = strtolower(trim((string) ($file->getClientMimeType() ?? '')));

        if (
            in_array($clientExtension, self::BLOCKED_EXTENSIONS, true)
            || in_array($guessedExtension, self::BLOCKED_EXTENSIONS, true)
        ) {
            return $this->fail('Por seguridad no se permiten archivos SVG, HTML, PHP, XML o scripts.');
        }

        foreach (self::BLOCKED_MIME_FRAGMENTS as $fragment) {
            if (
                ($mimeType !== '' && str_contains($mimeType, $fragment))
                || ($clientMimeType !== '' && str_contains($clientMimeType, $fragment))
            ) {
                return $this->fail('El tipo MIME del archivo no corresponde a una imagen segura permitida.');
            }
        }

        $normalizedHeader = strtolower(ltrim($binary, "\xEF\xBB\xBF \t\r\n\0"));
        if (str_starts_with($normalizedHeader, '<?xml')) {
            return $this->fail('No se permiten documentos XML/SVG disfrazados de imagen.');
        }

        foreach (self::DANGEROUS_BYTE_PATTERNS as $pattern) {
            if (stripos($binary, $pattern) !== false) {
                return $this->fail('El contenido binario contiene código o etiquetas no permitidas.');
            }
        }

        $allowedExtensions = $allowIcons
            ? ['jpg', 'jpeg', 'png', 'webp', 'ico']
            : ['jpg', 'jpeg', 'png', 'webp'];

        if ($clientExtension !== '' && ! in_array($clientExtension, $allowedExtensions, true)) {
            $formatsLabel = $allowIcons ? 'JPG, PNG, WEBP o ICO' : 'JPG, PNG o WEBP';

            return $this->fail("Formato no permitido. Usa una imagen {$formatsLabel}.");
        }

        if ($allowIcons && $this->hasIcoMagicSignature($binary)) {
            if ($clientExtension !== '' && $clientExtension !== 'ico') {
                return $this->fail('La extensión del archivo no coincide con la firma binaria ICO.');
            }

            return [
                'valid' => true,
                'error' => null,
                'extension' => 'ico',
                'mime' => 'image/x-icon',
                'is_icon' => true,
                'width' => null,
                'height' => null,
            ];
        }

        $detected = $this->detectRasterFormat($binary);
        if ($detected === null) {
            $formatsLabel = $allowIcons ? 'JPG, PNG, WEBP o ICO' : 'JPG, PNG o WEBP';

            return $this->fail("La firma binaria del archivo no corresponde a una imagen válida ({$formatsLabel}).");
        }

        $imageInfo = @getimagesizefromstring($binary);
        if (
            ! is_array($imageInfo)
            || ($imageInfo[0] ?? 0) <= 0
            || ($imageInfo[1] ?? 0) <= 0
            || ($imageInfo[2] ?? null) !== $detected['imagetype']
        ) {
            return $this->fail('La estructura binaria de la imagen está dañada o no coincide con su formato.');
        }

        return [
            'valid' => true,
            'error' => null,
            'extension' => $detected['extension'],
            'mime' => $detected['mime'],
            'is_icon' => false,
            'width' => (int) $imageInfo[0],
            'height' => (int) $imageInfo[1],
        ];
    }

    /**
     * Validates and stores an uploaded image on the public disk.
     * Resizes/compresses with GD when ext-gd is available; stores validated bytes directly otherwise.
     */
    public function processAndStore(
        UploadedFile $file,
        string $folder,
        int $maxDimension = 1200,
        int $quality = 82,
        bool $allowIcons = false,
    ): ?string {
        $inspection = $this->inspectBinary($file, $allowIcons);
        if (! $inspection['valid'] || $inspection['extension'] === null) {
            return null;
        }

        $realPath = $file->getRealPath();
        $binary = is_string($realPath) ? @file_get_contents($realPath) : false;
        if (! is_string($binary) || $binary === '') {
            return null;
        }

        $cleanFolder = trim(str_replace(['..', '\\'], ['', '/'], $folder), '/');
        $prefix = $cleanFolder !== '' ? $cleanFolder . '/' : '';
        $targetExtension = $inspection['extension'];
        $payloadToStore = $binary;

        if (! $inspection['is_icon'] && extension_loaded('gd')) {
            $optimized = $this->optimizeWithGd($binary, $targetExtension, $maxDimension, $quality);
            if ($optimized !== null) {
                $payloadToStore = $optimized['bytes'];
                $targetExtension = $optimized['extension'];
            }
        }

        $relativePath = $prefix . Str::random(40) . '.' . $targetExtension;
        $stored = Storage::disk('public')->put($relativePath, $payloadToStore);

        if (! $stored) {
            return null;
        }

        return 'storage/' . ltrim($relativePath, '/');
    }

    /**
     * @return array{bytes: string, extension: string}|null
     */
    private function optimizeWithGd(string $binary, string $originalExtension, int $maxDimension, int $quality): ?array
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $source = @imagecreatefromstring($binary);
        if ($source === false) {
            return null;
        }

        try {
            $origWidth = imagesx($source);
            $origHeight = imagesy($source);

            if ($origWidth <= 0 || $origHeight <= 0) {
                return null;
            }

            $maxDimension = max(64, $maxDimension);
            $quality = max(40, min(95, $quality));

            $scale = min(1.0, $maxDimension / max($origWidth, $origHeight));
            $targetWidth = max(1, (int) round($origWidth * $scale));
            $targetHeight = max(1, (int) round($origHeight * $scale));

            $canvas = $source;
            $createdCanvas = false;

            if ($targetWidth !== $origWidth || $targetHeight !== $origHeight) {
                $resampled = imagecreatetruecolor($targetWidth, $targetHeight);
                if ($resampled !== false) {
                    imagealphablending($resampled, false);
                    imagesavealpha($resampled, true);
                    $transparent = imagecolorallocatealpha($resampled, 0, 0, 0, 127);
                    if ($transparent !== false) {
                        imagefilledrectangle($resampled, 0, 0, $targetWidth, $targetHeight, $transparent);
                    }
                    imagecopyresampled($resampled, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $origWidth, $origHeight);
                    $canvas = $resampled;
                    $createdCanvas = true;
                }
            } else {
                imagealphablending($canvas, false);
                imagesavealpha($canvas, true);
            }

            ob_start();
            $encoded = false;
            $outExt = $originalExtension;

            if (function_exists('imagewebp')) {
                $encoded = @imagewebp($canvas, null, $quality);
                $outExt = 'webp';
            } elseif ($originalExtension === 'png' && function_exists('imagepng')) {
                $encoded = @imagepng($canvas, null, 6);
                $outExt = 'png';
            } elseif (function_exists('imagejpeg')) {
                $encoded = @imagejpeg($canvas, null, $quality);
                $outExt = 'jpg';
            }

            $bytes = ob_get_clean();

            if ($createdCanvas) {
                imagedestroy($canvas);
            }

            if (! $encoded || ! is_string($bytes) || $bytes === '') {
                return null;
            }

            return [
                'bytes' => $bytes,
                'extension' => $outExt,
            ];
        } finally {
            imagedestroy($source);
        }
    }

    private function hasIcoMagicSignature(string $binary): bool
    {
        if (strlen($binary) < 22) {
            return false;
        }

        if (! str_starts_with($binary, "\x00\x00\x01\x00") && ! str_starts_with($binary, "\x00\x00\x02\x00")) {
            return false;
        }

        $unpacked = unpack('vcount', substr($binary, 4, 2));
        $count = is_array($unpacked) ? (int) ($unpacked['count'] ?? 0) : 0;

        return $count > 0 && strlen($binary) >= (6 + ($count * 16));
    }

    /**
     * @return array{extension: string, mime: string, imagetype: int}|null
     */
    private function detectRasterFormat(string $binary): ?array
    {
        if (str_starts_with($binary, "\xFF\xD8\xFF")) {
            return [
                'extension' => 'jpg',
                'mime' => 'image/jpeg',
                'imagetype' => IMAGETYPE_JPEG,
            ];
        }

        if (str_starts_with($binary, "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A")) {
            return [
                'extension' => 'png',
                'mime' => 'image/png',
                'imagetype' => IMAGETYPE_PNG,
            ];
        }

        if (strlen($binary) >= 12 && substr($binary, 0, 4) === 'RIFF' && substr($binary, 8, 4) === 'WEBP') {
            return [
                'extension' => 'webp',
                'mime' => 'image/webp',
                'imagetype' => IMAGETYPE_WEBP,
            ];
        }

        return null;
    }

    /**
     * @return array{
     *     valid: bool,
     *     error: string,
     *     extension: null,
     *     mime: null,
     *     is_icon: bool,
     *     width: null,
     *     height: null
     * }
     */
    private function fail(string $message): array
    {
        return [
            'valid' => false,
            'error' => $message,
            'extension' => null,
            'mime' => null,
            'is_icon' => false,
            'width' => null,
            'height' => null,
        ];
    }
}
