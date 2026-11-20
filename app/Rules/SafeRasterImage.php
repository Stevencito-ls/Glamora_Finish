<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\ImageOptimizerService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class SafeRasterImage implements ValidationRule
{
    public function __construct(
        private readonly bool $allowIcons = false,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail('El archivo seleccionado no es una carga válida.');

            return;
        }

        $inspection = app(ImageOptimizerService::class)->inspectBinary($value, $this->allowIcons);

        if (! $inspection['valid']) {
            $fail($inspection['error'] ?? 'El archivo cargado no superó la validación binaria de imagen segura.');
        }
    }
}
