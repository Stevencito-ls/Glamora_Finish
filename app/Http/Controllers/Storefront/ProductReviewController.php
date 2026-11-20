<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Enums\OrderStatus;
use App\Events\SecurityActionTriggered;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProductReviewController extends Controller
{
    public function store(Request $request, string $slug, SettingService $settingService): RedirectResponse
    {
        $product = Product::query()
            ->active()
            ->where('slug', $slug)
            ->firstOrFail();

        /** @var User $user */
        $user = Auth::user();

        $alreadyReviewed = ProductReview::query()
            ->where('product_id', $product->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($alreadyReviewed) {
            return redirect(route('product.show', $product->slug) . '#resenas')
                ->withErrors(['comment' => 'Ya registraste una reseña para este producto.'])
                ->with('review_error', 'Ya registraste una reseña para este producto.');
        }

        $cleanTitle = $this->cleanPlainText($request->input('title'));
        $cleanComment = $this->cleanPlainText($request->input('comment'));
        $cleanShade = $this->cleanPlainText($request->input('shade_purchased'));

        $request->merge([
            'title' => $cleanTitle !== '' ? $cleanTitle : null,
            'comment' => $cleanComment,
            'shade_purchased' => $cleanShade !== '' ? $cleanShade : null,
        ]);

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'min:3', 'max:150'],
            'comment' => ['required', 'string', 'min:10', 'max:1500'],
            'shade_purchased' => ['nullable', 'string', 'max:120'],
        ], [
            'rating.required' => 'Selecciona una calificación de 1 a 5 estrellas.',
            'rating.min' => 'La calificación mínima es 1 estrella.',
            'rating.max' => 'La calificación máxima es 5 estrellas.',
            'title.min' => 'El título debe tener al menos 3 caracteres.',
            'comment.required' => 'Escribe tu experiencia con el producto.',
            'comment.min' => 'Tu comentario debe tener al menos 10 caracteres.',
        ]);

        $verifiedOrder = Order::query()
            ->where('user_id', $user->id)
            ->where('status', OrderStatus::Entregado->value)
            ->whereHas('items.variant', fn ($q) => $q->where('product_id', $product->id))
            ->with(['items' => fn ($q) => $q->whereHas('variant', fn ($vq) => $vq->where('product_id', $product->id))])
            ->latest('id')
            ->first();

        $isVerifiedPurchase = $verifiedOrder instanceof Order;
        $shadePurchased = isset($validated['shade_purchased']) && trim((string) $validated['shade_purchased']) !== ''
            ? $this->cleanPlainText($validated['shade_purchased'])
            : null;

        if ($shadePurchased === null && $verifiedOrder instanceof Order) {
            $matchingItem = $verifiedOrder->items->first();
            if ($matchingItem instanceof OrderItem && trim((string) $matchingItem->variant_name) !== '') {
                $shadePurchased = $this->cleanPlainText($matchingItem->variant_name) ?: null;
            }
        }

        $autoApprove = $settingService->bool('reviews_auto_approve', false);
        $status = $autoApprove ? 'approved' : 'pending';

        try {
            $review = DB::transaction(function () use ($product, $user, $verifiedOrder, $validated, $shadePurchased, $isVerifiedPurchase, $status, $autoApprove): ProductReview {
                $review = ProductReview::query()->create([
                    'product_id' => $product->id,
                    'user_id' => $user->id,
                    'order_id' => $verifiedOrder?->id,
                    'rating' => (int) $validated['rating'],
                    'title' => isset($validated['title']) && trim((string) $validated['title']) !== ''
                        ? $this->cleanPlainText($validated['title'])
                        : null,
                    'comment' => $this->cleanPlainText($validated['comment']),
                    'shade_purchased' => $shadePurchased,
                    'is_verified_purchase' => $isVerifiedPurchase,
                    'status' => $status,
                    'moderated_by' => null,
                    'moderated_at' => $autoApprove ? now() : null,
                    'moderation_notes' => $autoApprove ? 'Aprobación automática habilitada en configuración.' : null,
                ]);

                SecurityActionTriggered::dispatch(
                    $user,
                    'product_review_submitted',
                    'info',
                    "Reseña registrada en «{$product->name}» ({$review->rating}/5, estado: {$status}).",
                    [
                        'review_id' => $review->id,
                        'product_id' => $product->id,
                        'is_verified_purchase' => $isVerifiedPurchase,
                        'status' => $status,
                    ],
                );

                return $review;
            });
        } catch (UniqueConstraintViolationException) {
            return redirect(route('product.show', $product->slug) . '#resenas')
                ->withErrors(['comment' => 'Ya registraste una reseña para este producto.'])
                ->with('review_error', 'Ya registraste una reseña para este producto.');
        }

        $message = $review->status === 'approved'
            ? '¡Gracias! Tu reseña ha sido publicada en el producto.'
            : '¡Gracias por tu reseña! Quedó pendiente de moderación editorial antes de publicarse.';

        return redirect(route('product.show', $product->slug) . '#resenas')
            ->with('review_success', $message);
    }

    private function cleanPlainText(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        $withoutScripts = (string) preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', (string) $value);

        return trim(strip_tags($withoutScripts));
    }
}
