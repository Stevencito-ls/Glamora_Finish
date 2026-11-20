<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Events\SecurityActionTriggered;
use App\Http\Controllers\Admin\Concerns\HandlesSafeMediaUploads;
use App\Http\Controllers\Controller;
use App\Rules\SafeRasterImage;
use App\Services\SettingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SettingController extends Controller
{
    use HandlesSafeMediaUploads;

    public function index(SettingService $settingService): View
    {
        $this->authorizeSettingsAccess();

        return view('admin.settings.index', [
            'settings' => $settingService->all(),
            'themes' => $settingService->themes(),
            'activeThemeKey' => $settingService->activeThemeKey(),
            'activeTheme' => $settingService->activeTheme(),
            'logoUrl' => $settingService->logoUrl(),
            'faviconUrl' => $settingService->faviconUrl(),
        ]);
    }

    public function update(Request $request, SettingService $settingService): RedirectResponse
    {
        $this->authorizeSettingsAccess();

        $themeKeys = array_keys($settingService->themes());

        $validated = $request->validate([
            'store_name' => ['required', 'string', 'min:2', 'max:120'],
            'store_tagline' => ['nullable', 'string', 'max:180'],
            'store_ruc' => ['nullable', 'string', 'regex:/^\d{11}$/'],
            'store_email' => ['required', 'email:rfc', 'max:150'],
            'store_phone' => ['nullable', 'string', 'max:30'],
            'store_address' => ['nullable', 'string', 'max:255'],
            'social_instagram' => ['nullable', 'url:http,https', 'max:255'],
            'social_facebook' => ['nullable', 'url:http,https', 'max:255'],
            'social_tiktok' => ['nullable', 'url:http,https', 'max:255'],
            'whatsapp_number' => ['nullable', 'string', 'max:30'],
            'whatsapp_checkout_enabled' => ['nullable', 'boolean'],
            'whatsapp_floating_enabled' => ['nullable', 'boolean'],
            'free_shipping_threshold' => ['required', 'numeric', 'min:0', 'max:50000'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'active_theme' => ['required', 'string', Rule::in($themeKeys)],
            'catalog_pdf_enabled' => ['nullable', 'boolean'],
            'reviews_auto_approve' => ['nullable', 'boolean'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_favicon' => ['nullable', 'boolean'],
            'logo_file' => ['nullable', 'file', 'max:4096', new SafeRasterImage(allowIcons: false)],
            'favicon_file' => ['nullable', 'file', 'max:1024', new SafeRasterImage(allowIcons: true)],
        ], [
            'store_name.required' => 'Ingresa el nombre comercial de la tienda.',
            'store_ruc.regex' => 'El RUC debe contener exactamente 11 dígitos numéricos.',
            'store_email.required' => 'Ingresa el correo comercial de contacto.',
            'store_email.email' => 'Ingresa un correo electrónico válido.',
            'social_instagram.url' => 'La URL de Instagram debe iniciar con http:// o https://.',
            'social_facebook.url' => 'La URL de Facebook debe iniciar con http:// o https://.',
            'social_tiktok.url' => 'La URL de TikTok debe iniciar con http:// o https://.',
            'free_shipping_threshold.required' => 'Ingresa el umbral mínimo para envío gratis.',
            'tax_rate.required' => 'Ingresa la tasa de IGV en decimal (ej. 0.18).',
            'tax_rate.max' => 'La tasa IGV decimal debe estar entre 0.00 y 1.00 (ej. 0.18 para 18%).',
            'active_theme.in' => 'El tema visual seleccionado no es válido.',
            'logo_file.max' => 'El logotipo no debe superar los 4 MB.',
            'favicon_file.max' => 'El favicon no debe superar 1 MB.',
        ]);

        $uploadedLogo = $this->storeSafePublicImage(
            request: $request,
            field: 'logo_file',
            folder: 'branding',
            allowIcons: false,
            maxDimension: 800,
            quality: 88,
        );

        $uploadedFavicon = $this->storeSafePublicImage(
            request: $request,
            field: 'favicon_file',
            folder: 'branding',
            allowIcons: true,
            maxDimension: 256,
            quality: 90,
        );

        $oldLogo = $settingService->string('store_logo_path') ?: null;
        $oldFavicon = $settingService->string('store_favicon_path') ?: null;
        $removeLogo = $request->boolean('remove_logo');
        $removeFavicon = $request->boolean('remove_favicon');
        $pathsToDelete = [];

        $this->runWithMediaCleanup(function () use (
            $validated,
            $request,
            $settingService,
            $uploadedLogo,
            $uploadedFavicon,
            $oldLogo,
            $oldFavicon,
            $removeLogo,
            $removeFavicon,
            &$pathsToDelete,
        ): void {
            DB::transaction(function () use (
                $validated,
                $request,
                $settingService,
                $uploadedLogo,
                $uploadedFavicon,
                $oldLogo,
                $oldFavicon,
                $removeLogo,
                $removeFavicon,
                &$pathsToDelete,
            ): void {
                $newLogo = $uploadedLogo ?? ($removeLogo ? null : $oldLogo);
                $newFavicon = $uploadedFavicon ?? ($removeFavicon ? null : $oldFavicon);

                if (($uploadedLogo !== null || $removeLogo) && $oldLogo !== null && $oldLogo !== $newLogo) {
                    $pathsToDelete[] = $oldLogo;
                }

                if (($uploadedFavicon !== null || $removeFavicon) && $oldFavicon !== null && $oldFavicon !== $newFavicon) {
                    $pathsToDelete[] = $oldFavicon;
                }

                $settingService->setMany([
                    'store_name' => trim((string) $validated['store_name']),
                    'store_tagline' => isset($validated['store_tagline']) ? trim((string) $validated['store_tagline']) : null,
                    'store_ruc' => isset($validated['store_ruc']) ? trim((string) $validated['store_ruc']) : null,
                    'store_email' => mb_strtolower(trim((string) $validated['store_email'])),
                    'store_phone' => isset($validated['store_phone']) ? trim((string) $validated['store_phone']) : null,
                    'store_address' => isset($validated['store_address']) ? trim((string) $validated['store_address']) : null,
                    'store_logo_path' => $newLogo,
                    'store_favicon_path' => $newFavicon,
                    'social_instagram' => isset($validated['social_instagram']) ? trim((string) $validated['social_instagram']) : null,
                    'social_facebook' => isset($validated['social_facebook']) ? trim((string) $validated['social_facebook']) : null,
                    'social_tiktok' => isset($validated['social_tiktok']) ? trim((string) $validated['social_tiktok']) : null,
                    'whatsapp_number' => isset($validated['whatsapp_number']) ? trim((string) $validated['whatsapp_number']) : null,
                    'whatsapp_checkout_enabled' => $request->boolean('whatsapp_checkout_enabled'),
                    'whatsapp_floating_enabled' => $request->boolean('whatsapp_floating_enabled'),
                    'free_shipping_threshold' => round((float) $validated['free_shipping_threshold'], 2),
                    'tax_rate' => round((float) $validated['tax_rate'], 4),
                    'active_theme' => (string) $validated['active_theme'],
                    'catalog_pdf_enabled' => $request->boolean('catalog_pdf_enabled'),
                    'reviews_auto_approve' => $request->boolean('reviews_auto_approve'),
                ]);

                SecurityActionTriggered::dispatch(
                    Auth::user(),
                    'settings_updated',
                    'info',
                    'Configuración White-Label y parámetros comerciales actualizados.',
                    [
                        'active_theme' => $validated['active_theme'],
                        'free_shipping_threshold' => (float) $validated['free_shipping_threshold'],
                        'tax_rate' => (float) $validated['tax_rate'],
                    ],
                );
            });
        }, $uploadedLogo, $uploadedFavicon);

        foreach (array_unique($pathsToDelete) as $oldPath) {
            $this->deleteSafePublicImage($oldPath);
        }

        return redirect()
            ->route('admin.settings.index')
            ->with('admin_success', 'Configuración White-Label, tema visual y reglas comerciales guardados correctamente.');
    }

    public function dismissOnboarding(Request $request, SettingService $settingService): RedirectResponse|JsonResponse
    {
        $this->authorizeSettingsAccess();

        $settingService->set('onboarding_dismissed', true);

        SecurityActionTriggered::dispatch(
            Auth::user(),
            'onboarding_dismissed',
            'info',
            'Guía inicial de configuración ocultada desde el dashboard.',
        );

        if ($request->expectsJson()) {
            return response()->json(['dismissed' => true]);
        }

        return redirect()
            ->route('admin.dashboard')
            ->with('admin_success', 'Guía de configuración inicial ocultada. Puedes revisar todos los ajustes en Configuración White-Label.');
    }

    private function authorizeSettingsAccess(): void
    {
        $user = Auth::user();
        abort_unless($user !== null && (bool) $user->is_active && $user->can('settings.manage'), 403);
    }
}
