<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\CustomerTier;
use App\Events\SecurityActionTriggered;
use App\Http\Controllers\Controller;
use App\Models\AbandonedCart;
use App\Models\Coupon;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\Post;
use App\Http\Controllers\Admin\Concerns\HandlesSafeMediaUploads;
use App\Models\Product;
use App\Rules\SafeRasterImage;
use App\Services\AbandonedCartRecoveryService;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MarketingController extends Controller
{
    use HandlesSafeMediaUploads;
    public function index(AbandonedCartRecoveryService $recoveryService): View
    {
        $now = now();

        $couponsStats = [
            'total' => Coupon::query()->count(),
            'active' => Coupon::query()->active()->count(),
            'valid_now' => Coupon::query()->validNow()->count(),
            'total_uses' => (int) Coupon::query()->sum('used_count'),
        ];

        $abandonedStats = [
            'unrecovered' => AbandonedCart::query()->unrecovered()->count(),
            'eligible' => $recoveryService->eligibleCount(),
            'recovered' => AbandonedCart::query()->whereNotNull('recovered_at')->count(),
            'potential_revenue' => round((float) AbandonedCart::query()->unrecovered()->sum('subtotal'), 2),
        ];

        $newsletterStats = [
            'total' => NewsletterSubscriber::query()->count(),
            'active' => NewsletterSubscriber::query()->active()->count(),
            'verified' => NewsletterSubscriber::query()->active()->verified()->count(),
            'by_segment' => NewsletterSubscriber::query()
                ->reorder()
                ->groupBy('segment')
                ->selectRaw('segment, COUNT(*) as total')
                ->pluck('total', 'segment'),
        ];

        $blogStats = [
            'total' => Post::query()->count(),
            'published' => Post::query()
                ->where('is_published', true)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', $now)
                ->count(),
            'drafts' => Post::query()->where('is_published', false)->count(),
        ];

        return view('admin.marketing.index', [
            'couponsStats' => $couponsStats,
            'abandonedStats' => $abandonedStats,
            'newsletterStats' => $newsletterStats,
            'blogStats' => $blogStats,
        ]);
    }

    // =========================================================================
    // CUPONES PROMOCIONALES
    // =========================================================================

    public function couponsIndex(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $statusFilter = (string) $request->query('status', '');
        $tierFilter = (string) $request->query('tier', '');
        $now = now();

        $coupons = Coupon::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($sub) use ($search): void {
                    $sub->where('code', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($statusFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($statusFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($statusFilter === 'valid', fn ($q) => $q->validNow())
            ->when($statusFilter === 'expired', fn ($q) => $q->whereNotNull('expires_at')->where('expires_at', '<', $now))
            ->when($tierFilter === 'all', fn ($q) => $q->whereNull('applicable_tier'))
            ->when($tierFilter !== '' && in_array($tierFilter, CustomerTier::values(), true), fn ($q) => $q->where('applicable_tier', $tierFilter))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $referencedCodes = Order::query()
            ->whereNotNull('coupon_code')
            ->reorder()
            ->distinct()
            ->pluck('coupon_code')
            ->map(static fn (mixed $code): string => strtoupper(trim((string) $code)))
            ->filter(static fn (string $code): bool => $code !== '')
            ->values()
            ->all();

        $lockedCouponIds = $coupons->getCollection()
            ->filter(fn (Coupon $c): bool => (int) $c->used_count > 0 || in_array(strtoupper((string) $c->code), $referencedCodes, true))
            ->pluck('id')
            ->all();

        return view('admin.marketing.coupons.index', [
            'coupons' => $coupons,
            'lockedCouponIds' => $lockedCouponIds,
            'customerTiers' => CustomerTier::cases(),
            'search' => $search,
            'statusFilter' => $statusFilter,
            'tierFilter' => $tierFilter,
            'stats' => [
                'total' => Coupon::query()->count(),
                'valid_now' => Coupon::query()->validNow()->count(),
                'total_uses' => (int) Coupon::query()->sum('used_count'),
            ],
        ]);
    }

    public function couponsCreate(): View
    {
        return view('admin.marketing.coupons.form', [
            'coupon' => new Coupon([
                'discount_type' => 'percentage',
                'min_order_amount' => 0,
                'is_active' => true,
            ]),
            'customerTiers' => CustomerTier::cases(),
            'isEdit' => false,
            'isLocked' => false,
        ]);
    }

    public function couponsStore(Request $request): RedirectResponse
    {
        $validated = $this->validateCoupon($request);

        /** @var Coupon $coupon */
        $coupon = DB::transaction(function () use ($validated, $request): Coupon {
            $coupon = Coupon::query()->create([
                'code' => strtoupper(trim((string) $validated['code'])),
                'description' => isset($validated['description']) && trim((string) $validated['description']) !== ''
                    ? trim((string) $validated['description'])
                    : null,
                'discount_type' => $validated['discount_type'],
                'discount_value' => round((float) $validated['discount_value'], 2),
                'min_order_amount' => round((float) ($validated['min_order_amount'] ?? 0), 2),
                'max_uses' => isset($validated['max_uses']) && $validated['max_uses'] !== '' ? (int) $validated['max_uses'] : null,
                'used_count' => 0,
                'applicable_tier' => ! empty($validated['applicable_tier']) ? $validated['applicable_tier'] : null,
                'starts_at' => ! empty($validated['starts_at']) ? Carbon::parse((string) $validated['starts_at']) : null,
                'expires_at' => ! empty($validated['expires_at']) ? Carbon::parse((string) $validated['expires_at']) : null,
                'is_active' => $request->boolean('is_active', true),
            ]);

            SecurityActionTriggered::dispatch(
                Auth::user(),
                'marketing_coupon_created',
                'info',
                "Cupón promocional «{$coupon->code}» creado en Marketing.",
                [
                    'coupon_id' => $coupon->id,
                    'code' => $coupon->code,
                    'discount_type' => $coupon->discount_type,
                    'discount_value' => (float) $coupon->discount_value,
                ],
            );

            return $coupon;
        });

        return redirect()
            ->route('admin.marketing.coupons.index')
            ->with('admin_success', "Cupón «{$coupon->code}» creado correctamente.");
    }

    public function couponsEdit(Coupon $coupon): View
    {
        return view('admin.marketing.coupons.form', [
            'coupon' => $coupon,
            'customerTiers' => CustomerTier::cases(),
            'isEdit' => true,
            'isLocked' => $this->couponHasUsage($coupon),
        ]);
    }

    public function couponsUpdate(Request $request, Coupon $coupon): RedirectResponse
    {
        $lockedPrecheck = $this->couponHasUsage($coupon);
        if ($lockedPrecheck) {
            return redirect()
                ->route('admin.marketing.coupons.index')
                ->with('admin_error', "El cupón «{$coupon->code}» ya registra usos o pedidos asociados y no puede editarse. Puedes activarlo o desactivarlo.");
        }

        $validated = $this->validateCoupon($request, $coupon);

        $updated = DB::transaction(function () use ($coupon, $validated, $request): bool {
            /** @var Coupon $lockedCoupon */
            $lockedCoupon = Coupon::query()->lockForUpdate()->findOrFail($coupon->id);

            if ($this->couponHasUsage($lockedCoupon)) {
                return false;
            }

            $lockedCoupon->update([
                'code' => strtoupper(trim((string) $validated['code'])),
                'description' => isset($validated['description']) && trim((string) $validated['description']) !== ''
                    ? trim((string) $validated['description'])
                    : null,
                'discount_type' => $validated['discount_type'],
                'discount_value' => round((float) $validated['discount_value'], 2),
                'min_order_amount' => round((float) ($validated['min_order_amount'] ?? 0), 2),
                'max_uses' => isset($validated['max_uses']) && $validated['max_uses'] !== '' ? (int) $validated['max_uses'] : null,
                'applicable_tier' => ! empty($validated['applicable_tier']) ? $validated['applicable_tier'] : null,
                'starts_at' => ! empty($validated['starts_at']) ? Carbon::parse((string) $validated['starts_at']) : null,
                'expires_at' => ! empty($validated['expires_at']) ? Carbon::parse((string) $validated['expires_at']) : null,
                'is_active' => $request->boolean('is_active'),
            ]);

            SecurityActionTriggered::dispatch(
                Auth::user(),
                'marketing_coupon_updated',
                'info',
                "Cupón promocional «{$lockedCoupon->code}» actualizado.",
                [
                    'coupon_id' => $lockedCoupon->id,
                    'code' => $lockedCoupon->code,
                    'discount_type' => $lockedCoupon->discount_type,
                    'discount_value' => (float) $lockedCoupon->discount_value,
                ],
            );

            return true;
        });

        if (! $updated) {
            return redirect()
                ->route('admin.marketing.coupons.index')
                ->with('admin_error', "El cupón «{$coupon->code}» ya registra usos o pedidos asociados y no puede editarse.");
        }

        $coupon->refresh();

        return redirect()
            ->route('admin.marketing.coupons.index')
            ->with('admin_success', "Cupón «{$coupon->code}» actualizado correctamente.");
    }

    public function couponsToggle(Coupon $coupon): RedirectResponse
    {
        $newState = DB::transaction(function () use ($coupon): bool {
            /** @var Coupon $lockedCoupon */
            $lockedCoupon = Coupon::query()->lockForUpdate()->findOrFail($coupon->id);
            $target = ! (bool) $lockedCoupon->is_active;

            $lockedCoupon->update(['is_active' => $target]);

            SecurityActionTriggered::dispatch(
                Auth::user(),
                'marketing_coupon_toggled',
                'info',
                "Cupón «{$lockedCoupon->code}» " . ($target ? 'activado' : 'desactivado') . '.',
                ['coupon_id' => $lockedCoupon->id, 'code' => $lockedCoupon->code, 'is_active' => $target],
            );

            return $target;
        });

        return redirect()
            ->route('admin.marketing.coupons.index')
            ->with('admin_success', "Cupón «{$coupon->code}» " . ($newState ? 'activado' : 'desactivado') . ' correctamente.');
    }

    public function couponsDestroy(Coupon $coupon): RedirectResponse
    {
        $result = DB::transaction(function () use ($coupon): array {
            /** @var Coupon $lockedCoupon */
            $lockedCoupon = Coupon::query()->lockForUpdate()->findOrFail($coupon->id);
            $code = $lockedCoupon->code;
            $id = $lockedCoupon->id;

            if ($this->couponHasUsage($lockedCoupon)) {
                return ['deleted' => false, 'code' => $code];
            }

            $lockedCoupon->delete();

            SecurityActionTriggered::dispatch(
                Auth::user(),
                'marketing_coupon_deleted',
                'warning',
                "Cupón promocional «{$code}» eliminado.",
                ['coupon_id' => $id, 'code' => $code],
            );

            return ['deleted' => true, 'code' => $code];
        });

        if (! $result['deleted']) {
            return redirect()
                ->route('admin.marketing.coupons.index')
                ->with('admin_error', "No se puede eliminar el cupón «{$result['code']}» porque registra usos o está referenciado en pedidos. Desactívalo en su lugar.");
        }

        return redirect()
            ->route('admin.marketing.coupons.index')
            ->with('admin_success', "Cupón «{$result['code']}» eliminado correctamente.");
    }

    // =========================================================================
    // CARRITOS ABANDONADOS & RECUPERACIÓN
    // =========================================================================

    public function abandonedCartsIndex(Request $request, AbandonedCartRecoveryService $recoveryService): View
    {
        $search = trim((string) $request->query('q', ''));
        $statusFilter = (string) $request->query('status', '');

        $eligibleIds = array_map(
            static fn (int|string $id): int => (int) $id,
            $recoveryService->eligibleQuery()->pluck('id')->all(),
        );

        $carts = AbandonedCart::query()
            ->with('user')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($sub) use ($search): void {
                    $sub->where('email', 'like', "%{$search}%")
                        ->orWhere('session_id', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($statusFilter === 'eligible', fn ($q) => $q->whereIn('id', $eligibleIds !== [] ? $eligibleIds : [0]))
            ->when($statusFilter === 'unrecovered', fn ($q) => $q->unrecovered())
            ->when($statusFilter === 'recovered', fn ($q) => $q->whereNotNull('recovered_at'))
            ->when($statusFilter === 'reminded', fn ($q) => $q->unrecovered()->where('reminder_count', '>', 0))
            ->latest('last_activity_at')
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'unrecovered' => AbandonedCart::query()->unrecovered()->count(),
            'eligible' => count($eligibleIds),
            'recovered' => AbandonedCart::query()->whereNotNull('recovered_at')->count(),
            'potential_revenue' => round((float) AbandonedCart::query()->unrecovered()->sum('subtotal'), 2),
        ];

        return view('admin.marketing.abandoned-carts.index', [
            'carts' => $carts,
            'eligibleIds' => $eligibleIds,
            'stats' => $stats,
            'search' => $search,
            'statusFilter' => $statusFilter,
        ]);
    }

    public function sendCartReminder(AbandonedCart $abandonedCart, AbandonedCartRecoveryService $recoveryService): RedirectResponse
    {
        $queued = $recoveryService->sendSingleReminder($abandonedCart, ignoreCooldown: true);

        if (! $queued) {
            return redirect()
                ->route('admin.marketing.abandoned-carts.index')
                ->with('admin_error', 'No se pudo encolar el recordatorio: el carrito ya fue recuperado, está vacío o no posee un correo válido.');
        }

        $abandonedCart->refresh();

        SecurityActionTriggered::dispatch(
            Auth::user(),
            'marketing_abandoned_cart_reminded',
            'info',
            "Recordatorio manual de carrito abandonado encolado para {$abandonedCart->email}.",
            [
                'abandoned_cart_id' => $abandonedCart->id,
                'email' => $abandonedCart->email,
                'reminder_count' => $abandonedCart->reminder_count,
            ],
        );

        return redirect()
            ->route('admin.marketing.abandoned-carts.index')
            ->with('admin_success', "Recordatorio encolado correctamente para {$abandonedCart->email}.");
    }

    public function dispatchEligibleReminders(Request $request, AbandonedCartRecoveryService $recoveryService): RedirectResponse
    {
        $validated = $request->validate([
            'minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'max_reminders' => ['nullable', 'integer', 'min:1', 'max:20'],
            'cooldown_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
        ]);

        $minutes = (int) ($validated['minutes'] ?? 60);
        $maxReminders = (int) ($validated['max_reminders'] ?? 3);
        $cooldownHours = (int) ($validated['cooldown_hours'] ?? 24);

        $result = $recoveryService->processEligible(
            minutesIdle: $minutes,
            maxReminders: $maxReminders,
            cooldownHours: $cooldownHours,
            dryRun: false,
        );

        if ($result['queued'] === 0) {
            return redirect()
                ->route('admin.marketing.abandoned-carts.index')
                ->with('admin_success', 'No se encontraron carritos abandonados elegibles pendientes de recordatorio.');
        }

        SecurityActionTriggered::dispatch(
            Auth::user(),
            'marketing_abandoned_carts_batch_dispatched',
            'info',
            "Despacho masivo de {$result['queued']} recordatorio(s) de carritos abandonados desde el panel.",
            $result,
        );

        return redirect()
            ->route('admin.marketing.abandoned-carts.index')
            ->with('admin_success', "Se encolaron {$result['queued']} recordatorio(s) de carritos abandonados elegibles.");
    }

    // =========================================================================
    // NEWSLETTER & SUSCRIPTORES
    // =========================================================================

    public function newsletterIndex(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $segmentFilter = trim((string) $request->query('segment', ''));
        $activeFilter = (string) $request->query('active', '');
        $verifiedFilter = (string) $request->query('verified', '');

        $segments = NewsletterSubscriber::query()
            ->reorder()
            ->whereNotNull('segment')
            ->where('segment', '!=', '')
            ->select('segment')
            ->distinct()
            ->orderBy('segment')
            ->pluck('segment');

        $validSegment = $segmentFilter !== '' && $segments->contains($segmentFilter) ? $segmentFilter : '';

        $subscribers = NewsletterSubscriber::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($sub) use ($search): void {
                    $sub->where('email', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when($validSegment !== '', fn ($q) => $q->where('segment', $validSegment))
            ->when($activeFilter === '1', fn ($q) => $q->where('is_active', true))
            ->when($activeFilter === '0', fn ($q) => $q->where('is_active', false))
            ->when($verifiedFilter === '1', fn ($q) => $q->where('is_verified', true))
            ->when($verifiedFilter === '0', fn ($q) => $q->where('is_verified', false))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'total' => NewsletterSubscriber::query()->count(),
            'active' => NewsletterSubscriber::query()->active()->count(),
            'verified' => NewsletterSubscriber::query()->active()->verified()->count(),
            'unsubscribed' => NewsletterSubscriber::query()->where('is_active', false)->count(),
        ];

        return view('admin.marketing.newsletter.index', [
            'subscribers' => $subscribers,
            'segments' => $segments,
            'stats' => $stats,
            'search' => $search,
            'segmentFilter' => $validSegment,
            'activeFilter' => $activeFilter,
            'verifiedFilter' => $verifiedFilter,
        ]);
    }

    public function newsletterToggleActive(NewsletterSubscriber $subscriber): RedirectResponse
    {
        $newState = DB::transaction(function () use ($subscriber): bool {
            /** @var NewsletterSubscriber $locked */
            $locked = NewsletterSubscriber::query()->lockForUpdate()->findOrFail($subscriber->id);
            $target = ! (bool) $locked->is_active;

            $locked->update([
                'is_active' => $target,
                'subscribed_at' => $target ? ($locked->subscribed_at ?? now()) : $locked->subscribed_at,
                'unsubscribed_at' => $target ? null : now(),
            ]);

            SecurityActionTriggered::dispatch(
                Auth::user(),
                'marketing_newsletter_updated',
                'info',
                "Suscriptor «{$locked->email}» " . ($target ? 'reactivado' : 'dado de baja') . '.',
                ['subscriber_id' => $locked->id, 'email' => $locked->email, 'is_active' => $target],
            );

            return $target;
        });

        return redirect()
            ->route('admin.marketing.newsletter.index')
            ->with('admin_success', "Suscriptor «{$subscriber->email}» " . ($newState ? 'reactivado' : 'dado de baja') . ' correctamente.');
    }

    public function newsletterToggleVerified(NewsletterSubscriber $subscriber): RedirectResponse
    {
        $newState = DB::transaction(function () use ($subscriber): bool {
            /** @var NewsletterSubscriber $locked */
            $locked = NewsletterSubscriber::query()->lockForUpdate()->findOrFail($subscriber->id);
            $target = ! (bool) $locked->is_verified;

            $locked->update(['is_verified' => $target]);

            SecurityActionTriggered::dispatch(
                Auth::user(),
                'marketing_newsletter_updated',
                'info',
                "Opt-In del suscriptor «{$locked->email}» marcado como " . ($target ? 'verificado' : 'pendiente') . '.',
                ['subscriber_id' => $locked->id, 'email' => $locked->email, 'is_verified' => $target],
            );

            return $target;
        });

        return redirect()
            ->route('admin.marketing.newsletter.index')
            ->with('admin_success', "Suscriptor «{$subscriber->email}» marcado como " . ($newState ? 'Opt-In verificado' : 'pendiente de verificación') . '.');
    }

    // =========================================================================
    // BLOG EDITORIAL & SHOP THE STORY
    // =========================================================================

    public function blogIndex(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $tagFilter = trim((string) $request->query('tag', ''));
        $statusFilter = (string) $request->query('status', '');

        $tags = Post::query()
            ->reorder()
            ->whereNotNull('category_tag')
            ->where('category_tag', '!=', '')
            ->select('category_tag')
            ->distinct()
            ->orderBy('category_tag')
            ->pluck('category_tag');

        $posts = Post::query()
            ->with('author')
            ->withCount('products')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($sub) use ($search): void {
                    $sub->where('title', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('category_tag', 'like', "%{$search}%");
                });
            })
            ->when($tagFilter !== '' && $tags->contains($tagFilter), fn ($q) => $q->where('category_tag', $tagFilter))
            ->when($statusFilter === 'published', fn ($q) => $q->where('is_published', true))
            ->when($statusFilter === 'draft', fn ($q) => $q->where('is_published', false))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.marketing.blog.index', [
            'posts' => $posts,
            'tags' => $tags,
            'search' => $search,
            'tagFilter' => $tagFilter,
            'statusFilter' => $statusFilter,
        ]);
    }

    public function blogCreate(): View
    {
        $products = Product::query()
            ->active()
            ->with('brand')
            ->orderBy('name')
            ->get(['id', 'brand_id', 'name', 'slug']);

        return view('admin.marketing.blog.form', [
            'post' => new Post([
                'reading_time_minutes' => 4,
                'is_published' => false,
            ]),
            'products' => $products,
            'selectedProducts' => [],
            'isEdit' => false,
        ]);
    }

    public function blogStore(Request $request): RedirectResponse
    {
        $validated = $this->validatePost($request);
        $uploadedCover = $this->handleCoverImageUpload($request);

        /** @var Post $post */
        $post = $this->runWithMediaCleanup(fn (): Post => DB::transaction(function () use ($validated, $uploadedCover, $request): Post {
            $slug = $this->resolveUniquePostSlug($validated['slug'] ?? null, $validated['title']);
            $isPublished = $request->boolean('is_published', false);
            $publishedAt = ! empty($validated['published_at'])
                ? Carbon::parse((string) $validated['published_at'])
                : ($isPublished ? now() : null);

            $coverImage = $uploadedCover ?? $this->sanitizeCoverPath($validated['cover_image'] ?? null);

            $post = Post::query()->create([
                'author_id' => Auth::id(),
                'title' => trim($validated['title']),
                'slug' => $slug,
                'category_tag' => trim($validated['category_tag']),
                'excerpt' => isset($validated['excerpt']) && trim((string) $validated['excerpt']) !== ''
                    ? trim((string) $validated['excerpt'])
                    : null,
                'content' => trim($validated['content']),
                'cover_image' => $coverImage,
                'reading_time_minutes' => max(1, (int) ($validated['reading_time_minutes'] ?? 4)),
                'is_published' => $isPublished,
                'published_at' => $publishedAt,
                'meta_title' => isset($validated['meta_title']) && trim((string) $validated['meta_title']) !== ''
                    ? trim((string) $validated['meta_title'])
                    : null,
                'meta_description' => isset($validated['meta_description']) && trim((string) $validated['meta_description']) !== ''
                    ? trim((string) $validated['meta_description'])
                    : null,
            ]);

            $this->syncPostProducts($post, $request);

            SecurityActionTriggered::dispatch(
                Auth::user(),
                'marketing_post_created',
                'info',
                "Artículo editorial «{$post->title}» creado (" . ($post->is_published ? 'publicado' : 'borrador') . ').',
                [
                    'post_id' => $post->id,
                    'slug' => $post->slug,
                    'is_published' => (bool) $post->is_published,
                ],
            );

            return $post;
        }), $uploadedCover);

        return redirect()
            ->route('admin.marketing.blog.index')
            ->with('admin_success', "Artículo «{$post->title}» creado correctamente.");
    }

    public function blogEdit(Post $post): View
    {
        $post->load('products');

        $products = Product::query()
            ->active()
            ->with('brand')
            ->orderBy('name')
            ->get(['id', 'brand_id', 'name', 'slug']);

        $selectedProducts = $post->products
            ->mapWithKeys(fn (Product $product): array => [
                $product->id => [
                    'sort_order' => (int) ($product->pivot?->sort_order ?? 1),
                    'custom_callout' => (string) ($product->pivot?->custom_callout ?? ''),
                ],
            ])
            ->all();

        return view('admin.marketing.blog.form', [
            'post' => $post,
            'products' => $products,
            'selectedProducts' => $selectedProducts,
            'isEdit' => true,
        ]);
    }

    public function blogUpdate(Request $request, Post $post): RedirectResponse
    {
        $validated = $this->validatePost($request, $post);
        $uploadedCover = $this->handleCoverImageUpload($request);
        $oldCoverImage = $post->cover_image;

        $this->runWithMediaCleanup(fn () => DB::transaction(function () use ($post, $validated, $uploadedCover, $request): void {
            /** @var Post $lockedPost */
            $lockedPost = Post::query()->lockForUpdate()->findOrFail($post->id);

            $slug = $this->resolveUniquePostSlug($validated['slug'] ?? null, $validated['title'], $lockedPost->id);
            $isPublished = $request->boolean('is_published');

            $publishedAt = ! empty($validated['published_at'])
                ? Carbon::parse((string) $validated['published_at'])
                : ($isPublished ? ($lockedPost->published_at ?? now()) : null);

            $manualCover = $this->sanitizeCoverPath($validated['cover_image'] ?? null);
            $coverImage = $uploadedCover ?? ($manualCover ?? $lockedPost->cover_image);

            $lockedPost->update([
                'title' => trim($validated['title']),
                'slug' => $slug,
                'category_tag' => trim($validated['category_tag']),
                'excerpt' => isset($validated['excerpt']) && trim((string) $validated['excerpt']) !== ''
                    ? trim((string) $validated['excerpt'])
                    : null,
                'content' => trim($validated['content']),
                'cover_image' => $coverImage,
                'reading_time_minutes' => max(1, (int) ($validated['reading_time_minutes'] ?? 4)),
                'is_published' => $isPublished,
                'published_at' => $publishedAt,
                'meta_title' => isset($validated['meta_title']) && trim((string) $validated['meta_title']) !== ''
                    ? trim((string) $validated['meta_title'])
                    : null,
                'meta_description' => isset($validated['meta_description']) && trim((string) $validated['meta_description']) !== ''
                    ? trim((string) $validated['meta_description'])
                    : null,
            ]);

            $this->syncPostProducts($lockedPost, $request);

            SecurityActionTriggered::dispatch(
                Auth::user(),
                'marketing_post_updated',
                'info',
                "Artículo editorial «{$lockedPost->title}» actualizado.",
                [
                    'post_id' => $lockedPost->id,
                    'slug' => $lockedPost->slug,
                    'is_published' => (bool) $lockedPost->is_published,
                ],
            );
        }), $uploadedCover);

        if ($uploadedCover !== null && $oldCoverImage !== null && $oldCoverImage !== $uploadedCover) {
            $this->deleteSafePublicImage($oldCoverImage);
        }

        $post->refresh();

        return redirect()
            ->route('admin.marketing.blog.index')
            ->with('admin_success', "Artículo «{$post->title}» actualizado correctamente.");
    }

    public function blogTogglePublish(Post $post): RedirectResponse
    {
        $newState = DB::transaction(function () use ($post): bool {
            /** @var Post $lockedPost */
            $lockedPost = Post::query()->lockForUpdate()->findOrFail($post->id);
            $target = ! (bool) $lockedPost->is_published;

            $lockedPost->update([
                'is_published' => $target,
                'published_at' => $target ? ($lockedPost->published_at ?? now()) : $lockedPost->published_at,
            ]);

            SecurityActionTriggered::dispatch(
                Auth::user(),
                'marketing_post_publish_toggled',
                'info',
                "Artículo «{$lockedPost->title}» marcado como " . ($target ? 'publicado' : 'borrador') . '.',
                ['post_id' => $lockedPost->id, 'slug' => $lockedPost->slug, 'is_published' => $target],
            );

            return $target;
        });

        return redirect()
            ->route('admin.marketing.blog.index')
            ->with('admin_success', "Artículo «{$post->title}» marcado como " . ($newState ? 'publicado' : 'borrador') . '.');
    }

    public function blogDestroy(Post $post): RedirectResponse
    {
        $title = $post->title;
        $oldCoverImage = $post->cover_image;

        DB::transaction(function () use ($post): void {
            /** @var Post $lockedPost */
            $lockedPost = Post::query()->lockForUpdate()->findOrFail($post->id);
            $postId = $lockedPost->id;
            $postTitle = $lockedPost->title;
            $postSlug = $lockedPost->slug;

            $lockedPost->products()->detach();
            $lockedPost->delete();

            SecurityActionTriggered::dispatch(
                Auth::user(),
                'marketing_post_deleted',
                'warning',
                "Artículo editorial «{$postTitle}» eliminado.",
                ['post_id' => $postId, 'slug' => $postSlug],
            );
        });

        $this->deleteSafePublicImage($oldCoverImage);

        return redirect()
            ->route('admin.marketing.blog.index')
            ->with('admin_success', "Artículo «{$title}» eliminado correctamente.");
    }

    // =========================================================================
    // HELPERS PRIVADOS
    // =========================================================================

    /**
     * @return array<string, mixed>
     */
    private function validateCoupon(Request $request, ?Coupon $coupon = null): array
    {
        $request->merge([
            'code' => strtoupper(trim((string) $request->input('code', ''))),
            'applicable_tier' => ($rawTier = trim((string) $request->input('applicable_tier', ''))) !== '' ? $rawTier : null,
        ]);

        return $request->validate([
            'code' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('coupons', 'code')->ignore($coupon?->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'discount_type' => ['required', 'string', Rule::in(['percentage', 'fixed'])],
            'discount_value' => [
                'required',
                'numeric',
                'gt:0',
                'max:999999',
                function (string $attribute, mixed $value, Closure $fail) use ($request): void {
                    if ($request->input('discount_type') === 'percentage' && (float) $value > 100.0) {
                        $fail('El descuento porcentual no puede superar el 100%.');
                    }
                },
            ],
            'min_order_amount' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'applicable_tier' => ['nullable', 'string', Rule::in(CustomerTier::values())],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => [
                'nullable',
                'date',
                function (string $attribute, mixed $value, Closure $fail) use ($request): void {
                    $startsAt = $request->input('starts_at');
                    if ($startsAt !== null && trim((string) $startsAt) !== '' && $value !== null && trim((string) $value) !== '') {
                        try {
                            if (Carbon::parse((string) $value)->lt(Carbon::parse((string) $startsAt))) {
                                $fail('La fecha de expiración debe ser igual o posterior a la fecha de inicio.');
                            }
                        } catch (\Throwable) {
                            // Handled by 'date' validation rule
                        }
                    }
                },
            ],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'code.required' => 'Ingresa el código promocional del cupón.',
            'code.regex' => 'El código del cupón solo puede contener letras, números, guiones y guiones bajos.',
            'code.unique' => 'Ya existe un cupón con este código.',
            'discount_value.required' => 'Ingresa el valor del descuento.',
            'discount_value.gt' => 'El valor del descuento debe ser mayor que 0.',
            'applicable_tier.in' => 'Selecciona un nivel de cliente válido.',
        ]);
    }

    private function couponHasUsage(Coupon $coupon): bool
    {
        if ((int) $coupon->used_count > 0) {
            return true;
        }

        return Order::query()
            ->whereRaw('UPPER(coupon_code) = ?', [strtoupper((string) $coupon->code)])
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePost(Request $request, ?Post $post = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:200'],
            'slug' => [
                'nullable',
                'string',
                'max:150',
                Rule::unique('posts', 'slug')->ignore($post?->id),
            ],
            'category_tag' => ['required', 'string', 'min:2', 'max:60'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'content' => ['required', 'string', 'min:10'],
            'cover_image' => ['nullable', 'string', 'max:255'],
            'cover_image_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096', new SafeRasterImage()],
            'reading_time_minutes' => ['nullable', 'integer', 'min:1', 'max:120'],
            'is_published' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'meta_title' => ['nullable', 'string', 'max:180'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'products' => ['nullable', 'array'],
            'products.*.selected' => ['nullable'],
            'products.*.sort_order' => ['nullable', 'integer', 'min:1', 'max:999'],
            'products.*.custom_callout' => ['nullable', 'string', 'max:180'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'product_callouts' => ['nullable', 'array'],
            'product_callouts.*' => ['nullable', 'string', 'max:180'],
        ], [
            'title.required' => 'Ingresa el título editorial del artículo.',
            'slug.unique' => 'El slug URL ya está asignado a otro artículo.',
            'category_tag.required' => 'Indica la etiqueta temática (ej. Skincare, Maquillaje, Profesional B2B).',
            'content.required' => 'Ingresa el contenido del artículo.',
            'cover_image_file.mimes' => 'La imagen de portada debe ser JPG, PNG o WEBP.',
        ]);
    }

    private function handleCoverImageUpload(Request $request): ?string
    {
        return $this->storeSafePublicImage($request, 'cover_image_file', 'blog');
    }

    private function sanitizeCoverPath(?string $path): ?string
    {
        return $this->sanitizeSafeImagePath($path);
    }

    private function resolveUniquePostSlug(?string $rawSlug, string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug(trim((string) $rawSlug) !== '' ? (string) $rawSlug : $title);
        if ($base === '') {
            $base = 'articulo';
        }

        $candidate = $base;
        $suffix = 2;

        while (
            Post::query()
                ->where('slug', $candidate)
                ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $candidate = "{$base}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }

    private function syncPostProducts(Post $post, Request $request): void
    {
        $syncPayload = [];
        $productsInput = $request->input('products');

        if (is_array($productsInput) && $productsInput !== []) {
            $candidateIds = [];
            foreach ($productsInput as $productId => $row) {
                if (is_array($row) && ! empty($row['selected'])) {
                    $candidateIds[] = (int) $productId;
                }
            }

            $validIds = $candidateIds !== []
                ? Product::query()->whereIn('id', $candidateIds)->pluck('id')->all()
                : [];

            $fallbackOrder = 1;
            foreach ($validIds as $validId) {
                $row = $productsInput[$validId] ?? $productsInput[(string) $validId] ?? [];
                $sortOrder = isset($row['sort_order']) && is_numeric($row['sort_order'])
                    ? max(1, (int) $row['sort_order'])
                    : $fallbackOrder;
                $callout = isset($row['custom_callout']) && trim((string) $row['custom_callout']) !== ''
                    ? Str::limit(trim((string) $row['custom_callout']), 180, '')
                    : null;

                $syncPayload[$validId] = [
                    'sort_order' => $sortOrder,
                    'custom_callout' => $callout,
                ];
                $fallbackOrder++;
            }
        } else {
            $productIds = $request->input('product_ids');
            if (is_array($productIds) && $productIds !== []) {
                $validIds = Product::query()
                    ->whereIn('id', array_map('intval', $productIds))
                    ->pluck('id')
                    ->all();
                $callouts = is_array($request->input('product_callouts')) ? $request->input('product_callouts') : [];

                $order = 1;
                foreach ($validIds as $validId) {
                    $rawCallout = $callouts[$validId] ?? $callouts[(string) $validId] ?? null;
                    $syncPayload[$validId] = [
                        'sort_order' => $order++,
                        'custom_callout' => is_string($rawCallout) && trim($rawCallout) !== ''
                            ? Str::limit(trim($rawCallout), 180, '')
                            : null,
                    ];
                }
            }
        }

        $post->products()->sync($syncPayload);
    }
}
