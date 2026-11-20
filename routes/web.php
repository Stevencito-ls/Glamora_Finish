<?php

declare(strict_types=1);

use App\Http\Controllers\Account\AccountController;
use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BannerController as AdminBannerController;
use App\Http\Controllers\Admin\BrandController as AdminBrandController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\FulfillmentController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\MarketingController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ReviewModerationController;
use App\Http\Controllers\Admin\RmaController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\WholesaleAdminController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Storefront\BlogController;
use App\Http\Controllers\Storefront\BrandController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CatalogController;
use App\Http\Controllers\Storefront\CatalogPdfController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\NewsletterController;
use App\Http\Controllers\Storefront\ProductController;
use App\Http\Controllers\Storefront\ProductReviewController;
use App\Http\Controllers\Storefront\SitemapController;
use App\Http\Controllers\Storefront\TrackingController;
use App\Http\Controllers\Storefront\WholesaleController;
use Illuminate\Support\Facades\Route;

Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/catalogo', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('/catalogo/pdf', [CatalogPdfController::class, 'download'])->name('catalog.pdf');
Route::get('/catalogo/{categoria}', [CatalogController::class, 'index'])->name('catalog.category');

Route::get('/producto/{slug}', [ProductController::class, 'show'])->name('product.show');
Route::post('/producto/{slug}/resenas', [ProductReviewController::class, 'store'])
    ->middleware(['auth', 'active'])
    ->name('product.reviews.store');

Route::get('/marcas', [BrandController::class, 'index'])->name('brands.index');

Route::get('/mayoristas', [WholesaleController::class, 'index'])->name('wholesale.index');
Route::post('/mayoristas/solicitud', [WholesaleController::class, 'storeApplication'])->name('wholesale.apply');

Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');

Route::get('/seguimiento', [TrackingController::class, 'index'])->name('tracking.index');
Route::post('/seguimiento/rma', [TrackingController::class, 'storeReturn'])->name('tracking.rma');

Route::post('/newsletter/suscribir', [NewsletterController::class, 'subscribe'])->name('newsletter.subscribe');

// Mi Bolsa de Compras & Cotización PDF
Route::get('/bolsa', [CartController::class, 'index'])->name('cart.index');
Route::post('/bolsa/agregar', [CartController::class, 'add'])->name('cart.add');
Route::patch('/bolsa/actualizar', [CartController::class, 'update'])->name('cart.update');
Route::delete('/bolsa/item/{variantId}', [CartController::class, 'remove'])
    ->whereNumber('variantId')
    ->name('cart.remove');
Route::post('/bolsa/cupon', [CartController::class, 'applyCoupon'])->name('cart.coupon.apply');
Route::delete('/bolsa/cupon', [CartController::class, 'removeCoupon'])->name('cart.coupon.remove');
Route::get('/bolsa/cotizacion-pdf', [CartController::class, 'quotePdf'])->name('cart.quote-pdf');

Route::redirect('/carrito', '/bolsa', 301);
Route::redirect('/carrito/cotizacion-pdf', '/bolsa/cotizacion-pdf', 301);

// Checkout Multi-Método
Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::get('/checkout/confirmacion/{orderNumber}', [CheckoutController::class, 'confirmation'])->name('checkout.confirmation');
Route::get('/checkout/confirmacion/{orderNumber}/pdf', [CheckoutController::class, 'confirmationPdf'])->name('checkout.confirmation.pdf');

