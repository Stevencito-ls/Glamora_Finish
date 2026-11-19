<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\CustomerTier;
use App\Http\Controllers\Controller;
use App\Models\User;
use Closure;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class RegisterController extends Controller
{
    public function showRegistrationForm(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:150', Rule::unique('users', 'email')],
            'phone' => ['required', 'string', 'min:7', 'max:25'],
            'document_type' => ['required', 'string', Rule::in(['DNI', 'RUC', 'CE'])],
            'document_number' => [
                'required',
                'string',
                'min:6',
                'max:20',
                function (string $attribute, mixed $value, Closure $fail) use ($request): void {
                    $docType = (string) $request->input('document_type', 'DNI');
                    $clean = trim((string) $value);

                    if ($docType === 'DNI' && preg_match('/^\d{8}$/', $clean) !== 1) {
                        $fail('El DNI debe contener exactamente 8 dígitos numéricos.');
                    } elseif ($docType === 'RUC' && preg_match('/^\d{11}$/', $clean) !== 1) {
                        $fail('El RUC debe contener exactamente 11 dígitos numéricos.');
                    } elseif ($docType === 'CE' && preg_match('/^[A-Za-z0-9\-]{6,20}$/', $clean) !== 1) {
                        $fail('El Carné de Extranjería debe tener entre 6 y 20 caracteres alfanuméricos.');
                    }
                },
            ],
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)->mixedCase()->numbers()->symbols(),
            ],
        ], [
            'name.required' => 'Ingresa tu nombre completo.',
            'email.required' => 'Ingresa tu correo electrónico.',
            'email.unique' => 'Ya existe una cuenta registrada con este correo electrónico.',
            'phone.required' => 'Ingresa un número de teléfono o WhatsApp.',
            'document_number.required' => 'Ingresa tu número de documento.',
            'password.required' => 'Crea una contraseña segura.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
        ]);

        /** @var User $user */
        $user = DB::transaction(function () use ($validated): User {
            $user = new User();
            $user->fill([
                'name' => trim($validated['name']),
                'email' => mb_strtolower(trim($validated['email'])),
                'password' => $validated['password'],
                'phone' => trim($validated['phone']),
                'document_type' => $validated['document_type'],
                'document_number' => trim($validated['document_number']),
                'city' => 'Lima',
            ]);
            $user->forceFill([
                'customer_tier' => CustomerTier::Minorista,
                'is_active' => true,
                'last_login_at' => now(),
            ]);
            $user->save();

            $clienteRole = Role::query()->firstOrCreate([
                'name' => 'Cliente',
                'guard_name' => 'web',
            ]);
            $user->assignRole($clienteRole);

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()
            ->route('account.index')
            ->with('account_success', '¡Bienvenida a Glamora Perú! Tu cuenta ha sido creada correctamente.');
    }
}
