<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\CustomerTier;
use App\Events\SecurityActionTriggered;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WholesaleProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class WholesaleAdminController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', WholesaleProfile::class);

        /** @var User $user */
        $user = Auth::user();
        $canManageAll = $user->hasAnyRole(['SuperAdmin', 'Administrador']);

        $statusFilter = (string) $request->query('status', '');
        $tierFilter = (string) $request->query('tier', '');
        $search = trim((string) $request->query('q', ''));

        $baseQuery = WholesaleProfile::query();
        if (! $canManageAll) {
            $baseQuery->where('user_id', $user->id);
        }

        $statusCounts = (clone $baseQuery)
            ->reorder()
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) as total')
            ->pluck('total', 'status');

        $profiles = (clone $baseQuery)
            ->with(['user.roles', 'approver'])
            ->when($statusFilter !== '', fn ($q) => $q->where('status', $statusFilter))
            ->when($tierFilter !== '', function ($q) use ($tierFilter): void {
                $q->where(function ($sub) use ($tierFilter): void {
                    $sub->where('approved_tier', $tierFilter)
                        ->orWhere(function ($inner) use ($tierFilter): void {
                            $inner->whereNull('approved_tier')->where('requested_tier', $tierFilter);
                        });
                });
            })
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('company_name', 'like', "%{$search}%")
                        ->orWhere('trade_name', 'like', "%{$search}%")
                        ->orWhere('ruc', 'like', "%{$search}%")
                        ->orWhere('contact_name', 'like', "%{$search}%")
                        ->orWhere('contact_email', 'like', "%{$search}%");
                });
            })
            ->latest('id')
            ->paginate(18)
            ->withQueryString();

        return view('admin.wholesale.index', [
            'profiles' => $profiles,
            'statusCounts' => $statusCounts,
            'statusFilter' => $statusFilter,
            'tierFilter' => $tierFilter,
            'search' => $search,
            'wholesaleTiers' => [CustomerTier::Bronce, CustomerTier::Plata, CustomerTier::Oro],
        ]);
    }

    public function show(WholesaleProfile $wholesaleProfile): View
    {
        $this->authorize('view', $wholesaleProfile);

        $wholesaleProfile->load([
            'user.roles',
            'user.orders' => fn ($q) => $q->with('payments')->latest('id')->limit(10),
            'approver',
        ]);

        return view('admin.wholesale.show', [
            'profile' => $wholesaleProfile,
            'wholesaleTiers' => [CustomerTier::Bronce, CustomerTier::Plata, CustomerTier::Oro],
        ]);
    }

    public function approve(Request $request, WholesaleProfile $wholesaleProfile): RedirectResponse
    {
        $this->authorize('approve', $wholesaleProfile);

        $validated = $request->validate([
            'approved_tier' => ['required', 'string', Rule::in([
                CustomerTier::Bronce->value,
                CustomerTier::Plata->value,
                CustomerTier::Oro->value,
            ])],
            'credit_limit' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'payment_terms_days' => ['required', 'integer', 'min:0', 'max:180'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'approved_tier.in' => 'Selecciona una escala mayorista válida (Bronce, Plata u Oro).',
            'credit_limit.required' => 'Define el límite de crédito aprobado (puede ser 0.00 para pago al contado).',
        ]);

        /** @var User $actor */
        $actor = Auth::user();
        $approvedTier = CustomerTier::from($validated['approved_tier']);

        DB::transaction(function () use ($wholesaleProfile, $validated, $approvedTier, $actor): void {
            /** @var WholesaleProfile $lockedProfile */
            $lockedProfile = WholesaleProfile::query()->lockForUpdate()->findOrFail($wholesaleProfile->id);
            /** @var User $lockedUser */
            $lockedUser = User::query()->with('roles')->lockForUpdate()->findOrFail($lockedProfile->user_id);

            $lockedProfile->update([
                'approved_tier' => $approvedTier,
                'credit_limit' => round((float) $validated['credit_limit'], 2),
                'payment_terms_days' => (int) $validated['payment_terms_days'],
                'status' => 'approved',
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'rejection_reason' => null,
                'notes' => isset($validated['notes']) && trim((string) $validated['notes']) !== ''
                    ? trim((string) $validated['notes'])
                    : $lockedProfile->notes,
            ]);

            $lockedUser->forceFill([
                'customer_tier' => $approvedTier,
            ])->save();

            if (! $lockedUser->hasAnyRole(['SuperAdmin', 'Administrador', 'Almacenero'])) {
                if (Role::query()->where('name', 'Vendedor B2B')->where('guard_name', 'web')->exists()) {
                    if (! $lockedUser->hasRole('Vendedor B2B')) {
                        $lockedUser->assignRole('Vendedor B2B');
                    }
                    if ($lockedUser->hasRole('Cliente')) {
                        $lockedUser->removeRole('Cliente');
                    }
                }
            }

            SecurityActionTriggered::dispatch(
                $actor,
                'wholesale_approved',
                'info',
                "Perfil B2B aprobado para {$lockedProfile->company_name} (RUC {$lockedProfile->ruc}) en {$approvedTier->label()} con línea S/ " . number_format((float) $lockedProfile->credit_limit, 2) . '.',
                [
                    'wholesale_profile_id' => $lockedProfile->id,
                    'user_id' => $lockedUser->id,
                    'ruc' => $lockedProfile->ruc,
                    'approved_tier' => $approvedTier->value,
                    'credit_limit' => (float) $lockedProfile->credit_limit,
                    'payment_terms_days' => $lockedProfile->payment_terms_days,
                ],
            );
        });

        return redirect()
            ->route('admin.wholesale.show', $wholesaleProfile)
            ->with('admin_success', "Cuenta mayorista {$wholesaleProfile->company_name} aprobada en escala {$approvedTier->label()}.");
    }

    public function reject(Request $request, WholesaleProfile $wholesaleProfile): RedirectResponse
    {
        $this->authorize('reject', $wholesaleProfile);

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'rejection_reason.required' => 'Ingresa el motivo del rechazo de la solicitud B2B.',
            'rejection_reason.min' => 'El motivo del rechazo debe tener al menos 5 caracteres.',
        ]);

        /** @var User $actor */
        $actor = Auth::user();

        DB::transaction(function () use ($wholesaleProfile, $validated, $actor): void {
            /** @var WholesaleProfile $lockedProfile */
            $lockedProfile = WholesaleProfile::query()->lockForUpdate()->findOrFail($wholesaleProfile->id);
            /** @var User $lockedUser */
            $lockedUser = User::query()->with('roles')->lockForUpdate()->findOrFail($lockedProfile->user_id);

            if ((float) $lockedProfile->credit_used > 0.0) {
                throw ValidationException::withMessages([
                    'rejection_reason' => 'Esta cuenta tiene deuda de crédito B2B vigente (S/ ' . number_format((float) $lockedProfile->credit_used, 2) . '). Liquida el saldo antes de revocar el perfil.',
                ]);
            }

            $reason = trim($validated['rejection_reason']);

            $lockedProfile->update([
                'status' => 'rejected',
                'approved_tier' => null,
                'credit_limit' => 0.00,
                'payment_terms_days' => 0,
                'approved_by' => null,
                'approved_at' => null,
                'rejection_reason' => $reason,
            ]);

            $lockedUser->forceFill([
                'customer_tier' => CustomerTier::Minorista,
            ])->save();

            if (! $lockedUser->hasAnyRole(['SuperAdmin', 'Administrador', 'Almacenero'])) {
                if ($lockedUser->hasRole('Vendedor B2B')) {
                    $lockedUser->removeRole('Vendedor B2B');
                }
                if (! $lockedUser->hasRole('Cliente') && Role::query()->where('name', 'Cliente')->where('guard_name', 'web')->exists()) {
                    $lockedUser->assignRole('Cliente');
                }
            }

            SecurityActionTriggered::dispatch(
                $actor,
                'wholesale_rejected',
                'warning',
                "Solicitud/cuenta B2B rechazada para {$lockedProfile->company_name} (RUC {$lockedProfile->ruc}): {$reason}",
                [
                    'wholesale_profile_id' => $lockedProfile->id,
                    'user_id' => $lockedUser->id,
                    'ruc' => $lockedProfile->ruc,
                    'reason' => $reason,
                ],
            );
        });

        return redirect()
            ->route('admin.wholesale.show', $wholesaleProfile)
            ->with('admin_success', "Solicitud mayorista de {$wholesaleProfile->company_name} rechazada.");
    }

    public function updateCredit(Request $request, WholesaleProfile $wholesaleProfile): RedirectResponse
    {
        $this->authorize('manageCredit', $wholesaleProfile);

        $validated = $request->validate([
            'approved_tier' => ['required', 'string', Rule::in([
                CustomerTier::Bronce->value,
                CustomerTier::Plata->value,
                CustomerTier::Oro->value,
            ])],
            'credit_limit' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'payment_terms_days' => ['required', 'integer', 'min:0', 'max:180'],
            'credit_payment_abono' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        /** @var User $actor */
        $actor = Auth::user();
        $newTier = CustomerTier::from($validated['approved_tier']);

        DB::transaction(function () use ($wholesaleProfile, $validated, $newTier, $actor): void {
            /** @var WholesaleProfile $lockedProfile */
            $lockedProfile = WholesaleProfile::query()->lockForUpdate()->findOrFail($wholesaleProfile->id);
            /** @var User $lockedUser */
            $lockedUser = User::query()->lockForUpdate()->findOrFail($lockedProfile->user_id);

            if ($lockedProfile->status !== 'approved') {
                throw ValidationException::withMessages([
                    'credit_limit' => 'Solo se puede ajustar la línea de crédito de cuentas B2B aprobadas.',
                ]);
            }

            $oldLimit = (float) $lockedProfile->credit_limit;
            $oldUsed = (float) $lockedProfile->credit_used;
            $oldTier = $lockedProfile->approved_tier?->value;

            $abono = round((float) ($validated['credit_payment_abono'] ?? 0), 2);
            if ($abono > $oldUsed + 0.01) {
                throw ValidationException::withMessages([
                    'credit_payment_abono' => 'El abono registrado no puede superar el crédito utilizado actual (S/ ' . number_format($oldUsed, 2) . ').',
                ]);
            }

            $newUsed = max(0.0, round($oldUsed - $abono, 2));
            $newLimit = round((float) $validated['credit_limit'], 2);

            $lockedProfile->update([
                'approved_tier' => $newTier,
                'credit_limit' => $newLimit,
                'credit_used' => $newUsed,
                'payment_terms_days' => (int) $validated['payment_terms_days'],
                'notes' => isset($validated['notes']) && trim((string) $validated['notes']) !== ''
                    ? trim((string) $validated['notes'])
                    : $lockedProfile->notes,
            ]);

            $lockedUser->forceFill([
                'customer_tier' => $newTier,
            ])->save();

            SecurityActionTriggered::dispatch(
                $actor,
                'wholesale_credit_updated',
                'warning',
                "Línea de crédito B2B ajustada para {$lockedProfile->company_name}: S/ " . number_format($oldLimit, 2) . ' -> S/ ' . number_format($newLimit, 2) . " ({$newTier->label()}).",
                [
                    'wholesale_profile_id' => $lockedProfile->id,
                    'ruc' => $lockedProfile->ruc,
                    'old_tier' => $oldTier,
                    'new_tier' => $newTier->value,
                    'old_credit_limit' => $oldLimit,
                    'new_credit_limit' => $newLimit,
                    'abono_applied' => $abono,
                    'credit_used' => $newUsed,
                ],
            );
        });

        return redirect()
            ->route('admin.wholesale.show', $wholesaleProfile)
            ->with('admin_success', 'Línea de crédito y escala B2B actualizadas correctamente.');
    }
}
