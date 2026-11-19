<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CustomerTier;
use App\Enums\OrderStatus;
use App\Enums\ReturnCondition;
use App\Models\Brand;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductVariant;
use App\Models\ReturnItem;
use App\Models\TieredPrice;
use App\Models\User;
use App\Models\WarehouseLocation;
use App\Models\WholesaleProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Sprint4Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_rbac_access_matrix_for_all_five_roles_and_inactive_users(): void
    {
        // Guest redirected to login
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));

        $superAdmin = User::query()->where('email', 'superadmin@glamora.pe')->firstOrFail();
        $admin = User::query()->where('email', 'admin@glamora.pe')->firstOrFail();
        $almacenero = User::query()->where('email', 'almacen@glamora.pe')->firstOrFail();
        $vendedorB2b = User::query()->where('email', 'b2b@glamora.pe')->firstOrFail();
        $cliente = User::query()->where('email', 'cliente@glamora.pe')->firstOrFail();

        $otherOrder = Order::query()->where('order_number', 'GLM-2026-0005')->firstOrFail();
        $ownB2bOrder = Order::query()->where('order_number', 'GLM-2026-0003')->firstOrFail();
        $otherRma = OrderReturn::query()->where('rma_number', 'RMA-2026-0001')->firstOrFail();
        $pendingWholesale = WholesaleProfile::query()->where('status', 'pending')->firstOrFail();
        $ownWholesale = WholesaleProfile::query()->where('user_id', $vendedorB2b->id)->firstOrFail();
        $product = Product::query()->firstOrFail();

        // 1. Cliente -> 403 en todo /admin
        $this->actingAs($cliente);
        foreach ([
            route('admin.dashboard'),
            route('admin.pim.products.index'),
            route('admin.pim.categories.index'),
            route('admin.pim.brands.index'),
            route('admin.inventory.index'),
            route('admin.inventory.kardex'),
            route('admin.inventory.locations.index'),
            route('admin.fulfillment.index'),
            route('admin.rma.index'),
            route('admin.wholesale.index'),
            route('admin.users.index'),
            route('admin.audit.index'),
        ] as $url) {
            $this->get($url)->assertForbidden();
        }

        // 2. Usuario staff inactivo -> 403 en todo /admin
        $inactiveStaff = User::query()->create([
            'name' => 'Staff Inactivo',
            'email' => 'inactivo.staff@glamora.pe',
            'password' => 'GlamoraTest#2026',
        ]);
        $inactiveStaff->forceFill(['is_active' => false])->save();
        $inactiveStaff->assignRole('Administrador');

        $this->actingAs($inactiveStaff);
        $this->get(route('admin.dashboard'))->assertForbidden();
        $this->get(route('admin.pim.products.index'))->assertForbidden();
        $this->get(route('admin.users.index'))->assertForbidden();

        // 3. SuperAdmin -> acceso total
        $this->actingAs($superAdmin);
        foreach ([
            route('admin.dashboard'),
            route('admin.pim.products.index'),
            route('admin.pim.products.create'),
            route('admin.pim.categories.index'),
            route('admin.pim.brands.index'),
            route('admin.inventory.index'),
            route('admin.inventory.kardex'),
            route('admin.inventory.locations.index'),
            route('admin.fulfillment.index'),
            route('admin.rma.index'),
            route('admin.wholesale.index'),
            route('admin.users.index'),
            route('admin.audit.index'),
        ] as $url) {
            $this->get($url)->assertOk();
        }

        // 4. Administrador -> sin inventory.locations.manage ni inventory.adjust
        $this->actingAs($admin);
        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Ventas Verificadas')
            ->assertSee('Margen Bruto Estimado');
        $this->get(route('admin.pim.products.create'))->assertOk();
        $this->get(route('admin.inventory.index'))->assertOk();
        $this->get(route('admin.inventory.locations.index'))->assertForbidden();
        $batch = ProductBatch::query()->firstOrFail();
        $this->post(route('admin.inventory.batches.adjust', $batch), [
            'action' => 'quarantine',
            'reason' => 'Intento sin permiso adjust',
        ])->assertForbidden();
        $this->get(route('admin.users.index'))->assertOk();
        $this->get(route('admin.audit.index'))->assertOk();

        // 5. Almacenero -> Inventario, ubicaciones, picking/despacho FEFO, RMA inspect; bloqueado en PIM create/edit, pagos verify, RMA resolve, wholesale, users, audit
        $this->actingAs($almacenero);
        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Rotación de Inventario')
            ->assertDontSee('Ventas Verificadas');
        $this->get(route('admin.pim.products.index'))->assertOk();
        $this->get(route('admin.pim.products.edit', $product))->assertOk();
        $this->get(route('admin.pim.products.create'))->assertForbidden();
        $this->put(route('admin.pim.products.update', $product), ['name' => 'No Permitido'])->assertForbidden();
        $this->get(route('admin.inventory.index'))->assertOk();
        $this->get(route('admin.inventory.locations.index'))->assertOk();
        $this->get(route('admin.fulfillment.index'))->assertOk();
        $this->get(route('admin.fulfillment.orders.show', $otherOrder))->assertOk();
        $otherPayment = $otherOrder->payments()->firstOrFail();
        $this->get(route('admin.fulfillment.payments.voucher', $otherPayment))->assertForbidden();
        $this->post(route('admin.fulfillment.payments.verify', $otherPayment))->assertForbidden();
        $this->patch(route('admin.fulfillment.orders.transition', $ownB2bOrder), [
            'status' => OrderStatus::Cancelado->value,
            'notes' => 'Almacenero no cancela',
        ])->assertForbidden();
        $this->get(route('admin.rma.index'))->assertOk();
        $this->post(route('admin.rma.resolve', $otherRma), [
            'status' => 'resuelto',
            'refund_amount' => 10,
            'resolution_notes' => 'No permitido',
        ])->assertForbidden();
        $this->get(route('admin.wholesale.index'))->assertForbidden();
        $this->get(route('admin.users.index'))->assertForbidden();
        $this->get(route('admin.audit.index'))->assertForbidden();

        // 6. Vendedor B2B -> solo sus propios pedidos/RMAs/perfil B2B; sin KPIs financieros ni inventario; sin acciones de staff ni IDOR
        $this->actingAs($vendedorB2b);
        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('Ventas Verificadas')
            ->assertDontSee('Rotación de Inventario')
            ->assertDontSee('GLM-2026-0005');
        $this->get(route('admin.pim.products.index'))->assertOk();
        $this->get(route('admin.pim.products.create'))->assertForbidden();
        $this->get(route('admin.inventory.index'))->assertForbidden();
        $this->get(route('admin.fulfillment.index'))
            ->assertOk()
            ->assertSee($ownB2bOrder->order_number)
            ->assertDontSee($otherOrder->order_number);
        $this->get(route('admin.fulfillment.orders.show', $ownB2bOrder))->assertOk();
        $this->get(route('admin.fulfillment.orders.show', $otherOrder))->assertForbidden();
        $this->get(route('admin.fulfillment.payments.voucher', $otherPayment))->assertForbidden();
        $this->post(route('admin.fulfillment.payments.verify', $otherPayment))->assertForbidden();
        $this->patch(route('admin.fulfillment.orders.transition', $ownB2bOrder), [
            'status' => OrderStatus::EnPicking->value,
        ])->assertForbidden();
        $this->get(route('admin.rma.show', $otherRma))->assertForbidden();
        $this->get(route('admin.wholesale.index'))
            ->assertOk()
            ->assertSee($ownWholesale->company_name)
            ->assertDontSee($pendingWholesale->company_name);
        $this->get(route('admin.wholesale.show', $ownWholesale))->assertOk();
        $this->get(route('admin.wholesale.show', $pendingWholesale))->assertForbidden();
        $this->post(route('admin.wholesale.approve', $pendingWholesale), [
            'approved_tier' => 'oro',
            'credit_limit' => 5000,
            'payment_terms_days' => 30,
        ])->assertForbidden();
        $this->get(route('admin.users.index'))->assertForbidden();
        $this->get(route('admin.audit.index'))->assertForbidden();
    }

    public function test_user_policy_protects_superadmin_and_prevents_self_lockout(): void
    {
        $superAdmin = User::query()->where('email', 'superadmin@glamora.pe')->firstOrFail();
        $admin = User::query()->where('email', 'admin@glamora.pe')->firstOrFail();
        $cliente = User::query()->where('email', 'cliente@glamora.pe')->firstOrFail();

        $superAdmin->forceFill(['two_factor_enabled' => true])->save();

        // Administrador NO puede crear SuperAdmin (ni con variante de mayúsculas/minúsculas)
        $this->actingAs($admin);
        $this->post(route('admin.users.store'), [
            'name' => 'Intento Super',
            'email' => 'fake.super@glamora.pe',
            'password' => 'GlamoraSafe#2026',
            'password_confirmation' => 'GlamoraSafe#2026',
            'role' => 'SuperAdmin',
            'customer_tier' => 'minorista',
        ])->assertForbidden();

        $this->post(route('admin.users.store'), [
            'name' => 'Intento Lowercase Super',
            'email' => 'fake.super2@glamora.pe',
            'password' => 'GlamoraSafe#2026',
            'password_confirmation' => 'GlamoraSafe#2026',
            'role' => 'superadmin',
            'customer_tier' => 'minorista',
        ])->assertForbidden();

        // Administrador NO puede editar, promover a SuperAdmin, desactivar, resetear 2FA ni eliminar a SuperAdmin
        $this->get(route('admin.users.edit', $superAdmin))->assertForbidden();
        $this->put(route('admin.users.update', $superAdmin), [
            'name' => 'Hack',
            'email' => $superAdmin->email,
            'role' => 'Administrador',
            'customer_tier' => 'oro',
            'is_active' => '1',
        ])->assertForbidden();
        $this->patch(route('admin.users.toggle-active', $superAdmin))->assertForbidden();
        $this->post(route('admin.users.reset-2fa', $superAdmin))->assertForbidden();
        $this->delete(route('admin.users.destroy', $superAdmin))->assertForbidden();

        // Administrador NO puede promover a un Cliente ni a sí mismo a SuperAdmin
        $this->put(route('admin.users.update', $cliente), [
            'name' => $cliente->name,
            'email' => $cliente->email,
            'role' => 'superadmin',
            'customer_tier' => 'minorista',
            'is_active' => '1',
        ])->assertForbidden();

        // Administrador NO puede auto-desactivarse ni auto-eliminarse
        $this->patch(route('admin.users.toggle-active', $admin))->assertForbidden();
        $this->delete(route('admin.users.destroy', $admin))->assertForbidden();
        $this->put(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'Administrador',
            'customer_tier' => 'minorista',
            'is_active' => '0',
        ])->assertSessionHasErrors('is_active');

        // Filtro con rol inexistente en /admin/usuarios NO debe lanzar 500
        $this->get(route('admin.users.index', ['role' => 'RolInexistente']))->assertOk();

        // SuperAdmin tampoco puede auto-desactivarse, auto-eliminarse ni degradar su propio rol
        $this->actingAs($superAdmin);
        $this->patch(route('admin.users.toggle-active', $superAdmin))->assertForbidden();
        $this->delete(route('admin.users.destroy', $superAdmin))->assertForbidden();
        $this->put(route('admin.users.update', $superAdmin), [
            'name' => $superAdmin->name,
            'email' => $superAdmin->email,
            'role' => 'Administrador',
            'customer_tier' => 'oro',
            'is_active' => '1',
        ])->assertForbidden();

        // SuperAdmin SÍ puede crear, editar, desactivar y eliminar un usuario nuevo sin historial
        $this->post(route('admin.users.store'), [
            'name' => 'Nuevo Operador',
            'email' => 'NUEVO.OPERADOR@GLAMORA.PE',
            'password' => 'GlamoraSafe#2026',
            'password_confirmation' => 'GlamoraSafe#2026',
            'role' => 'Almacenero',
            'customer_tier' => 'minorista',
            'is_active' => '1',
        ])->assertRedirect(route('admin.users.index'));

        $created = User::query()->where('email', 'nuevo.operador@glamora.pe')->firstOrFail();
        $this->assertTrue($created->hasRole('Almacenero'));

        // Email duplicado (incluso con mayúsculas) valida limpiamente sin 500
        $this->post(route('admin.users.store'), [
            'name' => 'Duplicado',
            'email' => 'NUEVO.OPERADOR@GLAMORA.PE',
            'password' => 'GlamoraSafe#2026',
            'password_confirmation' => 'GlamoraSafe#2026',
            'role' => 'Cliente',
            'customer_tier' => 'minorista',
        ])->assertSessionHasErrors('email');

        $this->patch(route('admin.users.toggle-active', $created))->assertRedirect(route('admin.users.index'));
        $created->refresh();
        $this->assertFalse((bool) $created->is_active);

        $this->delete(route('admin.users.destroy', $created))->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseMissing('users', ['id' => $created->id]);
    }

    public function test_pim_crud_variants_tiered_prices_and_unique_validations(): void
    {
        $admin = User::query()->where('email', 'admin@glamora.pe')->firstOrFail();
        $this->actingAs($admin);

        // Categoría y Marca CRUD + prevención XSS en títulos
        $this->post(route('admin.pim.categories.store'), [
            'name' => 'Cuidado Capilar Botánico',
            'slug' => 'skincare', // duplicado -> validation error sin 500
            'sort_order' => 5,
            'is_active' => '1',
        ])->assertSessionHasErrors('slug');

        $this->post(route('admin.pim.categories.store'), [
            'name' => 'Cuidado Capilar Botánico',
            'sort_order' => 5,
            'is_active' => '1',
        ])->assertRedirect(route('admin.pim.categories.index'));
        $category = Category::query()->where('slug', 'cuidado-capilar-botanico')->firstOrFail();

        $this->post(route('admin.pim.brands.store'), [
            'name' => 'Andes Botanical Lab',
            'country_origin' => 'Perú',
            'is_cruelty_free' => '1',
            'is_active' => '1',
        ])->assertRedirect(route('admin.pim.brands.index'));
        $brand = Brand::query()->where('slug', 'andes-botanical-lab')->firstOrFail();

        // Crear producto con variante inicial
        $this->post(route('admin.pim.products.store'), [
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Elixir Capilar <script>alert(1)</script>',
            'sanitary_registration' => 'nsoc99887-26pe',
            'min_stock_alert' => 5,
            'reorder_point' => 15,
            'is_active' => '1',
            'variant_sku' => 'glm-cap-001',
            'variant_shade_name' => 'Ámbar Andino',
            'variant_hex_color' => '#D4AF37',
            'variant_base_price' => 89.00,
            'variant_wholesale_base_price' => 68.00,
            'variant_cost_price' => 28.00,
            'variant_barcode' => '7759998887771',
        ])->assertRedirect();

        $product = Product::query()->where('sanitary_registration', 'NSOC99887-26PE')->firstOrFail();
        $variant = $product->variants()->firstOrFail();
        $this->assertSame('GLM-CAP-001', $variant->sku);

        // Verificar XSS escapado en vista edit
        $this->get(route('admin.pim.products.edit', $product))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);

        // SKU y Barcode duplicados en minúsculas no disparan 500
        $this->post(route('admin.pim.products.variants.store', $product), [
            'sku' => 'glm-cap-001',
            'shade_name' => 'Duplicado',
            'base_price' => 89.00,
            'wholesale_base_price' => 68.00,
            'cost_price' => 28.00,
            'barcode' => '7759998887771',
        ])->assertSessionHasErrors(['sku', 'barcode']);

        // Agregar segunda variante
        $this->post(route('admin.pim.products.variants.store', $product), [
            'sku' => 'GLM-CAP-002',
            'shade_name' => 'Lavanda Nocturna',
            'hex_color' => '#8A6BBE',
            'base_price' => 95.00,
            'wholesale_base_price' => 72.00,
            'cost_price' => 30.00,
        ])->assertRedirect(route('admin.pim.products.edit', $product));
        $variant2 = ProductVariant::query()->where('sku', 'GLM-CAP-002')->firstOrFail();

        // Guardar precio escalonado y re-enviar escala duplicada (updateOrCreate sin 500)
        $this->post(route('admin.pim.products.tiered-prices.store', [$product, $variant2]), [
            'customer_tier' => CustomerTier::Oro->value,
            'min_quantity' => 12,
            'max_quantity' => 24,
            'unit_price' => 65.00,
        ])->assertRedirect(route('admin.pim.products.edit', $product));

        $this->post(route('admin.pim.products.tiered-prices.store', [$product, $variant2]), [
            'customer_tier' => CustomerTier::Oro->value,
            'min_quantity' => 12,
            'max_quantity' => 36,
            'unit_price' => 62.00,
        ])->assertRedirect(route('admin.pim.products.edit', $product));

        $this->assertDatabaseHas('tiered_prices', [
            'product_variant_id' => $variant2->id,
            'customer_tier' => CustomerTier::Oro->value,
            'min_quantity' => 12,
            'max_quantity' => 36,
            'unit_price' => 62.00,
        ]);
        $this->assertSame(1, TieredPrice::query()->where('product_variant_id', $variant2->id)->count());

        // SoftDelete y Restaurar producto
        $this->delete(route('admin.pim.products.destroy', $product))->assertRedirect(route('admin.pim.products.index'));
        $this->assertSoftDeleted('products', ['id' => $product->id]);

        $this->post(route('admin.pim.products.restore', $product->id))->assertRedirect(route('admin.pim.products.edit', $product));
        $product->refresh();
        $this->assertNull($product->deleted_at);
        $this->assertTrue((bool) $product->is_active);
    }

    public function test_inventory_batches_kardex_locations_and_state_guards(): void
    {
        $almacenero = User::query()->where('email', 'almacen@glamora.pe')->firstOrFail();
        $this->actingAs($almacenero);

        // Crear ubicación y rechazar duplicado case-insensitive sin 500
        $this->post(route('admin.inventory.locations.store'), [
            'code' => 'alm-test-z9',
            'zone' => 'Zona Pruebas Térmicas',
            'aisle' => 'Z9',
            'rack' => 'R01',
            'shelf' => 'N1',
            'temperature_controlled' => '1',
            'is_active' => '1',
        ])->assertRedirect(route('admin.inventory.locations.index'));

        $location = WarehouseLocation::query()->where('code', 'ALM-TEST-Z9')->firstOrFail();

        $this->post(route('admin.inventory.locations.store'), [
            'code' => 'alm-test-z9',
            'zone' => 'Duplicada',
            'aisle' => 'Z9',
            'rack' => 'R01',
            'shelf' => 'N1',
        ])->assertSessionHasErrors('code');

        // Ingreso de nuevo lote -> entrada en Kardex
        $variant = ProductVariant::query()->where('sku', 'GLM-LB-VITC-30')->firstOrFail();
        $stockBefore = $variant->availableStock();

        $this->post(route('admin.inventory.batches.store'), [
            'product_variant_id' => $variant->id,
            'warehouse_location_id' => $location->id,
            'batch_number' => 'lot-2026-test-01',
            'manufacturing_date' => now()->subDays(10)->toDateString(),
            'expiration_date' => now()->addMonths(8)->toDateString(),
            'initial_quantity' => 25,
            'unit_cost' => 31.50,
            'supplier_name' => 'Lab Botánico Perú S.A.C.',
        ])->assertRedirect(route('admin.inventory.index'));

        $batch = ProductBatch::query()->where('batch_number', 'LOT-2026-TEST-01')->firstOrFail();
        $this->assertSame($stockBefore + 25, $variant->availableStock());
        $this->assertDatabaseHas('inventory_movements', [
            'product_batch_id' => $batch->id,
            'movement_type' => 'entrada',
            'quantity' => 25,
        ]);

        // Lote duplicado (en minúsculas) -> validation error sin 500
        $this->post(route('admin.inventory.batches.store'), [
            'product_variant_id' => $variant->id,
            'warehouse_location_id' => $location->id,
            'batch_number' => 'lot-2026-test-01',
            'expiration_date' => now()->addMonths(8)->toDateString(),
            'initial_quantity' => 10,
            'unit_cost' => 31.50,
            'supplier_name' => 'Lab Botánico Perú S.A.C.',
        ])->assertSessionHasErrors('batch_number');

        // Enviar a cuarentena y luego liberar
        $this->post(route('admin.inventory.batches.adjust', $batch), [
            'action' => 'quarantine',
            'reason' => 'Revisión preventiva de etiquetado DIGEMID.',
        ])->assertRedirect(route('admin.inventory.index'));
        $batch->refresh();
        $this->assertSame('quarantine', $batch->status);

        $this->post(route('admin.inventory.batches.adjust', $batch), [
            'action' => 'release_quarantine',
            'reason' => 'Etiquetado verificado conforme.',
        ])->assertRedirect(route('admin.inventory.index'));
        $batch->refresh();
        $this->assertSame('active', $batch->status);

        // Ajuste negativo válido y ajuste negativo por debajo de reserva bloqueado
        $this->post(route('admin.inventory.batches.adjust', $batch), [
            'action' => 'adjustment_out',
            'quantity_delta' => 5,
            'reason' => 'Muestras de control de calidad.',
        ])->assertRedirect(route('admin.inventory.index'));
        $batch->refresh();
        $this->assertSame(20, $batch->current_quantity);

        // Marcar vencido -> no puede liberarse de cuarentena ni recibir adjustment_in
        $this->post(route('admin.inventory.batches.adjust', $batch), [
            'action' => 'mark_expired',
            'reason' => 'Baja sanitaria por deterioro de lote.',
        ])->assertRedirect(route('admin.inventory.index'));
        $batch->refresh();
        $this->assertSame('expired', $batch->status);

        $this->post(route('admin.inventory.batches.adjust', $batch), [
            'action' => 'release_quarantine',
            'reason' => 'Intento indebido de reactivar vencido.',
        ])->assertSessionHasErrors('action');

        $this->post(route('admin.inventory.batches.adjust', $batch), [
            'action' => 'adjustment_in',
            'quantity_delta' => 5,
            'reason' => 'Intento indebido en lote vencido.',
        ])->assertSessionHasErrors('action');

        // Kardex con fechas inválidas no lanza 500
        $this->get(route('admin.inventory.kardex', ['date_from' => 'fecha-invalida', 'movement_type' => 'entrada']))
            ->assertOk()
            ->assertSee('LOT-2026-TEST-01');
    }

    public function test_fulfillment_payment_verification_fefo_dispatch_cancellation_voucher_and_pdf(): void
    {
        Storage::fake('public');

        $admin = User::query()->where('email', 'admin@glamora.pe')->firstOrFail();
        $almacenero = User::query()->where('email', 'almacen@glamora.pe')->firstOrFail();

        // 1. Voucher security: path traversal, non-existent file, valid file
        $pendingOrder = Order::query()->where('order_number', 'GLM-2026-0005')->firstOrFail();
        /** @var Payment $pendingPayment */
        $pendingPayment = $pendingOrder->payments()->firstOrFail();

        $this->actingAs($admin);
        // Sin voucher_path -> 404
        $this->get(route('admin.fulfillment.payments.voucher', $pendingPayment))->assertNotFound();

        // Path traversal -> 404
        $pendingPayment->update(['voucher_path' => '../../.env']);
        $this->get(route('admin.fulfillment.payments.voucher', $pendingPayment))->assertNotFound();

        // Extensión peligrosa -> 404
        Storage::disk('public')->put('vouchers/evil.html', '<script>alert(1)</script>');
        $pendingPayment->update(['voucher_path' => 'vouchers/evil.html']);
        $this->get(route('admin.fulfillment.payments.voucher', $pendingPayment))->assertNotFound();

        // Voucher PDF válido -> 200 con X-Content-Type-Options: nosniff
        Storage::disk('public')->put('vouchers/yape-904512.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n");
        $pendingPayment->update(['voucher_path' => 'vouchers/yape-904512.pdf']);
        $voucherResp = $this->get(route('admin.fulfillment.payments.voucher', $pendingPayment));
        $voucherResp->assertOk();
        $this->assertSame('nosniff', $voucherResp->headers->get('X-Content-Type-Options'));

        // 2. Verificar pago de GLM-2026-0005 -> reserva FEFO y cambia a PagoVerificado
        $this->post(route('admin.fulfillment.payments.verify', $pendingPayment), [
            'verification_notes' => 'Conciliado en extracto Yape BCP.',
        ])->assertRedirect(route('admin.fulfillment.orders.show', $pendingOrder));

        $pendingOrder->refresh();
        $pendingPayment->refresh();
        $this->assertSame('verified', $pendingPayment->status);
        $this->assertSame(OrderStatus::PagoVerificado, $pendingOrder->status);
        $this->assertNotEmpty($pendingOrder->items()->firstOrFail()->batch_allocations);

        // Idempotencia: re-verificar mismo pago falla limpiamente con ValidationException
        $this->post(route('admin.fulfillment.payments.verify', $pendingPayment))
            ->assertSessionHasErrors('payment');

        // 3. Almacenero avanza PagoVerificado -> EnPicking -> EnPacking -> Despachado (FEFO físico)
        $this->actingAs($almacenero);
        $this->patch(route('admin.fulfillment.orders.transition', $pendingOrder), [
            'status' => OrderStatus::EnPicking->value,
        ])->assertRedirect();

        $this->patch(route('admin.fulfillment.orders.transition', $pendingOrder), [
            'status' => OrderStatus::EnPacking->value,
        ])->assertRedirect();

        $this->patch(route('admin.fulfillment.orders.transition', $pendingOrder), [
            'status' => OrderStatus::Despachado->value,
            'notes' => 'Courier Olva guía #998811',
        ])->assertRedirect();

        $pendingOrder->refresh();
        $this->assertSame(OrderStatus::Despachado, $pendingOrder->status);
        $this->assertNotNull($pendingOrder->fefo_dispatched_at);
        $this->assertDatabaseHas('inventory_movements', [
            'reference_type' => Order::class,
            'reference_id' => $pendingOrder->id,
            'movement_type' => 'salida',
        ]);

        // 4. Descarga de Guía de Picking PDF sin ext-gd
        $pdfResponse = $this->get(route('admin.fulfillment.orders.dispatch-guide-pdf', $pendingOrder));
        $pdfResponse->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdfResponse->getContent());

        // 5. Cancelación de pedido B2B GLM-2026-0003 libera reservas FEFO y revierte crédito B2B sin bajar de 0.00
        $this->actingAs($admin);
        $b2bOrder = Order::query()->where('order_number', 'GLM-2026-0003')->firstOrFail();
        $b2bProfile = WholesaleProfile::query()->where('user_id', $b2bOrder->user_id)->firstOrFail();
        $creditBefore = (float) $b2bProfile->credit_used;

        $this->patch(route('admin.fulfillment.orders.transition', $b2bOrder), [
            'status' => OrderStatus::Cancelado->value,
            'notes' => 'Cancelación solicitada por cliente corporativo.',
        ])->assertRedirect();

        $b2bOrder->refresh();
        $b2bProfile->refresh();
        $this->assertSame(OrderStatus::Cancelado, $b2bOrder->status);
        $this->assertGreaterThanOrEqual(0.0, (float) $b2bProfile->credit_used);
        $this->assertEqualsWithDelta(max(0.0, $creditBefore - (float) $b2bOrder->total), (float) $b2bProfile->credit_used, 0.02);
        $this->assertSame('refunded', $b2bOrder->payments()->firstOrFail()->status);
        $this->assertDatabaseHas('inventory_movements', [
            'reference_type' => Order::class,
            'reference_id' => $b2bOrder->id,
            'movement_type' => 'liberacion',
        ]);
    }

    public function test_rma_inspection_restocks_once_idempotently_and_resolves_cleanly(): void
    {
        $almacenero = User::query()->where('email', 'almacen@glamora.pe')->firstOrFail();
        $admin = User::query()->where('email', 'admin@glamora.pe')->firstOrFail();

        $rma = OrderReturn::query()->where('rma_number', 'RMA-2026-0001')->with('items')->firstOrFail();
        /** @var ReturnItem $item */
        $item = $rma->items->firstOrFail();
        $batch = ProductBatch::query()->findOrFail($item->product_batch_id);
        $qtyBefore = $batch->current_quantity;

        // Almacenero inspecciona como 'reventa' -> avanza a en_revision y reintegra stock una sola vez
        $this->actingAs($almacenero);
        $this->post(route('admin.rma.inspect', $rma), [
            'items' => [
                $item->id => [
                    'condition' => ReturnCondition::Reventa->value,
                    'inspection_notes' => 'Caja sellada intacta.',
                ],
            ],
            'resolution_notes' => 'Apto para reintegro en rack.',
        ])->assertRedirect(route('admin.rma.show', $rma));

        $rma->refresh();
        $item->refresh();
        $batch->refresh();
        $this->assertSame('en_revision', $rma->status);
        $this->assertTrue($item->restocked);
        $this->assertSame($qtyBefore + $item->quantity, $batch->current_quantity);

        // Re-inspeccionar como reventa NO vuelve a incrementar stock
        $this->post(route('admin.rma.inspect', $rma), [
            'items' => [
                $item->id => [
                    'condition' => ReturnCondition::Reventa->value,
                    'inspection_notes' => 'Doble verificación.',
                ],
            ],
        ])->assertRedirect(route('admin.rma.show', $rma));

        $batch->refresh();
        $this->assertSame($qtyBefore + $item->quantity, $batch->current_quantity);
        $this->assertSame(
            1,
            InventoryMovement::query()
                ->where('reference_type', OrderReturn::class)
                ->where('reference_id', $rma->id)
                ->where('movement_type', 'devolucion')
                ->count()
        );

        // No se puede revertir condición a 'desecho' una vez reintegrado
        $this->post(route('admin.rma.inspect', $rma), [
            'items' => [
                $item->id => [
                    'condition' => ReturnCondition::Desecho->value,
                ],
            ],
        ])->assertSessionHasErrors("items.{$item->id}.condition");

        // Administrador resuelve financieramente
        $this->actingAs($admin);
        $this->post(route('admin.rma.resolve', $rma), [
            'status' => 'resuelto',
            'refund_amount' => 49.00,
            'resolution_notes' => 'Nota de crédito emitida y stock reintegrado.',
        ])->assertRedirect(route('admin.rma.show', $rma));

        $rma->refresh();
        $this->assertSame('resuelto', $rma->status);
        $this->assertSame(49.00, (float) $rma->refund_amount);

        // No puede resolverse dos veces
        $this->post(route('admin.rma.resolve', $rma), [
            'status' => 'rechazado',
            'resolution_notes' => 'Intento duplicado.',
        ])->assertSessionHasErrors('status');
    }

    public function test_wholesale_approval_credit_adjustment_and_audit_log_viewer(): void
    {
        $admin = User::query()->where('email', 'admin@glamora.pe')->firstOrFail();
        $this->actingAs($admin);

        // Aprobar postulante pendiente -> asigna rol Vendedor B2B, tier y línea de crédito
        $pending = WholesaleProfile::query()->where('status', 'pending')->firstOrFail();
        $applicant = $pending->user()->firstOrFail();

        $this->post(route('admin.wholesale.approve', $pending), [
            'approved_tier' => CustomerTier::Plata->value,
            'credit_limit' => 8000.00,
            'payment_terms_days' => 30,
            'notes' => 'Línea aprobada tras evaluación SUNAT.',
        ])->assertRedirect(route('admin.wholesale.show', $pending));

        $pending->refresh();
        $applicant->refresh();
        $this->assertSame('approved', $pending->status);
        $this->assertSame(CustomerTier::Plata, $pending->approved_tier);
        $this->assertSame(CustomerTier::Plata, $applicant->customer_tier);
        $this->assertTrue($applicant->hasRole('Vendedor B2B'));
        $this->assertFalse($applicant->hasRole('Cliente'));

        // Ajuste de crédito y abono en b2b@glamora.pe
        $b2bUser = User::query()->where('email', 'b2b@glamora.pe')->firstOrFail();
        $b2bProfile = $b2bUser->wholesaleProfile()->firstOrFail();
        $usedBefore = (float) $b2bProfile->credit_used;

        // Abono superior a deuda falla validación
        $this->patch(route('admin.wholesale.credit.update', $b2bProfile), [
            'approved_tier' => CustomerTier::Oro->value,
            'credit_limit' => 30000.00,
            'payment_terms_days' => 45,
            'credit_payment_abono' => $usedBefore + 500.00,
        ])->assertSessionHasErrors('credit_payment_abono');

        // Abono válido reduce credit_used
        $this->patch(route('admin.wholesale.credit.update', $b2bProfile), [
            'approved_tier' => CustomerTier::Oro->value,
            'credit_limit' => 30000.00,
            'payment_terms_days' => 45,
            'credit_payment_abono' => 126.90,
            'notes' => 'Ampliación de línea y pago parcial BCP.',
        ])->assertRedirect(route('admin.wholesale.show', $b2bProfile));

        $b2bProfile->refresh();
        $this->assertEqualsWithDelta(30000.00, (float) $b2bProfile->credit_limit, 0.01);
        $this->assertEqualsWithDelta(max(0.0, $usedBefore - 126.90), (float) $b2bProfile->credit_used, 0.01);

        // Visor de auditoría muestra eventos registrados y filtra sin errores
        $this->get(route('admin.audit.index'))
            ->assertOk()
            ->assertSee('wholesale_approved')
            ->assertSee('wholesale_credit_updated');

        $this->get(route('admin.audit.index', [
            'severity' => 'warning',
            'date_from' => 'fecha-invalida',
        ]))->assertOk();
    }
}
