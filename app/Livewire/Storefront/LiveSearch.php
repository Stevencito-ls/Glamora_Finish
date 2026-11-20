<?php

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartSessionService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Livewire\Component;

class LiveSearch extends Component
{
    public string $query = '';

    public bool $open = false;

    public function updatedQuery(): void
    {
        $this->open = mb_strlen(trim($this->query)) >= 2;
    }

    public function openSearch(): void
    {
        $this->open = true;
    }

    public function closeSearch(): void
    {
        $this->open = false;
    }

    public function clearSearch(): void
    {
        $this->query = '';
        $this->open = false;
    }

    public function submitSearch(): mixed
    {
        $term = trim($this->query);
        $this->open = false;

        if ($term === '') {
            return $this->redirect(route('catalog.index'));
        }

        return $this->redirect(route('catalog.index', ['buscar' => $term]));
    }

    public function quickAdd(int $variantId): void
    {
        $variant = ProductVariant::query()
            ->active()
            ->with(['product.brand', 'tieredPrices', 'batches'])
            ->find($variantId);

        if (! $variant instanceof ProductVariant || $variant->product === null || ! $variant->product->is_active) {
            $this->dispatch('swal:toast', title: 'No se encontró la variante seleccionada.', icon: 'error');

            return;
        }

        $result = app(CartSessionService::class)->addVariant($variant, 1);

        $this->dispatch(
            'swal:toast',
            title: $result['message'],
            icon: $result['added'] ? 'success' : 'warning',
            productName: "{$variant->product->name} — {$variant->shade_name}"
        );

        if ($result['added']) {
            $this->dispatch('cart-updated', openDrawer: false);
        }
    }

    public function render(): View
    {
        $term = trim($this->query);
        $activeTier = app(CartSessionService::class)->activeTier();
        $results = new EloquentCollection();

        if (mb_strlen($term) >= 2) {
            $like = '%' . $term . '%';

            $results = Product::query()
                ->active()
                ->with([
                    'brand',
                    'category',
                    'variants' => fn ($q) => $q->active()
                        ->orderByDesc('is_default')
                        ->orderBy('id')
                        ->with(['tieredPrices', 'batches']),
                ])
                ->where(function (Builder $q) use ($like): void {
                    $q->where('name', 'like', $like)
                        ->orWhere('short_description', 'like', $like)
                        ->orWhere('sanitary_registration', 'like', $like)
                        ->orWhereHas('brand', fn (Builder $bq) => $bq->where('name', 'like', $like))
                        ->orWhereHas('category', fn (Builder $cq) => $cq->where('name', 'like', $like))
                        ->orWhereHas('variants', function (Builder $vq) use ($like): void {
                            $vq->where('is_active', true)
                                ->where(function (Builder $sq) use ($like): void {
                                    $sq->where('sku', 'like', $like)
                                        ->orWhere('shade_name', 'like', $like);
                                });
                        });
                })
                ->orderByDesc('is_featured')
                ->orderBy('name')
                ->limit(6)
                ->get();
        }

        return view('livewire.storefront.live-search', [
            'results' => $results,
            'term' => $term,
            'activeTier' => $activeTier,
        ]);
    }
}
