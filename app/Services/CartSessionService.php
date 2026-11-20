<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CustomerTier;
use App\Enums\ShippingMethod;
use App\Models\AbandonedCart;
use App\Models\Coupon;
use App\Models\ProductVariant;
use App\Models\User;
use App\ValueObjects\MoneyAmount;
use Illuminate\Support\Facades\Auth;

class CartSessionService
{
    private const string SESSION_KEY = 'cart';

    private const string COUPON_SESSION_KEY = 'cart_coupon';

    private const string SHIPPING_METHOD_SESSION_KEY = 'cart_shipping_method';

    public function __construct(
        private readonly ?PricingService $pricingService = null,
        private readonly ?ShippingService $shippingService = null,
    ) {}

    public function activeTier(): CustomerTier
    {
        return $this->pricing()->resolveActiveTier();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getItems(): array
    {
        $raw = session(self::SESSION_KEY, []);

        if (! is_array($raw)) {
            return [];
        }

        $normalized = [];

        foreach ($raw as $item) {
            if (! is_array($item) || ! isset($item['product_variant_id'])) {
                continue;
            }

            $variantId = (int) $item['product_variant_id'];
            $normalized[$variantId] = $item;
        }

        return $normalized;
    }

    /**
     * @return array{added: bool, quantity: int, delta: int, capped: bool, message: string}
     */
    public function addVariant(ProductVariant $variant, int $quantity = 1, ?CustomerTier $tierOverride = null): array
    {
        $variant->loadMissing(['product.brand', 'tieredPrices', 'batches']);

        if (! $variant->is_active || $variant->product === null || ! $variant->product->is_active) {
            return [
                'added' => false,
                'quantity' => 0,
                'delta' => 0,
                'capped' => false,
                'message' => 'Esta variante no se encuentra disponible actualmente.',
            ];
        }

        $availableStock = $variant->availableStock();
        if ($availableStock <= 0) {
            return [
                'added' => false,
                'quantity' => 0,
                'delta' => 0,
                'capped' => false,
                'message' => "Stock FEFO agotado para {$variant->sku} ({$variant->shade_name}).",
            ];
        }

        $cart = $this->getItems();
        $existingQty = (int) ($cart[$variant->id]['quantity'] ?? 0);
        $requestedTotal = $existingQty + max(1, $quantity);
        $finalQty = min($availableStock, $requestedTotal);
        $delta = max(0, $finalQty - $existingQty);
        $capped = $requestedTotal > $availableStock;
        $productLabel = "{$variant->product->name} — {$variant->shade_name}";

        if ($delta === 0) {
            return [
                'added' => false,
                'quantity' => $finalQty,
                'delta' => 0,
                'capped' => true,
                'message' => "Ya cuentas con el stock máximo disponible en almacén ({$availableStock} uds.) para {$productLabel}.",
            ];
        }

        $tier = $tierOverride ?? $this->activeTier();
        $unitPrice = $this->pricing()->unitPriceFor($variant, $finalQty, $tier);
        $imagePath = $variant->image_url;

        $cart[$variant->id] = [
            'product_variant_id' => $variant->id,
            'product_id' => $variant->product_id,
            'product_slug' => $variant->product->slug,
            'product_name' => $variant->product->name,
            'brand_name' => $variant->product->brand?->name ?? 'Glamora',
            'sku' => $variant->sku,
            'shade_name' => $variant->shade_name,
            'hex_color' => $variant->hex_color ?? '#C87D72',
            'quantity' => $finalQty,
            'tier' => $tier->value,
            'base_price' => (float) $variant->base_price,
            'unit_price' => $unitPrice,
            'subtotal' => round($unitPrice * $finalQty, 2),
            'image_path' => $imagePath,
            'available_stock' => $availableStock,
        ];

        $this->persistCart($cart);

        $message = $capped
            ? "Se ajustó {$productLabel} al máximo disponible en almacén ({$finalQty} uds.)."
            : "Agregado a la Bolsa: {$productLabel} (x{$finalQty}).";

        return [
            'added' => true,
            'quantity' => $finalQty,
            'delta' => $delta,
            'capped' => $capped,
            'message' => $message,
        ];
    }

    /**
     * @param  array<int|string, int|string>  $variantQuantities
     * @return array{lines_added: int, total_units: int, warnings: list<string>}
     */
    public function addBatch(array $variantQuantities, ?CustomerTier $tierOverride = null): array
    {
        $cleanQuantities = [];
        foreach ($variantQuantities as $variantId => $qty) {
            $qtyInt = (int) $qty;
            if ($qtyInt > 0) {
                $cleanQuantities[(int) $variantId] = $qtyInt;
            }
        }

        if ($cleanQuantities === []) {
            return [
                'lines_added' => 0,
                'total_units' => 0,
                'warnings' => ['Selecciona al menos 1 unidad en la matriz para agregar a la Bolsa.'],
            ];
        }

        $variants = ProductVariant::query()
            ->whereIn('id', array_keys($cleanQuantities))
            ->with(['product.brand', 'tieredPrices', 'batches'])
            ->get()
            ->keyBy('id');

        $linesAdded = 0;
        $totalUnits = 0;
        $warnings = [];

        foreach ($cleanQuantities as $variantId => $qty) {
            $variant = $variants->get($variantId);
            if (! $variant instanceof ProductVariant) {
                continue;
            }

            $result = $this->addVariant($variant, $qty, $tierOverride);
            if ($result['added']) {
                $linesAdded++;
                $totalUnits += $result['delta'];
                if ($result['capped']) {
                    $warnings[] = $result['message'];
                }
            } else {
                $warnings[] = $result['message'];
            }
        }

        if ($linesAdded === 0 && $warnings === []) {
            $warnings[] = 'No se encontraron variantes disponibles para agregar a la Bolsa.';
        }

        return [
            'lines_added' => $linesAdded,
            'total_units' => $totalUnits,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array{updated: bool, capped: bool, removed: bool, message: string}
     */
    public function updateQuantity(int $variantId, int $quantity): array
    {
        $cart = $this->getItems();

        if (! isset($cart[$variantId])) {
            return [
                'updated' => false,
                'capped' => false,
                'removed' => false,
                'message' => 'El producto seleccionado no está en tu Bolsa.',
            ];
        }

        if ($quantity <= 0) {
            $this->removeItem($variantId);

            return [
                'updated' => true,
                'capped' => false,
                'removed' => true,
                'message' => 'Producto eliminado de tu Bolsa.',
            ];
        }

        $variant = ProductVariant::query()
            ->with(['product.brand', 'tieredPrices', 'batches'])
            ->find($variantId);

        if (
            ! $variant instanceof ProductVariant
            || ! $variant->is_active
            || $variant->product === null
            || ! $variant->product->is_active
        ) {
            $this->removeItem($variantId);

            return [
                'updated' => true,
                'capped' => false,
                'removed' => true,
                'message' => 'El producto dejó de estar disponible y se retiró de tu Bolsa.',
            ];
        }

        $availableStock = $variant->availableStock();
        if ($availableStock <= 0) {
            $this->removeItem($variantId);

            return [
                'updated' => true,
                'capped' => false,
                'removed' => true,
                'message' => "Stock agotado para {$variant->sku}; se retiró de tu Bolsa.",
            ];
        }

        $tier = $this->activeTier();
        $finalQty = min($availableStock, $quantity);
        $capped = $quantity > $availableStock;
        $unitPrice = $this->pricing()->unitPriceFor($variant, $finalQty, $tier);

        $cart[$variantId]['quantity'] = $finalQty;
        $cart[$variantId]['tier'] = $tier->value;
        $cart[$variantId]['base_price'] = (float) $variant->base_price;
        $cart[$variantId]['unit_price'] = $unitPrice;
        $cart[$variantId]['subtotal'] = round($unitPrice * $finalQty, 2);
        $cart[$variantId]['available_stock'] = $availableStock;

        $this->persistCart($cart);

        return [
            'updated' => true,
            'capped' => $capped,
            'removed' => false,
            'message' => $capped
                ? "Cantidad ajustada al máximo disponible en almacén ({$finalQty} uds.) para {$variant->product->name}."
                : "Cantidad actualizada para {$variant->product->name} (x{$finalQty}).",
        ];
    }

    public function removeItem(int $variantId): void
    {
        $cart = $this->getItems();
        unset($cart[$variantId]);
        $this->persistCart($cart);
    }

    public function clear(): void
    {
        session()->forget([self::COUPON_SESSION_KEY, self::SHIPPING_METHOD_SESSION_KEY]);
        $this->persistCart([]);
    }

    public function getCouponCode(): ?string
    {
        $code = session(self::COUPON_SESSION_KEY);

        return is_string($code) && trim($code) !== '' ? strtoupper(trim($code)) : null;
    }

    /**
     * @return array{applied: bool, message: string, coupon: ?Coupon}
     */
    public function applyCoupon(string $code): array
    {
        $this->refreshPricesIfTierChanged();
        $items = array_values($this->getItems());

        if ($items === []) {
            return [
                'applied' => false,
                'message' => 'Agrega productos a tu Bolsa antes de aplicar un cupón promocional.',
                'coupon' => null,
            ];
        }

        $subtotalCents = 0;
        foreach ($items as $item) {
            $qty = max(0, (int) ($item['quantity'] ?? 0));
            $unit = (float) ($item['unit_price'] ?? 0.0);
            $subtotalCents += MoneyAmount::fromFloat($unit, PricingService::DEFAULT_CURRENCY)->multiply($qty)->cents;
        }

        $validation = $this->pricing()->validateCoupon($code, round($subtotalCents / 100, 2), $this->activeTier());

        if (! $validation['valid'] || ! $validation['coupon'] instanceof Coupon) {
            return [
                'applied' => false,
                'message' => $validation['message'],
                'coupon' => null,
            ];
        }

        session([self::COUPON_SESSION_KEY => $validation['coupon']->code]);

        return [
            'applied' => true,
            'message' => $validation['message'],
            'coupon' => $validation['coupon'],
        ];
    }

    public function removeCoupon(): void
    {
        session()->forget(self::COUPON_SESSION_KEY);
    }

    public function getShippingMethod(): ShippingMethod
    {
        return $this->shipping()->resolveMethod(session(self::SHIPPING_METHOD_SESSION_KEY));
    }

    public function setShippingMethod(ShippingMethod $method): void
    {
        session([self::SHIPPING_METHOD_SESSION_KEY => $method->value]);
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(?ShippingMethod $shippingMethod = null): array
    {
        $this->refreshPricesIfTierChanged();

        $items = array_values($this->getItems());
        $method = $shippingMethod ?? $this->getShippingMethod();
        $totals = $this->pricing()->calculateCartTotals(
            items: $items,
            tier: $this->activeTier(),
            couponCode: $this->getCouponCode(),
            shippingMethod: $method,
        );

        return [
            'items' => $items,
            'items_count' => $totals['items_count'],
            'list_subtotal' => $totals['list_subtotal'],
            'subtotal' => $totals['subtotal'],
            'tier_savings' => $totals['tier_savings'],
            'tier' => $totals['tier'],
            'coupon' => $totals['coupon'],
            'coupon_code' => $totals['coupon_code'],
            'coupon_discount' => $totals['coupon_discount'],
            'coupon_warning' => $totals['coupon_warning'],
            'net_subtotal' => $totals['net_subtotal'],
            'tax_rate' => $totals['tax_rate'],
            'tax_percent' => $totals['tax_percent'],
            'taxable_base' => $totals['taxable_base'],
            'tax_igv' => $totals['tax_igv'],
            'shipping_method' => $totals['shipping_method'],
            'shipping_quote' => $totals['shipping_quote'],
            'shipping_cost' => $totals['shipping_cost'],
            'total' => $totals['total'],
            'price_calculation' => $totals['price_calculation'],
            'free_shipping_threshold' => $this->shipping()->freeShippingThreshold(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $cart
     */
    public function syncAbandonedCart(array $cart): void
    {
        $sessionId = session()->getId();
        if ($sessionId === '') {
            return;
        }

        if ($cart === []) {
            AbandonedCart::query()
                ->where('session_id', $sessionId)
                ->whereNull('recovered_at')
                ->delete();

            return;
        }

        $payload = [];
        $subtotal = 0.0;
        $itemsCount = 0;

        foreach ($cart as $item) {
            $qty = (int) ($item['quantity'] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $lineSubtotal = round((float) ($item['subtotal'] ?? 0.0), 2);
            $itemsCount += $qty;
            $subtotal += $lineSubtotal;

            $payload[] = [
                'product_variant_id' => (int) $item['product_variant_id'],
                'sku' => (string) ($item['sku'] ?? ''),
                'product_name' => (string) ($item['product_name'] ?? ''),
                'shade_name' => (string) ($item['shade_name'] ?? ''),
                'quantity' => $qty,
                'unit_price' => round((float) ($item['unit_price'] ?? 0.0), 2),
                'subtotal' => $lineSubtotal,
                'image_path' => (string) ($item['image_path'] ?? ''),
            ];
        }

        if ($payload === []) {
            AbandonedCart::query()
                ->where('session_id', $sessionId)
                ->whereNull('recovered_at')
                ->delete();

            return;
        }

        $user = Auth::user();

        AbandonedCart::query()->updateOrCreate(
            ['session_id' => $sessionId],
            [
                'user_id' => $user?->id,
                'email' => $user?->email,
                'cart_payload' => $payload,
                'subtotal' => round($subtotal, 2),
                'items_count' => $itemsCount,
                'last_activity_at' => now(),
                'recovered_at' => null,
            ]
        );
    }

    private function refreshPricesIfTierChanged(): void
    {
        $cart = $this->getItems();
        if ($cart === []) {
            return;
        }

        $activeTier = $this->activeTier();
        $needsRefresh = false;

        foreach ($cart as $item) {
            if (($item['tier'] ?? null) !== $activeTier->value) {
                $needsRefresh = true;
                break;
            }
        }

        if (! $needsRefresh) {
            return;
        }

        $variants = ProductVariant::query()
            ->whereIn('id', array_keys($cart))
            ->with(['product', 'tieredPrices', 'batches'])
            ->get()
            ->keyBy('id');

        $updatedCart = [];

        foreach ($cart as $variantId => $item) {
            $variant = $variants->get($variantId);
            if (! $variant instanceof ProductVariant || ! $variant->is_active || $variant->product === null || ! $variant->product->is_active) {
                continue;
            }

            $availableStock = $variant->availableStock();
            if ($availableStock <= 0) {
                continue;
            }

            $qty = min($availableStock, max(1, (int) ($item['quantity'] ?? 1)));
            $unitPrice = $this->pricing()->unitPriceFor($variant, $qty, $activeTier);

            $item['quantity'] = $qty;
            $item['tier'] = $activeTier->value;
            $item['base_price'] = (float) $variant->base_price;
            $item['unit_price'] = $unitPrice;
            $item['subtotal'] = round($unitPrice * $qty, 2);
            $item['available_stock'] = $availableStock;

            $updatedCart[$variantId] = $item;
        }

        $this->persistCart($updatedCart);
    }

    /**
     * @param  array<int, array<string, mixed>>  $cart
     */
    private function persistCart(array $cart): void
    {
        if ($cart === []) {
            session()->forget(self::SESSION_KEY);
        } else {
            session([self::SESSION_KEY => $cart]);
        }

        $this->syncAbandonedCart($cart);
    }

    private function pricing(): PricingService
    {
        return $this->pricingService ?? app(PricingService::class);
    }

    private function shipping(): ShippingService
    {
        return $this->shippingService ?? app(ShippingService::class);
    }
}
