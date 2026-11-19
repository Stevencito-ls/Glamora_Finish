<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\CustomerTier;
use App\Events\SecurityActionTriggered;
use App\Http\Controllers\Admin\Concerns\HandlesSafeMediaUploads;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TieredPrice;
use App\Support\CosmeticImageResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    use HandlesSafeMediaUploads;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Product::class);

        $search = trim((string) $request->query('q', ''));
        $categoryId = $request->query('category_id');
        $brandId = $request->query('brand_id');
        $statusFilter = (string) $request->query('status', '');
        $b2bFilter = (string) $request->query('b2b', '');
        $today = now()->toDateString();

        $query = Product::query()
            ->with(['category', 'brand'])
            ->withCount('variants')
            ->when($statusFilter === 'trashed', fn ($q) => $q->onlyTrashed())
            ->when($statusFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($statusFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($categoryId !== null && $categoryId !== '', fn ($q) => $q->where('category_id', (int) $categoryId))
            ->when($brandId !== null && $brandId !== '', fn ($q) => $q->where('brand_id', (int) $brandId))
            ->when($b2bFilter === '1', fn ($q) => $q->where('is_b2b_kit', true))
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('sanitary_registration', 'like', "%{$search}%")
                        ->orWhereHas('variants', function ($vq) use ($search): void {
                            $vq->where('sku', 'like', "%{$search}%")
                                ->orWhere('shade_name', 'like', "%{$search}%");
                        });
                });
            })
            ->latest('id');

        $products = $query->paginate(15)->withQueryString();

        $productIds = $products->getCollection()->pluck('id')->all();
        $stockByProduct = $productIds !== []
            ? DB::table('product_variants')
                ->leftJoin('product_batches', function ($join) use ($today): void {
                    $join->on('product_batches.product_variant_id', '=', 'product_variants.id')
                        ->where('product_batches.status', '=', 'active')
                        ->whereDate('product_batches.expiration_date', '>', $today)
                        ->whereColumn('product_batches.current_quantity', '>', 'product_batches.reserved_quantity');
                })
                ->whereIn('product_variants.product_id', $productIds)
                ->where('product_variants.is_active', true)
                ->groupBy('product_variants.product_id')
                ->selectRaw('product_variants.product_id, COALESCE(SUM(CASE WHEN product_batches.current_quantity > product_batches.reserved_quantity THEN product_batches.current_quantity - product_batches.reserved_quantity ELSE 0 END), 0) as available_stock')
                ->pluck('available_stock', 'product_id')
            : collect();

        $categories = Category::query()->with('parent')->ordered()->get();
        $brands = Brand::query()->orderBy('name')->get();

        return view('admin.pim.products.index', [
            'products' => $products,
            'stockByProduct' => $stockByProduct,
            'categories' => $categories,
            'brands' => $brands,
            'search' => $search,
            'categoryId' => $categoryId,
            'brandId' => $brandId,
            'statusFilter' => $statusFilter,
            'b2bFilter' => $b2bFilter,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Product::class);

        return view('admin.pim.products.create', [
            'categories' => Category::query()->with('parent')->ordered()->get(),
            'brands' => Brand::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $request->merge([
            'variant_sku' => strtoupper(trim((string) $request->input('variant_sku', ''))),
            'variant_barcode' => ($rawBarcode = trim((string) $request->input('variant_barcode', ''))) !== '' ? $rawBarcode : null,
        ]);

        $validated = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'name' => ['required', 'string', 'min:3', 'max:180'],
            'slug' => ['nullable', 'string', 'max:150', 'unique:products,slug'],
            'short_description' => ['nullable', 'string', 'max:300'],
            'description' => ['nullable', 'string'],
            'sanitary_registration' => ['required', 'string', 'max:100'],
            'ingredients' => ['nullable', 'string'],
            'how_to_use' => ['nullable', 'string'],
            'skin_type' => ['nullable', 'string', 'max:100'],
            'finish_type' => ['nullable', 'string', 'max:80'],
            'min_stock_alert' => ['required', 'integer', 'min:0', 'max:100000'],
            'reorder_point' => ['required', 'integer', 'min:0', 'max:100000', 'gte:min_stock_alert'],
            'is_featured' => ['nullable', 'boolean'],
            'is_new_launch' => ['nullable', 'boolean'],
            'is_b2b_kit' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'meta_title' => ['nullable', 'string', 'max:180'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'main_image' => ['nullable', 'string', 'max:255'],
            'main_image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'gallery_files' => ['nullable', 'array', 'max:10'],
            'gallery_files.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],

            // Initial default variant
            'variant_sku' => ['required', 'string', 'max:80', 'unique:product_variants,sku'],
            'variant_shade_name' => ['required', 'string', 'max:120'],
            'variant_hex_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'variant_weight_grams' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'variant_volume_ml' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'variant_base_price' => ['required', 'numeric', 'min:0.10', 'max:999999'],
            'variant_wholesale_base_price' => ['required', 'numeric', 'min:0.10', 'max:999999'],
            'variant_cost_price' => ['required', 'numeric', 'min:0', 'max:999999'],
            'variant_barcode' => ['nullable', 'string', 'max:80', 'unique:product_variants,barcode'],
            'variant_image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], [
            'name.required' => 'Ingresa el nombre comercial del producto.',
            'sanitary_registration.required' => 'El registro sanitario NSOC DIGEMID es obligatorio.',
            'reorder_point.gte' => 'El punto de reorden debe ser mayor o igual al stock mínimo de alerta.',
            'variant_sku.required' => 'Ingresa el SKU de la variante inicial.',
            'variant_sku.unique' => 'El SKU ingresado ya existe en el catálogo.',
            'variant_hex_color.regex' => 'El color hexadecimal debe tener formato #RRGGBB (ej. #C87D72).',
            'variant_barcode.unique' => 'El código de barras ya pertenece a otra variante.',
            'main_image_file.max' => 'La fotografía principal no debe superar los 4 MB.',
            'gallery_files.*.max' => 'Cada fotografía de la galería no debe superar los 4 MB.',
            'variant_image_file.max' => 'La fotografía de la variante no debe superar los 4 MB.',
        ]);

        $uploadedMainImage = $this->storeSafePublicImage($request, 'main_image_file', 'products');
        $uploadedGallery = $this->storeSafePublicImages($request, 'gallery_files', 'products/gallery');
        $uploadedVariantImage = $this->storeSafePublicImage($request, 'variant_image_file', 'variants');

        /** @var Product $product */
        $product = $this->runWithMediaCleanup(fn (): Product => DB::transaction(function () use ($validated, $uploadedMainImage, $uploadedGallery, $uploadedVariantImage, $request): Product {
            $slug = $this->resolveUniqueSlug($validated['slug'] ?? null, $validated['name']);
            $mainImage = $uploadedMainImage
                ?? $this->sanitizeSafeImagePath($validated['main_image'] ?? null)
                ?? CosmeticImageResolver::resolve(null, 'product', $slug);

            $gallery = array_values(array_unique(array_filter([
                $mainImage,
                ...$uploadedGallery,
            ])));

            $product = Product::query()->create([
                'category_id' => (int) $validated['category_id'],
                'brand_id' => (int) $validated['brand_id'],
                'name' => trim($validated['name']),
                'slug' => $slug,
                'short_description' => $validated['short_description'] ?? null,
                'description' => $validated['description'] ?? null,
                'sanitary_registration' => strtoupper(trim($validated['sanitary_registration'])),
                'ingredients' => $validated['ingredients'] ?? null,
                'how_to_use' => $validated['how_to_use'] ?? null,
                'skin_type' => $validated['skin_type'] ?? null,
                'finish_type' => $validated['finish_type'] ?? null,
                'min_stock_alert' => (int) $validated['min_stock_alert'],
                'reorder_point' => (int) $validated['reorder_point'],
                'is_featured' => $request->boolean('is_featured'),
                'is_new_launch' => $request->boolean('is_new_launch'),
                'is_b2b_kit' => $request->boolean('is_b2b_kit'),
                'is_active' => $request->boolean('is_active', true),
                'meta_title' => $validated['meta_title'] ?? null,
                'meta_description' => $validated['meta_description'] ?? null,
                'main_image' => $mainImage,
                'gallery' => $gallery,
            ]);

            ProductVariant::query()->create([
                'product_id' => $product->id,
                'sku' => strtoupper(trim($validated['variant_sku'])),
                'shade_name' => trim($validated['variant_shade_name']),
                'hex_color' => ! empty($validated['variant_hex_color']) ? strtoupper((string) $validated['variant_hex_color']) : '#C87D72',
                'weight_grams' => isset($validated['variant_weight_grams']) && $validated['variant_weight_grams'] !== '' ? (float) $validated['variant_weight_grams'] : null,
                'volume_ml' => isset($validated['variant_volume_ml']) && $validated['variant_volume_ml'] !== '' ? (float) $validated['variant_volume_ml'] : null,
                'base_price' => (float) $validated['variant_base_price'],
                'wholesale_base_price' => (float) $validated['variant_wholesale_base_price'],
                'cost_price' => (float) $validated['variant_cost_price'],
                'barcode' => isset($validated['variant_barcode']) && trim((string) $validated['variant_barcode']) !== '' ? trim((string) $validated['variant_barcode']) : null,
                'image_path' => $uploadedVariantImage ?? $product->main_image,
                'is_default' => true,
                'is_active' => true,
            ]);

            SecurityActionTriggered::dispatch(
                Auth::user(),
                'catalog_product_created',
                'info',
                "Producto «{$product->name}» (NSOC {$product->sanitary_registration}) creado en el PIM.",
                ['product_id' => $product->id, 'slug' => $product->slug],
            );

            return $product;
        }), $uploadedMainImage, $uploadedVariantImage, ...$uploadedGallery);

        return redirect()
            ->route('admin.pim.products.edit', $product)
            ->with('admin_success', "Producto «{$product->name}» creado con su variante inicial. Ahora puedes añadir más tonos o precios escalonados.");
    }

    public function edit(Product $product): View
    {
        $this->authorize('view', $product);

        $product->load([
            'category',
            'brand',
            'variants' => fn ($q) => $q->orderByDesc('is_default')->orderBy('id'),
            'variants.tieredPrices' => fn ($q) => $q->orderBy('customer_tier')->orderBy('min_quantity'),
            'variants.batches',
        ]);

        return view('admin.pim.products.edit', [
            'product' => $product,
            'categories' => Category::query()->with('parent')->ordered()->get(),
            'brands' => Brand::query()->orderBy('name')->get(),
            'customerTiers' => CustomerTier::cases(),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $validated = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'name' => ['required', 'string', 'min:3', 'max:180'],
            'slug' => ['nullable', 'string', 'max:150', Rule::unique('products', 'slug')->ignore($product->id)],
            'short_description' => ['nullable', 'string', 'max:300'],
            'description' => ['nullable', 'string'],
            'sanitary_registration' => ['required', 'string', 'max:100'],
            'ingredients' => ['nullable', 'string'],
            'how_to_use' => ['nullable', 'string'],
            'skin_type' => ['nullable', 'string', 'max:100'],
            'finish_type' => ['nullable', 'string', 'max:80'],
            'min_stock_alert' => ['required', 'integer', 'min:0', 'max:100000'],
            'reorder_point' => ['required', 'integer', 'min:0', 'max:100000', 'gte:min_stock_alert'],
            'is_featured' => ['nullable', 'boolean'],
            'is_new_launch' => ['nullable', 'boolean'],
            'is_b2b_kit' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'meta_title' => ['nullable', 'string', 'max:180'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'main_image' => ['nullable', 'string', 'max:255'],
            'main_image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'gallery_files' => ['nullable', 'array', 'max:10'],
            'gallery_files.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_gallery' => ['nullable', 'array'],
            'remove_gallery.*' => ['nullable', 'string', 'max:500'],
        ], [
            'sanitary_registration.required' => 'El registro sanitario NSOC DIGEMID es obligatorio.',
            'reorder_point.gte' => 'El punto de reorden debe ser mayor o igual al stock mínimo de alerta.',
            'main_image_file.max' => 'La fotografía principal no debe superar los 4 MB.',
            'gallery_files.*.max' => 'Cada fotografía de la galería no debe superar los 4 MB.',
        ]);

        $uploadedMainImage = $this->storeSafePublicImage($request, 'main_image_file', 'products');
        $uploadedGallery = $this->storeSafePublicImages($request, 'gallery_files', 'products/gallery');
        $oldMainImage = $product->main_image;
        $pathsToDeleteAfterCommit = [];

        $this->runWithMediaCleanup(fn () => DB::transaction(function () use ($product, $validated, $uploadedMainImage, $uploadedGallery, $oldMainImage, $request, &$pathsToDeleteAfterCommit): void {
            $slug = $this->resolveUniqueSlug($validated['slug'] ?? null, $validated['name'], $product->id);

            $newMainImage = $uploadedMainImage
                ?? ($this->sanitizeSafeImagePath($validated['main_image'] ?? null) ?? $product->main_image);

            if ($uploadedMainImage !== null && $oldMainImage !== null && $oldMainImage !== $uploadedMainImage) {
                $pathsToDeleteAfterCommit[] = $oldMainImage;
            }

            $existingGallery = is_array($product->gallery) ? $product->gallery : [];
            $removeList = is_array($validated['remove_gallery'] ?? null)
                ? array_filter(array_map('trim', $validated['remove_gallery']))
                : [];

            $keptGallery = [];
            foreach ($existingGallery as $entry) {
                if (! is_string($entry) || trim($entry) === '') {
                    continue;
                }

                $cleanEntry = trim($entry);
                if (in_array($cleanEntry, $removeList, true)) {
                    $pathsToDeleteAfterCommit[] = $cleanEntry;
                    continue;
                }

                if ($this->sanitizeSafeImagePath($cleanEntry) !== null) {
                    $keptGallery[] = $cleanEntry;
                }
            }

            $mergedGallery = array_values(array_unique(array_filter([
                $newMainImage,
                ...$keptGallery,
                ...$uploadedGallery,
            ])));

            $product->update([
                'category_id' => (int) $validated['category_id'],
                'brand_id' => (int) $validated['brand_id'],
                'name' => trim($validated['name']),
                'slug' => $slug,
                'short_description' => $validated['short_description'] ?? null,
                'description' => $validated['description'] ?? null,
                'sanitary_registration' => strtoupper(trim($validated['sanitary_registration'])),
                'ingredients' => $validated['ingredients'] ?? null,
                'how_to_use' => $validated['how_to_use'] ?? null,
                'skin_type' => $validated['skin_type'] ?? null,
                'finish_type' => $validated['finish_type'] ?? null,
                'min_stock_alert' => (int) $validated['min_stock_alert'],
                'reorder_point' => (int) $validated['reorder_point'],
                'is_featured' => $request->boolean('is_featured'),
                'is_new_launch' => $request->boolean('is_new_launch'),
                'is_b2b_kit' => $request->boolean('is_b2b_kit'),
                'is_active' => $request->boolean('is_active'),
                'meta_title' => $validated['meta_title'] ?? null,
                'meta_description' => $validated['meta_description'] ?? null,
                'main_image' => $newMainImage,
                'gallery' => $mergedGallery,
            ]);

            SecurityActionTriggered::dispatch(
                Auth::user(),
                'catalog_product_updated',
                'info',
                "Ficha técnica del producto «{$product->name}» actualizada.",
                ['product_id' => $product->id, 'sanitary_registration' => $product->sanitary_registration],
            );
        }), $uploadedMainImage, ...$uploadedGallery);

        foreach (array_unique($pathsToDeleteAfterCommit) as $oldPath) {
            $this->deleteSafePublicImage($oldPath);
        }

        return redirect()
            ->route('admin.pim.products.edit', $product)
            ->with('admin_success', "Ficha de «{$product->name}» actualizada correctamente.");
    }

    public function storeVariant(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $request->merge([
            'sku' => strtoupper(trim((string) $request->input('sku', ''))),
            'barcode' => ($rawBarcode = trim((string) $request->input('barcode', ''))) !== '' ? $rawBarcode : null,
        ]);

        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:80', 'unique:product_variants,sku'],
            'shade_name' => ['required', 'string', 'max:120'],
            'hex_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'weight_grams' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'volume_ml' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'base_price' => ['required', 'numeric', 'min:0.10', 'max:999999'],
            'wholesale_base_price' => ['required', 'numeric', 'min:0.10', 'max:999999'],
            'cost_price' => ['required', 'numeric', 'min:0', 'max:999999'],
            'barcode' => ['nullable', 'string', 'max:80', 'unique:product_variants,barcode'],
            'image_path' => ['nullable', 'string', 'max:255'],
            'image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'sku.required' => 'El SKU de la variante es obligatorio.',
            'sku.unique' => 'El SKU ingresado ya está en uso.',
            'hex_color.regex' => 'El tono hexadecimal debe tener formato #RRGGBB.',
            'barcode.unique' => 'El código de barras ya está asignado a otra variante.',
            'image_file.max' => 'La fotografía de la variante no debe superar los 4 MB.',
        ]);

        $uploadedImage = $this->storeSafePublicImage($request, 'image_file', 'variants');

        $this->runWithMediaCleanup(fn () => DB::transaction(function () use ($product, $validated, $uploadedImage, $request): void {
            $makeDefault = $request->boolean('is_default') || ! $product->variants()->exists();

            if ($makeDefault) {
                $product->variants()->update(['is_default' => false]);
            }

            $variant = ProductVariant::query()->create([
                'product_id' => $product->id,
                'sku' => strtoupper(trim($validated['sku'])),
                'shade_name' => trim($validated['shade_name']),
                'hex_color' => ! empty($validated['hex_color']) ? strtoupper((string) $validated['hex_color']) : '#C87D72',
                'weight_grams' => isset($validated['weight_grams']) && $validated['weight_grams'] !== '' ? (float) $validated['weight_grams'] : null,
                'volume_ml' => isset($validated['volume_ml']) && $validated['volume_ml'] !== '' ? (float) $validated['volume_ml'] : null,
                'base_price' => (float) $validated['base_price'],
                'wholesale_base_price' => (float) $validated['wholesale_base_price'],
                'cost_price' => (float) $validated['cost_price'],
                'barcode' => isset($validated['barcode']) && trim((string) $validated['barcode']) !== '' ? trim((string) $validated['barcode']) : null,
                'image_path' => $uploadedImage ?? ($this->sanitizeSafeImagePath($validated['image_path'] ?? null) ?? $product->main_image),
                'is_default' => $makeDefault,
                'is_active' => $request->boolean('is_active', true),
            ]);

            SecurityActionTriggered::dispatch(
                Auth::user(),
                'catalog_variant_created',
                'info',
                "Variante {$variant->sku} ({$variant->shade_name}) añadida a «{$product->name}».",
                ['product_id' => $product->id, 'variant_id' => $variant->id, 'sku' => $variant->sku],
            );
        }), $uploadedImage);

        return redirect()
            ->route('admin.pim.products.edit', $product)
            ->with('admin_success', 'Variante registrada correctamente.');
    }

    public function updateVariant(Request $request, Product $product, ProductVariant $variant): RedirectResponse
    {
        $this->authorize('update', $product);
        abort_unless($variant->product_id === $product->id, 404);

        $request->merge([
            'sku' => strtoupper(trim((string) $request->input('sku', ''))),
            'barcode' => ($rawBarcode = trim((string) $request->input('barcode', ''))) !== '' ? $rawBarcode : null,
        ]);

        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:80', Rule::unique('product_variants', 'sku')->ignore($variant->id)],
            'shade_name' => ['required', 'string', 'max:120'],
            'hex_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'weight_grams' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'volume_ml' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'base_price' => ['required', 'numeric', 'min:0.10', 'max:999999'],
            'wholesale_base_price' => ['required', 'numeric', 'min:0.10', 'max:999999'],
            'cost_price' => ['required', 'numeric', 'min:0', 'max:999999'],
            'barcode' => ['nullable', 'string', 'max:80', Rule::unique('product_variants', 'barcode')->ignore($variant->id)],
            'image_path' => ['nullable', 'string', 'max:255'],
            'image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'sku.unique' => 'El SKU ingresado ya pertenece a otra variante.',
            'hex_color.regex' => 'El tono hexadecimal debe tener formato #RRGGBB.',
            'barcode.unique' => 'El código de barras ya pertenece a otra variante.',
            'image_file.max' => 'La fotografía de la variante no debe superar los 4 MB.',
        ]);

        $uploadedImage = $this->storeSafePublicImage($request, 'image_file', 'variants');
        $oldImagePath = $variant->image_path;

        $this->runWithMediaCleanup(fn () => DB::transaction(function () use ($product, $variant, $validated, $uploadedImage, $request): void {
            $makeDefault = $request->boolean('is_default')
                || ! $product->variants()->where('id', '!=', $variant->id)->where('is_default', true)->exists();

            if ($makeDefault) {
                $product->variants()
                    ->where('id', '!=', $variant->id)
                    ->update(['is_default' => false]);
            }

            $newImagePath = $uploadedImage
                ?? ($this->sanitizeSafeImagePath($validated['image_path'] ?? null) ?? $variant->image_path);

            $variant->update([
                'sku' => strtoupper(trim($validated['sku'])),
                'shade_name' => trim($validated['shade_name']),
                'hex_color' => ! empty($validated['hex_color']) ? strtoupper((string) $validated['hex_color']) : '#C87D72',
                'weight_grams' => isset($validated['weight_grams']) && $validated['weight_grams'] !== '' ? (float) $validated['weight_grams'] : null,
                'volume_ml' => isset($validated['volume_ml']) && $validated['volume_ml'] !== '' ? (float) $validated['volume_ml'] : null,
                'base_price' => (float) $validated['base_price'],
                'wholesale_base_price' => (float) $validated['wholesale_base_price'],
                'cost_price' => (float) $validated['cost_price'],
                'barcode' => isset($validated['barcode']) && trim((string) $validated['barcode']) !== '' ? trim((string) $validated['barcode']) : null,
                'image_path' => $newImagePath,
                'is_default' => $makeDefault,
                'is_active' => $request->boolean('is_active'),
            ]);

            SecurityActionTriggered::dispatch(
                Auth::user(),
                'catalog_variant_updated',
                'info',
                "Variante {$variant->sku} actualizada en «{$product->name}».",
                ['product_id' => $product->id, 'variant_id' => $variant->id, 'sku' => $variant->sku],
            );
        }), $uploadedImage);

        if ($uploadedImage !== null && $oldImagePath !== null && $oldImagePath !== $uploadedImage && $oldImagePath !== $product->main_image) {
            $this->deleteSafePublicImage($oldImagePath);
        }

        return redirect()
            ->route('admin.pim.products.edit', $product)
            ->with('admin_success', "Variante {$variant->sku} actualizada correctamente.");
    }

    public function destroyVariant(Product $product, ProductVariant $variant): RedirectResponse
    {
        $this->authorize('update', $product);
        abort_unless($variant->product_id === $product->id, 404);

        if ($product->variants()->count() <= 1) {
            return back()->with('admin_error', 'El producto debe mantener al menos una variante.');
        }

        if ($variant->batches()->exists() || $variant->orderItems()->exists() || $variant->inventoryMovements()->exists() || $variant->returnItems()->exists()) {
            return back()->with('admin_error', "La variante {$variant->sku} tiene lotes FEFO o historial operativo; desactívala en lugar de eliminarla.");
        }

        $sku = $variant->sku;
        $oldImagePath = $variant->image_path;

        DB::transaction(function () use ($product, $variant, $sku): void {
            $wasDefault = (bool) $variant->is_default;
            $variant->delete();

            if ($wasDefault) {
                $next = $product->variants()->orderBy('id')->first();
                if ($next instanceof ProductVariant) {
                    $next->update(['is_default' => true]);
                }
            }

            SecurityActionTriggered::dispatch(
                Auth::user(),
                'catalog_variant_deleted',
                'warning',
                "Variante {$sku} eliminada de «{$product->name}».",
                ['product_id' => $product->id, 'sku' => $sku],
            );
        });

        if ($oldImagePath !== null && $oldImagePath !== $product->main_image) {
            $this->deleteSafePublicImage($oldImagePath);
        }

        return redirect()
            ->route('admin.pim.products.edit', $product)
            ->with('admin_success', "Variante {$sku} eliminada.");
    }

    public function storeTieredPrice(Request $request, Product $product, ProductVariant $variant): RedirectResponse
    {
        $this->authorize('update', $product);
        abort_unless($variant->product_id === $product->id, 404);

        $validated = $request->validate([
            'customer_tier' => ['required', 'string', Rule::in(CustomerTier::values())],
            'min_quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'max_quantity' => ['nullable', 'integer', 'gte:min_quantity', 'max:1000000'],
            'unit_price' => ['required', 'numeric', 'min:0.10', 'max:999999'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:95'],
        ], [
            'max_quantity.gte' => 'La cantidad máxima debe ser mayor o igual a la cantidad mínima.',
        ]);

        $tier = CustomerTier::from($validated['customer_tier']);
        $unitPrice = round((float) $validated['unit_price'], 2);
        $basePrice = max(0.01, (float) $variant->base_price);

        $discountPercent = isset($validated['discount_percent']) && $validated['discount_percent'] !== ''
            ? round((float) $validated['discount_percent'], 2)
            : max(0.0, round((($basePrice - $unitPrice) / $basePrice) * 100, 2));

        DB::transaction(function () use ($variant, $tier, $validated, $unitPrice, $discountPercent): void {
            /** @var ProductVariant $lockedVariant */
            $lockedVariant = ProductVariant::query()->lockForUpdate()->findOrFail($variant->id);

            TieredPrice::query()->updateOrCreate(
                [
                    'product_variant_id' => $lockedVariant->id,
                    'customer_tier' => $tier->value,
                    'min_quantity' => (int) $validated['min_quantity'],
                ],
                [
                    'max_quantity' => isset($validated['max_quantity']) && $validated['max_quantity'] !== ''
                        ? (int) $validated['max_quantity']
                        : null,
                    'unit_price' => $unitPrice,
                    'discount_percent' => $discountPercent,
                ]
            );
        });

        return redirect()
            ->route('admin.pim.products.edit', $product)
            ->with('admin_success', "Precio escalonado {$tier->label()} (desde {$validated['min_quantity']} uds.) guardado para {$variant->sku}.");
    }

    public function destroyTieredPrice(Product $product, ProductVariant $variant, TieredPrice $tieredPrice): RedirectResponse
    {
        $this->authorize('update', $product);
        abort_unless($variant->product_id === $product->id && $tieredPrice->product_variant_id === $variant->id, 404);

        $tieredPrice->delete();

        return redirect()
            ->route('admin.pim.products.edit', $product)
            ->with('admin_success', 'Regla de precio escalonado eliminada.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        $name = $product->name;

        DB::transaction(function () use ($product, $name): void {
            $product->update(['is_active' => false]);
            $product->delete();

            SecurityActionTriggered::dispatch(
                Auth::user(),
                'catalog_product_deleted',
                'warning',
                "Producto «{$name}» archivado (SoftDelete).",
                ['product_id' => $product->id],
            );
        });

        return redirect()
            ->route('admin.pim.products.index')
            ->with('admin_success', "Producto «{$name}» archivado correctamente.");
    }

    public function restore(int $productId): RedirectResponse
    {
        $product = Product::withTrashed()->findOrFail($productId);
        $this->authorize('restore', $product);

        DB::transaction(function () use ($product): void {
            $product->restore();
            $product->update(['is_active' => true]);

            SecurityActionTriggered::dispatch(
                Auth::user(),
                'catalog_product_restored',
                'info',
                "Producto «{$product->name}» restaurado y reactivado.",
                ['product_id' => $product->id],
            );
        });

        return redirect()
            ->route('admin.pim.products.edit', $product)
            ->with('admin_success', "Producto «{$product->name}» restaurado correctamente.");
    }

    private function resolveUniqueSlug(?string $rawSlug, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug(trim((string) $rawSlug) !== '' ? (string) $rawSlug : $name);
        if ($base === '') {
            $base = 'producto';
        }

        $candidate = $base;
        $suffix = 2;

        while (
            Product::withTrashed()
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
