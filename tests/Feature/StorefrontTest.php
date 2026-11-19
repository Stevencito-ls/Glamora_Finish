<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CustomerTier;
use App\Enums\OrderStatus;
use App\Livewire\Storefront\CartCounterDrawer;
use App\Livewire\Storefront\CatalogBrowser;
use App\Models\AbandonedCart;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WholesaleProfile;
use App\Services\CartSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_home_route_renders_storefront_sections_and_livewire_assets(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee('GLAMORA')
            ->assertSee('Aruma Atelier')
            ->assertSee('Kits Mayoristas para Salones')
            ->assertSee('/livewire/update');
    }

    public function test_catalog_routes_handle_valid_and_invalid_categories(): void
    {
        $this->get(route('catalog.index'))
            ->assertOk()
            ->assertSee('Catálogo Glamora');

        $this->get(route('catalog.category', 'maquillaje'))
            ->assertOk()
            ->assertSee('Maquillaje');

        $this->get(route('catalog.category', 'categoria-inexistente-404'))
            ->assertNotFound();
    }

    public function test_product_show_displays_fefo_and_handles_out_of_stock_variants_safely(): void
    {
        $product = Product::query()->where('slug', 'labial-liquido-velvet-matte')->firstOrFail();

        $this->get(route('product.show', $product->slug))
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee('NSOC54120-24PE')
            ->assertSee('Precios Escalonados');

        // Variante sin stock / sin lote activo debe renderizar sin errores
        $outOfStockProduct = Product::query()->create([
            'category_id' => $product->category_id,
            'brand_id' => $product->brand_id,
            'name' => 'Producto Prueba Sin Lotes',
            'slug' => 'producto-prueba-sin-lotes',
            'short_description' => 'Descripción corta segura',
            'description' => 'Descripción detallada',
            'is_active' => true,
            'main_image' => 'https://images.unsplash.com/photo-1586495777744-4413f21062fa?auto=format&fit=crop&w=1000&q=85',
            'gallery' => [],
        ]);

        ProductVariant::query()->create([
            'product_id' => $outOfStockProduct->id,
            'sku' => 'GLM-TEST-000',
            'shade_name' => 'Tono Agotado',
            'hex_color' => '#C87D72',
            'base_price' => 50.00,
            'wholesale_base_price' => 38.00,
            'cost_price' => 20.00,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->get(route('product.show', $outOfStockProduct->slug))
            ->assertOk()
            ->assertSee('Producto Prueba Sin Lotes')
            ->assertSee('Tono Agotado');
    }

    public function test_brands_and_blog_routes_render_and_sanitize_html(): void
    {
        $this->get(route('brands.index'))
            ->assertOk()
            ->assertSee('Marcas curadas de alta cosmética')
            ->assertSee('Aruma Atelier');

        $this->get(route('blog.index'))
            ->assertOk()
            ->assertSee('Guías de skincare');

        $this->get(route('blog.index', ['etiqueta' => 'Skincare']))
            ->assertOk()
            ->assertSee('Rutina antioxidante');

        $post = Post::query()->where('slug', 'rutina-vitamina-c-humedad-lima')->firstOrFail();
        $post->update([
            'content' => '<p>Contenido seguro</p><script>window.__xss=1;</script><p onclick="alert(1)">Texto</p>',
        ]);

        $this->get(route('blog.show', $post->slug))
            ->assertOk()
            ->assertSee('Contenido seguro')
            ->assertDontSee('<script>window.__xss=1;</script>', false)
            ->assertDontSee('onclick="alert(1)"', false);
    }

    public function test_wholesale_index_and_application_edge_cases(): void
    {
        $this->get(route('wholesale.index'))
            ->assertOk()
            ->assertSee('Niveles Bronce, Plata y Oro');

        // 1. Solicitud válida de empresa nueva
        $payload = [
            'ruc' => '20601112223',
            'company_name' => 'Atelier Cosmético Cusco S.A.C.',
            'trade_name' => 'Atelier Cusco',
            'business_type' => 'Salón de belleza y spa',
            'contact_name' => 'Mariana Quispe',
            'contact_email' => 'compras@ateliercusco.pe',
            'contact_phone' => '+51 984 112 233',
            'fiscal_address' => 'Av. El Sol 450',
            'district' => 'Cusco',
            'city' => 'Cusco',
            'requested_tier' => 'plata',
            'notes' => 'Interés en packs de cabina.',
        ];

        $this->from(route('wholesale.index'))
            ->post(route('wholesale.apply'), $payload)
            ->assertRedirect(route('wholesale.index') . '#solicitud-b2b')
            ->assertSessionHas('wholesale_success');

        $this->assertDatabaseHas('wholesale_profiles', [
            'ruc' => '20601112223',
            'status' => 'pending',
            'contact_email' => 'compras@ateliercusco.pe',
        ]);

        // 2. Validación de RUC inválido (menos de 11 dígitos)
        $this->post(route('wholesale.apply'), array_merge($payload, [
            'ruc' => '2060111',
            'contact_email' => 'otro@ateliercusco.pe',
        ]))->assertSessionHasErrors('ruc');

        // 3. RUC duplicado
        $this->post(route('wholesale.apply'), array_merge($payload, [
            'contact_email' => 'nuevo@otraempresa.pe',
        ]))->assertSessionHasErrors('ruc');

        // 4. Email ya registrado CON WholesaleProfile previo pendiente/aprobado
        $this->post(route('wholesale.apply'), array_merge($payload, [
            'ruc' => '20609998887',
            'contact_email' => 'b2b@glamora.pe',
        ]))->assertSessionHasErrors('contact_email');

        // 5. Email ya registrado como Cliente SIN WholesaleProfile previo -> debe asociar el perfil
        $this->post(route('wholesale.apply'), array_merge($payload, [
            'ruc' => '20607776665',
            'contact_email' => 'cliente@glamora.pe',
        ]))->assertRedirect()
            ->assertSessionHas('wholesale_success');

        $cliente = User::query()->where('email', 'cliente@glamora.pe')->firstOrFail();
        $this->assertDatabaseHas('wholesale_profiles', [
            'user_id' => $cliente->id,
            'ruc' => '20607776665',
            'status' => 'pending',
        ]);

        // 6. Email de staff interno rechazado
        $this->post(route('wholesale.apply'), array_merge($payload, [
            'ruc' => '20605554443',
            'contact_email' => 'admin@glamora.pe',
        ]))->assertSessionHasErrors('contact_email');
    }

    public function test_tracking_masks_pii_and_validates_rma_rules(): void
    {
        $order = Order::query()->where('order_number', 'GLM-2026-0001')->firstOrFail();

        // Búsqueda pública protege privacidad (enmascara email, teléfono, dirección exacta y DNI)
        $this->get(route('tracking.index', ['codigo' => 'GLM-2026-0001']))
            ->assertOk()
            ->assertSee('GLM-2026-0001')
            ->assertSee('Datos Protegidos')
            ->assertDontSee($order->customer_email)
            ->assertDontSee((string) $order->customer_phone)
            ->assertDontSee($order->shipping_address)
            ->assertDontSee((string) $order->document_number);

        // No permite RMA sobre pedido PendientePago, Cotizacion o Cancelado
        $pendingOrder = Order::query()->where('order_number', 'GLM-2026-0005')->firstOrFail();
        $this->post(route('tracking.rma'), [
            'order_number' => $pendingOrder->order_number,
            'requester_email' => $pendingOrder->customer_email,
            'reason' => 'Intento de devolución en estado pendiente de pago.',
        ])->assertSessionHasErrors('order_number');

        $order->update(['status' => OrderStatus::Cotizacion]);
        $this->post(route('tracking.rma'), [
            'order_number' => $order->order_number,
            'requester_email' => $order->customer_email,
            'reason' => 'Intento de devolución sobre cotización.',
        ])->assertSessionHasErrors('order_number');

        $order->update(['status' => OrderStatus::Cancelado]);
        $this->post(route('tracking.rma'), [
            'order_number' => $order->order_number,
            'requester_email' => $order->customer_email,
            'reason' => 'Intento de devolución sobre pedido cancelado.',
        ])->assertSessionHasErrors('order_number');

        $order->update(['status' => OrderStatus::Entregado]);

        // Correo distinto al comprador debe fallar
        $this->post(route('tracking.rma'), [
            'order_number' => $order->order_number,
            'requester_email' => 'intruso@ejemplo.pe',
            'reason' => 'Intento con correo diferente al del comprador.',
        ])->assertSessionHasErrors('requester_email');

        // Fallback por SKU con exceso de cantidad debe fallar
        $this->post(route('tracking.rma'), [
            'order_number' => $order->order_number,
            'requester_email' => $order->customer_email,
            'sku' => 'GLM-LB-VITC-30',
            'quantity' => 10,
            'reason' => 'Cantidad superior a la comprada vía SKU.',
        ])->assertSessionHasErrors('quantity');

        // No permite devolver más cantidad que la comprada
        $item = $order->items()->where('sku', 'GLM-LB-VITC-30')->firstOrFail();
        $this->post(route('tracking.rma'), [
            'order_number' => $order->order_number,
            'requester_email' => $order->customer_email,
            'reason' => 'Cantidad excesiva mayor a lo comprado.',
            'items' => [
                $item->id => ['selected' => '1', 'quantity' => $item->quantity + 3],
            ],
        ])->assertSessionHasErrors('items');

        // Solicitud RMA válida por cantidad permitida
        $this->post(route('tracking.rma'), [
            'order_number' => $order->order_number,
            'requester_email' => $order->customer_email,
            'reason' => 'Producto con caja sellada sin abrir para cambio.',
            'items' => [
                $item->id => ['selected' => '1', 'quantity' => 1],
            ],
        ])->assertRedirect(route('tracking.index', ['codigo' => $order->order_number]) . '#solicitud-rma')
            ->assertSessionHas('rma_success');

        $this->assertSame(2, OrderReturn::query()->where('order_id', $order->id)->count());

        // Segundo intento sobre el mismo ítem ya agotado debe fallar
        $this->post(route('tracking.rma'), [
            'order_number' => $order->order_number,
            'requester_email' => $order->customer_email,
            'reason' => 'Segundo intento duplicado por el mismo ítem.',
            'items' => [
                $item->id => ['selected' => '1', 'quantity' => 1],
            ],
        ])->assertSessionHasErrors('items');
    }

    public function test_newsletter_and_cart_add_endpoints(): void
    {
        // Newsletter sin el campo opcional 'name' no debe lanzar Undefined array key
        $this->from(route('home'))
            ->post(route('newsletter.subscribe'), [
                'email' => 'suscriptor.nuevo@glamora.pe',
                'segment' => 'mayorista',
            ])
            ->assertRedirect(route('home') . '#newsletter-footer')
            ->assertSessionHas('newsletter_status');

        $this->assertDatabaseHas('newsletter_subscribers', [
            'email' => 'suscriptor.nuevo@glamora.pe',
            'segment' => 'mayorista',
            'is_active' => true,
        ]);

        // Cart add JSON + tope FEFO
        $variant = ProductVariant::query()->where('sku', 'GLM-LB-VITC-30')->firstOrFail();
        $stock = $variant->availableStock();

        $this->postJson(route('cart.add'), [
            'variant_id' => $variant->id,
            'quantity' => 2,
        ])->assertOk()
            ->assertJsonPath('added', true)
            ->assertJsonPath('summary.items_count', 2);

        $this->assertSame(2, app(CartSessionService::class)->summary()['items_count']);

        // Solicitar más que el stock disponible ajusta al máximo y en un segundo intento con stock ya lleno retorna added=false
        $this->postJson(route('cart.add'), [
            'variant_id' => $variant->id,
            'quantity' => $stock + 50,
        ])->assertOk()
            ->assertJsonPath('added', true)
            ->assertJsonPath('summary.items_count', $stock);

        $this->postJson(route('cart.add'), [
            'variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertStatus(422)
            ->assertJsonPath('added', false);
    }

    public function test_livewire_catalog_browser_filters_sorts_matrix_and_cart_drawer(): void
    {
        // Vista inválida en URL hace fallback seguro a grid
        $this->get(route('catalog.index', ['vista' => 'invalida']))
            ->assertOk()
            ->assertSee('Labial Líquido Velvet Matte 12H');

        // Filtros y ordenamientos de CatalogBrowser (Grid)
        Livewire::test(CatalogBrowser::class)
            ->assertStatus(200)
            ->set('category', 'maquillaje')
            ->assertSee('Labial Líquido Velvet Matte 12H')
            ->set('category', 'labiales-y-gloss')
            ->set('brand', 'velvet-and-rose')
            ->set('sort', 'price_asc')
            ->assertSee('Labial Líquido Velvet Matte 12H')
            ->set('sort', 'price_desc')
            ->set('sort', 'newest')
            ->set('search', 'Terracota')
            ->assertSee('Labial Líquido Velvet Matte 12H')
            ->call('clearFilters')
            ->set('skinType', 'Todo tipo de piel')
            ->set('finishType', 'Mate aterciopelado')
            ->assertSee('Labial Líquido Velvet Matte 12H')
            ->call('clearFilters')
            ->set('onlyB2bKits', true)
            ->assertSee('Exhibidor Boutique Labiales Velvet Matte');

        // Vista Matriz por Tonos + agregar lote y quickAdd / addVariantFromMatrix + resumen cross-page
        $vitC = ProductVariant::query()->where('sku', 'GLM-LB-VITC-30')->firstOrFail();
        $labial = ProductVariant::query()->where('sku', 'GLM-VR-LAB-01')->firstOrFail();
        $lastVariant = ProductVariant::query()->orderByDesc('id')->firstOrFail();

        $expectedCrossPageTotal = round(
            ($vitC->priceForTier(CustomerTier::Minorista, 2) * 2)
            + ($lastVariant->priceForTier(CustomerTier::Minorista, 1) * 1),
            2
        );

        Livewire::test(CatalogBrowser::class, ['initialViewMode' => 'matrix'])
            ->set('matrixQuantities', [
                $vitC->id => 2,
                $lastVariant->id => 1,
            ])
            ->assertViewHas('matrixSelectedUnits', 3)
            ->assertViewHas('matrixSelectedTotal', $expectedCrossPageTotal)
            ->call('addBatchFromMatrix')
            ->assertDispatched('cart-updated', openDrawer: true);

        Livewire::test(CatalogBrowser::class)
            ->call('quickAdd', $labial->id)
            ->assertDispatched('cart-updated', openDrawer: false);

        Livewire::test(CatalogBrowser::class, ['initialViewMode' => 'matrix'])
            ->set("matrixQuantities.{$labial->id}", 2)
            ->call('addVariantFromMatrix', $labial->id)
            ->assertDispatched('cart-updated', openDrawer: false);

        $summary = app(CartSessionService::class)->summary();
        $this->assertSame(6, $summary['items_count']);

        // CartCounterDrawer: abrir, incrementar, decrementar, eliminar y vaciar
        Livewire::test(CartCounterDrawer::class)
            ->call('handleCartUpdated', true)
            ->assertSet('open', true)
            ->call('increment', $vitC->id)
            ->assertSee('Tu Bolsa Glamora (7)')
            ->call('decrement', $vitC->id)
            ->assertSee('Tu Bolsa Glamora (6)')
            ->call('removeItem', $labial->id)
            ->assertSee('Tu Bolsa Glamora (3)')
            ->call('clearCart')
            ->assertSee('Tu bolsa está vacía');
    }
}
