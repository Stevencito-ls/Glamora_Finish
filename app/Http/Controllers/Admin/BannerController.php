<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\BannerPosition;
use App\Events\SecurityActionTriggered;
use App\Http\Controllers\Admin\Concerns\HandlesSafeMediaUploads;
use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Support\CosmeticImageResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BannerController extends Controller
{
    use HandlesSafeMediaUploads;

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $positionFilter = (string) $request->query('position', '');
        $statusFilter = (string) $request->query('status', '');

        $banners = Banner::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($sub) use ($search): void {
                    $sub->where('title', 'like', "%{$search}%")
                        ->orWhere('subtitle', 'like', "%{$search}%")
                        ->orWhere('eyebrow', 'like', "%{$search}%")
                        ->orWhere('badge_text', 'like', "%{$search}%");
                });
            })
            ->when(in_array($positionFilter, BannerPosition::values(), true), fn ($q) => $q->where('position', $positionFilter))
            ->when($statusFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($statusFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('position')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'total' => Banner::query()->count(),
            'active' => Banner::query()->active()->count(),
            'hero_principal' => Banner::query()->active()->forPosition(BannerPosition::HeroPrincipal)->count(),
            'promo_secundario' => Banner::query()->active()->forPosition(BannerPosition::PromoSecundario)->count(),
            'portal_mayorista' => Banner::query()->active()->forPosition(BannerPosition::PortalMayorista)->count(),
        ];

        $uploadedMedia = $this->collectUploadedMediaFiles();

        return view('admin.banners.index', [
            'banners' => $banners,
            'positions' => BannerPosition::cases(),
            'stats' => $stats,
            'uploadedMedia' => $uploadedMedia,
            'search' => $search,
            'positionFilter' => $positionFilter,
            'statusFilter' => $statusFilter,
        ]);
    }

    public function create(): View
    {
        return view('admin.banners.form', [
            'banner' => new Banner([
                'position' => BannerPosition::HeroPrincipal,
                'is_active' => true,
                'sort_order' => 1,
            ]),
            'positions' => BannerPosition::cases(),
            'isEdit' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateBanner($request, isEdit: false);
        $uploadedMain = $this->storeSafePublicImage($request, 'image_file', 'banners');
        $uploadedAccent = $this->storeSafePublicImage($request, 'accent_image_file', 'banners');

        /** @var Banner $banner */
        $banner = $this->runWithMediaCleanup(fn (): Banner => DB::transaction(function () use ($validated, $uploadedMain, $uploadedAccent, $request): Banner {
            $imagePath = $uploadedMain
                ?? $this->sanitizeSafeImagePath($validated['image_path'] ?? null)
                ?? CosmeticImageResolver::resolve(null, 'banner', $validated['title']);

            $accentImagePath = $uploadedAccent
                ?? $this->sanitizeSafeImagePath($validated['accent_image_path'] ?? null);

            $banner = Banner::query()->create([
                'title' => trim($validated['title']),
                'subtitle' => isset($validated['subtitle']) && trim((string) $validated['subtitle']) !== ''
                    ? trim((string) $validated['subtitle'])
                    : null,
                'eyebrow' => isset($validated['eyebrow']) && trim((string) $validated['eyebrow']) !== ''
                    ? trim((string) $validated['eyebrow'])
                    : null,
                'badge_text' => isset($validated['badge_text']) && trim((string) $validated['badge_text']) !== ''
                    ? trim((string) $validated['badge_text'])
                    : null,
                'button_text' => isset($validated['button_text']) && trim((string) $validated['button_text']) !== ''
                    ? trim((string) $validated['button_text'])
                    : null,
                'button_url' => isset($validated['button_url']) && trim((string) $validated['button_url']) !== ''
                    ? trim((string) $validated['button_url'])
                    : null,
                'secondary_button_text' => isset($validated['secondary_button_text']) && trim((string) $validated['secondary_button_text']) !== ''
                    ? trim((string) $validated['secondary_button_text'])
                    : null,
                'secondary_button_url' => isset($validated['secondary_button_url']) && trim((string) $validated['secondary_button_url']) !== ''
                    ? trim((string) $validated['secondary_button_url'])
                    : null,
                'image_path' => $imagePath,
                'accent_image_path' => $accentImagePath,
                'position' => $validated['position'],
                'is_active' => $request->boolean('is_active', true),
                'sort_order' => (int) ($validated['sort_order'] ?? 0),
            ]);

            SecurityActionTriggered::dispatch(
                Auth::user(),
                'banner_created',
                'info',
                "Banner «{$banner->title}» ({$banner->position->label()}) creado.",
                ['banner_id' => $banner->id, 'position' => $banner->position->value],
            );

            return $banner;
        }), $uploadedMain, $uploadedAccent);

        return redirect()
            ->route('admin.banners.index')
            ->with('admin_success', "Banner «{$banner->title}» creado correctamente.");
    }

    public function edit(Banner $banner): View
    {
        return view('admin.banners.form', [
            'banner' => $banner,
            'positions' => BannerPosition::cases(),
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, Banner $banner): RedirectResponse
    {
        $validated = $this->validateBanner($request, isEdit: true);
        $uploadedMain = $this->storeSafePublicImage($request, 'image_file', 'banners');
        $uploadedAccent = $this->storeSafePublicImage($request, 'accent_image_file', 'banners');
        $oldMain = $banner->image_path;
        $oldAccent = $banner->accent_image_path;

        $this->runWithMediaCleanup(fn () => DB::transaction(function () use ($banner, $validated, $uploadedMain, $uploadedAccent, $request): void {
            $newMain = $uploadedMain
                ?? ($this->sanitizeSafeImagePath($validated['image_path'] ?? null) ?? $banner->image_path);

            $newAccent = $uploadedAccent
                ?? ($this->sanitizeSafeImagePath($validated['accent_image_path'] ?? null) ?? $banner->accent_image_path);

            $banner->update([
                'title' => trim($validated['title']),
                'subtitle' => isset($validated['subtitle']) && trim((string) $validated['subtitle']) !== ''
                    ? trim((string) $validated['subtitle'])
                    : null,
                'eyebrow' => isset($validated['eyebrow']) && trim((string) $validated['eyebrow']) !== ''
                    ? trim((string) $validated['eyebrow'])
                    : null,
                'badge_text' => isset($validated['badge_text']) && trim((string) $validated['badge_text']) !== ''
                    ? trim((string) $validated['badge_text'])
                    : null,
                'button_text' => isset($validated['button_text']) && trim((string) $validated['button_text']) !== ''
                    ? trim((string) $validated['button_text'])
                    : null,
                'button_url' => isset($validated['button_url']) && trim((string) $validated['button_url']) !== ''
                    ? trim((string) $validated['button_url'])
                    : null,
                'secondary_button_text' => isset($validated['secondary_button_text']) && trim((string) $validated['secondary_button_text']) !== ''
                    ? trim((string) $validated['secondary_button_text'])
                    : null,
                'secondary_button_url' => isset($validated['secondary_button_url']) && trim((string) $validated['secondary_button_url']) !== ''
                    ? trim((string) $validated['secondary_button_url'])
                    : null,
                'image_path' => $newMain,
                'accent_image_path' => $newAccent,
                'position' => $validated['position'],
                'is_active' => $request->boolean('is_active'),
                'sort_order' => (int) ($validated['sort_order'] ?? 0),
            ]);

            SecurityActionTriggered::dispatch(
                Auth::user(),
                'banner_updated',
                'info',
                "Banner «{$banner->title}» actualizado.",
                ['banner_id' => $banner->id, 'position' => $banner->position->value],
            );
        }), $uploadedMain, $uploadedAccent);

        if ($uploadedMain !== null && $oldMain !== null && $oldMain !== $uploadedMain) {
            $this->deleteSafePublicImage($oldMain);
        }

        if ($uploadedAccent !== null && $oldAccent !== null && $oldAccent !== $uploadedAccent) {
            $this->deleteSafePublicImage($oldAccent);
        }

        return redirect()
            ->route('admin.banners.index')
            ->with('admin_success', "Banner «{$banner->title}» actualizado correctamente.");
    }

    public function toggle(Banner $banner): RedirectResponse
    {
        $newState = DB::transaction(function () use ($banner): bool {
            /** @var Banner $locked */
            $locked = Banner::query()->lockForUpdate()->findOrFail($banner->id);
            $target = ! (bool) $locked->is_active;

            $locked->update(['is_active' => $target]);

            SecurityActionTriggered::dispatch(
                Auth::user(),
                'banner_toggled',
                'info',
                "Banner «{$locked->title}» " . ($target ? 'activado' : 'desactivado') . '.',
                ['banner_id' => $locked->id, 'is_active' => $target],
            );

            return $target;
        });

        return redirect()
            ->route('admin.banners.index')
            ->with('admin_success', "Banner «{$banner->title}» " . ($newState ? 'activado' : 'desactivado') . ' correctamente.');
    }

    public function destroy(Banner $banner): RedirectResponse
    {
        $title = $banner->title;
        $id = $banner->id;
        $oldMain = $banner->image_path;
        $oldAccent = $banner->accent_image_path;

        DB::transaction(function () use ($banner, $title, $id): void {
            $banner->delete();

            SecurityActionTriggered::dispatch(
                Auth::user(),
                'banner_deleted',
                'warning',
                "Banner «{$title}» eliminado.",
                ['banner_id' => $id],
            );
        });

        $this->deleteSafePublicImage($oldMain);
        $this->deleteSafePublicImage($oldAccent);

        return redirect()
            ->route('admin.banners.index')
            ->with('admin_success', "Banner «{$title}» eliminado correctamente.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validateBanner(Request $request, bool $isEdit): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:180'],
            'subtitle' => ['nullable', 'string', 'max:1000'],
            'eyebrow' => ['nullable', 'string', 'max:100'],
            'badge_text' => ['nullable', 'string', 'max:120'],
            'button_text' => ['nullable', 'string', 'max:80'],
            'button_url' => ['nullable', 'string', 'max:255'],
            'secondary_button_text' => ['nullable', 'string', 'max:80'],
            'secondary_button_url' => ['nullable', 'string', 'max:255'],
            'image_path' => ['nullable', 'string', 'max:255'],
            'image_file' => [
                $isEdit ? 'nullable' : 'required_without:image_path',
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:4096',
            ],
            'accent_image_path' => ['nullable', 'string', 'max:255'],
            'accent_image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'position' => ['required', 'string', Rule::in(BannerPosition::values())],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [
            'title.required' => 'Ingresa el titular principal del banner.',
            'position.required' => 'Selecciona la ubicación del banner en la tienda.',
            'image_file.required_without' => 'Sube una fotografía principal (JPG, PNG o WEBP) o proporciona una URL fotográfica válida.',
            'image_file.max' => 'La fotografía principal del banner no debe superar los 4 MB.',
            'accent_image_file.max' => 'La fotografía secundaria del banner no debe superar los 4 MB.',
        ]);
    }

    /**
     * @return list<array{folder: string, filename: string, url: string, size_kb: float}>
     */
    private function collectUploadedMediaFiles(): array
    {
        $disk = Storage::disk('public');
        $folders = ['banners', 'products', 'variants', 'categories', 'brands', 'blog'];
        $items = [];

        foreach ($folders as $folder) {
            if (! $disk->exists($folder)) {
                continue;
            }

            foreach ($disk->allFiles($folder) as $relativePath) {
                $ext = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
                if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    continue;
                }

                $items[] = [
                    'folder' => $folder,
                    'filename' => basename($relativePath),
                    'url' => asset('storage/' . ltrim($relativePath, '/')),
                    'size_kb' => round($disk->size($relativePath) / 1024, 1),
                ];

                if (count($items) >= 24) {
                    break 2;
                }
            }
        }

        return $items;
    }
}
