<?php

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Models\ProductVariant;
use App\Services\CartSessionService;
use App\Services\CrossSellService;
use App\Services\SettingService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class CartCounterDrawer extends Component
{
    public bool $open = false;

    #[On('cart-updated')]
    public function handleCartUpdated(bool $openDrawer = false): void
    {
        $service = app(CartSessionService::class);
        $service->syncAbandonedCart($service->getItems());

        if ($openDrawer) {
            $this->open = true;
        }
    }

    #[On('open-cart-drawer')]
    public function openDrawer(): void
    {
        $this->open = true;
    }

    public function closeDrawer(): void
    {
        $this->open = false;
    }

    public function increment(int $variantId): void
    {
        $service = app(CartSessionService::class);
        $items = $service->getItems();

        if (! isset($items[$variantId])) {
            return;
        }

        $currentQty = (int) ($items[$variantId]['quantity'] ?? 1);
        $result = $service->updateQuantity($variantId, $currentQty + 1);
        $this->dispatch('swal:toast', title: $result['message'], icon: $result['capped'] ? 'warning' : 'success');
    }

    public function decrement(int $variantId): void
    {
        $service = app(CartSessionService::class);
        $items = $service->getItems();

        if (! isset($items[$variantId])) {
            return;
        }

        $currentQty = (int) ($items[$variantId]['quantity'] ?? 1);
        $result = $service->updateQuantity($variantId, $currentQty - 1);
        $this->dispatch('swal:toast', title: $result['message'], icon: 'info');
    }

    public function removeItem(int $variantId): void
    {
        $service = app(CartSessionService::class);
        $items = $service->getItems();
        $productName = isset($items[$variantId]['product_name'])
            ? (string) $items[$variantId]['product_name']
            : 'Producto';

        $service->removeItem($variantId);
        $this->dispatch('swal:toast', title: "{$productName} retirado de tu Bolsa.", icon: 'info');
    }

    public function quickAddCrossSell(int $variantId): void
    {
        $variant = ProductVariant::query()
            ->active()
            ->with(['product.brand', 'tieredPrices', 'batches'])
            ->find($variantId);

        if (! $variant instanceof ProductVariant || $variant->product === null || ! $variant->product->is_active) {
            $this->dispatch('swal:toast', title: 'Este complemento ya no se encuentra disponible.', icon: 'warning');

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
            $this->dispatch('cart-updated', openDrawer: true);
        }
    }

    public function clearCart(): void
    {
        app(CartSessionService::class)->clear();
        $this->dispatch('swal:toast', title: 'Tu Bolsa de compras fue vaciada.', icon: 'info');
    }

    public function render(): View
    {
        $summary = app(CartSessionService::class)->summary();
        $recommendations = app(CrossSellService::class)->recommendationsForCart($summary['items'], 3);
        $settings = app(SettingService::class);

        return view('livewire.storefront.cart-counter-drawer', [
            'items' => $summary['items'],
            'itemsCount' => $summary['items_count'],
            'subtotal' => $summary['subtotal'],
            'tierSavings' => $summary['tier_savings'],
            'couponCode' => $summary['coupon_code'],
            'couponDiscount' => $summary['coupon_discount'],
            'shippingQuote' => $summary['shipping_quote'],
            'total' => $summary['total'],
            'activeTier' => $summary['tier'],
            'freeShippingThreshold' => $summary['free_shipping_threshold'],
            'recommendations' => $recommendations,
            'whatsappCartUrl' => $settings->buildCartWhatsappUrl($summary),
            'whatsappCheckoutEnabled' => $settings->bool('whatsapp_checkout_enabled', true),
        ]);
    }
}
