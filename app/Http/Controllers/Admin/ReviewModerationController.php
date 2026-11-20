<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Events\SecurityActionTriggered;
use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReviewModerationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeReviewsModeration();

        $statusFilter = (string) $request->query('status', '');
        $ratingFilter = (string) $request->query('rating', '');
        $verifiedFilter = (string) $request->query('verified', '');
        $search = trim((string) $request->query('q', ''));

        $reviews = ProductReview::query()
            ->with(['product', 'user', 'order', 'moderator'])
            ->when(in_array($statusFilter, ['pending', 'approved', 'rejected'], true), fn ($q) => $q->where('status', $statusFilter))
            ->when(in_array($ratingFilter, ['1', '2', '3', '4', '5'], true), fn ($q) => $q->where('rating', (int) $ratingFilter))
            ->when($verifiedFilter === '1', fn ($q) => $q->where('is_verified_purchase', true))
            ->when($verifiedFilter === '0', fn ($q) => $q->where('is_verified_purchase', false))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($sub) use ($search): void {
                    $sub->where('title', 'like', "%{$search}%")
                        ->orWhere('comment', 'like', "%{$search}%")
                        ->orWhere('shade_purchased', 'like', "%{$search}%")
                        ->orWhereHas('product', fn ($pq) => $pq->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $avgRating = ProductReview::query()->approved()->avg('rating');

        $stats = [
            'total' => ProductReview::query()->count(),
            'pending' => ProductReview::query()->pending()->count(),
            'approved' => ProductReview::query()->approved()->count(),
            'rejected' => ProductReview::query()->rejected()->count(),
            'avg_rating' => $avgRating !== null ? round((float) $avgRating, 1) : 0.0,
        ];

        return view('admin.reviews.index', [
            'reviews' => $reviews,
            'stats' => $stats,
            'statusFilter' => $statusFilter,
            'ratingFilter' => $ratingFilter,
            'verifiedFilter' => $verifiedFilter,
            'search' => $search,
        ]);
    }

    public function moderate(Request $request, ProductReview $review): RedirectResponse
    {
        $this->authorizeReviewsModeration();

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['approved', 'rejected', 'pending'])],
            'moderation_notes' => ['nullable', 'string', 'max:500'],
        ], [
            'status.required' => 'Selecciona la decisión de moderación.',
            'status.in' => 'El estado de moderación seleccionado no es válido.',
        ]);

        $cleanNotes = null;
        if (isset($validated['moderation_notes'])) {
            $stripped = trim(strip_tags((string) preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', (string) $validated['moderation_notes'])));
            $cleanNotes = $stripped !== '' ? $stripped : null;
        }

        DB::transaction(function () use ($review, $validated, $cleanNotes): void {
            /** @var ProductReview $locked */
            $locked = ProductReview::query()->lockForUpdate()->findOrFail($review->id);

            $locked->update([
                'status' => (string) $validated['status'],
                'moderated_by' => Auth::id(),
                'moderated_at' => now(),
                'moderation_notes' => $cleanNotes ?? $locked->moderation_notes,
            ]);

            SecurityActionTriggered::dispatch(
                Auth::user(),
                'review_moderated',
                'info',
                "Reseña #{$locked->id} moderada con estado «{$locked->status}».",
                [
                    'review_id' => $locked->id,
                    'product_id' => $locked->product_id,
                    'status' => $locked->status,
                ],
            );
        });

        return redirect()
            ->route('admin.reviews.index')
            ->with('admin_success', 'Reseña moderada correctamente.');
    }

    public function destroy(ProductReview $review): RedirectResponse
    {
        $this->authorizeReviewsModeration();

        $id = $review->id;
        $productId = $review->product_id;

        DB::transaction(function () use ($review, $id, $productId): void {
            $review->delete();

            SecurityActionTriggered::dispatch(
                Auth::user(),
                'review_deleted',
                'warning',
                "Reseña #{$id} eliminada del sistema.",
                [
                    'review_id' => $id,
                    'product_id' => $productId,
                ],
            );
        });

        return redirect()
            ->route('admin.reviews.index')
            ->with('admin_success', "Reseña #{$id} eliminada correctamente.");
    }

    private function authorizeReviewsModeration(): void
    {
        $user = Auth::user();
        abort_unless($user !== null && (bool) $user->is_active && $user->can('reviews.moderate'), 403);
    }
}
