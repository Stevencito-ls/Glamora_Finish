<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BannerPosition;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Livewire\Storefront\LiveSearch;
use App\Models\Announcement;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\CartSessionService;
use App\Services\CrossSellService;
use App\Services\ImageOptimizerService;
use App\Services\PricingService;
use App\Services\SettingService;
use App\Services\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class GlamoraProTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function tinyPng(): string
    {
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
        $this->assertNotFalse($bytes);

        return $bytes;
    }

    private function tinyIco(): string
    {
        $png = $this->tinyPng();
        // Valid ICO header (6 bytes) + 1 ICONDIRENTRY (16 bytes) + PNG payload
        $header = pack('vvv', 0, 1, 1);
        $entry = pack('CCCCvvVV', 1, 1, 0, 0, 1, 32, strlen($png), 22);

        return $header . $entry . $png;
    }

    public function test_rbac_matrix_and_sidebar_for_pro_permissions_and_pim_catalog_pdf(): void
    {
        $superAdmin = User::query()->where('email', 'superadmin@glamora.pe')->firstOrFail();
        $admin = User::query()->where('email', 'admin@glamora.pe')->firstOrFail();
        $almacenero = User::query()->where('email', 'almacen@glamora.pe')->firstOrFail();
        $vendedorB2b = User::query()->where('email', 'b2b@glamora.pe')->firstOrFail();
        $cliente = User::query()->where('email', 'cliente@glamora.pe')->firstOrFail();

        $announcement = Announcement::query()->firstOrFail();
        $review = ProductReview::query()->firstOrFail();

        // Guest redirected to login
        $this->get(route('admin.settings.index'))->assertRedirect(route('login'));
        $this->get(route('admin.announcements.index'))->assertRedirect(route('login'));
        $this->get(route('admin.reviews.index'))->assertRedirect(route('login'));
        $this->get(route('admin.pim.catalog-pdf'))->assertRedirect(route('login'));

        // SuperAdmin & Administrador: 200 on all PRO routes + sidebar links + PIM Catalog PDF
        foreach ([$superAdmin, $admin] as $authorizedUser) {
            $this->actingAs($authorizedUser);

            $this->get(route('admin.settings.index'))
                ->assertOk()
                ->assertSee('White-Label')
                ->assertSee('Anuncios Topbar')
                ->assertSee('Reseñas Verificadas');

            $this->get(route('admin.announcements.index'))->assertOk();
            $this->get(route('admin.reviews.index'))->assertOk();

            $this->get(route('admin.pim.products.index'))
                ->assertOk()
                ->assertSee(route('admin.pim.catalog-pdf'), false);

            $pdfResponse = $this->get(route('admin.pim.catalog-pdf'));
            $pdfResponse->assertOk();
            $this->assertStringContainsString('application/pdf', (string) $pdfResponse->headers->get('Content-Type'));
        }

        // Almacenero & Vendedor B2B: 403 on settings/announcements/reviews (read + write), 200 on pim.catalog-pdf (catalog.view)
        foreach ([$almacenero, $vendedorB2b] as $staffWithoutPro) {
            $this->actingAs($staffWithoutPro);

            $this->get(route('admin.dashboard'))
                ->assertOk()
                ->assertDontSee('White-Label &amp; Temas', false)
                ->assertDontSee('Anuncios Topbar')
                ->assertDontSee('Reseñas Verificadas');

            $this->get(route('admin.pim.catalog-pdf'))->assertOk();

            $this->get(route('admin.settings.index'))->assertForbidden();
            $this->put(route('admin.settings.update'), ['store_name' => 'Hack'])->assertForbidden();
            $this->post(route('admin.settings.onboarding-dismiss'))->assertForbidden();

            $this->get(route('admin.announcements.index'))->assertForbidden();
            $this->post(route('admin.announcements.store'), ['title' => 'Unauthorized'])->assertForbidden();
            $this->put(route('admin.announcements.update', $announcement), ['title' => 'Unauthorized'])->assertForbidden();
            $this->patch(route('admin.announcements.toggle', $announcement))->assertForbidden();
            $this->delete(route('admin.announcements.destroy', $announcement))->assertForbidden();

            $this->get(route('admin.reviews.index'))->assertForbidden();
            $this->patch(route('admin.reviews.moderate', $review), ['status' => 'approved'])->assertForbidden();
            $this->delete(route('admin.reviews.destroy', $review))->assertForbidden();
        }

        // Cliente: 403 across all admin routes including pim.catalog-pdf
        $this->actingAs($cliente);
        $this->get(route('admin.pim.catalog-pdf'))->assertForbidden();
        $this->get(route('admin.settings.index'))->assertForbidden();
        $this->get(route('admin.announcements.index'))->assertForbidden();
        $this->get(route('admin.reviews.index'))->assertForbidden();
    }

    public function test_white_label_settings_theme_whitelist_onboarding_and_logo_favicon_cleanup(): void
    {
        Storage::fake('public');

        $admin = User::query()->where('email', 'admin@glamora.pe')->firstOrFail();
        $this->actingAs($admin);

        // Onboarding visible before dismiss
        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Asistente Inicial Glamora PRO');

        $this->post(route('admin.settings.onboarding-dismiss'))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertTrue(app(SettingService::class)->bool('onboarding_dismissed'));
        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('Asistente Inicial Glamora PRO');

        // Reject invalid theme & javascript: social URLs
        $this->put(route('admin.settings.update'), [
            'store_name' => 'Glamora Boutique',
            'store_email' => 'hola@glamora.pe',
            'free_shipping_threshold' => 199,
            'tax_rate' => 0.18,
            'active_theme' => 'invalid_theme"><script>alert(1)</script>',
            'social_instagram' => 'javascript:alert(1)',
        ])->assertSessionHasErrors(['active_theme', 'social_instagram']);

        // Upload valid logo (PNG) + valid favicon (ICO) + change theme to noir_gold + custom commercial rules
        $firstLogo = UploadedFile::fake()->createWithContent('brand-logo.png', $this->tinyPng());
        $firstFavicon = UploadedFile::fake()->createWithContent('favicon.ico', $this->tinyIco());

        $this->put(route('admin.settings.update'), [
            'store_name' => 'Atelier Botanica Lima',
            'store_tagline' => 'Cosmética Natural White-Label',
            'store_ruc' => '20555444331',
            'store_email' => 'contacto@atelierbotanica.pe',
            'store_phone' => '+51 999 111 222',
            'store_address' => 'Av. Conquistadores 450, San Isidro',
            'social_instagram' => 'https://instagram.com/atelierbotanica',
            'whatsapp_number' => '987123456',
            'whatsapp_checkout_enabled' => '1',
            'whatsapp_floating_enabled' => '1',
            'free_shipping_threshold' => 150.00,
            'tax_rate' => 0.18,
            'active_theme' => 'noir_gold',
            'catalog_pdf_enabled' => '1',
            'logo_file' => $firstLogo,
            'favicon_file' => $firstFavicon,
        ])->assertRedirect(route('admin.settings.index'))
            ->assertSessionHas('admin_success');

        $settings = app(SettingService::class);
        $oldLogoPath = $settings->string('store_logo_path');
        $oldFaviconPath = $settings->string('store_favicon_path');

        $this->assertStringStartsWith('storage/branding/', $oldLogoPath);
        $this->assertStringStartsWith('storage/branding/', $oldFaviconPath);
        Storage::disk('public')->assertExists(substr($oldLogoPath, strlen('storage/')));
        Storage::disk('public')->assertExists(substr($oldFaviconPath, strlen('storage/')));

        // WhatsApp normalization + dynamic threshold
        $this->assertSame('51987123456', $settings->whatsappNumber());
        $this->assertEqualsWithDelta(150.00, app(ShippingService::class)->freeShippingThreshold(), 0.01);
        $this->assertEqualsWithDelta(0.18, app(PricingService::class)->taxRate(), 0.0001);

        // Storefront renders noir_gold theme & white-label branding
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-theme="noir_gold"', false)
            ->assertSee('id="glamora-dynamic-theme"', false)
            ->assertSee('--color-glamora-cream: #F5F3EE;', false)
            ->assertSee('ATELIER BOTANICA LIMA')
            ->assertSee('https://wa.me/51987123456', false);

        // Replace logo & favicon -> old files automatically deleted
        $secondLogo = UploadedFile::fake()->createWithContent('brand-logo-v2.png', $this->tinyPng());
        $secondFavicon = UploadedFile::fake()->createWithContent('favicon-v2.png', $this->tinyPng());

        $this->put(route('admin.settings.update'), [
            'store_name' => 'Atelier Botanica Lima',
            'store_email' => 'contacto@atelierbotanica.pe',
            'free_shipping_threshold' => 150.00,
            'tax_rate' => 0.18,
            'active_theme' => 'botanical_skincare',
            'logo_file' => $secondLogo,
            'favicon_file' => $secondFavicon,
        ])->assertRedirect(route('admin.settings.index'));

        $settings->clearCache();
        $newLogoPath = $settings->string('store_logo_path');
        $newFaviconPath = $settings->string('store_favicon_path');
        $this->assertNotSame($oldLogoPath, $newLogoPath);
        $this->assertNotSame($oldFaviconPath, $newFaviconPath);
        Storage::disk('public')->assertMissing(substr($oldLogoPath, strlen('storage/')));
        Storage::disk('public')->assertMissing(substr($oldFaviconPath, strlen('storage/')));
        Storage::disk('public')->assertExists(substr($newLogoPath, strlen('storage/')));
        Storage::disk('public')->assertExists(substr($newFaviconPath, strlen('storage/')));

        // If transaction fails mid-update, newly uploaded logo/favicon are rolled back/cleaned up and old ones preserved
        StoreSetting::saving(static function (StoreSetting $model): void {
            if ($model->key === 'store_name') {
                throw new RuntimeException('Simulated DB failure');
            }
        });

        $txFailLogo = UploadedFile::fake()->createWithContent('rollback-logo.png', $this->tinyPng());
        try {
            $this->withoutExceptionHandling()
                ->put(route('admin.settings.update'), [
                    'store_name' => 'Should Rollback',
                    'store_email' => 'contacto@atelierbotanica.pe',
                    'free_shipping_threshold' => 150.00,
                    'tax_rate' => 0.18,
                    'active_theme' => 'glamora_nude',
                    'logo_file' => $txFailLogo,
                ]);
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertSame('Simulated DB failure', $e->getMessage());
        } finally {
            StoreSetting::flushEventListeners();
        }

        // Old logo must still exist; only 2 files (current logo + current favicon) remain
        Storage::disk('public')->assertExists(substr($newLogoPath, strlen('storage/')));
        Storage::disk('public')->assertExists(substr($newFaviconPath, strlen('storage/')));
        $this->assertCount(2, Storage::disk('public')->allFiles('branding'));

        // Remove logo & favicon checkboxes delete existing files cleanly
        $this->put(route('admin.settings.update'), [
            'store_name' => 'Atelier Botanica Lima',
            'store_email' => 'contacto@atelierbotanica.pe',
            'free_shipping_threshold' => 150.00,
            'tax_rate' => 0.18,
            'active_theme' => 'glamora_nude',
            'remove_logo' => '1',
            'remove_favicon' => '1',
        ])->assertRedirect(route('admin.settings.index'));

        $settings->clearCache();
        $this->assertSame('', $settings->string('store_logo_path'));
        $this->assertSame('', $settings->string('store_favicon_path'));
        $this->assertSame([], Storage::disk('public')->allFiles('branding'));

        // Direct DB tampering of active_theme falls back safely to glamora_nude whitelist
        DB::table('store_settings')->updateOrInsert(
            ['key' => 'active_theme'],
            ['value' => '"><script>alert("xss")</script>', 'type' => 'string', 'group' => 'theme', 'label' => 'Tema', 'is_public' => true]
        );
        $settings->clearCache();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-theme="glamora_nude"', false)
            ->assertDontSee('<script>alert("xss")</script>', false);
    }

    public function test_upload_security_rejects_svg_svgz_html_php_and_polyglots_across_all_endpoints(): void
    {
        Storage::fake('public');

        $admin = User::query()->where('email', 'admin@glamora.pe')->firstOrFail();
        $this->actingAs($admin);

        $svgPayload = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(1)</script></svg>';
        $svgzBinary = gzencode($svgPayload);
        $this->assertIsString($svgzBinary);

        $maliciousPayloads = [
            'svg' => UploadedFile::fake()->createWithContent('evil.svg', $svgPayload),
            'svgz' => UploadedFile::fake()->createWithContent('evil.svgz', $svgzBinary),
            'svgz_disguised_png' => UploadedFile::fake()->createWithContent('disguised.png', $svgzBinary),
            'html_as_jpg' => UploadedFile::fake()->createWithContent('page.jpg', '<!DOCTYPE html><html><body><script>alert(1)</script></body></html>'),
            'php_as_png' => UploadedFile::fake()->createWithContent('shell.png', '<?php system($_GET["cmd"]); ?>'),
            'polyglot_png_php' => UploadedFile::fake()->createWithContent('poly-php.png', $this->tinyPng() . '<?php echo "pwn"; ?>'),
            'polyglot_png_svg' => UploadedFile::fake()->createWithContent('poly-svg.png', $this->tinyPng() . '<svg onload=alert(1)>'),
            'polyglot_png_shorttag' => UploadedFile::fake()->createWithContent('poly-short.png', $this->tinyPng() . '<?= $_GET[0] ?>'),
            'xml_bom' => UploadedFile::fake()->createWithContent('bom.webp', "\xEF\xBB\xBF<?XML version=\"1.0\"?><svg/>"),
            'fake_short_ico' => UploadedFile::fake()->createWithContent('tiny.ico', "\x00\x00\x01\x00\x01\x00"),
            'ico_disguised_as_logo' => UploadedFile::fake()->createWithContent('icon.ico', $this->tinyIco()),
        ];

        $optimizer = app(ImageOptimizerService::class);
        foreach ($maliciousPayloads as $label => $file) {
            $allowIcons = $label === 'fake_short_ico';
            $result = $optimizer->inspectBinary($file, allowIcons: $allowIcons);
            $this->assertFalse($result['valid'], "El payload malicioso '{$label}' debió ser rechazado por ImageOptimizerService.");
        }

        // 1. Logo & Favicon in SettingController
        $this->put(route('admin.settings.update'), [
            'store_name' => 'Glamora Test',
            'store_email' => 'info.glamora@gmail.com',
            'free_shipping_threshold' => 199,
            'tax_rate' => 0.18,
            'active_theme' => 'glamora_nude',
            'logo_file' => UploadedFile::fake()->createWithContent('poly-logo.png', $this->tinyPng() . '<?php echo 1; ?>'),
            'favicon_file' => UploadedFile::fake()->createWithContent('evil-fav.svgz', $svgzBinary),
        ])->assertSessionHasErrors(['logo_file', 'favicon_file']);

        // 2. Product main image, gallery & initial variant
        $category = Category::query()->firstOrFail();
        $brand = Brand::query()->firstOrFail();
        $product = Product::query()->firstOrFail();
        $variant = $product->variants()->firstOrFail();

        $this->post(route('admin.pim.products.store'), [
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Producto Políglota Bloqueado',
            'sanitary_registration' => 'NSOC99887-26PE',
            'min_stock_alert' => 5,
            'reorder_point' => 10,
            'main_image_file' => UploadedFile::fake()->createWithContent('poly-main.png', $this->tinyPng() . '<script>alert(1)</script>'),
            'gallery_files' => [UploadedFile::fake()->createWithContent('gal.png', $this->tinyPng() . '<?php exit; ?>')],
            'variant_sku' => 'GLM-POLY-TEST-01',
            'variant_shade_name' => 'Tono Test',
            'variant_base_price' => 49.90,
            'variant_wholesale_base_price' => 35.00,
            'variant_cost_price' => 15.00,
            'variant_image_file' => UploadedFile::fake()->createWithContent('var.html', '<html><script>alert(1)</script></html>'),
        ])->assertSessionHasErrors(['main_image_file', 'gallery_files.0', 'variant_image_file']);

        // 3. Variant store & update endpoints
        $this->post(route('admin.pim.products.variants.store', $product), [
            'sku' => 'GLM-POLY-VAR-02',
            'shade_name' => 'Tono Bloqueado',
            'base_price' => 39.90,
            'wholesale_base_price' => 28.00,
            'cost_price' => 12.00,
            'image_file' => UploadedFile::fake()->createWithContent('poly-var.png', $this->tinyPng() . '<?php phpinfo(); ?>'),
        ])->assertSessionHasErrors('image_file');

        $this->put(route('admin.pim.products.variants.update', [$product, $variant]), [
            'sku' => $variant->sku,
            'shade_name' => $variant->shade_name,
            'base_price' => $variant->base_price,
            'wholesale_base_price' => $variant->wholesale_base_price,
            'cost_price' => $variant->cost_price,
            'image_file' => UploadedFile::fake()->createWithContent('poly-var-upd.png', $svgzBinary),
        ])->assertSessionHasErrors('image_file');

        // 4. Categories
        $this->post(route('admin.pim.categories.store'), [
            'name' => 'Cat Políglota',
            'image_file' => UploadedFile::fake()->createWithContent('cat.png', $this->tinyPng() . '<svg onload=alert(1)>'),
        ])->assertSessionHasErrors('image_file');

        // 5. Brands (logo + banner)
        $this->post(route('admin.pim.brands.store'), [
            'name' => 'Marca Políglota',
            'country_origin' => 'Perú',
            'logo_file' => UploadedFile::fake()->createWithContent('brand.png', $this->tinyPng() . '<?php echo 1; ?>'),
            'banner_file' => UploadedFile::fake()->createWithContent('banner.php', '<?php system("id"); ?>'),
        ])->assertSessionHasErrors(['logo_file', 'banner_file']);

        // 6. Banners (main + accent)
        $this->post(route('admin.banners.store'), [
            'title' => 'Banner Políglota',
            'position' => BannerPosition::HeroPrincipal->value,
            'image_file' => UploadedFile::fake()->createWithContent('ban1.png', $this->tinyPng() . '<?= 1 ?>'),
            'accent_image_file' => UploadedFile::fake()->createWithContent('ban2.svg', $svgPayload),
        ])->assertSessionHasErrors(['image_file', 'accent_image_file']);

        // 7. Blog
        $this->post(route('admin.marketing.blog.store'), [
            'title' => 'Blog Políglota',
            'category_tag' => 'Seguridad',
            'content' => '<p>Contenido seguro de prueba.</p>',
            'cover_image_file' => UploadedFile::fake()->createWithContent('blog.png', $this->tinyPng() . '<iframe src="evil"></iframe>'),
        ])->assertSessionHasErrors('cover_image_file');

        // Ensure zero files were saved to disk
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_announcements_xss_and_safe_link_urls_in_topbar_and_banners(): void
    {
        $admin = User::query()->where('email', 'admin@glamora.pe')->firstOrFail();
        $this->actingAs($admin);

        // Announcement::sanitizeLinkUrl blocks all unsafe URL schemes & traversal patterns
        foreach ([
            'javascript:alert(1)',
            'JAVASCRIPT:alert(1)',
            'data:text/html,<script>alert(1)</script>',
            'vbscript:msgbox(1)',
            '//evil.example/phish',
            '/\evil.example/phish',
            '/catalogo/../admin',
            "https://glamora.pe\n<script>",
        ] as $unsafeUrl) {
            $this->assertNull(Announcement::sanitizeLinkUrl($unsafeUrl), "URL peligrosa no bloqueada: {$unsafeUrl}");

            $this->post(route('admin.announcements.store'), [
                'title' => 'Anuncio con URL bloqueada',
                'link_url' => $unsafeUrl,
            ])->assertSessionHasErrors('link_url');
        }

        // Valid relative & https URLs accepted
        $this->assertSame('/catalogo', Announcement::sanitizeLinkUrl('/catalogo'));
        $this->assertSame('#resenas', Announcement::sanitizeLinkUrl('#resenas'));
        $this->assertSame('https://glamora.pe/promo', Announcement::sanitizeLinkUrl('https://glamora.pe/promo'));

        // XSS payloads in title, badge_text, link_label are escaped safely in Topbar and Admin table
        $this->post(route('admin.announcements.store'), [
            'title' => 'Promo <script>alert("title")</script> Glamora',
            'badge_text' => '<img src=x onerror=alert(1)>',
            'link_label' => '<b>Ir ahora</b>',
            'link_url' => '/catalogo',
            'sort_order' => 0,
            'is_active' => '1',
        ])->assertRedirect(route('admin.announcements.index'));

        $created = Announcement::query()->where('title', 'like', '%Promo%Glamora%')->firstOrFail();

        $this->get(route('admin.announcements.index'))
            ->assertOk()
            ->assertDontSee('<script>alert("title")</script>', false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false)
            ->assertSee('&lt;script&gt;alert(&quot;title&quot;)&lt;/script&gt;', false);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('<script>alert("title")</script>', false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false)
            ->assertSee('&lt;script&gt;alert(&quot;title&quot;)&lt;/script&gt;', false);

        // Toggle & Delete
        $this->patch(route('admin.announcements.toggle', $created))
            ->assertRedirect(route('admin.announcements.index'));
        $created->refresh();
        $this->assertFalse((bool) $created->is_active);

        $this->delete(route('admin.announcements.destroy', $created))
            ->assertRedirect(route('admin.announcements.index'));
        $this->assertDatabaseMissing('announcements', ['id' => $created->id]);

        // Banner button URLs also sanitize javascript: URLs in Home hero slides
        $banner = Banner::query()->where('position', BannerPosition::HeroPrincipal->value)->firstOrFail();
        $banner->update([
            'button_url' => 'javascript:alert(99)',
            'secondary_button_url' => 'data:text/html,xss',
        ]);
        $this->assertNull($banner->safeButtonUrl());
        $this->assertNull($banner->safeSecondaryButtonUrl());

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('javascript:alert(99)', false)
            ->assertDontSee('data:text/html,xss', false);
    }

    public function test_catalog_pdf_white_label_public_toggle_and_admin_override(): void
    {
        $admin = User::query()->where('email', 'admin@glamora.pe')->firstOrFail();
        $settings = app(SettingService::class);
        $settings->set('store_name', 'Glamora WhiteLabel PDF');
        $settings->set('catalog_pdf_enabled', true);

        $publicPdf = $this->get(route('catalog.pdf'));
        $publicPdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $publicPdf->headers->get('Content-Type'));
        $this->assertStringContainsString(
            'Catalogo-glamora-whitelabel-pdf-',
            (string) $publicPdf->headers->get('Content-Disposition')
        );

        // Disable public PDF download -> storefront returns 404, admin PIM endpoint still works (200)
        $settings->set('catalog_pdf_enabled', false);

        $this->get(route('catalog.pdf'))->assertNotFound();

        $this->actingAs($admin)
            ->get(route('admin.pim.catalog-pdf'))
            ->assertOk();
    }

    public function test_whatsapp_checkout_and_quick_order_flow_respects_fefo_and_feature_toggle(): void
    {
        $variant = ProductVariant::query()->where('sku', 'GLM-LB-VITC-30')->firstOrFail();
        $initialStock = $variant->availableStock();
        $this->assertGreaterThanOrEqual(2, $initialStock);

        /** @var CartSessionService $cart */
        $cart = app(CartSessionService::class);
        $cart->addVariant($variant, 2);

        // 1. Standard payment_method=whatsapp flow
        $response = $this->post(route('checkout.store'), [
            'customer_name' => 'Valeria Rojas',
            'customer_phone' => '+51 987 654 111',
            'shipping_method' => 'estandar',
            'payment_method' => PaymentMethod::Whatsapp->value,
        ]);

        $order = Order::query()->where('customer_name', 'Valeria Rojas')->latest('id')->firstOrFail();
        $response->assertRedirect(route('checkout.confirmation', $order->order_number))
            ->assertSessionHas('whatsapp_order_url');

        $this->assertSame(OrderStatus::PendientePago, $order->status);
        $payment = Payment::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(PaymentMethod::Whatsapp, $payment->payment_method);
        $this->assertSame('pending', $payment->status);
        $this->assertSame('WA-' . $order->order_number, $payment->transaction_reference);

        // FEFO stock reserved
        $variant->unsetRelation('batches');
        $this->assertSame($initialStock - 2, $variant->availableStock());
        $this->assertDatabaseHas('inventory_movements', [
            'reference_type' => Order::class,
            'reference_id' => $order->id,
            'movement_type' => 'reserva',
            'quantity' => -2,
        ]);

        // Confirmation page displays WhatsApp URL
        $this->get(route('checkout.confirmation', $order->order_number))
            ->assertOk()
            ->assertSee('https://wa.me/', false)
            ->assertSee($order->order_number);

        // 2. Quick WhatsApp checkout (whatsapp_quick=1) with authenticated user & missing optional fields
        $cliente = User::query()->where('email', 'cliente@glamora.pe')->firstOrFail();
        $this->actingAs($cliente);
        $cart->addVariant($variant, 1);

        $quickResponse = $this->post(route('checkout.store'), [
            'whatsapp_quick' => '1',
        ]);

        $quickOrder = Order::query()->where('user_id', $cliente->id)->latest('id')->firstOrFail();
        $quickResponse->assertRedirect(route('checkout.confirmation', $quickOrder->order_number))
            ->assertSessionHas('whatsapp_order_url');
        $this->assertSame(OrderStatus::PendientePago, $quickOrder->status);
        $this->assertSame(PaymentMethod::Whatsapp, $quickOrder->payments()->firstOrFail()->payment_method);

        $variant->unsetRelation('batches');
        $this->assertSame($initialStock - 3, $variant->availableStock());

        // 3. Validation fails cleanly when customer_name / customer_phone missing as guest
        auth()->logout();
        $cart->addVariant($variant, 1);
        $this->post(route('checkout.store'), [
            'whatsapp_quick' => '1',
        ])->assertSessionHasErrors(['customer_name', 'customer_phone']);

        // 4. When whatsapp_checkout_enabled = false, UI hides WA checkout and POST rejects both whatsapp & whatsapp_quick=1
        app(SettingService::class)->set('whatsapp_checkout_enabled', false);

        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertDontSee('Enviar Pedido por WhatsApp');

        $stockBeforeBlocked = $variant->availableStock();

        $this->post(route('checkout.store'), [
            'customer_name' => 'Cliente Bloqueado',
            'customer_phone' => '917328085',
            'payment_method' => PaymentMethod::Whatsapp->value,
        ])->assertSessionHasErrors('payment_method');

        $this->post(route('checkout.store'), [
            'customer_name' => 'Cliente Quick Bloqueado',
            'customer_phone' => '917328085',
            'whatsapp_quick' => '1',
        ])->assertSessionHasErrors('payment_method');

        $variant->unsetRelation('batches');
        $this->assertSame($stockBeforeBlocked, $variant->availableStock());
        $this->assertDatabaseMissing('orders', ['customer_name' => 'Cliente Bloqueado']);
        $this->assertDatabaseMissing('orders', ['customer_name' => 'Cliente Quick Bloqueado']);
    }

    public function test_product_reviews_verified_only_on_entregado_html_cleaned_unique_and_moderation(): void
    {
        $cliente = User::query()->where('email', 'cliente@glamora.pe')->firstOrFail();
        $b2bUser = User::query()->where('email', 'b2b@glamora.pe')->firstOrFail();
        $admin = User::query()->where('email', 'admin@glamora.pe')->firstOrFail();

        // Product purchased by cliente in GLM-2026-0001 (Entregado), not yet reviewed
        $deliveredProduct = Product::query()->where('slug', 'labial-liquido-velvet-matte')->firstOrFail();

        // Product purchased by b2bUser in GLM-2026-0003 (PagoVerificado, NOT Entregado!)
        $paidNotDeliveredProduct = Product::query()->where('slug', 'pack-cabina-facial-kora-derma-b2b')->firstOrFail();

        // 1. User with PagoVerificado (not Entregado) -> review saved with is_verified_purchase = false, order_id = null
        $this->actingAs($b2bUser)
            ->post(route('product.reviews.store', $paidNotDeliveredProduct->slug), [
                'rating' => 4,
                'title' => '<b>Excelente kit</b><script>alert(1)</script>',
                'comment' => '<p>Muy completo para cabina facial.</p><script>window.__xss=1;</script>',
                'shade_purchased' => '<i>Kit Hidratación</i>',
            ])
            ->assertRedirect(route('product.show', $paidNotDeliveredProduct->slug) . '#resenas')
            ->assertSessionHas('review_success');

        $nonDeliveredReview = ProductReview::query()
            ->where('product_id', $paidNotDeliveredProduct->id)
            ->where('user_id', $b2bUser->id)
            ->firstOrFail();

        $this->assertFalse((bool) $nonDeliveredReview->is_verified_purchase);
        $this->assertNull($nonDeliveredReview->order_id);
        $this->assertSame('pending', $nonDeliveredReview->status);
        $this->assertSame('Excelente kit', $nonDeliveredReview->title);
        $this->assertSame('Muy completo para cabina facial.', $nonDeliveredReview->comment);
        $this->assertSame('Kit Hidratación', $nonDeliveredReview->shade_purchased);

        // 2. Pure HTML/script payload fails validation cleanly after HTML cleaning
        $this->actingAs($cliente)
            ->post(route('product.reviews.store', $deliveredProduct->slug), [
                'rating' => 5,
                'comment' => '<script>alert("pure_xss_attack")</script>',
            ])
            ->assertSessionHasErrors('comment');
        $this->assertDatabaseMissing('product_reviews', [
            'product_id' => $deliveredProduct->id,
            'user_id' => $cliente->id,
        ]);

        // 3. User with Entregado purchase -> is_verified_purchase = true and order_id linked
        $this->actingAs($cliente)
            ->post(route('product.reviews.store', $deliveredProduct->slug), [
                'rating' => 5,
                'title' => 'Tono <img src=x onerror=alert(1)> espectacular',
                'comment' => 'Dura todo el día sin resecar los labios. <script>alert(2)</script>',
            ])
            ->assertRedirect(route('product.show', $deliveredProduct->slug) . '#resenas')
            ->assertSessionHas('review_success');

        $deliveredReview = ProductReview::query()
            ->where('product_id', $deliveredProduct->id)
            ->where('user_id', $cliente->id)
            ->firstOrFail();

        $this->assertTrue((bool) $deliveredReview->is_verified_purchase);
        $this->assertNotNull($deliveredReview->order_id);
        $this->assertSame('Tono  espectacular', $deliveredReview->title);
        $this->assertSame('Dura todo el día sin resecar los labios.', $deliveredReview->comment);

        // 4. Duplicate review on same product by same user is blocked (1 per product/user)
        $this->actingAs($cliente)
            ->post(route('product.reviews.store', $deliveredProduct->slug), [
                'rating' => 3,
                'comment' => 'Intento duplicado de reseña en el mismo producto.',
            ])
            ->assertRedirect(route('product.show', $deliveredProduct->slug) . '#resenas')
            ->assertSessionHasErrors('comment');

        $this->assertSame(
            1,
            ProductReview::query()->where('product_id', $deliveredProduct->id)->where('user_id', $cliente->id)->count()
        );

        // 5. Admin moderation approves review with XSS-safe notes and renders cleanly on product & admin views
        $this->actingAs($admin)
            ->patch(route('admin.reviews.moderate', $deliveredReview), [
                'status' => 'approved',
                'moderation_notes' => 'Verificada <script>alert("mod")</script> correctamente',
            ])
            ->assertRedirect(route('admin.reviews.index'));

        $deliveredReview->refresh();
        $this->assertSame('approved', $deliveredReview->status);
        $this->assertSame('Verificada  correctamente', $deliveredReview->moderation_notes);

        // Even if a malicious user name or raw DB value contains HTML, Blade escapes it safely
        $cliente->update(['name' => 'Sofía <script>alert("name")</script> Navarro']);
        $this->get(route('product.show', $deliveredProduct->slug))
            ->assertOk()
            ->assertSee('Dura todo el día sin resecar los labios.')
            ->assertSee('Compra Verificada')
            ->assertDontSee('<script>alert("name")</script>', false);

        $this->actingAs($admin)
            ->get(route('admin.reviews.index'))
            ->assertOk()
            ->assertDontSee('<script>alert("name")</script>', false)
            ->assertSee('&lt;script&gt;alert(&quot;name&quot;)&lt;/script&gt;', false);
    }

    public function test_cross_sell_service_and_live_search_work_cleanly_without_errors(): void
    {
        $crossSell = app(CrossSellService::class);
        $bestsellers = $crossSell->bestsellerIds(6);
        $this->assertNotEmpty($bestsellers);

        $product = Product::query()->with(['variants.batches', 'category'])->where('slug', 'labial-liquido-velvet-matte')->firstOrFail();
        $routine = $crossSell->completeRoutineFor($product, 4);
        $this->assertGreaterThan(0, $routine->count());
        $this->assertFalse($routine->contains('id', $product->id));

        $badges = $crossSell->badgesFor($routine->concat([$product]), $bestsellers);
        $this->assertArrayHasKey($product->id, $badges);
        $this->assertArrayHasKey('badges', $badges[$product->id]);

        $variant = $product->variants()->active()->firstOrFail();
        $cartRecs = $crossSell->recommendationsForCart([
            ['product_variant_id' => $variant->id, 'product_id' => $product->id, 'quantity' => 1],
        ], 3);
        $this->assertGreaterThan(0, $cartRecs->count());
        $this->assertFalse($cartRecs->contains('id', $product->id));

        // LiveSearch component search & quickAdd
        $vitC = ProductVariant::query()->where('sku', 'GLM-LB-VITC-30')->firstOrFail();
        Livewire::test(LiveSearch::class)
            ->set('query', 'Vitamina')
            ->assertSet('open', true)
            ->assertSee('Vitamina C')
            ->call('quickAdd', $vitC->id)
            ->assertDispatched('cart-updated', openDrawer: false);

        $this->assertSame(1, app(CartSessionService::class)->summary()['items_count']);
    }
}
