<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\CustomerTier;
use App\Events\SecurityActionTriggered;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $roles = Role::query()->where('guard_name', 'web')->orderBy('name')->get();

        $search = trim((string) $request->query('q', ''));
        $roleFilter = trim((string) $request->query('role', ''));
        $statusFilter = (string) $request->query('is_active', '');
        $tierFilter = (string) $request->query('customer_tier', '');

        $validRoleFilter = $roleFilter !== '' && $roles->contains('name', $roleFilter) ? $roleFilter : '';

        $users = User::query()
            ->with(['roles', 'wholesaleProfile'])
            ->when($validRoleFilter !== '', fn ($q) => $q->role($validRoleFilter))
            ->when($statusFilter === '1', fn ($q) => $q->where('is_active', true))
            ->when($statusFilter === '0', fn ($q) => $q->where('is_active', false))
            ->when($tierFilter !== '' && in_array($tierFilter, CustomerTier::values(), true), fn ($q) => $q->where('customer_tier', $tierFilter))
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('document_number', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => $roles,
            'customerTiers' => CustomerTier::cases(),
            'search' => $search,
            'roleFilter' => $validRoleFilter,
            'statusFilter' => $statusFilter,
            'tierFilter' => $tierFilter,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('admin.users.form', [
            'targetUser' => new User(['is_active' => true, 'document_type' => 'DNI', 'city' => 'Lima']),
            'roles' => $this->assignableRolesFor(Auth::user()),
            'customerTiers' => CustomerTier::cases(),
            'isEdit' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = Auth::user();
        $requestedRole = trim((string) $request->input('role', ''));

        $this->authorize('create', [User::class, $requestedRole]);

        if (strcasecmp($requestedRole, 'SuperAdmin') === 0 && ! $actor->hasRole('SuperAdmin')) {
            abort(403, 'Solo un SuperAdmin puede crear o asignar el rol SuperAdmin.');
        }

        if ($request->has('email')) {
            $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'role' => ['required', 'string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            'customer_tier' => ['required', 'string', Rule::in(CustomerTier::values())],
            'phone' => ['nullable', 'string', 'max:25'],
            'document_type' => ['nullable', 'string', Rule::in(['DNI', 'RUC', 'CE'])],
            'document_number' => ['nullable', 'string', 'max:20'],
            'default_address' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'email.unique' => 'El correo electrónico ya está registrado.',
            'password.confirmed' => 'Las contraseñas ingresadas no coinciden.',
        ]);

        $canonicalRole = Role::query()
            ->where('guard_name', 'web')
            ->where('name', trim($validated['role']))
            ->firstOrFail()
            ->name;

        if (strcasecmp($canonicalRole, 'SuperAdmin') === 0 && ! $actor->hasRole('SuperAdmin')) {
            abort(403, 'Solo un SuperAdmin puede crear usuarios con rol SuperAdmin.');
        }

        /** @var User $created */
        $created = DB::transaction(function () use ($validated, $canonicalRole, $actor, $request): User {
            $user = new User();
            $user->fill([
                'name' => trim($validated['name']),
                'email' => mb_strtolower(trim($validated['email'])),
                'password' => $validated['password'],
                'phone' => $validated['phone'] ?? null,
                'document_type' => $validated['document_type'] ?? 'DNI',
                'document_number' => $validated['document_number'] ?? null,
                'default_address' => $validated['default_address'] ?? null,
                'district' => $validated['district'] ?? null,
                'city' => ! empty($validated['city']) ? trim((string) $validated['city']) : 'Lima',
            ]);
            $user->forceFill([
                'email_verified_at' => now(),
                'customer_tier' => CustomerTier::from($validated['customer_tier']),
                'is_active' => $request->boolean('is_active', true),
                'two_factor_enabled' => false,
            ]);
            $user->save();

            $user->syncRoles([$canonicalRole]);

            SecurityActionTriggered::dispatch(
                $actor,
                'admin_user_created',
                $canonicalRole === 'SuperAdmin' ? 'warning' : 'info',
                "Usuario {$user->email} creado con rol {$canonicalRole}.",
                [
                    'target_user_id' => $user->id,
                    'target_email' => $user->email,
                    'role' => $canonicalRole,
                    'customer_tier' => $validated['customer_tier'],
                    'is_active' => (bool) $user->is_active,
                ],
            );

            return $user;
        });

        return redirect()
            ->route('admin.users.index')
            ->with('admin_success', "Usuario {$created->name} ({$created->email}) creado correctamente.");
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        $user->load('roles');

        return view('admin.users.form', [
            'targetUser' => $user,
            'roles' => $this->assignableRolesFor(Auth::user()),
            'customerTiers' => CustomerTier::cases(),
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        /** @var User $actor */
        $actor = Auth::user();
        $requestedRole = trim((string) $request->input('role', ''));

        $this->authorize('assignRole', [$user, $requestedRole]);

        if ($request->has('email')) {
            $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'role' => ['required', 'string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            'customer_tier' => ['required', 'string', Rule::in(CustomerTier::values())],
            'phone' => ['nullable', 'string', 'max:25'],
            'document_type' => ['nullable', 'string', Rule::in(['DNI', 'RUC', 'CE'])],
            'document_number' => ['nullable', 'string', 'max:20'],
            'default_address' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $canonicalRole = Role::query()
            ->where('guard_name', 'web')
            ->where('name', trim($validated['role']))
            ->firstOrFail()
            ->name;

        /** @var User $updatedUser */
        $updatedUser = DB::transaction(function () use ($user, $validated, $canonicalRole, $actor, $request): User {
            /** @var User $lockedUser */
            $lockedUser = User::query()->with('roles')->lockForUpdate()->findOrFail($user->id);

            $wasSuperAdmin = $lockedUser->hasRole('SuperAdmin');
            $newRole = $canonicalRole;

            if (($wasSuperAdmin || strcasecmp($newRole, 'SuperAdmin') === 0) && ! $actor->hasRole('SuperAdmin')) {
                abort(403, 'Solo un SuperAdmin puede modificar usuarios o asignar el rol SuperAdmin.');
            }

            if ($wasSuperAdmin && $newRole !== 'SuperAdmin') {
                if ($lockedUser->id === $actor->id) {
                    throw ValidationException::withMessages([
                        'role' => 'No puedes retirar tu propio rol SuperAdmin.',
                    ]);
                }

                $otherActiveSuperAdmins = User::role('SuperAdmin')
                    ->where('is_active', true)
                    ->where('id', '!=', $lockedUser->id)
                    ->count();

                if ($otherActiveSuperAdmins === 0) {
                    throw ValidationException::withMessages([
                        'role' => 'Debe existir al menos otro SuperAdmin activo antes de cambiar este rol.',
                    ]);
                }
            }

            $newActive = $request->boolean('is_active');
            if ($lockedUser->id === $actor->id && ! $newActive) {
                throw ValidationException::withMessages([
                    'is_active' => 'No puedes desactivar tu propia cuenta administrativa.',
                ]);
            }

            if ($wasSuperAdmin && ! $newActive) {
                $otherActiveSuperAdmins = User::role('SuperAdmin')
                    ->where('is_active', true)
                    ->where('id', '!=', $lockedUser->id)
                    ->count();

                if ($otherActiveSuperAdmins === 0) {
                    throw ValidationException::withMessages([
                        'is_active' => 'No puedes desactivar al último SuperAdmin activo del sistema.',
                    ]);
                }
            }

            $oldRole = $lockedUser->getRoleNames()->first();

            $lockedUser->fill([
                'name' => trim($validated['name']),
                'email' => mb_strtolower(trim($validated['email'])),
                'phone' => $validated['phone'] ?? null,
                'document_type' => $validated['document_type'] ?? 'DNI',
                'document_number' => $validated['document_number'] ?? null,
                'default_address' => $validated['default_address'] ?? null,
                'district' => $validated['district'] ?? null,
                'city' => ! empty($validated['city']) ? trim((string) $validated['city']) : 'Lima',
            ]);

            $sensitive = [
                'customer_tier' => CustomerTier::from($validated['customer_tier']),
                'is_active' => $newActive,
            ];

            if (! empty($validated['password'])) {
                $sensitive['password'] = $validated['password'];
            }

            $lockedUser->forceFill($sensitive)->save();
            $lockedUser->syncRoles([$newRole]);

            $roleChanged = $oldRole !== $newRole;

            SecurityActionTriggered::dispatch(
                $actor,
                $roleChanged ? 'admin_user_role_changed' : 'admin_user_updated',
                $roleChanged || $newRole === 'SuperAdmin' ? 'warning' : 'info',
                "Usuario {$lockedUser->email} actualizado (rol: {$oldRole} -> {$newRole}, activo: " . ($newActive ? 'sí' : 'no') . ').',
                [
                    'target_user_id' => $lockedUser->id,
                    'target_email' => $lockedUser->email,
                    'old_role' => $oldRole,
                    'new_role' => $newRole,
                    'is_active' => $newActive,
                ],
            );

            return $lockedUser;
        });

        return redirect()
            ->route('admin.users.index')
            ->with('admin_success', "Usuario {$updatedUser->email} actualizado correctamente.");
    }

    public function toggleActive(User $user): RedirectResponse
    {
        $this->authorize('toggleActive', $user);

        /** @var User $actor */
        $actor = Auth::user();

        $newState = DB::transaction(function () use ($user, $actor): bool {
            /** @var User $lockedUser */
            $lockedUser = User::query()->with('roles')->lockForUpdate()->findOrFail($user->id);

            if ($lockedUser->id === $actor->id) {
                abort(403, 'No puedes desactivar tu propia cuenta.');
            }

            if ($lockedUser->hasRole('SuperAdmin') && ! $actor->hasRole('SuperAdmin')) {
                abort(403, 'Solo un SuperAdmin puede activar o desactivar a otro SuperAdmin.');
            }

            $targetActive = ! (bool) $lockedUser->is_active;

            if ($lockedUser->hasRole('SuperAdmin') && ! $targetActive) {
                $otherActiveSuperAdmins = User::role('SuperAdmin')
                    ->where('is_active', true)
                    ->where('id', '!=', $lockedUser->id)
                    ->count();

                if ($otherActiveSuperAdmins === 0) {
                    throw ValidationException::withMessages([
                        'user' => 'No se puede desactivar al último SuperAdmin activo.',
                    ]);
                }
            }

            $lockedUser->forceFill([
                'is_active' => $targetActive,
            ])->save();

            SecurityActionTriggered::dispatch(
                $actor,
                'admin_user_status_toggled',
                'warning',
                "Cuenta {$lockedUser->email} " . ($targetActive ? 'activada' : 'desactivada') . " por {$actor->email}.",
                [
                    'target_user_id' => $lockedUser->id,
                    'target_email' => $lockedUser->email,
                    'is_active' => $targetActive,
                ],
            );

            return $targetActive;
        });

        return redirect()
            ->route('admin.users.index')
            ->with('admin_success', "Cuenta {$user->email} " . ($newState ? 'reactivada' : 'desactivada') . ' correctamente.');
    }

    public function resetTwoFactor(User $user): RedirectResponse
    {
        $this->authorize('resetTwoFactor', $user);

        /** @var User $actor */
        $actor = Auth::user();

        DB::transaction(function () use ($user, $actor): void {
            /** @var User $lockedUser */
            $lockedUser = User::query()->with('roles')->lockForUpdate()->findOrFail($user->id);

            if ($lockedUser->hasRole('SuperAdmin') && ! $actor->hasRole('SuperAdmin')) {
                abort(403, 'Solo un SuperAdmin puede restablecer el 2FA de otro SuperAdmin.');
            }

            $lockedUser->forceFill([
                'two_factor_enabled' => false,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ])->save();

            SecurityActionTriggered::dispatch(
                $actor,
                'admin_2fa_reset',
                'critical',
                "2FA TOTP restablecido administrativamente para {$lockedUser->email} por {$actor->email}.",
                [
                    'target_user_id' => $lockedUser->id,
                    'target_email' => $lockedUser->email,
                ],
            );
        });

        return redirect()
            ->route('admin.users.index')
            ->with('admin_success', "Autenticación 2FA de {$user->email} restablecida correctamente.");
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        /** @var User $actor */
        $actor = Auth::user();

        if ($user->orders()->exists() || $user->inventoryMovements()->exists()) {
            return back()->with('admin_error', "El usuario {$user->email} posee historial de pedidos o Kardex. Desactívalo para preservar la trazabilidad.");
        }

        DB::transaction(function () use ($user, $actor): void {
            /** @var User $lockedUser */
            $lockedUser = User::query()->with('roles')->lockForUpdate()->findOrFail($user->id);

            if ($lockedUser->id === $actor->id) {
                abort(403, 'No puedes eliminar tu propia cuenta.');
            }

            if ($lockedUser->hasRole('SuperAdmin') && ! $actor->hasRole('SuperAdmin')) {
                abort(403, 'Solo un SuperAdmin puede eliminar una cuenta SuperAdmin.');
            }

            if ($lockedUser->hasRole('SuperAdmin')) {
                $otherActiveSuperAdmins = User::role('SuperAdmin')
                    ->where('is_active', true)
                    ->where('id', '!=', $lockedUser->id)
                    ->count();

                if ($otherActiveSuperAdmins === 0) {
                    throw ValidationException::withMessages([
                        'user' => 'No se puede eliminar al último SuperAdmin del sistema.',
                    ]);
                }
            }

            $email = $lockedUser->email;
            $targetId = $lockedUser->id;

            $lockedUser->delete();

            SecurityActionTriggered::dispatch(
                $actor,
                'admin_user_deleted',
                'warning',
                "Usuario {$email} eliminado por {$actor->email}.",
                ['target_user_id' => $targetId, 'target_email' => $email],
            );
        });

        return redirect()
            ->route('admin.users.index')
            ->with('admin_success', "Usuario {$user->email} eliminado.");
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Role>
     */
    private function assignableRolesFor(?User $actor): \Illuminate\Database\Eloquent\Collection
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->when($actor === null || ! $actor->hasRole('SuperAdmin'), fn ($q) => $q->where('name', '!=', 'SuperAdmin'))
            ->orderBy('name')
            ->get();
    }
}
