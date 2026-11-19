<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Events\SecurityActionTriggered;
use App\Http\Controllers\Admin\Concerns\HandlesSafeMediaUploads;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BrandController extends Controller
{
    use HandlesSafeMediaUploads;

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $statusFilter = (string) $request->query('status', '');

        $brands = Brand::query()
            ->withCount('products')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($sub) use ($search): void {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('country_origin', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when($statusFilter === 'active', fn ($query) => $query->where('is_active', true))
            ->when($statusFilter === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.pim.brands.index', [
            'brands' => $brands,
            'search' => $search,
            'statusFilter' => $statusFilter,
        ]);
    }

    public function create(): View
    {
        return view('admin.pim.brands.form', [
            'brand' => new Brand([
                'country_origin' => 'Perú',
                'is_cruelty_free' => true,
                'is_active' => true,
                'sort_order' => 0,
            ]),
            'isEdit' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateBrand($request);
        $logoPath = $this->storeSafePublicImage($request, 'logo_file', 'brands');
        $bannerPath = $this->storeSafePublicImage($request, 'banner_file', 'brands/banners');

        /** @var Brand $brand */
        $brand = $this->runWithMediaCleanup(fn (): Brand => DB::transaction(function () use ($validated, $logoPath, $bannerPath, $request): Brand {
            $slug = $this->resolveUniqueSlug($validated['slug'] ?? null, $validated['name']);

            $brand = Brand::query()->create([
                'name' => trim($validated['name']),
                'slug' => $slug,
                'country_origin' => trim($validated['country_origin']),
                'description' => $validated['description'] ?? null,
                'logo_path' => $logoPath ?? $this->sanitizeSafeImagePath($validated['logo_path'] ?? null),
                'banner_path' => $bannerPath ?? $this->sanitizeSafeImagePath($validated['banner_path'] ?? null),
                'website_url' => $validated['website_url'] ?? null,
                'is_cruelty_free' => $request->boolean('is_cruelty_free', true),
                'is_featured' => $request->boolean('is_featured', false),
                'is_active' => $request->boolean('is_active', true),
                'sort_order' => (int) ($validated['sort_order'] ?? 0),
            ]);

            SecurityActionTriggered::dispatch(
                Auth::user(),
                'catalog_brand_created',
                'info',
                "Marca «{$brand->name}» registrada en el catálogo PIM.",
                ['brand_id' => $brand->id, 'slug' => $brand->slug],
            );

            return $brand;
        }), $logoPath, $bannerPath);

        return redirect()
            ->route('admin.pim.brands.index')
            ->with('admin_success', "Marca «{$brand->name}» creada correctamente.");
    }

    public function edit(Brand $brand): View
    {
        return view('admin.pim.brands.form', [
            'brand' => $brand,
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, Brand $brand): RedirectResponse
    {
        $validated = $this->validateBrand($request, $brand);
        $logoPath = $this->storeSafePublicImage($request, 'logo_file', 'brands');
        $bannerPath = $this->storeSafePublicImage($request, 'banner_file', 'brands/banners');
        $oldLogo = $brand->logo_path;
        $oldBanner = $brand->banner_path;

        $this->runWithMediaCleanup(fn () => DB::transaction(function () use ($brand, $validated, $logoPath, $bannerPath, $request): void {
            $slug = $this->resolveUniqueSlug($validated['slug'] ?? null, $validated['name'], $brand->id);

            $brand->update([
                'name' => trim($validated['name']),
                'slug' => $slug,
                'country_origin' => trim($validated['country_origin']),
                'description' => $validated['description'] ?? null,
                'logo_path' => $logoPath ?? ($this->sanitizeSafeImagePath($validated['logo_path'] ?? null) ?? $brand->logo_path),
                'banner_path' => $bannerPath ?? ($this->sanitizeSafeImagePath($validated['banner_path'] ?? null) ?? $brand->banner_path),
                'website_url' => $validated['website_url'] ?? null,
                'is_cruelty_free' => $request->boolean('is_cruelty_free'),
                'is_featured' => $request->boolean('is_featured'),
                'is_active' => $request->boolean('is_active'),
                'sort_order' => (int) ($validated['sort_order'] ?? 0),
            ]);

            SecurityActionTriggered::dispatch(
                Auth::user(),
                'catalog_brand_updated',
                'info',
                "Marca «{$brand->name}» actualizada.",
                ['brand_id' => $brand->id, 'slug' => $brand->slug],
            );
        }), $logoPath, $bannerPath);

        if ($logoPath !== null && $oldLogo !== null && $oldLogo !== $logoPath) {
            $this->deleteSafePublicImage($oldLogo);
        }

        if ($bannerPath !== null && $oldBanner !== null && $oldBanner !== $bannerPath) {
            $this->deleteSafePublicImage($oldBanner);
        }

        return redirect()
            ->route('admin.pim.brands.index')
            ->with('admin_success', "Marca «{$brand->name}» actualizada correctamente.");
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        if ($brand->products()->withTrashed()->exists()) {
            return back()->with('admin_error', "No se puede eliminar «{$brand->name}» porque tiene productos asociados. Desactívala en su lugar.");
        }

        $name = $brand->name;
        $id = $brand->id;
        $oldLogo = $brand->logo_path;
        $oldBanner = $brand->banner_path;

        DB::transaction(function () use ($brand, $name, $id): void {
            $brand->delete();

            SecurityActionTriggered::dispatch(
                Auth::user(),
                'catalog_brand_deleted',
                'warning',
                "Marca «{$name}» eliminada del catálogo PIM.",
                ['brand_id' => $id],
            );
        });

        $this->deleteSafePublicImage($oldLogo);
        $this->deleteSafePublicImage($oldBanner);

        return redirect()
            ->route('admin.pim.brands.index')
            ->with('admin_success', "Marca «{$name}» eliminada.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validateBrand(Request $request, ?Brand $brand = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'slug' => [
                'nullable',
                'string',
                'max:150',
                Rule::unique('brands', 'slug')->ignore($brand?->id),
            ],
            'country_origin' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:2000'],
            'logo_path' => ['nullable', 'string', 'max:255'],
            'logo_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'banner_path' => ['nullable', 'string', 'max:255'],
            'banner_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'is_cruelty_free' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [
            'name.required' => 'Ingresa el nombre de la marca.',
            'country_origin.required' => 'Ingresa el país de origen.',
            'slug.unique' => 'El slug ya pertenece a otra marca.',
            'website_url.url' => 'Ingresa una URL válida para el sitio web de la marca.',
            'logo_file.max' => 'El logo/fotografía de la marca no debe superar los 4 MB.',
            'banner_file.max' => 'El banner editorial de la marca no debe superar los 4 MB.',
        ]);
    }

    private function resolveUniqueSlug(?string $rawSlug, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug(trim((string) $rawSlug) !== '' ? (string) $rawSlug : $name);
        if ($base === '') {
            $base = 'marca';
        }

        $candidate = $base;
        $suffix = 2;

        while (
            Brand::query()
                ->where('slug', $candidate)
                ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $candidate = "{$base}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }
}