// Autenticación & 2FA TOTP
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.store');

    Route::get('/registro', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/registro', [RegisterController::class, 'register'])->name('register.store');

    Route::get('/2fa', [TwoFactorChallengeController::class, 'showChallenge'])->name('two-factor.challenge');
    Route::post('/2fa', [TwoFactorChallengeController::class, 'verify'])->name('two-factor.verify');
});

Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// Portal Privado del Cliente
Route::middleware(['auth', 'active'])
    ->prefix('mi-cuenta')
    ->name('account.')
    ->group(function (): void {
        Route::get('/', [AccountController::class, 'index'])->name('index');

        Route::get('/pedidos', [AccountController::class, 'orders'])->name('orders');
        Route::get('/pedidos/{orderNumber}', [AccountController::class, 'showOrder'])->name('orders.show');
        Route::get('/pedidos/{orderNumber}/pdf', [AccountController::class, 'orderPdf'])->name('orders.pdf');

        Route::get('/direcciones', [AccountController::class, 'addresses'])->name('addresses');
        Route::post('/direcciones', [AccountController::class, 'storeAddress'])->name('addresses.store');
        Route::patch('/direcciones/{address}/predeterminada', [AccountController::class, 'setDefaultAddress'])->name('addresses.default');
        Route::delete('/direcciones/{address}', [AccountController::class, 'destroyAddress'])->name('addresses.destroy');

        Route::get('/devoluciones', [AccountController::class, 'returns'])->name('returns');
        Route::post('/devoluciones', [AccountController::class, 'storeReturn'])->name('returns.store');

        Route::get('/seguridad', [AccountController::class, 'security'])->name('security');
        Route::patch('/seguridad/perfil', [AccountController::class, 'updateProfile'])->name('security.profile');
        Route::patch('/seguridad/password', [AccountController::class, 'updatePassword'])->name('security.password');
        Route::post('/seguridad/2fa/activar', [AccountController::class, 'enableTwoFactor'])->name('security.2fa.enable');
        Route::post('/seguridad/2fa/confirmar', [AccountController::class, 'confirmTwoFactor'])->name('security.2fa.confirm');
        Route::post('/seguridad/2fa/desactivar', [AccountController::class, 'disableTwoFactor'])->name('security.2fa.disable');
    });

