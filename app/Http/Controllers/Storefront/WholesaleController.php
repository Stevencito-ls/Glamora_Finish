<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Enums\BannerPosition;
use App\Enums\CustomerTier;
use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\SecurityLog;
use App\Models\User;
use App\Models\WholesaleProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class WholesaleController extends Controller
{
    public function index(): View
    {
        $tiers = [
            [
                'tier' => CustomerTier::Bronce,
                'badge' => 'Salones & Boutiques Iniciales',
                'discount_range' => '10% a 14% desc.',
                'avg_discount' => 12,
                'moq' => 800,
                'credit_days' => 0,
                'perks' => [
                    'Acceso inmediato a precios Bronce desde S/ 800 mensuales',
                    'Descuento extra de 14% llevando 6+ unidades por tono/SKU',
                    'Certificados NSOC DIGEMID y ficha técnica descargable',
                ],
            ],
            [
                'tier' => CustomerTier::Plata,
                'badge' => 'Centros Estéticos & Tiendas Multimarca',
                'discount_range' => '18% a 22% desc.',
                'avg_discount' => 20,
                'moq' => 2500,
                'credit_days' => 15,
                'perks' => [
                    'Escala Plata con 22% de descuento desde 10+ unidades por SKU',
                    'Línea de crédito comercial evaluada hasta a 15 días',
                    'Prioridad en reposición de lanzamientos y material digital',
                ],
            ],
            [
                'tier' => CustomerTier::Oro,
                'badge' => 'Spas, Cadenas & Distribuidores Regionales',
                'discount_range' => '25% a 30% desc.',
                'avg_discount' => 28,
                'moq' => 6000,
                'credit_days' => 30,
                'perks' => [
                    'Máximo margen mayorista: hasta 30% de descuento desde 12+ unidades',
                    'Línea de crédito B2B aprobada a 30 días sin intereses',
                    'Exhibidores acrílicos, probadores de cortesía y envío gratuito',
                ],
            ],
        ];

        $wholesaleBanner = Banner::query()
            ->active()
            ->forPosition(BannerPosition::PortalMayorista)
            ->ordered()
            ->first();

        return view('storefront.wholesale.index', [
            'tiers' => $tiers,
            'wholesaleBanner' => $wholesaleBanner,
        ]);
    }

    public function storeApplication(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ruc' => ['required', 'string', 'regex:/^\d{11}$/'],
            'company_name' => ['required', 'string', 'min:3', 'max:180'],
            'trade_name' => ['nullable', 'string', 'max:180'],
            'business_type' => ['required', 'string', 'max:80'],
            'contact_name' => ['required', 'string', 'min:3', 'max:150'],
            'contact_email' => ['required', 'email:rfc', 'max:150'],
            'contact_phone' => ['required', 'string', 'min:7', 'max:25'],
            'fiscal_address' => ['required', 'string', 'min:5', 'max:255'],
            'district' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'requested_tier' => ['required', 'string', Rule::in([
                CustomerTier::Bronce->value,
                CustomerTier::Plata->value,
                CustomerTier::Oro->value,
            ])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'ruc.required' => 'El número de RUC es obligatorio.',
            'ruc.regex' => 'El RUC debe tener exactamente 11 dígitos numéricos.',
            'company_name.required' => 'Ingresa la razón social de tu empresa.',
            'business_type.required' => 'Selecciona el giro de tu negocio.',
            'contact_name.required' => 'Ingresa el nombre del representante o contacto.',
            'contact_email.required' => 'Ingresa un correo electrónico corporativo o de contacto.',
            'contact_email.email' => 'El formato del correo electrónico no es válido.',
            'contact_phone.required' => 'Ingresa un teléfono o WhatsApp de contacto.',
            'fiscal_address.required' => 'Ingresa la dirección fiscal o de entrega.',
            'district.required' => 'Ingresa el distrito.',
            'city.required' => 'Ingresa la ciudad o departamento.',
            'requested_tier.in' => 'Selecciona un nivel mayorista válido (Bronce, Plata u Oro).',
        ]);

        $email = mb_strtolower(trim($validated['contact_email']));
        $ruc = trim($validated['ruc']);
        $companyName = trim($validated['company_name']);
        $requestedTier = CustomerTier::from($validated['requested_tier']);

        $profile = DB::transaction(function () use ($validated, $email, $ruc, $companyName, $requestedTier, $request): WholesaleProfile {
            $user = User::query()->where('email', $email)->lockForUpdate()->first();

            if ($user instanceof User) {
                if (! $user->is_active) {
                    throw ValidationException::withMessages([
                        'contact_email' => 'Esta cuenta se encuentra desactivada. Contáctanos para regularizar tu registro.',
                    ]);
                }

                if ($user->hasAnyRole(['SuperAdmin', 'Administrador', 'Almacenero'])) {
                    throw ValidationException::withMessages([
                        'contact_email' => 'Este correo pertenece a una cuenta operativa interna y no puede postular como cliente B2B.',
                    ]);
                }

                $existingProfile = WholesaleProfile::query()
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
                    ->first();

                if ($existingProfile !== null && in_array($existingProfile->status, ['pending', 'approved'], true)) {
                    $statusText = $existingProfile->status === 'approved'
                        ? 'ya cuenta con una cuenta mayorista B2B aprobada'
                        : 'ya tiene una solicitud mayorista en evaluación';

                    throw ValidationException::withMessages([
                        'contact_email' => "El correo {$email} {$statusText} (RUC {$existingProfile->ruc}).",
                    ]);
                }

                $emailTakenByOtherProfile = WholesaleProfile::query()
                    ->where('contact_email', $email)
                    ->where('user_id', '!=', $user->id)
                    ->whereIn('status', ['pending', 'approved'])
                    ->exists();

                if ($emailTakenByOtherProfile) {
                    throw ValidationException::withMessages([
                        'contact_email' => "El correo {$email} ya figura como contacto activo en otra cuenta mayorista.",
                    ]);
                }

                $rucTaken = WholesaleProfile::query()
                    ->where('ruc', $ruc)
                    ->where('user_id', '!=', $user->id)
                    ->lockForUpdate()
                    ->exists();

                if ($rucTaken) {
                    throw ValidationException::withMessages([
                        'ruc' => "El RUC {$ruc} ya se encuentra registrado en otra solicitud o cuenta mayorista Glamora.",
                    ]);
                }
            } else {
                $emailTakenByProfile = WholesaleProfile::query()
                    ->where('contact_email', $email)
                    ->whereIn('status', ['pending', 'approved'])
                    ->exists();

                if ($emailTakenByProfile) {
                    throw ValidationException::withMessages([
                        'contact_email' => "El correo {$email} ya figura en una solicitud o cuenta mayorista Glamora.",
                    ]);
                }

                $rucTaken = WholesaleProfile::query()
                    ->where('ruc', $ruc)
                    ->lockForUpdate()
                    ->exists();

                if ($rucTaken) {
                    throw ValidationException::withMessages([
                        'ruc' => "El RUC {$ruc} ya se encuentra registrado en otra solicitud o cuenta mayorista Glamora.",
                    ]);
                }

                $existingProfile = null;

                $user = new User();
                $user->fill([
                    'name' => trim($validated['contact_name']),
                    'email' => $email,
                    'password' => Hash::make(Str::random(32)),
                    'phone' => trim($validated['contact_phone']),
                    'document_type' => 'RUC',
                    'document_number' => $ruc,
                    'default_address' => trim($validated['fiscal_address']),
                    'district' => trim($validated['district']),
                    'city' => trim($validated['city']),
                ]);
                $user->forceFill([
                    'customer_tier' => CustomerTier::Minorista,
                    'is_active' => true,
                ]);
                $user->save();

                if (Role::query()->where('name', 'Cliente')->where('guard_name', 'web')->exists()) {
                    $user->assignRole('Cliente');
                }
            }

            $payload = [
                'company_name' => $companyName,
                'trade_name' => isset($validated['trade_name']) && trim((string) $validated['trade_name']) !== ''
                    ? trim((string) $validated['trade_name'])
                    : null,
                'ruc' => $ruc,
                'business_type' => trim($validated['business_type']),
                'contact_name' => trim($validated['contact_name']),
                'contact_phone' => trim($validated['contact_phone']),
                'contact_email' => $email,
                'fiscal_address' => trim($validated['fiscal_address']),
                'district' => trim($validated['district']),
                'city' => trim($validated['city']),
                'requested_tier' => $requestedTier,
                'approved_tier' => null,
                'credit_limit' => 0.00,
                'credit_used' => 0.00,
                'payment_terms_days' => 0,
                'status' => 'pending',
                'approved_by' => null,
                'approved_at' => null,
                'rejection_reason' => null,
                'notes' => isset($validated['notes']) && trim((string) $validated['notes']) !== ''
                    ? trim((string) $validated['notes'])
                    : null,
            ];

            if ($existingProfile !== null) {
                $existingProfile->update($payload);
                $profile = $existingProfile;
            } else {
                $profile = WholesaleProfile::query()->create(array_merge(
                    ['user_id' => $user->id],
                    $payload
                ));
            }

            SecurityLog::query()->create([
                'user_id' => $user->id,
                'event_type' => 'wholesale_application_submitted',
                'severity' => 'info',
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
                'description' => Str::limit(
                    "Solicitud B2B registrada para {$companyName} (RUC {$ruc}) — Nivel {$requestedTier->label()}.",
                    250
                ),
                'metadata' => [
                    'wholesale_profile_id' => $profile->id,
                    'ruc' => $ruc,
                    'company_name' => $companyName,
                    'requested_tier' => $requestedTier->value,
                    'city' => $validated['city'],
                ],
            ]);

            return $profile;
        });

        return back()
            ->withFragment('solicitud-b2b')
            ->with(
                'wholesale_success',
                "¡Solicitud mayorista recibida para {$profile->company_name} (RUC {$profile->ruc})! Nuestro equipo comercial evaluará tu perfil para el nivel {$requestedTier->label()} en un plazo máximo de 24 horas hábiles."
            );
    }
}
