<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Events\SecurityActionTriggered;
use App\Http\Controllers\Admin\Concerns\HandlesSafeMediaUploads;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Rules\SafeRasterImage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    use HandlesSafeMediaUploads;
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $parentFilter = (string) $request->query('parent', '');
        $statusFilter = (string) $request->query('status', '');

        $categories = Category::query()
            ->with('parent')
            ->withCount(['children', 'products'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($sub) use ($search): void {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when($parentFilter === 'root', fn ($query) => $query->whereNull('parent_id'))
            ->when(is_numeric($parentFilter), fn ($query) => $query->where('parent_id', (int) $parentFilter))
            ->when($statusFilter === 'active', fn ($query) => $query->where('is_active', true))
            ->when($statusFilter === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $rootCategories = Category::query()->root()->ordered()->get();

        return view('admin.pim.categories.index', [
            'categories' => $categories,
            'rootCategories' => $rootCategories,
            'search' => $search,
            'parentFilter' => $parentFilter,
            'statusFilter' => $statusFilter,
        ]);
    }

    public function create(): View
    {
        $parentOptions = Category::query()->ordered()->get();

        return view('admin.pim.categories.form', [
            'category' => new Category(['is_active' => true, 'sort_order' => 0]),
            'parentOptions' => $parentOptions,
            'isEdit' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateCategory($request);

        $imagePath = $this->handleImageUpload($request);

        /** @var Category $category */
        $category = $this->runWithMediaCleanup(fn (): Category => DB::transaction(function () use ($validated, $imagePath, $request): Category {
            $slug = $this->resolveUniqueSlug($validated['slug'] ?? null, $validated['name']);

            $category = Category::query()->create([
                'parent_id' => ($validated['parent_id'] ?? null) ?: null,
                'name' => trim($validated['name']),
                'slug' => $slug,
                'description' => $validated['description'] ?? null,
                'image_path' => $imagePath ?? $this->sanitizeSafeImagePath($validated['image_path'] ?? null),
                'sort_order' => (int) ($validated['sort_order'] ?? 0),
                'is_active' => $request->boolean('is_active', true),
                'meta_title' => $validated['meta_title'] ?? null,
                'meta_description' => $validated['meta_description'] ?? null,
            ]);

            SecurityActionTriggered::dispatch(
                Auth::user(),
                'catalog_category_created',
                'info',
                "Categoría «{$category->name}» creada en el PIM.",
                ['category_id' => $category->id, 'slug' => $category->slug],
            );

            return $category;
        }), $imagePath);

        return redirect()
            ->route('admin.pim.categories.index')
            ->with('admin_success', "Categoría «{$category->name}» creada correctamente.");
    }

    public function edit(Category $category): View
    {
        $excludedIds = $this->collectDescendantIds($category);
        $excludedIds[] = $category->id;

        $parentOptions = Category::query()
            ->whereNotIn('id', $excludedIds)
            ->ordered()
            ->get();

        return view('admin.pim.categories.form', [
            'category' => $category,
            'parentOptions' => $parentOptions,
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $validated = $this->validateCategory($request, $category);

        $excludedIds = $this->collectDescendantIds($category);
        $excludedIds[] = $category->id;

        if (($validated['parent_id'] ?? null) !== null && in_array((int) $validated['parent_id'], $excludedIds, true)) {
            return back()
                ->withInput()
                ->withErrors(['parent_id' => 'Una categoría no puede depender de sí misma ni de una de sus subcategorías.']);
        }

        $imagePath = $this->handleImageUpload($request);
        $oldImagePath = $category->image_path;

        $this->runWithMediaCleanup(fn () => DB::transaction(function () use ($category, $validated, $imagePath, $request): void {
            $slug = $this->resolveUniqueSlug($validated['slug'] ?? null, $validated['name'], $category->id);

            $category->update([
                'parent_id' => ($validated['parent_id'] ?? null) ?: null,
                'name' => trim($validated['name']),
                'slug' => $slug,
                'description' => $validated['description'] ?? null,
                'image_path' => $imagePath ?? ($this->sanitizeSafeImagePath($validated['image_path'] ?? null) ?? $category->image_path),
                'sort_order' => (int) ($validated['sort_order'] ?? 0),
                'is_active' => $request->boolean('is_active'),
                'meta_title' => $validated['meta_title'] ?? null,
                'meta_description' => $validated['meta_description'] ?? null,
            ]);

            SecurityActionTriggered::dispatch(
                Auth::user(),
                'catalog_category_updated',
                'info',
                "Categoría «{$category->name}» actualizada.",
                ['category_id' => $category->id, 'slug' => $category->slug],
            );
        }), $imagePath);

        if ($imagePath !== null && $oldImagePath !== null && $oldImagePath !== $imagePath) {
            $this->deleteSafePublicImage($oldImagePath);
        }

        return redirect()
            ->route('admin.pim.categories.index')
            ->with('admin_success', "Categoría «{$category->name}» actualizada correctamente.");
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->children()->exists()) {
            return back()->with('admin_error', "No se puede eliminar «{$category->name}» porque tiene subcategorías asociadas.");
        }

        if ($category->products()->withTrashed()->exists()) {
            return back()->with('admin_error', "No se puede eliminar «{$category->name}» porque tiene productos asociados. Desactívala en su lugar.");
        }

        $name = $category->name;
        $id = $category->id;
        $oldImagePath = $category->image_path;

        DB::transaction(function () use ($category, $name, $id): void {
            $category->delete();

            SecurityActionTriggered::dispatch(
                Auth::user(),
                'catalog_category_deleted',
                'warning',
                "Categoría «{$name}» eliminada del PIM.",
                ['category_id' => $id],
            );
        });

        $this->deleteSafePublicImage($oldImagePath);

        return redirect()
            ->route('admin.pim.categories.index')
            ->with('admin_success', "Categoría «{$name}» eliminada.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCategory(Request $request, ?Category $category = null): array
    {
        return $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'slug' => [
                'nullable',
                'string',
                'max:150',
                Rule::unique('categories', 'slug')->ignore($category?->id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'image_path' => ['nullable', 'string', 'max:255'],
            'image_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096', new SafeRasterImage()],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
            'meta_title' => ['nullable', 'string', 'max:180'],
            'meta_description' => ['nullable', 'string', 'max:320'],
        ], [
            'name.required' => 'Ingresa el nombre de la categoría.',
            'slug.unique' => 'El slug ya está en uso por otra categoría.',
            'image_file.max' => 'La fotografía de la categoría no debe superar los 4 MB.',
        ]);
    }

    private function handleImageUpload(Request $request): ?string
    {
        return $this->storeSafePublicImage($request, 'image_file', 'categories');
    }

    private function resolveUniqueSlug(?string $rawSlug, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug(trim((string) $rawSlug) !== '' ? (string) $rawSlug : $name);
        if ($base === '') {
            $base = 'categoria';
        }

        $candidate = $base;
        $suffix = 2;

        while (
            Category::query()
                ->where('slug', $candidate)
                ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $candidate = "{$base}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }

    /**
     * @return list<int>
     */
    private function collectDescendantIds(Category $category): array
    {
        $ids = [];
        $queue = [$category->id];

        while ($queue !== []) {
            $childIds = Category::query()
                ->whereIn('parent_id', $queue)
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();

            $newIds = array_values(array_diff($childIds, $ids));
            if ($newIds === []) {
                break;
            }

            $ids = array_merge($ids, $newIds);
            $queue = $newIds;
        }

        return $ids;
    }
}