// Backoffice Administrativo Glamora (Staff activo)
Route::middleware(['auth', 'active', 'role:SuperAdmin|Administrador|Almacenero|Vendedor B2B'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        // Catálogo PIM
        Route::prefix('pim')->name('pim.')->group(function (): void {
            // Categorías
            Route::prefix('categorias')->name('categories.')->group(function (): void {
                Route::get('/', [AdminCategoryController::class, 'index'])
                    ->middleware('permission:catalog.view')
                    ->name('index');
                Route::get('/crear', [AdminCategoryController::class, 'create'])
                    ->middleware('permission:catalog.create')
                    ->name('create');
                Route::post('/', [AdminCategoryController::class, 'store'])
                    ->middleware('permission:catalog.create')
                    ->name('store');
                Route::get('/{category}/editar', [AdminCategoryController::class, 'edit'])
                    ->middleware('permission:catalog.edit')
                    ->name('edit');
                Route::put('/{category}', [AdminCategoryController::class, 'update'])
                    ->middleware('permission:catalog.edit')
                    ->name('update');
                Route::delete('/{category}', [AdminCategoryController::class, 'destroy'])
                    ->middleware('permission:catalog.delete')
                    ->name('destroy');
            });

            // Marcas
            Route::prefix('marcas')->name('brands.')->group(function (): void {
                Route::get('/', [AdminBrandController::class, 'index'])
                    ->middleware('permission:catalog.view')
                    ->name('index');
                Route::get('/crear', [AdminBrandController::class, 'create'])
                    ->middleware('permission:catalog.create')
                    ->name('create');
                Route::post('/', [AdminBrandController::class, 'store'])
                    ->middleware('permission:catalog.create')
                    ->name('store');
                Route::get('/{brand}/editar', [AdminBrandController::class, 'edit'])
                    ->middleware('permission:catalog.edit')
                    ->name('edit');
                Route::put('/{brand}', [AdminBrandController::class, 'update'])
                    ->middleware('permission:catalog.edit')
                    ->name('update');
                Route::delete('/{brand}', [AdminBrandController::class, 'destroy'])
                    ->middleware('permission:catalog.delete')
                    ->name('destroy');
            });

            // Productos, Variantes y Precios Escalonados
            Route::get('/catalogo-pdf', [AdminProductController::class, 'catalogPdf'])
                ->middleware('permission:catalog.view')
                ->name('catalog-pdf');

            Route::prefix('productos')->name('products.')->group(function (): void {
                Route::get('/', [AdminProductController::class, 'index'])
                    ->middleware('permission:catalog.view')
                    ->name('index');
                Route::get('/crear', [AdminProductController::class, 'create'])
                    ->middleware('permission:catalog.create')
                    ->name('create');
                Route::post('/', [AdminProductController::class, 'store'])
                    ->middleware('permission:catalog.create')
                    ->name('store');
                Route::get('/{product}/editar', [AdminProductController::class, 'edit'])
                    ->middleware('permission:catalog.view')
                    ->name('edit');
                Route::put('/{product}', [AdminProductController::class, 'update'])
                    ->middleware('permission:catalog.edit')
                    ->name('update');
                Route::delete('/{product}', [AdminProductController::class, 'destroy'])
                    ->middleware('permission:catalog.delete')
                    ->name('destroy');
                Route::post('/{productId}/restaurar', [AdminProductController::class, 'restore'])
                    ->whereNumber('productId')
                    ->middleware('permission:catalog.delete')
                    ->name('restore');

                // Variantes
                Route::post('/{product}/variantes', [AdminProductController::class, 'storeVariant'])
                    ->middleware('permission:catalog.edit')
                    ->name('variants.store');
                Route::put('/{product}/variantes/{variant}', [AdminProductController::class, 'updateVariant'])
                    ->middleware('permission:catalog.edit')
                    ->name('variants.update');
                Route::delete('/{product}/variantes/{variant}', [AdminProductController::class, 'destroyVariant'])
                    ->middleware('permission:catalog.edit')
                    ->name('variants.destroy');

                // Precios Escalonados
                Route::post('/{product}/variantes/{variant}/precios-escalonados', [AdminProductController::class, 'storeTieredPrice'])
                    ->middleware('permission:catalog.edit')
                    ->name('tiered-prices.store');
                Route::delete('/{product}/variantes/{variant}/precios-escalonados/{tieredPrice}', [AdminProductController::class, 'destroyTieredPrice'])
                    ->middleware('permission:catalog.edit')
                    ->name('tiered-prices.destroy');
            });
        });

        // Inventario FEFO, Lotes, Ubicaciones y Kardex
        Route::prefix('inventario')->name('inventory.')->group(function (): void {
            Route::get('/', [InventoryController::class, 'index'])
                ->middleware('permission:inventory.view')
                ->name('index');
            Route::get('/kardex', [InventoryController::class, 'kardex'])
                ->middleware('permission:inventory.view')
                ->name('kardex');
            Route::post('/lotes', [InventoryController::class, 'storeBatch'])
                ->middleware('permission:inventory.batches.manage')
                ->name('batches.store');
            Route::post('/lotes/{batch}/ajustar', [InventoryController::class, 'adjustBatch'])
                ->middleware('permission:inventory.adjust')
                ->name('batches.adjust');

            Route::prefix('ubicaciones')->name('locations.')->middleware('permission:inventory.locations.manage')->group(function (): void {
                Route::get('/', [InventoryController::class, 'locationsIndex'])->name('index');
                Route::post('/', [InventoryController::class, 'storeLocation'])->name('store');
                Route::put('/{location}', [InventoryController::class, 'updateLocation'])->name('update');
                Route::delete('/{location}', [InventoryController::class, 'destroyLocation'])->name('destroy');
            });
        });

        // Fulfillment FEFO, Conciliación de Pagos y Guía PDF
        Route::prefix('fulfillment')->name('fulfillment.')->group(function (): void {
            Route::get('/', [FulfillmentController::class, 'index'])
                ->middleware('permission:orders.view')
                ->name('index');
            Route::get('/pedidos/{order}', [FulfillmentController::class, 'show'])
                ->middleware('permission:orders.view')
                ->name('orders.show');
            Route::patch('/pedidos/{order}/estado', [FulfillmentController::class, 'transitionStatus'])
                ->middleware('permission:orders.update_status')
                ->name('orders.transition');
            Route::get('/pedidos/{order}/guia-pdf', [FulfillmentController::class, 'dispatchGuidePdf'])
                ->middleware('permission:orders.view')
                ->name('orders.dispatch-guide-pdf');

            Route::get('/pagos/{payment}/voucher', [FulfillmentController::class, 'voucher'])
                ->middleware('permission:payments.view')
                ->name('payments.voucher');
            Route::post('/pagos/{payment}/verificar', [FulfillmentController::class, 'verifyPayment'])
                ->middleware('permission:payments.verify')
                ->name('payments.verify');
            Route::post('/pagos/{payment}/rechazar', [FulfillmentController::class, 'rejectPayment'])
                ->middleware('permission:payments.verify')
                ->name('payments.reject');
        });

        // Devoluciones RMA & Reintegro FEFO
        Route::prefix('rma')->name('rma.')->group(function (): void {
            Route::get('/', [RmaController::class, 'index'])
                ->middleware('permission:returns.view')
                ->name('index');
            Route::get('/{orderReturn}', [RmaController::class, 'show'])
                ->middleware('permission:returns.view')
                ->name('show');
            Route::post('/{orderReturn}/inspeccionar', [RmaController::class, 'inspect'])
                ->middleware('permission:returns.inspect')
                ->name('inspect');
            Route::post('/{orderReturn}/resolver', [RmaController::class, 'resolve'])
                ->middleware('permission:returns.resolve')
                ->name('resolve');
        });

        // Cuentas Mayoristas B2B & Crédito
        Route::prefix('mayoristas')->name('wholesale.')->group(function (): void {
            Route::get('/', [WholesaleAdminController::class, 'index'])
                ->middleware('permission:wholesale.view')
                ->name('index');
            Route::get('/{wholesaleProfile}', [WholesaleAdminController::class, 'show'])
                ->middleware('permission:wholesale.view')
                ->name('show');
            Route::post('/{wholesaleProfile}/aprobar', [WholesaleAdminController::class, 'approve'])
                ->middleware('permission:wholesale.approve')
                ->name('approve');
            Route::post('/{wholesaleProfile}/rechazar', [WholesaleAdminController::class, 'reject'])
                ->middleware('permission:wholesale.approve')
                ->name('reject');
            Route::patch('/{wholesaleProfile}/credito', [WholesaleAdminController::class, 'updateCredit'])
                ->middleware('permission:wholesale.manage_credit')
                ->name('credit.update');
        });

        // Usuarios & Roles Spatie
        Route::prefix('usuarios')->name('users.')->middleware('permission:users.manage')->group(function (): void {
            Route::get('/', [UserManagementController::class, 'index'])->name('index');
            Route::get('/crear', [UserManagementController::class, 'create'])->name('create');
            Route::post('/', [UserManagementController::class, 'store'])->name('store');
            Route::get('/{user}/editar', [UserManagementController::class, 'edit'])->name('edit');
            Route::put('/{user}', [UserManagementController::class, 'update'])->name('update');
            Route::patch('/{user}/estado', [UserManagementController::class, 'toggleActive'])->name('toggle-active');
            Route::post('/{user}/reset-2fa', [UserManagementController::class, 'resetTwoFactor'])->name('reset-2fa');
            Route::delete('/{user}', [UserManagementController::class, 'destroy'])->name('destroy');
        });

        // Visor de Auditoría de Seguridad
        Route::get('/auditoria', [AuditLogController::class, 'index'])
            ->middleware('permission:security.logs.view')
            ->name('audit.index');

        // Banners y Multimedia Editorial (Home, Promociones & Portal Mayorista)
        Route::middleware('permission:catalog.edit|marketing.coupons|marketing.blog')->group(function (): void {
            Route::get('/multimedia', [AdminBannerController::class, 'index'])->name('multimedia.index');

            Route::prefix('banners')->name('banners.')->group(function (): void {
                Route::get('/', [AdminBannerController::class, 'index'])->name('index');
                Route::get('/crear', [AdminBannerController::class, 'create'])->name('create');
                Route::post('/', [AdminBannerController::class, 'store'])->name('store');
                Route::get('/{banner}/editar', [AdminBannerController::class, 'edit'])->name('edit');
                Route::put('/{banner}', [AdminBannerController::class, 'update'])->name('update');
                Route::patch('/{banner}/estado', [AdminBannerController::class, 'toggle'])->name('toggle');
                Route::delete('/{banner}', [AdminBannerController::class, 'destroy'])->name('destroy');
            });
        });

        // Marketing & Retención (Cupones, Carritos Abandonados, Newsletter & Blog)
        Route::prefix('marketing')->name('marketing.')->group(function (): void {
            Route::get('/', [MarketingController::class, 'index'])
                ->middleware('permission:marketing.coupons|marketing.abandoned_carts|marketing.newsletter|marketing.blog')
                ->name('index');

            // Cupones Promocionales
            Route::prefix('cupones')
                ->name('coupons.')
                ->middleware('permission:marketing.coupons')
                ->group(function (): void {
                    Route::get('/', [MarketingController::class, 'couponsIndex'])->name('index');
                    Route::get('/crear', [MarketingController::class, 'couponsCreate'])->name('create');
                    Route::post('/', [MarketingController::class, 'couponsStore'])->name('store');
                    Route::get('/{coupon}/editar', [MarketingController::class, 'couponsEdit'])->name('edit');
                    Route::put('/{coupon}', [MarketingController::class, 'couponsUpdate'])->name('update');
                    Route::patch('/{coupon}/estado', [MarketingController::class, 'couponsToggle'])->name('toggle');
                    Route::delete('/{coupon}', [MarketingController::class, 'couponsDestroy'])->name('destroy');
                });

            // Carritos Abandonados
            Route::prefix('carritos-abandonados')
                ->name('abandoned-carts.')
                ->middleware('permission:marketing.abandoned_carts')
                ->group(function (): void {
                    Route::get('/', [MarketingController::class, 'abandonedCartsIndex'])->name('index');
                    Route::post('/despachar-elegibles', [MarketingController::class, 'dispatchEligibleReminders'])->name('dispatch-eligible');
                    Route::post('/{abandonedCart}/recordatorio', [MarketingController::class, 'sendCartReminder'])->name('remind');
                });

            // Newsletter & Suscriptores
            Route::prefix('newsletter')
                ->name('newsletter.')
                ->middleware('permission:marketing.newsletter')
                ->group(function (): void {
                    Route::get('/', [MarketingController::class, 'newsletterIndex'])->name('index');
                    Route::patch('/{subscriber}/estado', [MarketingController::class, 'newsletterToggleActive'])->name('toggle-active');
                    Route::patch('/{subscriber}/verificacion', [MarketingController::class, 'newsletterToggleVerified'])->name('toggle-verified');
                });

            // Blog Editorial & Shop the Story
            Route::prefix('blog')
                ->name('blog.')
                ->middleware('permission:marketing.blog')
                ->group(function (): void {
                    Route::get('/', [MarketingController::class, 'blogIndex'])->name('index');
                    Route::get('/crear', [MarketingController::class, 'blogCreate'])->name('create');
                    Route::post('/', [MarketingController::class, 'blogStore'])->name('store');
                    Route::get('/{post}/editar', [MarketingController::class, 'blogEdit'])->name('edit');
                    Route::put('/{post}', [MarketingController::class, 'blogUpdate'])->name('update');
                    Route::patch('/{post}/publicacion', [MarketingController::class, 'blogTogglePublish'])->name('toggle-publish');
                    Route::delete('/{post}', [MarketingController::class, 'blogDestroy'])->name('destroy');
                });
        });

        // Configuración White-Label, Tema & Onboarding
        Route::prefix('configuracion')
            ->name('settings.')
            ->middleware('permission:settings.manage')
            ->group(function (): void {
                Route::get('/', [SettingController::class, 'index'])->name('index');
                Route::put('/', [SettingController::class, 'update'])->name('update');
                Route::post('/onboarding-dismiss', [SettingController::class, 'dismissOnboarding'])->name('onboarding-dismiss');
            });

        // Anuncios Dinámicos del Topbar
        Route::prefix('anuncios')
            ->name('announcements.')
            ->middleware('permission:announcements.manage')
            ->group(function (): void {
                Route::get('/', [AnnouncementController::class, 'index'])->name('index');
                Route::post('/', [AnnouncementController::class, 'store'])->name('store');
                Route::put('/{announcement}', [AnnouncementController::class, 'update'])->name('update');
                Route::patch('/{announcement}/estado', [AnnouncementController::class, 'toggle'])->name('toggle');
                Route::delete('/{announcement}', [AnnouncementController::class, 'destroy'])->name('destroy');
            });

        // Moderación de Reseñas de Clientes
        Route::prefix('resenas')
            ->name('reviews.')
            ->middleware('permission:reviews.moderate')
            ->group(function (): void {
                Route::get('/', [ReviewModerationController::class, 'index'])->name('index');
                Route::patch('/{review}/moderar', [ReviewModerationController::class, 'moderate'])->name('moderate');
                Route::delete('/{review}', [ReviewModerationController::class, 'destroy'])->name('destroy');
            });
    });
