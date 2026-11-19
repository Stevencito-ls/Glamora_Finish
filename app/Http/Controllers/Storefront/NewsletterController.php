<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class NewsletterController extends Controller
{
    public function subscribe(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('newsletter', [
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:150'],
            'segment' => ['required', 'string', Rule::in(['minorista', 'mayorista'])],
        ], [
            'email.required' => 'Ingresa tu correo electrónico para suscribirte.',
            'email.email' => 'Ingresa un correo electrónico válido.',
            'segment.in' => 'Selecciona un segmento válido (minorista o mayorista).',
        ]);

        $email = mb_strtolower(trim($validated['email']));
        $existing = NewsletterSubscriber::query()->where('email', $email)->first();
        $token = $existing?->opt_in_token ?: Str::random(40);

        $subscriber = NewsletterSubscriber::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => isset($validated['name']) && trim((string) $validated['name']) !== ''
                    ? trim((string) $validated['name'])
                    : $existing?->name,
                'segment' => $validated['segment'],
                'opt_in_token' => $token,
                'is_verified' => $existing?->is_verified ?? false,
                'is_active' => true,
                'subscribed_at' => now(),
                'unsubscribed_at' => null,
            ]
        );

        $segmentLabel = $subscriber->segment === 'mayorista' ? 'Mayorista B2B' : 'Minorista';

        return back()
            ->withFragment('newsletter-footer')
            ->with(
                'newsletter_status',
                "¡Suscripción registrada en el segmento {$segmentLabel} para {$subscriber->email}!"
            )
            ->with(
                'newsletter_token_hint',
                'Token Opt-In asignado: ' . Str::limit((string) $subscriber->opt_in_token, 12, '...')
            );
    }
}
