<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Services\CartSessionService;
use App\Services\CrossSellService;
use App\Services\SettingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    public function index(
        CartSessionService $cartService,
        CrossSellService $crossSellService,
        SettingService $settingService,
    ): View {
        $summary = $cartService->summary();
        $recommendations = $crossSellService->recommendationsForCart($summary['items'], 3);
        $bestsellerIds = $crossSellService->bestsellerIds();
        $productBadges = $crossSellService->badgesFor($recommendations, $bestsellerIds);

        return view('storefront.cart.index', [
            'items' => $summary['items'],
            'itemsCount' => $summary['items_count'],
            'listSubtotal' => $summary['list_subtotal'],
            'subtotal' => $summary['subtotal'],
            'tierSavings' => $summary['tier_savings'],
            'activeTier' => $summary['tier'],
            'coupon' => $summary['coupon'],
            'couponCode' => $summary['coupon_code'],
            'couponDiscount' => $summary['coupon_discount'],
            'couponWarning' => $summary['coupon_warning'],
            'netSubtotal' => $summary['net_subtotal'],
            'taxRate' => $summary['tax_rate'],
            'taxPercent' => $summary['tax_percent'],
            'taxableBase' => $summary['taxable_base'],
            'taxIgv' => $summary['tax_igv'],
            'shippingQuote' => $summary['shipping_quote'],
            'shippingCost' => $summary['shipping_cost'],
            'total' => $summary['total'],
            'freeShippingThreshold' => $summary['free_shipping_threshold'],
            'recommendations' => $recommendations,
            'productBadges' => $productBadges,
            'whatsappCartUrl' => $settingService->buildCartWhatsappUrl($summary, Auth::user()?->name),
            'whatsappCheckoutEnabled' => $settingService->bool('whatsapp_checkout_enabled', true),
        ]);
    }

    public function add(Request $request, CartSessionService $cartService): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:500'],
        ], [
            'variant_id.required' => 'Selecciona un tono o presentación válida.',
            'variant_id.exists' => 'La variante seleccionada no existe.',
            'quantity.min' => 'La cantidad mínima es 1 unidad.',
        ]);

        $variant = ProductVariant::query()
            ->with(['product.brand', 'tieredPrices', 'batches'])
            ->findOrFail((int) $validated['variant_id']);

        $result = $cartService->addVariant(
            variant: $variant,
            quantity: (int) ($validated['quantity'] ?? 1),
        );

        if ($request->expectsJson()) {
            return response()->json([
                'added' => $result['added'],
                'message' => $result['message'],
                'summary' => $cartService->summary(),
            ], $result['added'] ? 200 : 422);
        }

        if (! $result['added']) {
            return back()->with('cart_error', $result['message']);
        }

        return back()->with('cart_success', $result['message']);
    }

    public function update(Request $request, CartSessionService $cartService): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'quantity' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'quantities' => ['nullable', 'array'],
            'quantities.*' => ['integer', 'min:0', 'max:10000'],
        ], [
            'quantity.integer' => 'La cantidad debe ser un número entero.',
            'quantity.min' => 'La cantidad no puede ser negativa.',
            'quantities.*.integer' => 'Cada cantidad debe ser un número entero.',
            'quantities.*.min' => 'Las cantidades no pueden ser negativas.',
        ]);

        $messages = [];
        $hadCapped = false;

        if (isset($validated['quantities']) && is_array($validated['quantities'])) {
            foreach ($validated['quantities'] as $variantId => $qty) {
                $result = $cartService->updateQuantity((int) $variantId, (int) $qty);
                if ($result['capped']) {
                    $hadCapped = true;
                    $messages[] = $result['message'];
                }
            }

            $finalMessage = $messages !== []
                ? implode(' ', $messages)
                : 'Cantidades de tu Bolsa actualizadas según precios vigentes.';
        } elseif (isset($validated['variant_id'])) {
            $result = $cartService->updateQuantity(
                (int) $validated['variant_id'],
                (int) ($validated['quantity'] ?? 1),
            );
            $hadCapped = $result['capped'];
            $finalMessage = $result['message'];
        } else {
            return back()->with('cart_error', 'No se recibieron cantidades para actualizar.');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'updated' => true,
                'capped' => $hadCapped,
                'message' => $finalMessage,
                'summary' => $cartService->summary(),
            ]);
        }

        return redirect()
            ->route('cart.index')
            ->with('cart_success', $finalMessage);
    }

    public function remove(Request $request, int $variantId, CartSessionService $cartService): RedirectResponse|JsonResponse
    {
        $items = $cartService->getItems();
        $productName = isset($items[$variantId]['product_name'])
            ? (string) $items[$variantId]['product_name']
            : 'Producto';

        $cartService->removeItem($variantId);

        if ($request->expectsJson()) {
            return response()->json([
                'removed' => true,
                'summary' => $cartService->summary(),
            ]);
        }

        return redirect()
            ->route('cart.index')
            ->with('cart_success', "{$productName} fue retirado de tu Bolsa.");
    }

    public function applyCoupon(Request $request, CartSessionService $cartService): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
        ], [
            'code.required' => 'Ingresa un código de cupón promocional.',
        ]);

        $result = $cartService->applyCoupon($validated['code']);

        if (! $result['applied']) {
            return redirect()
                ->route('cart.index')
                ->withErrors(['code' => $result['message']])
                ->withInput();
        }

        return redirect()
            ->route('cart.index')
            ->with('cart_success', $result['message']);
    }

    public function removeCoupon(CartSessionService $cartService): RedirectResponse
    {
        $cartService->removeCoupon();

        return redirect()
            ->route('cart.index')
            ->with('cart_success', 'Cupón promocional retirado.');
    }

    public function quotePdf(CartSessionService $cartService, SettingService $settingService): Response|RedirectResponse
    {
        $summary = $cartService->summary();

        if ($summary['items_count'] === 0) {
            return redirect()
                ->route('cart.index')
                ->with('cart_error', 'Tu Bolsa está vacía. Agrega productos antes de descargar una cotización PDF.');
        }

        $quoteNumber = 'COT-' . now()->format('Ymd-His');
        $issuedAt = now();
        $validUntil = now()->addDays(7);

        $pdf = Pdf::loadView('pdf.quotation', [
            'quoteNumber' => $quoteNumber,
            'issuedAt' => $issuedAt,
            'validUntil' => $validUntil,
            'customer' => Auth::user(),
            'summary' => $summary,
            'settings' => $settingService->all(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download("Cotizacion-Glamora-{$quoteNumber}.pdf");
    }
}
