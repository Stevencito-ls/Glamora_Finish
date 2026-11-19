<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CustomerTier;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReturnCondition;
use App\Enums\ShippingMethod;
use App\Models\AbandonedCart;
use App\Models\Coupon;
use App\Models\InventoryMovement;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\Payment;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductVariant;
use App\Models\ReturnItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class OperationsAndMarketingSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $admin = User::query()->where('email', 'admin@glamora.pe')->first();
            $almacenero = User::query()->where('email', 'almacen@glamora.pe')->first();
            $cliente = User::query()->where('email', 'cliente@glamora.pe')->first();
            $b2bUser = User::query()->where('email', 'b2b@glamora.pe')->first();
            $applicantUser = User::query()->where('email', 'postulante.b2b@glamora.pe')->first();

            $coupons = $this->seedCoupons();
            $orders = $this->seedOrders($admin, $almacenero, $cliente, $b2bUser, $applicantUser, $coupons);
            $this->seedReturns($almacenero, $orders);
            $this->seedBlogPosts($admin);
            $this->seedNewsletterSubscribers();
            $this->seedAbandonedCarts($cliente);
        });
    }

    /**
     * @return array<string, Coupon>
     */
    private function seedCoupons(): array
    {
        $definitions = [
            'GLAMORA10' => [
                'description' => '10% de descuento en toda la tienda Glamora para compras desde S/ 120.00.',
                'discount_type' => 'percentage',
                'discount_value' => 10.00,
                'min_order_amount' => 120.00,
                'max_uses' => 500,
                'used_count' => 1,
                'applicable_tier' => null,
                'starts_at' => now()->subDays(30),
                'expires_at' => now()->addDays(90),
                'is_active' => true,
            ],
            'BIENVENIDA15' => [
                'description' => '15% de bienvenida exclusivo para clientes minoristas en pedidos desde S/ 100.00.',
                'discount_type' => 'percentage',
                'discount_value' => 15.00,
                'min_order_amount' => 100.00,
                'max_uses' => 250,
                'used_count' => 1,
                'applicable_tier' => CustomerTier::Minorista,
                'starts_at' => now()->subDays(20),
                'expires_at' => now()->addDays(120),
                'is_active' => true,
            ],
            'ORO50' => [
                'description' => 'Bono fijo de S/ 50.00 para reposiciones Mayorista Oro desde S/ 800.00.',
                'discount_type' => 'fixed',
                'discount_value' => 50.00,
                'min_order_amount' => 800.00,
                'max_uses' => 100,
                'used_count' => 1,
                'applicable_tier' => CustomerTier::Oro,
                'starts_at' => now()->subDays(15),
                'expires_at' => now()->addDays(60),
                'is_active' => true,
            ],
        ];

        $map = [];

        foreach ($definitions as $code => $data) {
            $map[$code] = Coupon::query()->updateOrCreate(
                ['code' => $code],
                $data
            );
        }

        return $map;
    }

    /**
     * @param  array<string, Coupon>  $coupons
     * @return array<string, Order>
     */
    private function seedOrders(
        ?User $admin,
        ?User $almacenero,
        ?User $cliente,
        ?User $b2bUser,
        ?User $applicantUser,
        array $coupons,
    ): array {
        $variants = ProductVariant::query()
            ->with('product')
            ->get()
            ->keyBy('sku');

        $orders = [];

        // 1. Pedido Minorista Entregado (con cupón BIENVENIDA15)
        $orders['GLM-2026-0001'] = $this->createSeededOrder(
            orderNumber: 'GLM-2026-0001',
            trackingCode: 'GLM-TRK-260001',
            user: $cliente,
            tier: CustomerTier::Minorista,
            status: OrderStatus::Entregado,
            shippingMethod: ShippingMethod::Estandar,
            shippingCost: 12.00,
            coupon: $coupons['BIENVENIDA15'],
            isB2b: false,
            lineSpecs: [
                ['sku' => 'GLM-LB-VITC-30', 'qty' => 1],
                ['sku' => 'GLM-VR-LAB-03', 'qty' => 1],
                ['sku' => 'GLM-KD-CER-50', 'qty' => 1],
            ],
            paymentSpec: [
                'method' => PaymentMethod::Tarjeta,
                'status' => 'verified',
                'reference' => 'NIUBIZ-44981203',
                'verified_by' => $admin?->id,
                'verified_at' => now()->subDays(9),
                'notes' => 'Autorización automática 3D Secure Visa.',
            ],
            variants: $variants,
            actor: $almacenero,
            fefoAction: 'dispatch',
            timestamps: [
                'created_at' => now()->subDays(9),
                'fefo_dispatched_at' => now()->subDays(8),
                'delivered_at' => now()->subDays(7),
            ],
            notes: 'Entregado en recepción de edificio en Miraflores.',
        );

        // 2. Pedido Minorista Entregado (con cupón GLAMORA10, base para RMA resuelto)
        $orders['GLM-2026-0002'] = $this->createSeededOrder(
            orderNumber: 'GLM-2026-0002',
            trackingCode: 'GLM-TRK-260002',
            user: $cliente,
            tier: CustomerTier::Minorista,
            status: OrderStatus::Entregado,
            shippingMethod: ShippingMethod::Express,
            shippingCost: 22.00,
            coupon: $coupons['GLAMORA10'],
            isB2b: false,
            lineSpecs: [
                ['sku' => 'GLM-AA-GLO-01', 'qty' => 2],
                ['sku' => 'GLM-AA-BAS-210', 'qty' => 1],
            ],
            paymentSpec: [
                'method' => PaymentMethod::YapePlin,
                'status' => 'verified',
                'reference' => 'YAPE-774102',
                'verified_by' => $admin?->id,
                'verified_at' => now()->subDays(6),
                'notes' => 'Voucher Yape verificado contra estado de cuenta BCP.',
            ],
            variants: $variants,
            actor: $almacenero,
            fefoAction: 'dispatch',
            timestamps: [
                'created_at' => now()->subDays(6),
                'fefo_dispatched_at' => now()->subDays(5),
                'delivered_at' => now()->subDays(4),
            ],
            notes: 'Despacho Express 24h Lima Metropolitana.',
        );

        // 3. Pedido B2B Oro (b2b@glamora.pe) con total EXACTO de S/ 1,126.90 pagado con CreditoB2b
        // 1x GLM-B2B-KD-HID (615.00) + 1x GLM-SM-SOL-TON (66.00) + 13x GLM-VR-LAB-01 (34.30 c/u = 445.90) = 1126.90
        $orders['GLM-2026-0003'] = $this->createSeededOrder(
            orderNumber: 'GLM-2026-0003',
            trackingCode: 'GLM-TRK-260003',
            user: $b2bUser,
            tier: CustomerTier::Oro,
            status: OrderStatus::PagoVerificado,
            shippingMethod: ShippingMethod::Estandar,
            shippingCost: 0.00,
            coupon: null,
            isB2b: true,
            lineSpecs: [
                ['sku' => 'GLM-B2B-KD-HID', 'qty' => 1],
                ['sku' => 'GLM-SM-SOL-TON', 'qty' => 1],
                ['sku' => 'GLM-VR-LAB-01', 'qty' => 13],
            ],
            paymentSpec: [
                'method' => PaymentMethod::CreditoB2b,
                'status' => 'verified',
                'reference' => 'CRED-B2B-2026-003',
                'b2b_due_date' => now()->addDays(25)->toDateString(),
                'verified_by' => $admin?->id,
                'verified_at' => now()->subDays(5),
                'notes' => 'Cargo directo a línea de crédito B2B Oro (Aura Dorada S.A.C.) a 30 días.',
            ],
            variants: $variants,
            actor: $almacenero,
            fefoAction: 'reserve',
            timestamps: [
                'created_at' => now()->subDays(5),
            ],
            notes: 'Reposición quincenal Salón & Spa Aura Dorada S.A.C. con crédito B2B vigente.',
        );

        // 4. Pedido B2B En Picking (con cupón ORO50 y pago por Transferencia)
        $orders['GLM-2026-0004'] = $this->createSeededOrder(
            orderNumber: 'GLM-2026-0004',
            trackingCode: 'GLM-TRK-260004',
            user: $b2bUser,
            tier: CustomerTier::Oro,
            status: OrderStatus::EnPicking,
            shippingMethod: ShippingMethod::Estandar,
            shippingCost: 0.00,
            coupon: $coupons['ORO50'],
            isB2b: true,
            lineSpecs: [
                ['sku' => 'GLM-B2B-KD-ANT', 'qty' => 1],
                ['sku' => 'GLM-LB-VITC-30', 'qty' => 4],
            ],
            paymentSpec: [
                'method' => PaymentMethod::Transferencia,
                'status' => 'verified',
                'reference' => 'BCP-OP-8849201',
                'verified_by' => $admin?->id,
                'verified_at' => now()->subDay(),
                'notes' => 'Transferencia interbancaria BCP confirmada.',
            ],
            variants: $variants,
            actor: $almacenero,
            fefoAction: 'reserve',
            timestamps: [
                'created_at' => now()->subDays(2),
            ],
            notes: 'En preparación FEFO en zona climatizada para envío a Surco.',
        );

        // 5. Pedido Minorista Pendiente de Pago (Yape pendiente de verificación)
        $orders['GLM-2026-0005'] = $this->createSeededOrder(
            orderNumber: 'GLM-2026-0005',
            trackingCode: 'GLM-TRK-260005',
            user: $applicantUser,
            tier: CustomerTier::Minorista,
            status: OrderStatus::PendientePago,
            shippingMethod: ShippingMethod::Estandar,
            shippingCost: 12.00,
            coupon: null,
            isB2b: false,
            lineSpecs: [
                ['sku' => 'GLM-KD-NIA-30', 'qty' => 2],
                ['sku' => 'GLM-AA-DEL-01', 'qty' => 1],
            ],
            paymentSpec: [
                'method' => PaymentMethod::YapePlin,
                'status' => 'pending',
                'reference' => 'YAPE-904512',
                'notes' => 'Comprobante adjuntado; pendiente de conciliación por administración.',
            ],
            variants: $variants,
            actor: $almacenero,
            fefoAction: 'none',
            timestamps: [
                'created_at' => now()->subHours(8),
            ],
            notes: 'Envío a provincia (Arequipa). Esperando validación de pago Yape.',
        );

        return $orders;
    }

    /**
     * @param  list<array{sku: string, qty: int}>  $lineSpecs
     * @param  array<string, mixed>  $paymentSpec
     * @param  \Illuminate\Support\Collection<string, ProductVariant>  $variants
     * @param  array<string, mixed>  $timestamps
     */
    private function createSeededOrder(
        string $orderNumber,
        string $trackingCode,
        ?User $user,
        CustomerTier $tier,
        OrderStatus $status,
        ShippingMethod $shippingMethod,
        float $shippingCost,
        ?Coupon $coupon,
        bool $isB2b,
        array $lineSpecs,
        array $paymentSpec,
        Collection $variants,
        ?User $actor,
        string $fefoAction,
        array $timestamps,
        string $notes,
    ): Order {
        $existing = Order::query()->where('order_number', $orderNumber)->first();
        if ($existing !== null) {
            return $existing;
        }

        $subtotal = 0.0;
        $preparedLines = [];

        foreach ($lineSpecs as $spec) {
            $variant = $variants->get($spec['sku']);
            if (! $variant instanceof ProductVariant || $variant->product === null) {
                throw new RuntimeException("No se encontró la variante o producto para el SKU {$spec['sku']} en OperationsAndMarketingSeeder.");
            }

            $qty = $spec['qty'];
            $unitPrice = $variant->priceForTier($tier, $qty);
            $lineSubtotal = round($unitPrice * $qty, 2);
            $subtotal += $lineSubtotal;

            $preparedLines[] = [
                'variant' => $variant,
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'subtotal' => $lineSubtotal,
            ];
        }

        $subtotal = round($subtotal, 2);
        $discountAmount = $coupon !== null ? round($coupon->calculateDiscount($subtotal), 2) : 0.0;
        $netSubtotal = max(0.0, round($subtotal - $discountAmount, 2));
        $taxableBase = round($netSubtotal / 1.18, 2);
        $taxIgv = round($netSubtotal - $taxableBase, 2);
        $total = round($netSubtotal + $shippingCost, 2);
        $createdAt = $timestamps['created_at'] ?? now();

        $order = new Order([
            'order_number' => $orderNumber,
            'tracking_code' => $trackingCode,
            'user_id' => $user?->id,
            'customer_name' => $user?->name ?? 'Cliente Glamora',
            'customer_email' => $user?->email ?? 'cliente@glamora.pe',
            'customer_phone' => $user?->phone,
            'document_type' => $user?->document_type ?? 'DNI',
            'document_number' => $user?->document_number,
            'shipping_address' => $user?->default_address ?? 'Av. Larco 1150',
            'shipping_district' => $user?->district ?? 'Miraflores',
            'shipping_city' => $user?->city ?? 'Lima',
            'shipping_method' => $shippingMethod,
            'shipping_cost' => $shippingCost,
            'customer_tier' => $tier,
            'status' => $status,
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'coupon_code' => $coupon?->code,
            'tax_igv' => $taxIgv,
            'total' => $total,
            'is_b2b' => $isB2b,
            'notes' => $notes,
            'fefo_dispatched_at' => $timestamps['fefo_dispatched_at'] ?? null,
            'delivered_at' => $timestamps['delivered_at'] ?? null,
        ]);
        $order->created_at = $createdAt;
        $order->updated_at = $createdAt;
        $order->save();

        foreach ($preparedLines as $line) {
            /** @var ProductVariant $variant */
            $variant = $line['variant'];
            $allocations = [];
            $primaryBatchId = null;

            if ($fefoAction !== 'none') {
                $allocations = $this->allocateFefoBatches(
                    variant: $variant,
                    quantityNeeded: $line['qty'],
                    mode: $fefoAction,
                    order: $order,
                    actor: $actor,
                );
                $primaryBatchId = $allocations[0]['batch_id'] ?? null;
            }

            OrderItem::query()->create([
                'order_id' => $order->id,
                'product_variant_id' => $variant->id,
                'product_batch_id' => $primaryBatchId,
                'product_name' => $variant->product->name,
                'variant_name' => $variant->shade_name,
                'sku' => $variant->sku,
                'shade_hex' => $variant->hex_color,
                'quantity' => $line['qty'],
                'unit_price' => $line['unit_price'],
                'subtotal' => $line['subtotal'],
                'batch_allocations' => $allocations !== [] ? $allocations : null,
            ]);
        }

        Payment::query()->create([
            'order_id' => $order->id,
            'payment_method' => $paymentSpec['method'],
            'amount' => $total,
            'currency' => 'PEN',
            'status' => $paymentSpec['status'],
            'transaction_reference' => $paymentSpec['reference'] ?? null,
            'b2b_due_date' => $paymentSpec['b2b_due_date'] ?? null,
            'verified_by' => $paymentSpec['verified_by'] ?? null,
            'verified_at' => $paymentSpec['verified_at'] ?? null,
            'notes' => $paymentSpec['notes'] ?? null,
        ]);

        return $order;
    }

    /**
     * @return list<array{batch_id: int, batch_number: string, quantity: int, expiration_date: string}>
     */
    private function allocateFefoBatches(
        ProductVariant $variant,
        int $quantityNeeded,
        string $mode,
        Order $order,
        ?User $actor,
    ): array {
        $remaining = $quantityNeeded;
        $allocations = [];

        $batches = $variant->batches()->availableForFefo()->lockForUpdate()->get();

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $available = $batch->availableQuantity();
            if ($available <= 0) {
                continue;
            }

            $take = min($remaining, $available);

            if ($mode === 'dispatch') {
                $batch->decrement('current_quantity', $take);
                $movementType = 'salida';
                $movementNote = "Salida FEFO por despacho de pedido {$order->order_number} (lote {$batch->batch_number}).";
            } else {
                $batch->increment('reserved_quantity', $take);
                $movementType = 'reserva';
                $movementNote = "Reserva FEFO asignada a pedido {$order->order_number} (lote {$batch->batch_number}).";
            }

            $batch->refresh();
            $variant->unsetRelation('batches');
            $variantBalanceAfter = $variant->availableStock();

            InventoryMovement::query()->create([
                'product_variant_id' => $variant->id,
                'product_batch_id' => $batch->id,
                'warehouse_location_id' => $batch->warehouse_location_id,
                'user_id' => $actor?->id,
                'movement_type' => $movementType,
                'quantity' => -$take,
                'balance_after' => $variantBalanceAfter,
                'unit_cost' => $batch->unit_cost,
                'reference_type' => Order::class,
                'reference_id' => $order->id,
                'notes' => $movementNote,
            ]);

            $allocations[] = [
                'batch_id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'quantity' => $take,
                'expiration_date' => $batch->expiration_date->toDateString(),
            ];

            $remaining -= $take;
        }

        if ($remaining > 0) {
            throw new RuntimeException("Stock FEFO insuficiente para {$variant->sku} en pedido {$order->order_number} (faltante: {$remaining}).");
        }

        return $allocations;
    }

    /**
     * @param  array<string, Order>  $orders
     */
    private function seedReturns(?User $almacenero, array $orders): void
    {
        // RMA 1: Solicitado (pendiente de inspección)
        $order1 = $orders['GLM-2026-0001'];
        if (! OrderReturn::query()->where('rma_number', 'RMA-2026-0001')->exists()) {
            $orderItem1 = $order1->items()->where('sku', 'GLM-VR-LAB-03')->first();

            if ($orderItem1 !== null) {
                $rma1 = OrderReturn::query()->create([
                    'rma_number' => 'RMA-2026-0001',
                    'order_id' => $order1->id,
                    'user_id' => $order1->user_id,
                    'requester_name' => $order1->customer_name,
                    'requester_email' => $order1->customer_email,
                    'reason' => 'Error al elegir el tono del labial; desea cambio por Terracota Limeña. Caja sellada sin abrir.',
                    'status' => 'solicitado',
                    'refund_amount' => 0.00,
                ]);

                ReturnItem::query()->create([
                    'return_id' => $rma1->id,
                    'order_item_id' => $orderItem1->id,
                    'product_variant_id' => $orderItem1->product_variant_id,
                    'product_batch_id' => $orderItem1->product_batch_id,
                    'quantity' => 1,
                    'condition' => null,
                    'restocked' => false,
                ]);
            }
        }

        // RMA 2: Resuelto (apto para reventa + reintegro a stock del lote original)
        $order2 = $orders['GLM-2026-0002'];
        if (! OrderReturn::query()->where('rma_number', 'RMA-2026-0002')->exists()) {
            $orderItem2 = $order2->items()->where('sku', 'GLM-AA-GLO-01')->first();

            if ($orderItem2 !== null) {
                $rma2 = OrderReturn::query()->create([
                    'rma_number' => 'RMA-2026-0002',
                    'order_id' => $order2->id,
                    'user_id' => $order2->user_id,
                    'requester_name' => $order2->customer_name,
                    'requester_email' => $order2->customer_email,
                    'reason' => 'Compra duplicada de 1 unidad de Gloss Cristal Rosé; producto con precinto de seguridad intacto.',
                    'status' => 'resuelto',
                    'inspector_id' => $almacenero?->id,
                    'inspected_at' => now()->subDays(2),
                    'refund_amount' => 48.60,
                    'resolution_notes' => 'Verificado en zona ALM-CUAR-E01. Precinto intacto y fecha vigente; reingresado al lote FEFO original.',
                ]);

                ReturnItem::query()->create([
                    'return_id' => $rma2->id,
                    'order_item_id' => $orderItem2->id,
                    'product_variant_id' => $orderItem2->product_variant_id,
                    'product_batch_id' => $orderItem2->product_batch_id,
                    'quantity' => 1,
                    'condition' => ReturnCondition::Reventa,
                    'restocked' => true,
                    'inspection_notes' => 'Aprobado por control sanitario para reventa inmediata.',
                ]);

                $batch = $orderItem2->product_batch_id !== null
                    ? ProductBatch::query()->find($orderItem2->product_batch_id)
                    : null;

                if ($batch !== null && $orderItem2->variant !== null) {
                    $batch->increment('current_quantity', 1);
                    $batch->refresh();
                    $orderItem2->variant->unsetRelation('batches');

                    InventoryMovement::query()->create([
                        'product_variant_id' => $orderItem2->product_variant_id,
                        'product_batch_id' => $batch->id,
                        'warehouse_location_id' => $batch->warehouse_location_id,
                        'user_id' => $almacenero?->id,
                        'movement_type' => 'devolucion',
                        'quantity' => 1,
                        'balance_after' => $orderItem2->variant->availableStock(),
                        'unit_cost' => $batch->unit_cost,
                        'reference_type' => OrderReturn::class,
                        'reference_id' => $rma2->id,
                        'notes' => "Reingreso por devolución RMA-2026-0002 apta para reventa (lote {$batch->batch_number}).",
                    ]);
                }
            }
        }
    }

    private function seedBlogPosts(?User $author): void
    {
        $products = Product::query()->get()->keyBy('slug');

        $postsSpec = [
            [
                'slug' => 'rutina-vitamina-c-humedad-lima',
                'title' => 'Rutina antioxidante para el clima costero: Vitamina C + Ceramidas + FPS 50+',
                'category_tag' => 'Skincare',
                'excerpt' => 'Aprende cómo blindar tu barrera cutánea frente a la humedad limeña y la radiación UV extrema en 3 pasos dermatológicos.',
                'content' => '<p>En ciudades costeras con alta humedad y radiación UV índice 11+, la piel necesita fórmulas ligeras que no saturen el poro pero bloqueen el estrés oxidativo desde primera hora de la mañana.</p><h3>Paso 1: Antioxidante con Camu Camu Amazónico</h3><p>El ácido etil ascórbico al 15% estabilizado con ácido ferúlico potencia la luminosidad y atenúa manchas post-solares sin irritar.</p><h3>Paso 2: Sellado biomimético de barrera</h3><p>La combinación de 5 ceramidas y escualano vegetal mantiene la hidratación elástica durante 72 horas.</p><h3>Paso 3: Fotoprotección mineral toque seco</h3><p>Finaliza con óxido de zinc no-nano FPS 50+ resistente al sudor y sin residuo blanquecino.</p>',
                'cover_image' => 'https://images.unsplash.com/photo-1620916566398-39f1143ab7be?auto=format&fit=crop&w=1200&q=85',
                'reading_time_minutes' => 5,
                'published_at' => now()->subDays(14),
                'products' => [
                    'serum-vitamina-c-camu-camu' => 'Paso 1 · Aplica 4 gotas cada mañana sobre piel limpia',
                    'crema-gel-ceramidas-escualano' => 'Paso 2 · Sella la hidratación sin sensación grasa',
                    'fluido-solar-mineral-fps50' => 'Paso 3 · Protección mineral invisible FPS 50+',
                ],
            ],
            [
                'slug' => 'guia-tonos-labiales-subtono-latino',
                'title' => 'Colorimetría editorial: elige el labial Velvet Matte ideal según tu subtono de piel',
                'category_tag' => 'Maquillaje',
                'excerpt' => 'Desde terracotas cálidas hasta borgoñas intensos: descubre qué pigmentos iluminan instantáneamente tu rostro.',
                'content' => '<p>Encontrar el tono de labial perfecto transforma todo el look sin necesidad de recargar el maquillaje de ojos o rostro.</p><h3>Subtonos cálidos y oliva</h3><p>Los tonos terracota y coral suavizan las sombras del rostro y aportan vitalidad inmediata.</p><h3>Subtonos neutros y fríos</h3><p>El rosa nude y el borgoña profundo equilibran la tez y estilizan la sonrisa con un acabado aterciopelado de 12 horas.</p>',
                'cover_image' => 'https://images.unsplash.com/photo-1586495777744-4413f21062fa?auto=format&fit=crop&w=1200&q=85',
                'reading_time_minutes' => 4,
                'published_at' => now()->subDays(8),
                'products' => [
                    'labial-liquido-velvet-matte' => 'Favorito editorial · Duración 12H con manteca de cupuaçu',
                    'gloss-voluminizador-peptidos' => 'Tip Pro · Úsalo encima para efecto volumen espejo',
                    'rubor-liquido-sedoso-botanical' => 'Combínalo en pómulos para look monocromático',
                ],
            ],
            [
                'slug' => 'gestion-cabina-b2b-rentabilidad-salon',
                'title' => 'Rentabilidad en salones y spas: protocolos de cabina y rotación FEFO sin merma',
                'category_tag' => 'Profesional B2B',
                'excerpt' => 'Descubre cómo los centros estéticos aliados de Glamora maximizan su margen mayorista con exhibidores llave en mano y lotes trazables.',
                'content' => '<p>Un centro estético rentable combina servicios en cabina de alto margen con venta sugerida en recepción.</p><h3>Control sanitario DIGEMID y método FEFO</h3><p>Despachar siempre el lote con vencimiento más cercano reduce a cero las pérdidas por caducidad en vitrina.</p><h3>Escala Oro hasta 30% de descuento</h3><p>Los socios B2B acceden a packs cerrados y línea de crédito a 30 días para sostener inventario constante.</p>',
                'cover_image' => 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?auto=format&fit=crop&w=1200&q=85',
                'reading_time_minutes' => 6,
                'published_at' => now()->subDays(3),
                'products' => [
                    'pack-cabina-facial-kora-derma-b2b' => 'Cabina Pro · Protocolo completo x12 frascos',
                    'exhibidor-labiales-velvet-matte-b2b' => 'Recepción · Display acrílico listo para venta retail',
                    'serum-niacinamida-10-zinc' => 'Top rotación · Ideal para protocolo seborregulador',
                ],
            ],
        ];

        foreach ($postsSpec as $spec) {
            $post = Post::query()->updateOrCreate(
                ['slug' => $spec['slug']],
                [
                    'author_id' => $author?->id,
                    'title' => $spec['title'],
                    'category_tag' => $spec['category_tag'],
                    'excerpt' => $spec['excerpt'],
                    'content' => $spec['content'],
                    'cover_image' => $spec['cover_image'],
                    'reading_time_minutes' => $spec['reading_time_minutes'],
                    'is_published' => true,
                    'published_at' => $spec['published_at'],
                    'meta_title' => "{$spec['title']} | Blog Glamora",
                    'meta_description' => $spec['excerpt'],
                ]
            );

            $syncPayload = [];
            $sortOrder = 1;

            foreach ($spec['products'] as $productSlug => $callout) {
                $product = $products->get($productSlug);
                if ($product !== null) {
                    $syncPayload[$product->id] = [
                        'sort_order' => $sortOrder++,
                        'custom_callout' => $callout,
                    ];
                }
            }

            $post->products()->sync($syncPayload);
        }
    }

    private function seedNewsletterSubscribers(): void
    {
        $subscribers = [
            [
                'email' => 'cliente@glamora.pe',
                'name' => 'Sofía Navarro',
                'segment' => 'skincare',
                'is_verified' => true,
                'is_active' => true,
                'subscribed_at' => now()->subDays(25),
                'unsubscribed_at' => null,
            ],
            [
                'email' => 'b2b@glamora.pe',
                'name' => 'Luciana Salazar (Aura Dorada)',
                'segment' => 'b2b',
                'is_verified' => true,
                'is_active' => true,
                'subscribed_at' => now()->subDays(40),
                'unsubscribed_at' => null,
            ],
            [
                'email' => 'mariana.castro@gmail.com',
                'name' => 'Mariana Castro',
                'segment' => 'maquillaje',
                'is_verified' => true,
                'is_active' => true,
                'subscribed_at' => now()->subDays(12),
                'unsubscribed_at' => null,
            ],
            [
                'email' => 'paula.diaz@estudio.pe',
                'name' => 'Paula Díaz',
                'segment' => 'general',
                'is_verified' => false,
                'is_active' => true,
                'subscribed_at' => now()->subDays(2),
                'unsubscribed_at' => null,
            ],
            [
                'email' => 'fernanda.vega@outlook.com',
                'name' => 'Fernanda Vega',
                'segment' => 'general',
                'is_verified' => true,
                'is_active' => false,
                'subscribed_at' => now()->subDays(60),
                'unsubscribed_at' => now()->subDays(10),
            ],
        ];

        foreach ($subscribers as $subscriber) {
            $existing = NewsletterSubscriber::query()->where('email', $subscriber['email'])->first();

            NewsletterSubscriber::query()->updateOrCreate(
                ['email' => $subscriber['email']],
                [
                    'name' => $subscriber['name'],
                    'segment' => $subscriber['segment'],
                    'opt_in_token' => $existing?->opt_in_token ?? Str::random(40),
                    'is_verified' => $subscriber['is_verified'],
                    'is_active' => $subscriber['is_active'],
                    'subscribed_at' => $subscriber['subscribed_at'],
                    'unsubscribed_at' => $subscriber['unsubscribed_at'],
                ]
            );
        }
    }

    private function seedAbandonedCarts(?User $cliente): void
    {
        $vitC = ProductVariant::query()->where('sku', 'GLM-LB-VITC-30')->first();
        $solar = ProductVariant::query()->where('sku', 'GLM-SM-SOL-INV')->first();
        $labial = ProductVariant::query()->where('sku', 'GLM-VR-LAB-01')->first();
        $rubor = ProductVariant::query()->where('sku', 'GLM-VR-RUB-01')->first();

        if ($vitC === null || $solar === null || $labial === null || $rubor === null) {
            return;
        }

        AbandonedCart::query()->updateOrCreate(
            ['session_id' => 'sess_glamora_abandoned_001'],
            [
                'user_id' => $cliente?->id,
                'email' => $cliente?->email ?? 'cliente@glamora.pe',
                'cart_payload' => [
                    [
                        'product_variant_id' => $vitC->id,
                        'sku' => $vitC->sku,
                        'product_name' => 'Sérum Vitamina C 15% + Ácido Ferúlico Amazónico',
                        'shade_name' => $vitC->shade_name,
                        'quantity' => 1,
                        'unit_price' => (float) $vitC->base_price,
                        'subtotal' => (float) $vitC->base_price,
                        'image_path' => $vitC->image_url,
                    ],
                    [
                        'product_variant_id' => $solar->id,
                        'sku' => $solar->sku,
                        'product_name' => 'Fluido Solar Mineral Toque Seco FPS 50+ PA++++',
                        'shade_name' => $solar->shade_name,
                        'quantity' => 1,
                        'unit_price' => (float) $solar->base_price,
                        'subtotal' => (float) $solar->base_price,
                        'image_path' => $solar->image_url,
                    ],
                ],
                'subtotal' => round((float) $vitC->base_price + (float) $solar->base_price, 2),
                'items_count' => 2,
                'last_activity_at' => now()->subHours(3)->subMinutes(15),
                'reminder_count' => 0,
                'last_reminded_at' => null,
                'recovered_at' => null,
            ]
        );

        AbandonedCart::query()->updateOrCreate(
            ['session_id' => 'sess_glamora_abandoned_002'],
            [
                'user_id' => null,
                'email' => 'valeria.campos@outlook.com',
                'cart_payload' => [
                    [
                        'product_variant_id' => $labial->id,
                        'sku' => $labial->sku,
                        'product_name' => 'Labial Líquido Velvet Matte 12H',
                        'shade_name' => $labial->shade_name,
                        'quantity' => 2,
                        'unit_price' => (float) $labial->base_price,
                        'subtotal' => round((float) $labial->base_price * 2, 2),
                        'image_path' => $labial->image_url,
                    ],
                    [
                        'product_variant_id' => $rubor->id,
                        'sku' => $rubor->sku,
                        'product_name' => 'Rubor Líquido Sedoso Botanical Cheek',
                        'shade_name' => $rubor->shade_name,
                        'quantity' => 1,
                        'unit_price' => (float) $rubor->base_price,
                        'subtotal' => (float) $rubor->base_price,
                        'image_path' => $rubor->image_url,
                    ],
                ],
                'subtotal' => round(((float) $labial->base_price * 2) + (float) $rubor->base_price, 2),
                'items_count' => 3,
                'last_activity_at' => now()->subHours(6),
                'reminder_count' => 0,
                'last_reminded_at' => null,
                'recovered_at' => null,
            ]
        );
    }
}
