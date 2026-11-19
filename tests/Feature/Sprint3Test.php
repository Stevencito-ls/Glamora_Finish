<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CustomerTier;
use App\Enums\OrderStatus;
use App\Enums\ReturnCondition;
use App\Enums\ShippingMethod;
use App\Livewire\Storefront\CartCounterDrawer;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\ProductBatch;
use App\Models\ProductVariant;
use App\Models\ReturnItem;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\WholesaleProfile;
use App\Services\CartSessionService;
use App\Services\FefoService;
use App\Services\PricingService;
use App\Services\ShippingService;
use App\Services\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class Sprint3Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_shipping_quote_pricing_service_and_totp_service_work_accurately(): void
    {
        $shipping = app(ShippingService::class);

        $paidQuote = $shipping->quote(ShippingMethod::Estandar, 120.00);
        $this->assertFalse($paidQuote->isFree);
        $this->assertSame(12.00, $paidQuote->cost->amount);
        $this->assertSame(79.00, $paidQuote->remainingForFree->amount);
        $this->assertGreaterThan(60.0, $paidQuote->progressPercent);

        $freeQuote = $shipping->quote(ShippingMethod::Estandar, 199.00);
        $this->assertTrue($freeQuote->isFree);
        $this->assertSame(0.00, $freeQuote->cost->amount);
        $this->assertSame(0.00, $freeQuote->remainingForFree->amount);
        $this->assertSame(100.0, $freeQuote->progressPercent);

        $expressQuote = $shipping->quote(ShippingMethod::Express, 250.00);
        $this->assertFalse($expressQuote->isFree);
        $this->assertSame(22.00, $expressQuote->cost->amount);

        // PricingService IGV 18% included
        $pricing = app(PricingService::class);
        $variant = ProductVariant::query()->where('sku', 'GLM-LB-VITC-30')->firstOrFail();
        $calc = $pricing->calculateVariant($variant, 1, CustomerTier::Minorista);
        $this->assertEqualsWithDelta($calc->netSubtotal->amount, $calc->taxableBase->amount + $calc->taxIgv->amount, 0.02);

        // TotpService RFC 6238
        $totp = app(TotpService::class);
        $secret = $totp->generateSecret();
        $this->assertSame(32, strlen($secret));

        $now = time();
        $codeNow = $totp->getCode($secret, $now);
        $codePrev = $totp->getCode($secret, $now - 30);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $codeNow);
        $this->assertTrue($totp->verifyCode($secret, $codeNow, 1, $now));
        $this->assertTrue($totp->verifyCode($secret, $codePrev, 1, $now));
        $this->assertFalse($totp->verifyCode($secret, '000000', 1, $now + 3600));

        $uri = $totp->getProvisioningUri('cliente@glamora.pe', $secret);
        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString("secret={$secret}", $uri);

        $recovery = $totp->generateRecoveryCodes();
        $this->assertCount(8, $recovery);
    }

    public function test_fefo_service_allocates_depletes_cancels_and_restocks_returns(): void
    {
        $fefo = app(FefoService::class);
        $variant = ProductVariant::query()->where('sku', 'GLM-KD-CER-50')->firstOrFail();
        $batches = $variant->batches()->availableForFefo()->get();
        $this->assertGreaterThanOrEqual(2, $batches->count());

        $firstBatch = $batches->first();
        $this->assertSame(0, $firstBatch->reserved_quantity);
        $firstQty = $firstBatch->availableQuantity();

        $order = Order::query()->create([
            'order_number' => 'GLM-2026-9901',
            'tracking_code' => 'GLM-TRK-269901',
            'customer_name' => 'Test FEFO',
            'customer_email' => 'fefo@glamora.pe',
            'shipping_address' => 'Av. Pardo 123',
            'shipping_district' => 'Miraflores',
            'shipping_city' => 'Lima',
            'shipping_method' => ShippingMethod::Estandar,
            'shipping_cost' => 0,
            'customer_tier' => CustomerTier::Minorista,
            'status' => OrderStatus::PagoVerificado,
            'subtotal' => 500,
            'discount_amount' => 0,
            'tax_igv' => 76.27,
            'total' => 500,
        ]);

        // Despacho directo consumiendo todo el primer lote próximo a vencer + 2 unidades del segundo lote
        $allocations = $fefo->allocateForOrder($variant, $firstQty + 2, $order, 'dispatch');
        $this->assertCount(2, $allocations);
        $this->assertSame($firstBatch->id, $allocations[0]['batch_id']);
        $this->assertSame($firstQty, $allocations[0]['quantity']);

        $firstBatch->refresh();
        $this->assertSame(0, $firstBatch->current_quantity);
        $this->assertSame('depleted', $firstBatch->status);

        $orderItem = OrderItem::query()->create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'product_batch_id' => $allocations[0]['batch_id'],
            'product_name' => $variant->product->name,
            'variant_name' => $variant->shade_name,
            'sku' => $variant->sku,
            'quantity' => $firstQty + 2,
            'unit_price' => 79.0,
            'subtotal' => ($firstQty + 2) * 79.0,
            'batch_allocations' => $allocations,
        ]);

        // Liberación por cancelación (cuando ya fue despachado)
        $order->update(['fefo_dispatched_at' => now()]);
        $fefo->releaseForCancellation($order);
        $firstBatch->refresh();
        $this->assertSame($firstQty, $firstBatch->current_quantity);
        $this->assertSame('active', $firstBatch->status);

        // Reintegro por RMA apto para reventa
        $rma = OrderReturn::query()->create([
            'rma_number' => 'RMA-2026-9901',
            'order_id' => $order->id,
            'requester_name' => 'Test FEFO',
            'requester_email' => 'fefo@glamora.pe',
            'reason' => 'Prueba de reingreso sanitario.',
            'status' => 'resuelto',
            'refund_amount' => 79.00,
        ]);

        $returnItem = ReturnItem::query()->create([
            'return_id' => $rma->id,
            'order_item_id' => $orderItem->id,
            'product_variant_id' => $variant->id,
            'product_batch_id' => $firstBatch->id,
            'quantity' => 1,
            'condition' => ReturnCondition::Reventa,
            'restocked' => false,
        ]);

        $fefo->restockFromReturn($rma, $returnItem);
        $firstBatch->refresh();
        $returnItem->refresh();
        $this->assertTrue($returnItem->restocked);
        $this->assertSame($firstQty + 1, $firstBatch->current_quantity);
    }

    public function test_cart_page_coupons_drawer_and_quotation_pdf_without_gd(): void
    {
        $variant = ProductVariant::query()->where('sku', 'GLM-LB-VITC-30')->firstOrFail();

        $this->post(route('cart.add'), [
            'variant_id' => $variant->id,
            'quantity' => 2,
        ])->assertRedirect();

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee($variant->product->name)
            ->assertSee('Descargar Cotización PDF');

        // Actualizar cantidad a 3 unidades
        $this->patch(route('cart.update'), [
            'variant_id' => $variant->id,
            'quantity' => 3,
        ])->assertRedirect(route('cart.index'));

        $this->assertSame(3, app(CartSessionService::class)->summary()['items_count']);

        // Aplicar cupón válido GLAMORA10
        $this->post(route('cart.coupon.apply'), ['code' => 'glamora10'])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('cart_success');

        // Cupón ORO50 debe fallar para minorista
        $this->post(route('cart.coupon.apply'), ['code' => 'ORO50'])
            ->assertSessionHasErrors('code');

        // Drawer muestra progreso de envío gratis y cupón
        Livewire::test(CartCounterDrawer::class)
            ->call('openDrawer')
            ->assertSee('Envío')
            ->assertSee('GLAMORA10');

        // Descargar Cotización PDF sin ext-gd
        $pdfResponse = $this->get(route('cart.quote-pdf'));
        $pdfResponse->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdfResponse->getContent());

        // Quitar cupón y eliminar ítem
        $this->delete(route('cart.coupon.remove'))->assertRedirect(route('cart.index'));
        $this->delete(route('cart.remove', $variant->id))->assertRedirect(route('cart.index'));
        $this->assertSame(0, app(CartSessionService::class)->summary()['items_count']);
    }

    public function test_checkout_all_five_payment_methods_and_validations(): void
    {
        Storage::fake('public');

        $vitC = ProductVariant::query()->where('sku', 'GLM-LB-VITC-30')->firstOrFail();
        $cliente = User::query()->where('email', 'cliente@glamora.pe')->firstOrFail();

        // 1. Checkout con Tarjeta (autenticado como cliente)
        $this->actingAs($cliente);
        $this->post(route('cart.add'), ['variant_id' => $vitC->id, 'quantity' => 2]);
        $this->post(route('cart.coupon.apply'), ['code' => 'GLAMORA10']);

        $response = $this->post(route('checkout.store'), [
            'customer_name' => $cliente->name,
            'customer_email' => $cliente->email,
            'customer_phone' => '912777888',
            'document_type' => 'DNI',
            'document_number' => '72456789',
            'shipping_address' => 'Calle Berlín 345, Dpto 502',
            'shipping_district' => 'Miraflores',
            'shipping_city' => 'Lima',
            'shipping_method' => 'estandar',
            'payment_method' => 'tarjeta',
            'card_holder' => 'Sofía Navarro',
            'card_last4' => '4242',
            'three_ds_code' => '3DS-OK-991',
        ]);

        $orderCard = Order::query()->where('customer_email', $cliente->email)->latest('id')->firstOrFail();
        $response->assertRedirect(route('checkout.confirmation', $orderCard->order_number));
        $this->assertSame(OrderStatus::PagoVerificado, $orderCard->status);
        $this->assertSame('verified', $orderCard->payments()->firstOrFail()->status);
        $this->assertNotEmpty($orderCard->items()->firstOrFail()->batch_allocations);
        $this->assertSame(0, app(CartSessionService::class)->summary()['items_count']);

        $this->get(route('checkout.confirmation', $orderCard->order_number))
            ->assertOk()
            ->assertSee($orderCard->order_number);

        $confirmPdf = $this->get(route('checkout.confirmation.pdf', $orderCard->order_number));
        $confirmPdf->assertOk();
        $this->assertStringStartsWith('%PDF-', $confirmPdf->getContent());

        // 2. Checkout invitado con Yape/Plin + subida de voucher PDF
        auth()->logout();
        $this->post(route('cart.add'), ['variant_id' => $vitC->id, 'quantity' => 1]);

        $voucherFile = UploadedFile::fake()->create('comprobante-yape.pdf', 120, 'application/pdf');
        $yapeResponse = $this->post(route('checkout.store'), [
            'customer_name' => 'Invitada Glamora',
            'customer_email' => 'invitada@glamora.pe',
            'customer_phone' => '999111222',
            'document_type' => 'DNI',
            'document_number' => '44556677',
            'shipping_address' => 'Av. Arequipa 1200',
            'shipping_district' => 'Lince',
            'shipping_city' => 'Lima',
            'shipping_method' => 'express',
            'payment_method' => 'yape_plin',
            'transaction_reference' => 'YAPE-554433',
            'voucher' => $voucherFile,
        ]);

        $orderYape = Order::query()->where('customer_email', 'invitada@glamora.pe')->latest('id')->firstOrFail();
        $yapeResponse->assertRedirect(route('checkout.confirmation', $orderYape->order_number));
        $this->assertSame(OrderStatus::PendientePago, $orderYape->status);
        $yapePayment = $orderYape->payments()->firstOrFail();
        $this->assertSame('pending', $yapePayment->status);
        $this->assertNotNull($yapePayment->voucher_path);
        Storage::disk('public')->assertExists($yapePayment->voucher_path);

        // 3. Checkout invitado con Transferencia bancaria + voucher
        $this->post(route('cart.add'), ['variant_id' => $vitC->id, 'quantity' => 1]);
        $bankVoucher = UploadedFile::fake()->create('bcp-voucher.pdf', 80, 'application/pdf');
        $this->post(route('checkout.store'), [
            'customer_name' => 'Transferencia Test',
            'customer_email' => 'transfer@glamora.pe',
            'customer_phone' => '999444333',
            'document_type' => 'DNI',
            'document_number' => '40112233',
            'shipping_address' => 'Av. Javier Prado 450',
            'shipping_district' => 'San Isidro',
            'shipping_city' => 'Lima',
            'shipping_method' => 'same_day',
            'payment_method' => 'transferencia',
            'bank_name' => 'BCP',
            'transaction_reference' => 'BCP-112233',
            'voucher' => $bankVoucher,
        ])->assertRedirect();

        $orderBank = Order::query()->where('customer_email', 'transfer@glamora.pe')->latest('id')->firstOrFail();
        $this->assertSame(OrderStatus::PendientePago, $orderBank->status);
        Storage::disk('public')->assertExists($orderBank->payments()->firstOrFail()->voucher_path);

        // 4. Checkout invitado con Cripto
        $this->post(route('cart.add'), ['variant_id' => $vitC->id, 'quantity' => 1]);
        $this->post(route('checkout.store'), [
            'customer_name' => 'Cripto Test',
            'customer_email' => 'cripto@glamora.pe',
            'customer_phone' => '999222333',
            'document_type' => 'CE',
            'document_number' => 'CE-998877',
            'shipping_address' => 'Av. El Golf 210',
            'shipping_district' => 'San Isidro',
            'shipping_city' => 'Lima',
            'shipping_method' => 'estandar',
            'payment_method' => 'cripto',
            'crypto_network' => 'TRC20',
            'crypto_tx_hash' => 'TX998877665544332211AABBCCDD',
        ])->assertRedirect();

        $orderCrypto = Order::query()->where('customer_email', 'cripto@glamora.pe')->latest('id')->firstOrFail();
        $this->assertSame(OrderStatus::PendientePago, $orderCrypto->status);
        $this->assertSame('TRC20', $orderCrypto->payments()->firstOrFail()->crypto_network);

        // 5. Checkout B2B con Crédito B2B (b2b@glamora.pe)
        $b2bUser = User::query()->where('email', 'b2b@glamora.pe')->firstOrFail();
        $profileBefore = $b2bUser->wholesaleProfile()->firstOrFail();
        $creditUsedBefore = (float) $profileBefore->credit_used;

        $this->actingAs($b2bUser);
        $this->post(route('cart.add'), ['variant_id' => $vitC->id, 'quantity' => 4]);

        $b2bResponse = $this->post(route('checkout.store'), [
            'customer_name' => $b2bUser->name,
            'customer_email' => $b2bUser->email,
            'customer_phone' => '982555666',
            'document_type' => 'RUC',
            'document_number' => '20604891234',
            'shipping_address' => 'Av. Primavera 890, Local 2',
            'shipping_district' => 'Santiago de Surco',
            'shipping_city' => 'Lima',
            'shipping_method' => 'estandar',
            'payment_method' => 'credito_b2b',
        ]);

        $orderB2b = Order::query()->where('user_id', $b2bUser->id)->latest('id')->firstOrFail();
        $b2bResponse->assertRedirect(route('checkout.confirmation', $orderB2b->order_number));
        $this->assertSame(OrderStatus::PagoVerificado, $orderB2b->status);
        $this->assertTrue($orderB2b->is_b2b);
        $this->assertNotNull($orderB2b->payments()->firstOrFail()->b2b_due_date);

        $profileBefore->refresh();
        $this->assertEqualsWithDelta($creditUsedBefore + (float) $orderB2b->total, (float) $profileBefore->credit_used, 0.02);
    }

    public function test_auth_registration_security_logs_and_2fa_challenge_flow(): void
    {
        // Registro con contraseña débil falla
        $this->post(route('register.store'), [
            'name' => 'Nueva Clienta',
            'email' => 'nueva@glamora.pe',
            'phone' => '999888111',
            'document_type' => 'DNI',
            'document_number' => '71234567',
            'password' => 'debil123',
            'password_confirmation' => 'debil123',
        ])->assertSessionHasErrors('password');

        // Registro válido crea usuario Cliente + SecurityLog
        $this->post(route('register.store'), [
            'name' => 'Valeria Rojas',
            'email' => 'valeria.rojas@glamora.pe',
            'phone' => '999888111',
            'document_type' => 'DNI',
            'document_number' => '71234567',
            'password' => 'GlamoraSafe#2026',
            'password_confirmation' => 'GlamoraSafe#2026',
        ])->assertRedirect(route('account.index'));

        $user = User::query()->where('email', 'valeria.rojas@glamora.pe')->firstOrFail();
        $this->assertTrue($user->hasRole('Cliente'));
        $this->assertDatabaseHas('security_logs', [
            'user_id' => $user->id,
            'event_type' => 'auth_registered',
        ]);

        // Activar y confirmar 2FA
        $this->post(route('account.security.2fa.enable'))
            ->assertRedirect(route('account.security'));
        $user->refresh();
        $this->assertNotEmpty($user->two_factor_secret);
        $this->assertFalse($user->two_factor_enabled);

        $totp = app(TotpService::class);
        $validCode = $totp->getCode($user->two_factor_secret);

        $this->post(route('account.security.2fa.confirm'), ['code' => $validCode])
            ->assertRedirect(route('account.security'))
            ->assertSessionHas('two_factor_recovery_codes');

        $user->refresh();
        $this->assertTrue($user->two_factor_enabled);
        $recoveryCodes = $user->two_factor_recovery_codes;
        $this->assertCount(8, $recoveryCodes);

        // Logout -> Login fallido registra auth_failed
        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->assertGuest();

        $this->post(route('login.store'), [
            'email' => 'valeria.rojas@glamora.pe',
            'password' => 'IncorrectPassword#1',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseHas('security_logs', [
            'user_id' => $user->id,
            'event_type' => 'auth_failed',
            'severity' => 'warning',
        ]);

        // Login válido debe redirigir a /2fa
        $this->post(route('login.store'), [
            'email' => 'valeria.rojas@glamora.pe',
            'password' => 'GlamoraSafe#2026',
        ])->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();

        // Código inválido falla
        $this->post(route('two-factor.verify'), ['code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->assertGuest();

        // Código de recuperación válido autentica y consume el código
        $this->post(route('two-factor.verify'), ['code' => $recoveryCodes[0]])
            ->assertRedirect(route('account.index'));
        $this->assertAuthenticatedAs($user);

        $user->refresh();
        $this->assertCount(7, $user->two_factor_recovery_codes);

        // Desactivar 2FA con contraseña actual
        $this->post(route('account.security.2fa.disable'), [
            'current_password' => 'GlamoraSafe#2026',
        ])->assertRedirect(route('account.security'));

        $user->refresh();
        $this->assertFalse($user->two_factor_enabled);
        $this->assertNull($user->two_factor_secret);

        // Cuenta inactiva bloqueada en login
        $user->forceFill(['is_active' => false])->save();
        $this->post(route('logout'));
        $this->post(route('login.store'), [
            'email' => 'valeria.rojas@glamora.pe',
            'password' => 'GlamoraSafe#2026',
        ])->assertSessionHasErrors('email');
    }

    public function test_account_portal_orders_addresses_returns_and_security(): void
    {
        $this->get(route('account.index'))->assertRedirect(route('login'));

        $cliente = User::query()->where('email', 'cliente@glamora.pe')->firstOrFail();
        $this->actingAs($cliente);

        $this->get(route('account.index'))->assertOk()->assertSee($cliente->name);
        $this->get(route('account.orders'))->assertOk()->assertSee('GLM-2026-0001');
        $this->get(route('account.orders.show', 'GLM-2026-0001'))->assertOk()->assertSee('GLM-2026-0001');

        // PDF del pedido propio
        $orderPdf = $this->get(route('account.orders.pdf', 'GLM-2026-0001'));
        $orderPdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $orderPdf->getContent());

        // Pedido de otro usuario bloqueado (403)
        $this->get(route('account.orders.show', 'GLM-2026-0003'))->assertForbidden();

        // Direcciones: crear, cambiar predeterminada y eliminar
        $this->get(route('account.addresses'))->assertOk();
        $this->post(route('account.addresses.store'), [
            'label' => 'Oficina San Isidro',
            'recipient_name' => 'Sofía Navarro',
            'phone' => '912777888',
            'address_line' => 'Av. Camino Real 456',
            'district' => 'San Isidro',
            'city' => 'Lima',
            'is_default' => '1',
        ])->assertRedirect(route('account.addresses'));

        $newAddress = UserAddress::query()->where('user_id', $cliente->id)->where('label', 'Oficina San Isidro')->firstOrFail();
        $this->assertTrue($newAddress->is_default);

        $oldAddress = UserAddress::query()->where('user_id', $cliente->id)->where('id', '!=', $newAddress->id)->firstOrFail();
        $this->patch(route('account.addresses.default', $oldAddress))
            ->assertRedirect(route('account.addresses'));
        $oldAddress->refresh();
        $this->assertTrue($oldAddress->is_default);

        $this->delete(route('account.addresses.destroy', $newAddress))
            ->assertRedirect(route('account.addresses'));
        $this->assertDatabaseMissing('user_addresses', ['id' => $newAddress->id]);

        // Devoluciones RMA
        $this->get(route('account.returns'))->assertOk();
        $order = Order::query()->where('order_number', 'GLM-2026-0001')->firstOrFail();
        $item = $order->items()->where('sku', 'GLM-LB-VITC-30')->firstOrFail();

        $this->post(route('account.returns.store'), [
            'order_number' => $order->order_number,
            'reason' => 'Cambio solicitado desde portal privado de cliente.',
            'items' => [
                $item->id => ['selected' => '1', 'quantity' => 1],
            ],
        ])->assertRedirect(route('account.returns'))
            ->assertSessionHas('account_success');

        // Seguridad: actualizar perfil y contraseña
        $this->get(route('account.security'))->assertOk();
        $this->patch(route('account.security.profile'), [
            'name' => 'Sofía Navarro Updated',
            'phone' => '+51 912 777 999',
            'document_type' => 'DNI',
            'document_number' => '72456789',
            'default_address' => 'Calle Berlín 345',
            'district' => 'Miraflores',
            'city' => 'Lima',
        ])->assertRedirect(route('account.security'));

        $this->patch(route('account.security.password'), [
            'current_password' => 'GlamoraCliente#2026',
            'password' => 'NuevoPassSeguro#2026',
            'password_confirmation' => 'NuevoPassSeguro#2026',
        ])->assertRedirect(route('account.security'));

        $cliente->refresh();
        $this->assertSame('Sofía Navarro Updated', $cliente->name);
    }

    public function test_checkout_confirmation_and_pdf_block_idor_across_guests_and_users(): void
    {
        // Invitado sin pedidos en sesión no puede ver ni descargar PDF de pedidos ajenos
        $this->get(route('checkout.confirmation', 'GLM-2026-0001'))->assertForbidden();
        $this->get(route('checkout.confirmation.pdf', 'GLM-2026-0001'))->assertForbidden();
        $this->get(route('checkout.confirmation', 'GLM-2026-9999'))->assertNotFound();

        // Usuario autenticado cliente no puede acceder a confirmación/PDF de usuario B2B
        $cliente = User::query()->where('email', 'cliente@glamora.pe')->firstOrFail();
        $b2b = User::query()->where('email', 'b2b@glamora.pe')->firstOrFail();

        $this->actingAs($cliente);
        $this->get(route('checkout.confirmation', 'GLM-2026-0003'))->assertForbidden();
        $this->get(route('checkout.confirmation.pdf', 'GLM-2026-0003'))->assertForbidden();

        // Dueño autenticado sí accede a su propio pedido
        $this->get(route('checkout.confirmation', 'GLM-2026-0001'))->assertOk();
        $this->get(route('checkout.confirmation.pdf', 'GLM-2026-0001'))->assertOk();

        // B2B no accede a pedido de cliente
        $this->actingAs($b2b);
        $this->get(route('checkout.confirmation', 'GLM-2026-0001'))->assertForbidden();
        $this->get(route('checkout.confirmation.pdf', 'GLM-2026-0001'))->assertForbidden();

        // Invitado crea orden: su propia sesión guarda checkout_allowed_orders y permite ver/PDF
        auth()->logout();
        $vitC = ProductVariant::query()->where('sku', 'GLM-LB-VITC-30')->firstOrFail();
        $this->post(route('cart.add'), ['variant_id' => $vitC->id, 'quantity' => 1]);

        $this->post(route('checkout.store'), [
            'customer_name' => 'Visitante Real',
            'customer_email' => 'visitante.real@glamora.pe',
            'customer_phone' => '999000111',
            'document_type' => 'DNI',
            'document_number' => '45678901',
            'shipping_address' => 'Av. Pardo 777',
            'shipping_district' => 'Miraflores',
            'shipping_city' => 'Lima',
            'shipping_method' => 'estandar',
            'payment_method' => 'tarjeta',
            'card_holder' => 'Visitante Real',
            'card_last4' => '1111',
        ])->assertRedirect();

        $guestOrder = Order::query()->where('customer_email', 'visitante.real@glamora.pe')->latest('id')->firstOrFail();
        $this->assertContains($guestOrder->order_number, session('checkout_allowed_orders', []));
        $this->get(route('checkout.confirmation', $guestOrder->order_number))->assertOk();
        $this->get(route('checkout.confirmation.pdf', $guestOrder->order_number))->assertOk();

        // Otra sesión limpia sin checkout_allowed_orders es bloqueada (403)
        session()->forget('checkout_allowed_orders');
        $this->get(route('checkout.confirmation', $guestOrder->order_number))->assertForbidden();
        $this->get(route('checkout.confirmation.pdf', $guestOrder->order_number))->assertForbidden();

        // Si alguien tiene el order_number en sesión pero pertenece a otro user_id autenticado, debe bloquearse (403)
        $this->actingAs($cliente)
            ->withSession(['checkout_allowed_orders' => ['GLM-2026-0003']])
            ->get(route('checkout.confirmation', 'GLM-2026-0003'))
            ->assertForbidden();
    }

    public function test_auth_rate_limiting_2fa_ttl_recovery_single_use_and_inactive_blocking(): void
    {
        RateLimiter::clear('login:cliente@glamora.pe|127.0.0.1');

        // 1. Rate limiting en LoginController (5 fallos -> 6to bloqueado)
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.store'), [
                'email' => 'cliente@glamora.pe',
                'password' => 'WrongPass#123',
            ])->assertSessionHasErrors('email');
        }

        $this->post(route('login.store'), [
            'email' => 'cliente@glamora.pe',
            'password' => 'GlamoraCliente#2026',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseHas('security_logs', [
            'event_type' => 'auth_rate_limited',
            'severity' => 'warning',
        ]);

        // 2. Configurar 2FA para probar consumo único de recovery code, TTL de 10 min y rate limiting 2FA
        $totp = app(TotpService::class);
        $secret = $totp->generateSecret();
        $recoveryCodes = $totp->generateRecoveryCodes(4);

        $cliente = User::query()->where('email', 'cliente@glamora.pe')->firstOrFail();
        $cliente->forceFill([
            'two_factor_enabled' => true,
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $recoveryCodes,
            'two_factor_confirmed_at' => now(),
        ])->save();

        RateLimiter::clear('login:cliente@glamora.pe|127.0.0.1');
        RateLimiter::clear("two-factor:{$cliente->id}|127.0.0.1");

        // Login dispara desafío 2FA con TTL
        $this->post(route('login.store'), [
            'email' => 'cliente@glamora.pe',
            'password' => 'GlamoraCliente#2026',
        ])->assertRedirect(route('two-factor.challenge'));
        $this->assertTrue(session()->has('two_factor:expires_at'));

        // Desafío expirado (> 10 minutos) redirige a login
        $this->travel(11)->minutes();
        $this->get(route('two-factor.challenge'))->assertRedirect(route('login'));
        $this->assertFalse(session()->has('two_factor:user_id'));
        $this->travelBack();

        // Re-login y rate limit en 2FA (5 fallos -> 6to bloqueado)
        $this->post(route('login.store'), [
            'email' => 'cliente@glamora.pe',
            'password' => 'GlamoraCliente#2026',
        ])->assertRedirect(route('two-factor.challenge'));

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('two-factor.verify'), ['code' => '000000'])
                ->assertSessionHasErrors('code');
        }

        $this->post(route('two-factor.verify'), ['code' => $totp->getCode($secret)])
            ->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->assertDatabaseHas('security_logs', [
            'user_id' => $cliente->id,
            'event_type' => 'two_factor_rate_limited',
        ]);

        // Limpiar throttle y probar que el código de recuperación se consume una sola vez
        RateLimiter::clear("two-factor:{$cliente->id}|127.0.0.1");

        $this->post(route('two-factor.verify'), ['code' => $recoveryCodes[0]])
            ->assertRedirect(route('account.index'));
        $this->assertAuthenticatedAs($cliente);

        $cliente->refresh();
        $this->assertCount(3, $cliente->two_factor_recovery_codes);
        $this->assertNotContains($recoveryCodes[0], $cliente->two_factor_recovery_codes);

        // Logout y re-intentar con el MISMO recovery code ya consumido debe fallar
        $this->post(route('logout'));
        $this->post(route('login.store'), [
            'email' => 'cliente@glamora.pe',
            'password' => 'GlamoraCliente#2026',
        ])->assertRedirect(route('two-factor.challenge'));

        $this->post(route('two-factor.verify'), ['code' => $recoveryCodes[0]])
            ->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_account_ownership_returns_empty_state_and_2fa_disable_password_checks(): void
    {
        $cliente = User::query()->where('email', 'cliente@glamora.pe')->firstOrFail();
        $b2b = User::query()->where('email', 'b2b@glamora.pe')->firstOrFail();

        $b2bAddress = UserAddress::query()->where('user_id', $b2b->id)->firstOrFail();
        $b2bOrder = Order::query()->where('order_number', 'GLM-2026-0003')->firstOrFail();
        $b2bOrderItem = $b2bOrder->items()->firstOrFail();

        $this->actingAs($cliente);

        // No puede ver/descargar PDF de pedido ajeno
        $this->get(route('account.orders.show', 'GLM-2026-0003'))->assertForbidden();
        $this->get(route('account.orders.pdf', 'GLM-2026-0003'))->assertForbidden();

        // No puede modificar ni eliminar direcciones ajenas
        $this->patch(route('account.addresses.default', $b2bAddress))->assertForbidden();
        $this->delete(route('account.addresses.destroy', $b2bAddress))->assertForbidden();

        // No puede registrar RMA sobre pedido ajeno ni inyectar order_item_id ajeno en su propio pedido
        $this->post(route('account.returns.store'), [
            'order_number' => 'GLM-2026-0003',
            'reason' => 'Intento de RMA sobre pedido ajeno.',
        ])->assertSessionHasErrors('order_number');

        $this->post(route('account.returns.store'), [
            'order_number' => 'GLM-2026-0001',
            'reason' => 'Intento de inyección de item ajeno en pedido propio.',
            'items' => [
                $b2bOrderItem->id => ['selected' => '1', 'quantity' => 1],
            ],
        ])->assertSessionHasErrors('items');

        // Desactivar 2FA exige contraseña actual válida
        $cliente->forceFill([
            'two_factor_enabled' => true,
            'two_factor_secret' => app(TotpService::class)->generateSecret(),
        ])->save();

        $this->post(route('account.security.2fa.disable'), [
            'current_password' => 'ClaveIncorrecta#999',
        ])->assertSessionHasErrors('current_password');

        $cliente->refresh();
        $this->assertTrue($cliente->two_factor_enabled);

        // Usuario sin pedidos elegibles puede cargar /mi-cuenta/devoluciones sin error 500
        $applicant = User::query()->where('email', 'postulante.b2b@glamora.pe')->firstOrFail();
        $this->actingAs($applicant)
            ->get(route('account.returns'))
            ->assertOk()
            ->assertSee('No tienes pedidos elegibles');
    }

    public function test_checkout_b2b_credit_vouchers_coupon_limits_shipping_and_fefo_rollback(): void
    {
        Storage::fake('public');

        $vitC = ProductVariant::query()->where('sku', 'GLM-LB-VITC-30')->firstOrFail();
        $cliente = User::query()->where('email', 'cliente@glamora.pe')->firstOrFail();
        $applicant = User::query()->where('email', 'postulante.b2b@glamora.pe')->firstOrFail();
        $b2b = User::query()->where('email', 'b2b@glamora.pe')->firstOrFail();

        $basePayload = [
            'customer_name' => 'Cliente Test',
            'customer_email' => 'cliente@glamora.pe',
            'customer_phone' => '999888777',
            'document_type' => 'DNI',
            'document_number' => '72456789',
            'shipping_address' => 'Av. Larco 100',
            'shipping_district' => 'Miraflores',
            'shipping_city' => 'Lima',
            'shipping_method' => 'estandar',
        ];

        // 1. Invitado y postulante B2B pendiente no pueden pagar con credito_b2b
        $this->post(route('cart.add'), ['variant_id' => $vitC->id, 'quantity' => 1]);
        $this->post(route('checkout.store'), array_merge($basePayload, [
            'payment_method' => 'credito_b2b',
        ]))->assertSessionHasErrors('payment_method');

        $this->actingAs($applicant);
        $this->post(route('checkout.store'), array_merge($basePayload, [
            'payment_method' => 'credito_b2b',
        ]))->assertSessionHasErrors('payment_method');

        // 2. Yape/Plin y Transferencia exigen voucher; Cripto exige network y tx_hash
        $this->actingAs($cliente);
        $this->post(route('checkout.store'), array_merge($basePayload, [
            'payment_method' => 'yape_plin',
            'transaction_reference' => 'YAPE-12345',
        ]))->assertSessionHasErrors('voucher');

        $this->post(route('checkout.store'), array_merge($basePayload, [
            'payment_method' => 'transferencia',
            'transaction_reference' => 'BCP-12345',
        ]))->assertSessionHasErrors('voucher');

        $this->post(route('checkout.store'), array_merge($basePayload, [
            'payment_method' => 'cripto',
        ]))->assertSessionHasErrors(['crypto_network', 'crypto_tx_hash']);

        // 3. Umbral envío gratis 199.00 evaluado tras cupón y Express nunca gratis
        $pricing = app(PricingService::class);
        $shipping = app(ShippingService::class);
        $coupon10 = Coupon::query()->where('code', 'GLAMORA10')->firstOrFail();
        // 3 * 89.00 = 267.00 -> con GLAMORA10 (-26.70) = 240.30 (>= 199.00 -> estandar gratis, express 22)
        // 2 * 89.00 = 178.00 -> con GLAMORA10 (-17.80) = 160.20 (< 199.00 -> estandar 12)
        // Probamos exactamente umbral post-cupón: subtotal 210 - 10% = 189 < 199 -> estandar 12
        $quoteAfterDiscount = $shipping->quote(ShippingMethod::Estandar, 189.00);
        $this->assertFalse($quoteAfterDiscount->isFree);
        $this->assertSame(12.00, $quoteAfterDiscount->cost->amount);

        $expressAboveThreshold = $shipping->quote(ShippingMethod::Express, 250.00);
        $this->assertFalse($expressAboveThreshold->isFree);
        $this->assertSame(22.00, $expressAboveThreshold->cost->amount);

        // 4. Coupon::used_count se incrementa en transacción y respeta max_uses
        $coupon10->update(['max_uses' => $coupon10->used_count + 1]);
        $usedBefore = $coupon10->used_count;

        $this->post(route('cart.add'), ['variant_id' => $vitC->id, 'quantity' => 2]); // total 3
        $this->post(route('cart.coupon.apply'), ['code' => 'GLAMORA10']);

        $this->post(route('checkout.store'), array_merge($basePayload, [
            'payment_method' => 'tarjeta',
            'card_last4' => '4242',
        ]))->assertRedirect();

        $coupon10->refresh();
        $this->assertSame($usedBefore + 1, $coupon10->used_count);

        // Si otro usuario intenta usar el cupón ya agotado en sesión, checkout lo rechaza y no supera max_uses
        $this->post(route('cart.add'), ['variant_id' => $vitC->id, 'quantity' => 2]);
        session(['cart_coupon' => 'GLAMORA10']);

        $this->post(route('checkout.store'), array_merge($basePayload, [
            'payment_method' => 'tarjeta',
            'card_last4' => '4242',
        ]))->assertSessionHasErrors('coupon');

        $coupon10->refresh();
        $this->assertSame($usedBefore + 1, $coupon10->used_count);

        // 5. Crédito B2B insuficiente rechazado + rollback atómico cuando falla FEFO
        $this->actingAs($b2b);
        /** @var WholesaleProfile $profile */
        $profile = $b2b->wholesaleProfile()->firstOrFail();
        $profile->update([
            'credit_limit' => (float) $profile->credit_used + 20.00,
        ]);

        $this->post(route('cart.add'), ['variant_id' => $vitC->id, 'quantity' => 2]);
        $creditUsedSnapshot = (float) $profile->credit_used;

        $this->post(route('checkout.store'), array_merge($basePayload, [
            'customer_name' => $b2b->name,
            'customer_email' => $b2b->email,
            'document_type' => 'RUC',
            'document_number' => '20604891234',
            'payment_method' => 'credito_b2b',
        ]))->assertSessionHasErrors('payment_method');

        $profile->refresh();
        $this->assertEqualsWithDelta($creditUsedSnapshot, (float) $profile->credit_used, 0.01);

        // Restaurar cupo suficiente pero agotar lotes FEFO antes del POST -> debe hacer rollback de credit_used
        $profile->update(['credit_limit' => 25000.00]);
        ProductBatch::query()
            ->where('product_variant_id', $vitC->id)
            ->update(['reserved_quantity' => \Illuminate\Support\Facades\DB::raw('current_quantity')]);

        $this->post(route('checkout.store'), array_merge($basePayload, [
            'customer_name' => $b2b->name,
            'customer_email' => $b2b->email,
            'document_type' => 'RUC',
            'document_number' => '20604891234',
            'payment_method' => 'credito_b2b',
        ]))->assertRedirect(route('checkout.index'))
            ->assertSessionHasErrors('stock');

        $profile->refresh();
        $this->assertEqualsWithDelta($creditUsedSnapshot, (float) $profile->credit_used, 0.01);

        // 6. Parámetro no numérico en DELETE /carrito/item/{variantId} devuelve 404 limpio (no 500 TypeError)
        $this->delete('/carrito/item/no-numerico')->assertNotFound();
    }
}
