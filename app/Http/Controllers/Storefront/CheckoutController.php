<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Enums\CustomerTier;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ShippingMethod;
use App\Events\SecurityActionTriggered;
use App\Exceptions\InsufficientFefoStockException;
use App\Http\Controllers\Controller;
use App\Models\AbandonedCart;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WholesaleProfile;
use App\Services\CartSessionService;
use App\Services\FefoService;
use App\Services\PricingService;
use App\Services\ShippingService;
use App\ValueObjects\MoneyAmount;
use App\ValueObjects\PriceCalculation;
use Barryvdh\DomPDF\Facade\Pdf;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class CheckoutController extends Controller
{
    public function index(
        CartSessionService $cartService,
        ShippingService $shippingService,
    ): View|RedirectResponse {
        $summary = $cartService->summary();

        if ($summary['items_count'] === 0) {
            return redirect()
                ->route('cart.index')
                ->with('cart_error', 'Tu carrito está vacío. Agrega productos antes de iniciar el checkout.');
        }

        $user = Auth::user();
        $addresses = collect();
        $defaultAddress = null;
        $wholesaleProfile = null;
        $availableB2bCredit = 0.0;
        $canUseB2bCredit = false;

        if ($user instanceof User) {
            $user->loadMissing(['addresses', 'wholesaleProfile']);
            $addresses = $user->addresses->sortByDesc('is_default')->values();
            $defaultAddress = $addresses->first();
            if ($user->hasApprovedWholesale() && $user->wholesaleProfile !== null) {
                $wholesaleProfile = $user->wholesaleProfile;
                $availableB2bCredit = $wholesaleProfile->availableCredit();
                $canUseB2bCredit = $availableB2bCredit >= $summary['total'] || $availableB2bCredit > 0.0;
            }
        }

        $shippingQuotes = collect($shippingService->allQuotes($summary['net_subtotal']))
            ->mapWithKeys(fn ($quote, $key): array => [$key => $quote->toArray()])
            ->all();

        return view('storefront.checkout.index', [
            'summary' => $summary,
            'user' => $user,
            'addresses' => $addresses,
            'defaultAddress' => $defaultAddress,
            'wholesaleProfile' => $wholesaleProfile,
            'availableB2bCredit' => $availableB2bCredit,
            'canUseB2bCredit' => $canUseB2bCredit,
            'shippingMethods' => ShippingMethod::cases(),
            'shippingQuotes' => $shippingQuotes,
            'paymentMethods' => PaymentMethod::cases(),
        ]);
    }

    public function store(
        Request $request,
        CartSessionService $cartService,
        PricingService $pricingService,
        ShippingService $shippingService,
        FefoService $fefoService,
    ): RedirectResponse {
        $cartItems = $cartService->getItems();

        if ($cartItems === []) {
            return redirect()
                ->route('cart.index')
                ->with('cart_error', 'Tu carrito está vacío. Agrega productos antes de finalizar tu compra.');
        }

        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'min:3', 'max:150'],
            'customer_email' => ['required', 'email:rfc', 'max:150'],
            'customer_phone' => ['required', 'string', 'min:7', 'max:25'],
            'document_type' => ['required', 'string', Rule::in(['DNI', 'RUC', 'CE'])],
            'document_number' => [
                'required',
                'string',
                'min:6',
                'max:20',
                function (string $attribute, mixed $value, Closure $fail) use ($request): void {
                    $clean = trim((string) $value);
                    $docType = (string) $request->input('document_type', 'DNI');

                    if ($docType === 'DNI' && preg_match('/^\d{8}$/', $clean) !== 1) {
                        $fail('El DNI debe contener exactamente 8 dígitos numéricos.');
                    } elseif ($docType === 'RUC' && preg_match('/^\d{11}$/', $clean) !== 1) {
                        $fail('El RUC debe contener exactamente 11 dígitos numéricos.');
                    } elseif ($docType === 'CE' && preg_match('/^[A-Za-z0-9\-]{6,20}$/', $clean) !== 1) {
                        $fail('El Carné de Extranjería debe tener entre 6 y 20 caracteres alfanuméricos.');
                    }
                },
            ],
            'shipping_address' => ['required', 'string', 'min:5', 'max:255'],
            'shipping_district' => ['required', 'string', 'min:2', 'max:100'],
            'shipping_city' => ['required', 'string', 'min:2', 'max:100'],
            'shipping_method' => ['required', 'string', Rule::in(ShippingMethod::values())],
            'payment_method' => ['required', 'string', Rule::in(PaymentMethod::values())],
            'notes' => ['nullable', 'string', 'max:1000'],
            'card_holder' => ['nullable', 'string', 'max:120'],
            'card_last4' => ['required_if:payment_method,tarjeta', 'nullable', 'digits:4'],
            'three_ds_code' => ['nullable', 'string', 'max:40'],
            'bank_name' => ['nullable', 'string', 'max:60'],
            'transaction_reference' => [
                Rule::requiredIf(fn (): bool => in_array($request->input('payment_method'), ['yape_plin', 'transferencia'], true)),
                'nullable',
                'string',
                'min:4',
                'max:120',
            ],
            'voucher' => [
                Rule::requiredIf(fn (): bool => in_array($request->input('payment_method'), ['yape_plin', 'transferencia'], true)),
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp,pdf',
                'max:4096',
            ],
            'crypto_network' => [
                Rule::requiredIf(fn (): bool => $request->input('payment_method') === 'cripto'),
                'nullable',
                'string',
                Rule::in(['TRC20', 'Polygon', 'ERC20']),
            ],
            'crypto_tx_hash' => [
                Rule::requiredIf(fn (): bool => $request->input('payment_method') === 'cripto'),
                'nullable',
                'string',
                'min:10',
                'max:150',
            ],
        ], [
            'customer_name.required' => 'Ingresa el nombre completo del cliente o destinatario.',
            'customer_email.required' => 'Ingresa tu correo electrónico para la confirmación.',
            'customer_phone.required' => 'Ingresa un teléfono o WhatsApp de contacto.',
            'document_number.required' => 'Ingresa tu número de documento (DNI, RUC o CE).',
            'shipping_address.required' => 'Ingresa la dirección exacta de entrega.',
            'shipping_district.required' => 'Ingresa el distrito de entrega.',
            'shipping_city.required' => 'Ingresa la ciudad de entrega.',
            'card_last4.required_if' => 'Ingresa los últimos 4 dígitos de tu tarjeta para validar la pasarela.',
            'card_last4.digits' => 'Los últimos dígitos de la tarjeta deben ser exactamente 4 números.',
            'transaction_reference.required' => 'Ingresa el número de operación o referencia del pago.',
            'voucher.required' => 'Adjunta la captura o comprobante de pago (JPG, PNG, WEBP o PDF máx. 4 MB).',
            'voucher.mimes' => 'El comprobante debe ser una imagen JPG, PNG, WEBP o un archivo PDF.',
            'voucher.max' => 'El comprobante no debe superar los 4 MB.',
            'crypto_network.required' => 'Selecciona la red blockchain de tu transferencia cripto.',
            'crypto_tx_hash.required' => 'Ingresa el hash de transacción (TxID) para verificar tu pago cripto.',
        ]);

        $paymentMethod = PaymentMethod::from($validated['payment_method']);
        $shippingMethod = ShippingMethod::from($validated['shipping_method']);
        $user = Auth::user();
        $authenticatedUser = $user instanceof User ? $user : null;

        if ($authenticatedUser !== null && ! $authenticatedUser->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'Tu cuenta se encuentra desactivada. Contáctanos para reactivar el acceso.',
                ]);
        }

        if ($paymentMethod === PaymentMethod::CreditoB2b && ($authenticatedUser === null || ! $authenticatedUser->hasApprovedWholesale())) {
            throw ValidationException::withMessages([
                'payment_method' => 'El pago con Crédito B2B solo está habilitado para cuentas mayoristas aprobadas.',
            ]);
        }

        $voucherPath = null;
        if ($paymentMethod->requiresVoucher() && $request->hasFile('voucher')) {
            $voucherPath = $request->file('voucher')->store('vouchers', 'public');
        }

        $couponCodeInSession = $cartService->getCouponCode();
        $sessionId = session()->getId();

        try {
            /** @var Order $order */
            $order = DB::transaction(function () use (
                $validated,
                $cartItems,
                $paymentMethod,
                $shippingMethod,
                $authenticatedUser,
                $voucherPath,
                $couponCodeInSession,
                $sessionId,
                $pricingService,
                $shippingService,
                $fefoService,
            ): Order {
                $tier = $pricingService->resolveActiveTier($authenticatedUser);
                $variantIds = array_map(static fn (array $row): int => (int) $row['product_variant_id'], $cartItems);

                $variants = ProductVariant::query()
                    ->whereIn('id', $variantIds)
                    ->with(['product', 'tieredPrices'])
                    ->get()
                    ->keyBy('id');

                $preparedLines = [];
                $listCents = 0;
                $subtotalCents = 0;

                foreach ($cartItems as $item) {
                    $variantId = (int) $item['product_variant_id'];
                    $qty = max(1, (int) ($item['quantity'] ?? 1));
                    $variant = $variants->get($variantId);

                    if (! $variant instanceof ProductVariant || ! $variant->is_active || $variant->product === null || ! $variant->product->is_active) {
                        throw new InsufficientFefoStockException('Uno de los productos de tu carrito ya no está disponible.');
                    }

                    $unitPrice = $pricingService->unitPriceFor($variant, $qty, $tier);
                    $lineSubtotal = round($unitPrice * $qty, 2);

                    $listCents += MoneyAmount::fromFloat((float) $variant->base_price)->multiply($qty)->cents;
                    $subtotalCents += MoneyAmount::fromFloat($unitPrice)->multiply($qty)->cents;

                    $preparedLines[] = [
                        'variant' => $variant,
                        'quantity' => $qty,
                        'unit_price' => $unitPrice,
                        'subtotal' => $lineSubtotal,
                    ];
                }

                $listSubtotal = round($listCents / 100, 2);
                $subtotal = round($subtotalCents / 100, 2);

                $coupon = null;
                $discountAmount = 0.0;

                if ($couponCodeInSession !== null) {
                    $coupon = Coupon::query()
                        ->whereRaw('UPPER(code) = ?', [strtoupper($couponCodeInSession)])
                        ->lockForUpdate()
                        ->first();

                    if (! $coupon instanceof Coupon || ! $coupon->isValidFor($subtotal, $tier)) {
                        throw ValidationException::withMessages([
                            'coupon' => "El cupón {$couponCodeInSession} ya no es válido o alcanzó su límite de usos. Revisa el resumen antes de confirmar.",
                        ]);
                    }

                    $discountAmount = $coupon->calculateDiscount($subtotal);
                }

                $netSubtotal = max(0.0, round($subtotal - $discountAmount, 2));
                $shippingQuote = $shippingService->quote($shippingMethod, $netSubtotal);
                $shippingCost = $shippingQuote->cost->amount;

                $priceCalculation = new PriceCalculation(
                    quantity: 1,
                    baseUnitPrice: MoneyAmount::fromFloat($listSubtotal),
                    effectiveUnitPrice: MoneyAmount::fromFloat($subtotal),
                    tier: $tier,
                    discountPercent: $listSubtotal > 0 ? max(0.0, (($listSubtotal - $subtotal) / $listSubtotal) * 100) : 0.0,
                    couponDiscount: MoneyAmount::fromFloat($discountAmount),
                    shippingCost: MoneyAmount::fromFloat($shippingCost),
                    taxRate: $pricingService->taxRate(),
                    taxIncluded: true,
                );

                $taxIgv = $priceCalculation->taxIgv->amount;
                $total = $priceCalculation->grandTotal->amount;

                $b2bDueDate = null;
                $isB2b = $paymentMethod === PaymentMethod::CreditoB2b
                    || $tier !== CustomerTier::Minorista
                    || ($authenticatedUser !== null && $authenticatedUser->hasApprovedWholesale());

                if ($paymentMethod === PaymentMethod::CreditoB2b) {
                    if ($authenticatedUser === null) {
                        throw ValidationException::withMessages([
                            'payment_method' => 'Debes iniciar sesión con tu cuenta mayorista para usar Crédito B2B.',
                        ]);
                    }

                    $wholesaleProfile = WholesaleProfile::query()
                        ->where('user_id', $authenticatedUser->id)
                        ->lockForUpdate()
                        ->first();

                    if (! $wholesaleProfile instanceof WholesaleProfile || $wholesaleProfile->status !== 'approved') {
                        throw ValidationException::withMessages([
                            'payment_method' => 'Tu perfil mayorista B2B no se encuentra aprobado para crédito.',
                        ]);
                    }

                    $availableCredit = $wholesaleProfile->availableCredit();
                    if ($availableCredit < $total) {
                        throw ValidationException::withMessages([
                            'payment_method' => sprintf(
                                'Crédito B2B insuficiente (disponible: S/ %s, requerido: S/ %s).',
                                number_format($availableCredit, 2),
                                number_format($total, 2)
                            ),
                        ]);
                    }

                    $wholesaleProfile->credit_used = round((float) $wholesaleProfile->credit_used + $total, 2);
                    $wholesaleProfile->save();

                    $termsDays = max(1, (int) ($wholesaleProfile->payment_terms_days ?: 30));
                    $b2bDueDate = now()->addDays($termsDays)->toDateString();
                }

                $orderStatus = $paymentMethod->isInstant()
                    ? OrderStatus::PagoVerificado
                    : OrderStatus::PendientePago;

                $orderNumber = $this->generateUniqueOrderNumber();
                $trackingCode = $this->generateUniqueTrackingCode($orderNumber);

                $order = Order::query()->create([
                    'order_number' => $orderNumber,
                    'tracking_code' => $trackingCode,
                    'user_id' => $authenticatedUser?->id,
                    'customer_name' => trim($validated['customer_name']),
                    'customer_email' => mb_strtolower(trim($validated['customer_email'])),
                    'customer_phone' => trim($validated['customer_phone']),
                    'document_type' => $validated['document_type'],
                    'document_number' => $validated['document_number'] !== null ? trim((string) $validated['document_number']) : null,
                    'shipping_address' => trim($validated['shipping_address']),
                    'shipping_district' => trim($validated['shipping_district']),
                    'shipping_city' => trim($validated['shipping_city']),
                    'shipping_method' => $shippingMethod,
                    'shipping_cost' => $shippingCost,
                    'customer_tier' => $tier,
                    'status' => $orderStatus,
                    'subtotal' => $subtotal,
                    'discount_amount' => $discountAmount,
                    'coupon_code' => $coupon?->code,
                    'tax_igv' => $taxIgv,
                    'total' => $total,
                    'is_b2b' => $isB2b,
                    'notes' => isset($validated['notes']) && trim((string) $validated['notes']) !== ''
                        ? trim((string) $validated['notes'])
                        : null,
                ]);

                $allocationsByVariantId = [];
                $sortedLines = $preparedLines;
                usort(
                    $sortedLines,
                    static fn (array $a, array $b): int => $a['variant']->id <=> $b['variant']->id
                );

                foreach ($sortedLines as $line) {
                    /** @var ProductVariant $variant */
                    $variant = $line['variant'];

                    $allocationsByVariantId[$variant->id] = $fefoService->allocateForOrder(
                        variant: $variant,
                        quantityNeeded: $line['quantity'],
                        order: $order,
                        mode: 'reserve',
                        actor: $authenticatedUser,
                    );
                }

                foreach ($preparedLines as $line) {
                    /** @var ProductVariant $variant */
                    $variant = $line['variant'];
                    $allocations = $allocationsByVariantId[$variant->id] ?? [];

                    OrderItem::query()->create([
                        'order_id' => $order->id,
                        'product_variant_id' => $variant->id,
                        'product_batch_id' => $allocations[0]['batch_id'] ?? null,
                        'product_name' => $variant->product->name,
                        'variant_name' => $variant->shade_name,
                        'sku' => $variant->sku,
                        'shade_hex' => $variant->hex_color,
                        'quantity' => $line['quantity'],
                        'unit_price' => $line['unit_price'],
                        'subtotal' => $line['subtotal'],
                        'batch_allocations' => $allocations,
                    ]);
                }

                if ($coupon instanceof Coupon) {
                    $coupon->increment('used_count');
                }

                [$transactionRef, $paymentNotes] = $this->resolvePaymentMeta($paymentMethod, $validated, $order->order_number);

                Payment::query()->create([
                    'order_id' => $order->id,
                    'payment_method' => $paymentMethod,
                    'amount' => $total,
                    'currency' => 'PEN',
                    'status' => $paymentMethod->isInstant() ? 'verified' : 'pending',
                    'transaction_reference' => $transactionRef,
                    'voucher_path' => $voucherPath,
                    'crypto_network' => $paymentMethod === PaymentMethod::Cripto ? ($validated['crypto_network'] ?? null) : null,
                    'crypto_tx_hash' => $paymentMethod === PaymentMethod::Cripto ? trim((string) ($validated['crypto_tx_hash'] ?? '')) : null,
                    'b2b_due_date' => $b2bDueDate,
                    'verified_by' => null,
                    'verified_at' => $paymentMethod->isInstant() ? now() : null,
                    'notes' => $paymentNotes,
                ]);

                if ($sessionId !== '') {
                    AbandonedCart::query()
                        ->where('session_id', $sessionId)
                        ->whereNull('recovered_at')
                        ->update([
                            'user_id' => $authenticatedUser?->id,
                            'email' => mb_strtolower(trim($validated['customer_email'])),
                            'recovered_at' => now(),
                        ]);
                }

                SecurityActionTriggered::dispatch(
                    $authenticatedUser,
                    $paymentMethod === PaymentMethod::CreditoB2b ? 'b2b_credit_order_created' : 'order_created',
                    'info',
                    "Pedido {$order->order_number} registrado vía {$paymentMethod->label()} por S/ " . number_format($total, 2) . '.',
                    [
                        'order_number' => $order->order_number,
                        'payment_method' => $paymentMethod->value,
                        'total' => $total,
                        'is_b2b' => $isB2b,
                    ],
                );

                return $order;
            });
        } catch (ValidationException $e) {
            if ($voucherPath !== null) {
                Storage::disk('public')->delete($voucherPath);
            }

            if (array_key_exists('coupon', $e->errors())) {
                $cartService->removeCoupon();
            }

            throw $e;
        } catch (InsufficientFefoStockException $e) {
            if ($voucherPath !== null) {
                Storage::disk('public')->delete($voucherPath);
            }

            return redirect()
                ->route('checkout.index')
                ->withInput()
                ->withErrors(['stock' => $e->getMessage()])
                ->with('cart_error', $e->getMessage());
        } catch (Throwable $e) {
            if ($voucherPath !== null) {
                Storage::disk('public')->delete($voucherPath);
            }

            throw $e;
        }

        $cartService->clear();

        $allowedOrders = session('checkout_allowed_orders', []);
        if (! is_array($allowedOrders)) {
            $allowedOrders = [];
        }
        $allowedOrders[] = $order->order_number;
        session(['checkout_allowed_orders' => array_values(array_unique($allowedOrders))]);

        return redirect()
            ->route('checkout.confirmation', $order->order_number)
            ->with('checkout_success', "Pedido {$order->order_number} registrado correctamente con trazabilidad FEFO.");
    }

    public function confirmation(string $orderNumber): View
    {
        $order = $this->resolveAuthorizedOrder($orderNumber);

        return view('storefront.checkout.confirmation', [
            'order' => $order,
        ]);
    }

    public function confirmationPdf(string $orderNumber): Response
    {
        $order = $this->resolveAuthorizedOrder($orderNumber);

        $pdf = Pdf::loadView('pdf.order', [
            'order' => $order,
        ])->setPaper('a4', 'portrait');

        return $pdf->download("Nota-Pedido-{$order->order_number}.pdf");
    }

    private function resolveAuthorizedOrder(string $orderNumber): Order
    {
        $normalized = strtoupper(trim($orderNumber));

        $order = Order::query()
            ->with(['items.variant.product', 'payments', 'user'])
            ->whereRaw('UPPER(order_number) = ?', [$normalized])
            ->firstOrFail();

        $userId = Auth::id();
        $allowedOrders = session('checkout_allowed_orders', []);
        $isOwner = $userId !== null && $order->user_id !== null && $order->user_id === $userId;
        $inAllowedSession = is_array($allowedOrders)
            && in_array($order->order_number, $allowedOrders, true)
            && ($order->user_id === null || $order->user_id === $userId);

        if (! $isOwner && ! $inAllowedSession) {
            abort(403, 'No tienes permiso para visualizar la confirmación de este pedido.');
        }

        return $order;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: string, 1: string}
     */
    private function resolvePaymentMeta(PaymentMethod $method, array $validated, string $orderNumber): array
    {
        return match ($method) {
            PaymentMethod::Tarjeta => [
                'NIUBIZ-3DS-' . ($validated['card_last4'] ?? '0000') . '-' . strtoupper(Str::random(6)),
                sprintf(
                    'Pago simulado 3D Secure autorizado (%s · terminación ••••%s · token %s).',
                    trim((string) ($validated['card_holder'] ?? $validated['customer_name'])),
                    (string) ($validated['card_last4'] ?? '0000'),
                    trim((string) ($validated['three_ds_code'] ?? '3DS-OK')) ?: '3DS-OK'
                ),
            ],
            PaymentMethod::YapePlin => [
                trim((string) $validated['transaction_reference']),
                'Comprobante Yape/Plin adjuntado; pendiente de conciliación financiera.',
            ],
            PaymentMethod::Transferencia => [
                trim((string) $validated['transaction_reference']),
                sprintf(
                    'Transferencia bancaria (%s) con voucher adjunto; pendiente de verificación.',
                    trim((string) ($validated['bank_name'] ?? 'Banco')) ?: 'Banco'
                ),
            ],
            PaymentMethod::Cripto => [
                strtoupper(trim((string) $validated['crypto_network'])) . ':' . mb_substr(trim((string) $validated['crypto_tx_hash']), 0, 24),
                sprintf(
                    'Pago cripto en red %s; pendiente de confirmación on-chain.',
                    (string) $validated['crypto_network']
                ),
            ],
            PaymentMethod::CreditoB2b => [
                'CRED-B2B-' . $orderNumber,
                'Cargo automático a línea de crédito mayorista Glamora B2B aprobada.',
            ],
        };
    }

    private function generateUniqueOrderNumber(): string
    {
        $year = now()->format('Y');
        $prefix = "GLM-{$year}-";

        $latest = Order::query()
            ->where('order_number', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('order_number');

        $nextNumber = 1;
        if (is_string($latest) && preg_match('/-(\d+)$/', $latest, $matches) === 1) {
            $nextNumber = ((int) $matches[1]) + 1;
        }

        do {
            $candidate = $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
            $exists = Order::query()->where('order_number', $candidate)->exists();
            $nextNumber++;
        } while ($exists);

        return $candidate;
    }

    private function generateUniqueTrackingCode(string $orderNumber): string
    {
        if (preg_match('/GLM-(\d{4})-(\d+)/', $orderNumber, $matches) === 1) {
            $shortYear = substr($matches[1], -2);
            $candidate = "GLM-TRK-{$shortYear}{$matches[2]}";

            if (! Order::query()->where('tracking_code', $candidate)->exists()) {
                return $candidate;
            }
        }

        do {
            $candidate = 'GLM-TRK-' . now()->format('y') . strtoupper(Str::random(6));
        } while (Order::query()->where('tracking_code', $candidate)->exists());

        return $candidate;
    }
}
