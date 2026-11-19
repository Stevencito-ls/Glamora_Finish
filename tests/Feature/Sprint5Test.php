<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\SendAbandonedCartReminders;
use App\Enums\CustomerTier;
use App\Mail\AbandonedCartReminderMail;
use App\Models\AbandonedCart;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SecurityLog;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Sprint5Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_sitemap_xml_and_robots_txt_expose_public_entities_and_exclude_private_routes(): void
    {
        $activeCategory = Category::query()->active()->firstOrFail();
        $activeProduct = Product::query()->active()->firstOrFail();
        $publishedPost = Post::query()->published()->firstOrFail();

        $inactiveCategory = Category::query()->create([
            'name' => 'Categoría Oculta Sitemap',
            'slug' => 'categoria-oculta-sitemap',
            'sort_order' => 99,
            'is_active' => false,
        ]);

        $inactiveProduct = Product::query()->create([
            'category_id' => $activeCategory->id,
            'brand_id' => $activeProduct->brand_id,
            'name' => 'Producto Oculto Sitemap',
            'slug' => 'producto-oculto-sitemap',
            'sanitary_registration' => 'NSOC00001-26PE',
            'min_stock_alert' => 5,
            'reorder_point' => 10,
            'is_active' => false,
        ]);

        $draftPost = Post::query()->create([
            'title' => 'Borrador Oculto Sitemap',
            'slug' => 'borrador-oculto-sitemap',
            'category_tag' => 'Skincare',
            'content' => '<p>Contenido borrador interno.</p>',
            'reading_time_minutes' => 3,
            'is_published' => false,
            'published_at' => null,
        ]);

        $response = $this->get(route('sitemap'));
        $response->assertOk();

        $contentType = (string) $response->headers->get('Content-Type');
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('application/xml', $contentType);
        $this->assertStringContainsString('max-age=1800', $cacheControl);

        $xmlString = $response->getContent();
        $this->assertIsString($xmlString);
        $xml = simplexml_load_string($xmlString);
        $this->assertNotFalse($xml);

        $locs = [];
        foreach ($xml->url as $urlNode) {
            $locs[] = (string) $urlNode->loc;
        }

        // Rutas principales + entidades públicas activas
        foreach ([
            route('home'),
            route('catalog.index'),
            route('brands.index'),
            route('wholesale.index'),
            route('blog.index'),
            route('tracking.index'),
            route('catalog.category', $activeCategory->slug),
            route('product.show', $activeProduct->slug),
            route('blog.show', $publishedPost->slug),
        ] as $expectedLoc) {
            $this->assertContains($expectedLoc, $locs);
        }

        // Entidades inactivas, borradores y rutas privadas excluidas
        foreach ([
            route('catalog.category', $inactiveCategory->slug),
            route('product.show', $inactiveProduct->slug),
            route('blog.show', $draftPost->slug),
            route('cart.index'),
            route('checkout.index'),
            route('login'),
            route('register'),
            route('account.index'),
            route('admin.dashboard'),
        ] as $excludedLoc) {
            $this->assertNotContains($excludedLoc, $locs);
        }

        // Validar public/robots.txt
        $robotsPath = public_path('robots.txt');
        $this->assertFileExists($robotsPath);
        $robotsContent = (string) file_get_contents($robotsPath);

        foreach ([
            'Disallow: /admin',
            'Disallow: /mi-cuenta',
            'Disallow: /carrito',
            'Disallow: /checkout',
            'Disallow: /login',
            'Disallow: /registro',
            'Disallow: /2fa',
            'Disallow: /livewire/',
            'Sitemap: /sitemap.xml',
        ] as $directive) {
            $this->assertStringContainsString($directive, $robotsContent);
        }
    }

    public function test_storefront_seo_meta_opengraph_twitter_noindex_and_json_ld_anti_xss(): void
    {
        // 1. Home: Organization + WebSite SearchAction + OG + Twitter + index, follow
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<meta name="robots" content="index, follow">', false)
            ->assertSee('<meta property="og:type" content="website">', false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
            ->assertSee('"@type":"Organization"', false)
            ->assertSee('"@type":"WebSite"', false)
            ->assertSee('"@type":"SearchAction"', false);

        // 2. Catálogo sin categoría: corrige bug de título prefijado con " — Catálogo"
        $this->get(route('catalog.index'))
            ->assertOk()
            ->assertSee('<title>Catálogo de Maquillaje, Skincare &amp; B2B — Glamora Perú</title>', false)
            ->assertDontSee('<title> — Catálogo Glamora Perú</title>', false)
            ->assertSee('"@type":"CollectionPage"', false)
            ->assertSee('"@type":"BreadcrumbList"', false);

        $category = Category::query()->where('slug', 'skincare')->firstOrFail();
        $this->get(route('catalog.category', $category->slug))
            ->assertOk()
            ->assertSee('Skincare')
            ->assertSee('"@type":"CollectionPage"', false)
            ->assertSee('"@type":"BreadcrumbList"', false);

        // 3. Producto + XSS protection en SEO y JSON-LD (JSON_HEX_TAG)
        $product = Product::query()->where('slug', 'labial-liquido-velvet-matte')->firstOrFail();
        $product->update([
            'meta_title' => 'Labial </script><script>alert("xss")</script>',
            'meta_description' => 'Descripción "segura" <script>alert(2)</script>',
        ]);

        $productResponse = $this->get(route('product.show', $product->slug));
        $productResponse->assertOk()
            ->assertSee('<meta property="og:type" content="product">', false)
            ->assertSee('"@type":"Product"', false)
            ->assertSee('"@type":"Offer"', false)
            ->assertSee('"priceCurrency":"PEN"', false)
            ->assertSee('"@type":"BreadcrumbList"', false)
            ->assertDontSee('<script>alert("xss")</script>', false)
            ->assertDontSee('<script>alert(2)</script>', false)
            ->assertSee('&lt;/script&gt;&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', false);

        // Verificar que la descripción dentro de JSON-LD escapó los tags con \u003C
        $this->assertStringContainsString('\u003Cscript\u003Ealert(2)\u003C/script\u003E', $productResponse->getContent());

        // 4. Brands, Wholesale, Blog index & Blog show
        $this->get(route('brands.index'))
            ->assertOk()
            ->assertSee('"@type":"CollectionPage"', false)
            ->assertSee('"@type":"ItemList"', false);

        $this->get(route('wholesale.index'))
            ->assertOk()
            ->assertSee('"@type":"WebPage"', false)
            ->assertSee('"@type":"Service"', false);

        $this->get(route('blog.index'))
            ->assertOk()
            ->assertSee('"@type":"Blog"', false);

        $post = Post::query()->published()->firstOrFail();
        $this->get(route('blog.show', $post->slug))
            ->assertOk()
            ->assertSee('<meta property="og:type" content="article">', false)
            ->assertSee('"@type":"Article"', false);

        // 5. Tracking: indexable sin búsqueda, noindex cuando $searched === true
        $this->get(route('tracking.index'))
            ->assertOk()
            ->assertSee('<meta name="robots" content="index, follow">', false);

        $this->get(route('tracking.index', ['codigo' => 'GLM-2026-0001']))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);

        // 6. Todas las 12 vistas privadas / transaccionales emiten noindex, nofollow
        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);

        $cliente = User::query()->where('email', 'cliente@glamora.pe')->firstOrFail();

        $this->withSession([
            'two_factor:user_id' => $cliente->id,
            'two_factor:expires_at' => now()->addMinutes(5)->timestamp,
        ])->get(route('two-factor.challenge'))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);

        // Checkout index requiere un ítem en el carrito
        $variant = ProductVariant::query()->where('sku', 'GLM-LB-VITC-30')->firstOrFail();
        $this->actingAs($cliente);
        $this->post(route('cart.add'), [
            'variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);

        $clienteOrder = Order::query()->where('user_id', $cliente->id)->firstOrFail();

        foreach ([
            route('checkout.confirmation', $clienteOrder->order_number),
            route('account.index'),
            route('account.orders'),
            route('account.orders.show', $clienteOrder->order_number),
            route('account.addresses'),
            route('account.returns'),
            route('account.security'),
        ] as $privateUrl) {
            $this->get($privateUrl)
                ->assertOk()
                ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        }
    }

    public function test_abandoned_cart_recovery_service_dry_run_queue_cooldown_and_artisan_command(): void
    {
        Mail::fake();

        $cart = AbandonedCart::query()->where('session_id', 'sess_glamora_abandoned_001')->firstOrFail();
        $mailable = new AbandonedCartReminderMail($cart);
        $this->assertInstanceOf(ShouldQueue::class, $mailable);
        $this->assertSame(3, $mailable->tries);
        $this->assertSame([60, 300], $mailable->backoff);

        $mailable->assertSeeInHtml('GLAMORA');
        $mailable->assertSeeInHtml('S/');
        $mailable->assertSeeInHtml(route('cart.index'));

        // Carrito reciente (< 60 min) -> NO elegible
        $recentCart = AbandonedCart::query()->create([
            'session_id' => 'sess_recent_cart',
            'email' => 'reciente@glamora.pe',
            'cart_payload' => [['product_name' => 'Producto Reciente', 'quantity' => 1, 'unit_price' => 50, 'subtotal' => 50]],
            'subtotal' => 50.00,
            'items_count' => 1,
            'last_activity_at' => now()->subMinutes(15),
            'reminder_count' => 0,
        ]);

        // Carrito ya recuperado -> NO elegible
        $recoveredCart = AbandonedCart::query()->create([
            'session_id' => 'sess_recovered_cart',
            'email' => 'recuperado@glamora.pe',
            'cart_payload' => [['product_name' => 'Producto Recuperado', 'quantity' => 1, 'unit_price' => 60, 'subtotal' => 60]],
            'subtotal' => 60.00,
            'items_count' => 1,
            'last_activity_at' => now()->subHours(5),
            'reminder_count' => 0,
            'recovered_at' => now()->subHour(),
        ]);

        // Carrito en cooldown (recordado hace 2h, cooldown 24h) -> NO elegible
        $cooldownCart = AbandonedCart::query()->create([
            'session_id' => 'sess_cooldown_cart',
            'email' => 'cooldown@glamora.pe',
            'cart_payload' => [['product_name' => 'Producto Cooldown', 'quantity' => 1, 'unit_price' => 70, 'subtotal' => 70]],
            'subtotal' => 70.00,
            'items_count' => 1,
            'last_activity_at' => now()->subHours(10),
            'reminder_count' => 1,
            'last_reminded_at' => now()->subHours(2),
        ]);

        // Carrito en máximo de recordatorios (3/3) -> NO elegible
        $maxedCart = AbandonedCart::query()->create([
            'session_id' => 'sess_maxed_cart',
            'email' => 'maxed@glamora.pe',
            'cart_payload' => [['product_name' => 'Producto Maxed', 'quantity' => 1, 'unit_price' => 80, 'subtotal' => 80]],
            'subtotal' => 80.00,
            'items_count' => 1,
            'last_activity_at' => now()->subDays(4),
            'reminder_count' => 3,
            'last_reminded_at' => now()->subDays(2),
        ]);

        // 1. Dry-run no encola ni muta la base de datos
        $this->artisan('glamora:send-abandoned-carts', ['--dry-run' => true])
            ->assertExitCode(0);

        Mail::assertNothingQueued();
        $cart->refresh();
        $this->assertSame(0, $cart->reminder_count);
        $this->assertNull($cart->last_reminded_at);

        // 2. Ejecución real solo encola los 2 carritos elegibles del seeder
        $this->artisan('glamora:send-abandoned-carts')
            ->assertExitCode(0);

        Mail::assertQueued(AbandonedCartReminderMail::class, 2);
        Mail::assertQueued(AbandonedCartReminderMail::class, fn (AbandonedCartReminderMail $mail): bool => $mail->hasTo('cliente@glamora.pe'));
        Mail::assertQueued(AbandonedCartReminderMail::class, fn (AbandonedCartReminderMail $mail): bool => $mail->hasTo('valeria.campos@outlook.com'));

        $cart->refresh();
        $this->assertSame(1, $cart->reminder_count);
        $this->assertNotNull($cart->last_reminded_at);

        $recentCart->refresh();
        $recoveredCart->refresh();
        $cooldownCart->refresh();
        $maxedCart->refresh();
        $this->assertSame(0, $recentCart->reminder_count);
        $this->assertSame(0, $recoveredCart->reminder_count);
        $this->assertSame(1, $cooldownCart->reminder_count);
        $this->assertSame(3, $maxedCart->reminder_count);

        $this->assertDatabaseHas('security_logs', [
            'event_type' => 'abandoned_cart_reminders_dispatched',
        ]);

        // 3. Re-ejecución inmediata respeta el cooldown de 24h y no encola correos nuevos
        Mail::fake();
        $this->artisan('glamora:send-abandoned-carts')
            ->assertExitCode(0);
        Mail::assertNothingQueued();

        // 4. Verificar registro del comando en Laravel Schedule
        $events = app(Schedule::class)->events();
        $scheduledCommand = collect($events)->first(
            fn ($event): bool => is_string($event->command) && str_contains($event->command, 'glamora:send-abandoned-carts')
        );
        $this->assertNotNull($scheduledCommand, 'El comando glamora:send-abandoned-carts debe estar programado en Schedule.');
        $this->assertSame(SendAbandonedCartReminders::class, SendAbandonedCartReminders::class);
    }

    public function test_admin_marketing_rbac_and_coupons_abandoned_carts_newsletter_lifecycle(): void
    {
        Mail::fake();

        $admin = User::query()->where('email', 'admin@glamora.pe')->firstOrFail();
        $almacenero = User::query()->where('email', 'almacen@glamora.pe')->firstOrFail();
        $vendedorB2b = User::query()->where('email', 'b2b@glamora.pe')->firstOrFail();
        $cliente = User::query()->where('email', 'cliente@glamora.pe')->firstOrFail();

        // 1. RBAC: invitado redirige a login; roles sin permiso reciben 403 y no ven sección en sidebar
        $this->get(route('admin.marketing.index'))->assertRedirect(route('login'));

        foreach ([$cliente, $almacenero, $vendedorB2b] as $unauthorizedUser) {
            $this->actingAs($unauthorizedUser);
            foreach ([
                route('admin.marketing.index'),
                route('admin.marketing.coupons.index'),
                route('admin.marketing.abandoned-carts.index'),
                route('admin.marketing.newsletter.index'),
                route('admin.marketing.blog.index'),
            ] as $url) {
                $this->get($url)->assertForbidden();
            }
        }

        $this->actingAs($almacenero)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('Marketing &amp; Retención', false);

        // 2. Administrador accede al Hub y visualiza el sidebar
        $this->actingAs($admin);
        $this->get(route('admin.marketing.index'))
            ->assertOk()
            ->assertSee('Marketing &amp; Retención', false)
            ->assertSee('Cupones Promocionales');

        // 3. Cupones: validaciones de porcentaje > 100 y fecha expiración < inicio
        $this->post(route('admin.marketing.coupons.store'), [
            'code' => 'invalido101',
            'discount_type' => 'percentage',
            'discount_value' => 110,
            'starts_at' => now()->addDays(5)->toDateTimeString(),
            'expires_at' => now()->addDays(2)->toDateTimeString(),
        ])->assertSessionHasErrors(['discount_value', 'expires_at']);

        // Crear cupón válido con normalización a mayúsculas y SecurityActionTriggered
        $this->post(route('admin.marketing.coupons.store'), [
            'code' => '  glam-sprint5  ',
            'description' => 'Campaña prueba Sprint 5',
            'discount_type' => 'percentage',
            'discount_value' => 15,
            'min_order_amount' => 90,
            'max_uses' => 50,
            'applicable_tier' => CustomerTier::Minorista->value,
            'starts_at' => now()->subHour()->toDateTimeString(),
            'expires_at' => now()->addDays(15)->toDateTimeString(),
            'is_active' => '1',
        ])->assertRedirect(route('admin.marketing.coupons.index'));

        $createdCoupon = Coupon::query()->where('code', 'GLAM-SPRINT5')->firstOrFail();
        $this->assertDatabaseHas('security_logs', [
            'event_type' => 'marketing_coupon_created',
        ]);

        // Editar y alternar estado de cupón sin uso
        $this->put(route('admin.marketing.coupons.update', $createdCoupon), [
            'code' => 'glam-sprint5-v2',
            'description' => 'Campaña actualizada',
            'discount_type' => 'fixed',
            'discount_value' => 25,
            'min_order_amount' => 100,
            'max_uses' => 80,
            'is_active' => '1',
        ])->assertRedirect(route('admin.marketing.coupons.index'));

        $createdCoupon->refresh();
        $this->assertSame('GLAM-SPRINT5-V2', $createdCoupon->code);
        $this->assertSame('fixed', $createdCoupon->discount_type);

        $this->patch(route('admin.marketing.coupons.toggle', $createdCoupon))
            ->assertRedirect(route('admin.marketing.coupons.index'));
        $createdCoupon->refresh();
        $this->assertFalse((bool) $createdCoupon->is_active);

        // Cupón usado o referenciado en pedidos (GLAMORA10) NO puede editarse ni eliminarse
        $usedCoupon = Coupon::query()->where('code', 'GLAMORA10')->firstOrFail();
        $originalDiscount = (float) $usedCoupon->discount_value;

        $this->put(route('admin.marketing.coupons.update', $usedCoupon), [
            'code' => 'GLAMORA10',
            'discount_type' => 'percentage',
            'discount_value' => 50,
            'is_active' => '1',
        ])->assertRedirect(route('admin.marketing.coupons.index'))
            ->assertSessionHas('admin_error');

        $this->delete(route('admin.marketing.coupons.destroy', $usedCoupon))
            ->assertRedirect(route('admin.marketing.coupons.index'))
            ->assertSessionHas('admin_error');

        $usedCoupon->refresh();
        $this->assertEqualsWithDelta($originalDiscount, (float) $usedCoupon->discount_value, 0.01);

        // Eliminar cupón sin usos ni pedidos sí funciona
        $this->delete(route('admin.marketing.coupons.destroy', $createdCoupon))
            ->assertRedirect(route('admin.marketing.coupons.index'))
            ->assertSessionHas('admin_success');
        $this->assertDatabaseMissing('coupons', ['id' => $createdCoupon->id]);

        // 4. Carritos Abandonados Admin: envío individual y despacho elegibles
        $this->get(route('admin.marketing.abandoned-carts.index'))
            ->assertOk()
            ->assertSee('cliente@glamora.pe');

        $cart1 = AbandonedCart::query()->where('session_id', 'sess_glamora_abandoned_001')->firstOrFail();
        $this->post(route('admin.marketing.abandoned-carts.remind', $cart1))
            ->assertRedirect(route('admin.marketing.abandoned-carts.index'))
            ->assertSessionHas('admin_success');

        $cart1->refresh();
        $this->assertSame(1, $cart1->reminder_count);
        Mail::assertQueued(AbandonedCartReminderMail::class, 1);

        $this->post(route('admin.marketing.abandoned-carts.dispatch-eligible'))
            ->assertRedirect(route('admin.marketing.abandoned-carts.index'))
            ->assertSessionHas('admin_success');

        Mail::assertQueued(AbandonedCartReminderMail::class, 2);

        // 5. Newsletter Admin: filtro dinámico de segmentos y toggles
        $this->get(route('admin.marketing.newsletter.index', ['segment' => 'skincare']))
            ->assertOk()
            ->assertSee('cliente@glamora.pe')
            ->assertDontSee('mariana.castro@gmail.com');

        $subscriber = NewsletterSubscriber::query()->where('email', 'paula.diaz@estudio.pe')->firstOrFail();
        $wasVerified = (bool) $subscriber->is_verified;
        $wasActive = (bool) $subscriber->is_active;

        $this->patch(route('admin.marketing.newsletter.toggle-verified', $subscriber))
            ->assertRedirect(route('admin.marketing.newsletter.index'));
        $this->patch(route('admin.marketing.newsletter.toggle-active', $subscriber))
            ->assertRedirect(route('admin.marketing.newsletter.index'));

        $subscriber->refresh();
        $this->assertSame(! $wasVerified, (bool) $subscriber->is_verified);
        $this->assertSame(! $wasActive, (bool) $subscriber->is_active);
    }

    public function test_admin_marketing_blog_crud_product_sync_cover_upload_and_storefront_publication(): void
    {
        Storage::fake('public');

        $admin = User::query()->where('email', 'admin@glamora.pe')->firstOrFail();
        $this->actingAs($admin);

        $product1 = Product::query()->where('slug', 'serum-vitamina-c-camu-camu')->firstOrFail();
        $product2 = Product::query()->where('slug', 'fluido-solar-mineral-fps50')->firstOrFail();

        // Crear artículo en borrador con portada raster PNG segura (sin ext-gd) y 2 productos en post_product
        $pngBinary = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
        $this->assertNotFalse($pngBinary);
        $coverFile = UploadedFile::fake()->createWithContent('portada-botanica.png', $pngBinary);

        $this->post(route('admin.marketing.blog.store'), [
            'title' => 'Protocolo Facial Botánico Lima 2026',
            'category_tag' => 'Dermocosmética',
            'excerpt' => 'Guía editorial de protección diaria en zonas urbanas.',
            'content' => '<p>Combina antioxidantes de camu camu con protector mineral.</p><h3>Recomendación</h3><p>Uso matutino.</p>',
            'cover_image_file' => $coverFile,
            'reading_time_minutes' => 5,
            'meta_title' => 'Protocolo Facial Botánico Lima 2026 — Glamora',
            'meta_description' => 'Protocolo antioxidante y solar para el clima limeño.',
            'products' => [
                $product1->id => [
                    'selected' => '1',
                    'sort_order' => 1,
                    'custom_callout' => 'Paso 1 · Vitamina C Amazónica',
                ],
                $product2->id => [
                    'selected' => '1',
                    'sort_order' => 2,
                    'custom_callout' => 'Paso 2 · Escudo Mineral FPS 50+',
                ],
            ],
        ])->assertRedirect(route('admin.marketing.blog.index'))
            ->assertSessionHas('admin_success');

        $post = Post::query()->where('slug', 'protocolo-facial-botanico-lima-2026')->firstOrFail();
        $this->assertFalse((bool) $post->is_published);
        $this->assertNotNull($post->cover_image);
        $this->assertStringStartsWith('storage/blog/', (string) $post->cover_image);
        Storage::disk('public')->assertExists(str_replace('storage/', '', (string) $post->cover_image));

        $this->assertDatabaseHas('post_product', [
            'post_id' => $post->id,
            'product_id' => $product1->id,
            'sort_order' => 1,
            'custom_callout' => 'Paso 1 · Vitamina C Amazónica',
        ]);
        $this->assertDatabaseHas('post_product', [
            'post_id' => $post->id,
            'product_id' => $product2->id,
            'sort_order' => 2,
        ]);

        // Mientras es borrador NO aparece en Storefront ni Sitemap
        $this->get(route('blog.show', $post->slug))->assertNotFound();
        $this->get(route('sitemap'))->assertDontSee(route('blog.show', $post->slug));

        // Actualizar post cambiando sincronización a 1 producto y publicarlo
        $this->put(route('admin.marketing.blog.update', $post), [
            'title' => 'Protocolo Facial Botánico Lima 2026 Editado',
            'slug' => $post->slug,
            'category_tag' => 'Skincare',
            'excerpt' => 'Resumen actualizado.',
            'content' => '<p>Contenido editorial actualizado para clientes y salones.</p>',
            'reading_time_minutes' => 6,
            'is_published' => '1',
            'products' => [
                $product2->id => [
                    'selected' => '1',
                    'sort_order' => 1,
                    'custom_callout' => 'Imprescindible · Toque seco invisible',
                ],
            ],
        ])->assertRedirect(route('admin.marketing.blog.index'));

        $post->refresh();
        $this->assertTrue((bool) $post->is_published);
        $this->assertNotNull($post->published_at);
        $this->assertDatabaseMissing('post_product', [
            'post_id' => $post->id,
            'product_id' => $product1->id,
        ]);
        $this->assertDatabaseHas('post_product', [
            'post_id' => $post->id,
            'product_id' => $product2->id,
            'custom_callout' => 'Imprescindible · Toque seco invisible',
        ]);

        // Ahora visible en Storefront y en Sitemap XML
        $this->get(route('blog.index'))
            ->assertOk()
            ->assertSee('Protocolo Facial Botánico Lima 2026 Editado');
        $this->get(route('blog.show', $post->slug))
            ->assertOk()
            ->assertSee($product2->name)
            ->assertSee('Imprescindible · Toque seco invisible')
            ->assertSee('"@type":"Article"', false);
        $this->get(route('sitemap'))
            ->assertOk()
            ->assertSee(route('blog.show', $post->slug));

        // Toggle publish a borrador y eliminar
        $this->patch(route('admin.marketing.blog.toggle-publish', $post))
            ->assertRedirect(route('admin.marketing.blog.index'));
        $post->refresh();
        $this->assertFalse((bool) $post->is_published);

        $this->delete(route('admin.marketing.blog.destroy', $post))
            ->assertRedirect(route('admin.marketing.blog.index'));

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
        $this->assertDatabaseMissing('post_product', ['post_id' => $post->id]);
        $this->assertGreaterThan(0, SecurityLog::query()->where('event_type', 'marketing_post_deleted')->count());
    }

    public function test_paso8_security_svg_rejection_xss_sanitization_queue_step_and_rbac(): void
    {
        Storage::fake('public');

        /** @var User $superAdmin */
        $superAdmin = User::role('SuperAdmin')->firstOrFail();
        $cliente = User::query()->where('email', 'cliente@glamora.pe')->firstOrFail();
        $almacenero = User::query()->where('email', 'almacen@glamora.pe')->firstOrFail();
        $vendedorB2b = User::query()->where('email', 'b2b@glamora.pe')->firstOrFail();

        // 1. SuperAdmin accede sin errores a todo Marketing y sin doble escape de '&' en Admin Layout
        $this->actingAs($superAdmin);
        foreach ([
            route('admin.marketing.index'),
            route('admin.marketing.coupons.index'),
            route('admin.marketing.abandoned-carts.index'),
            route('admin.marketing.newsletter.index'),
            route('admin.marketing.blog.index'),
        ] as $marketingUrl) {
            $this->get($marketingUrl)
                ->assertOk()
                ->assertDontSee('&amp;amp;', false);
        }

        $this->get(route('admin.marketing.index'))
            ->assertOk()
            ->assertSee('Marketing &amp; Retención', false);

        // 2. Rechazo de .svg con XSS (<script>, <foreignObject>, onload) en Blog, Productos, Marcas y Categorías
        $maliciousSvg = UploadedFile::fake()->createWithContent(
            'xss.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(1)</script><foreignObject><body>XSS</body></foreignObject></svg>'
        );

        $this->post(route('admin.marketing.blog.store'), [
            'title' => 'Post SVG Bloqueado',
            'category_tag' => 'Seguridad',
            'content' => '<p>Contenido válido</p>',
            'cover_image_file' => $maliciousSvg,
        ])->assertSessionHasErrors('cover_image_file');
        $this->assertDatabaseMissing('posts', ['title' => 'Post SVG Bloqueado']);

        $category = Category::query()->firstOrFail();
        $brand = Brand::query()->firstOrFail();

        $this->post(route('admin.pim.products.store'), [
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Producto SVG Bloqueado',
            'sanitary_registration' => 'NSOC99999-26PE',
            'min_stock_alert' => 5,
            'reorder_point' => 10,
            'main_image_file' => $maliciousSvg,
            'variant_sku' => 'GLM-SVG-TEST-01',
            'variant_shade_name' => 'Tono Test',
            'variant_base_price' => 59.90,
            'variant_wholesale_base_price' => 45.00,
            'variant_cost_price' => 20.00,
        ])->assertSessionHasErrors('main_image_file');
        $this->assertDatabaseMissing('products', ['name' => 'Producto SVG Bloqueado']);

        $this->post(route('admin.pim.brands.store'), [
            'name' => 'Marca SVG Bloqueada',
            'country_origin' => 'Perú',
            'logo_file' => $maliciousSvg,
        ])->assertSessionHasErrors('logo_file');
        $this->assertDatabaseMissing('brands', ['name' => 'Marca SVG Bloqueada']);

        $this->post(route('admin.pim.categories.store'), [
            'name' => 'Categoría SVG Bloqueada',
            'image_file' => $maliciousSvg,
        ])->assertSessionHasErrors('image_file');
        $this->assertDatabaseMissing('categories', ['name' => 'Categoría SVG Bloqueada']);
        $this->assertSame([], Storage::disk('public')->allFiles());

        // 3. Sanitización estricta de rutas manuales peligrosas y XSS DOM en HTML de Blog
        $this->post(route('admin.marketing.blog.store'), [
            'title' => 'Post Sanitizado Paso 8',
            'category_tag' => 'Dermocosmética',
            'content' => '<p/onclick=alert(1)>Párrafo botánico limpio</p>'
                . '<p title="x"onmouseover=alert(2)>Segundo bloque</p>'
                . '<script>alert(3)</script>'
                . '<svg onload=alert(4)><circle r="10"/></svg>'
                . '<a href="javascript:alert(5)">Enlace neutro</a>',
            'cover_image' => 'javascript:alert(1)',
            'is_published' => '1',
        ])->assertRedirect(route('admin.marketing.blog.index'));

        $xssPost = Post::query()->where('title', 'Post Sanitizado Paso 8')->firstOrFail();
        $this->assertNull($xssPost->cover_image);

        $safeHtml = $xssPost->safeHtmlContent();
        $this->assertStringNotContainsString('<svg', $safeHtml);
        $this->assertStringNotContainsString('onclick', $safeHtml);
        $this->assertStringNotContainsString('onmouseover', $safeHtml);
        $this->assertStringNotContainsString('alert(', $safeHtml);
        $this->assertStringNotContainsString('javascript:', $safeHtml);

        $this->get(route('blog.show', $xssPost->slug))
            ->assertOk()
            ->assertSee('Párrafo botánico limpio')
            ->assertSee('Segundo bloque')
            ->assertSee('Enlace neutro')
            ->assertDontSee('onclick=', false)
            ->assertDontSee('onmouseover=', false)
            ->assertDontSee('alert(3)', false)
            ->assertDontSee('onload=alert(4)', false)
            ->assertDontSee('<circle', false)
            ->assertDontSee('javascript:alert(5)', false);

        // 4. Preservación de reminderStep en AbandonedCartReminderMail tras SerializesModels + update en BD
        $cart = AbandonedCart::query()->create([
            'session_id' => 'sess_serializes_step_test',
            'email' => 'queue-step@glamora.pe',
            'cart_payload' => [['product_name' => 'Sérum Queue Test', 'quantity' => 1, 'unit_price' => 89.90, 'subtotal' => 89.90]],
            'subtotal' => 89.90,
            'items_count' => 1,
            'last_activity_at' => now()->subHours(3),
            'reminder_count' => 0,
        ]);

        $queuedMail = new AbandonedCartReminderMail($cart, 1);
        $serialized = serialize($queuedMail);
        $cart->forceFill(['reminder_count' => 1, 'last_reminded_at' => now()])->save();

        /** @var AbandonedCartReminderMail $restoredMail */
        $restoredMail = unserialize($serialized);
        $this->assertSame('Dejaste productos en tu bolsa Glamora Perú', $restoredMail->envelope()->subject);
        $renderedHtml = $restoredMail->render();
        $this->assertStringContainsString('Sérum Queue Test', $renderedHtml);

        // 5. RBAC de escritura en Marketing y bloqueo de usuario inactivo en /mi-cuenta
        foreach ([$cliente, $almacenero, $vendedorB2b] as $unauthorized) {
            $this->actingAs($unauthorized)
                ->post(route('admin.marketing.coupons.store'), ['code' => 'NOAUTH'])
                ->assertForbidden();
            $this->actingAs($unauthorized)
                ->post(route('admin.marketing.blog.store'), ['title' => 'No auth'])
                ->assertForbidden();
        }

        $cliente->forceFill(['is_active' => false])->save();
        $this->actingAs($cliente)
            ->get(route('account.index'))
            ->assertForbidden();
    }
}
