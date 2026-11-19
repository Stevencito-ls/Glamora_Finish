@extends('layouts.storefront')

@section('title', 'Seguridad & 2FA TOTP — Glamora Perú')
@section('meta_robots', 'noindex, nofollow')

@section('content')
    @component('account.partials.shell')
        {{-- Profile Information --}}
        <div class="card-glamora p-6">
            <h2 class="font-serif text-2xl font-semibold text-glamora-charcoal">
                Datos Personales &amp; Facturación
            </h2>

            <form action="{{ route('account.security.profile') }}" method="POST" class="mt-5 grid gap-4 sm:grid-cols-2">
                @csrf
                @method('PATCH')

                <div>
                    <label for="prof_name" class="mb-1 block text-xs font-semibold text-glamora-charcoal">Nombre completo *</label>
                    <input id="prof_name" type="text" name="name" value="{{ old('name', $user->name) }}" required class="input-glamora">
                </div>

                <div>
                    <label for="prof_phone" class="mb-1 block text-xs font-semibold text-glamora-charcoal">Teléfono *</label>
                    <input id="prof_phone" type="text" name="phone" value="{{ old('phone', $user->phone) }}" required class="input-glamora">
                </div>

                <div>
                    <label for="prof_doc_type" class="mb-1 block text-xs font-semibold text-glamora-charcoal">Tipo documento *</label>
                    <select id="prof_doc_type" name="document_type" class="input-glamora">
                        <option value="DNI" @selected(old('document_type', $user->document_type) === 'DNI')>DNI</option>
                        <option value="RUC" @selected(old('document_type', $user->document_type) === 'RUC')>RUC</option>
                        <option value="CE" @selected(old('document_type', $user->document_type) === 'CE')>CE</option>
                    </select>
                </div>

                <div>
                    <label for="prof_doc_num" class="mb-1 block text-xs font-semibold text-glamora-charcoal">N.º Documento *</label>
                    <input id="prof_doc_num" type="text" name="document_number" value="{{ old('document_number', $user->document_number) }}" required class="input-glamora font-mono">
                </div>

                <div class="sm:col-span-2">
                    <label for="prof_addr" class="mb-1 block text-xs font-semibold text-glamora-charcoal">Dirección principal</label>
                    <input id="prof_addr" type="text" name="default_address" value="{{ old('default_address', $user->default_address) }}" class="input-glamora">
                </div>

                <div>
                    <label for="prof_district" class="mb-1 block text-xs font-semibold text-glamora-charcoal">Distrito</label>
                    <input id="prof_district" type="text" name="district" value="{{ old('district', $user->district) }}" class="input-glamora">
                </div>

                <div>
                    <label for="prof_city" class="mb-1 block text-xs font-semibold text-glamora-charcoal">Ciudad</label>
                    <input id="prof_city" type="text" name="city" value="{{ old('city', $user->city) }}" class="input-glamora">
                </div>

                <div class="sm:col-span-2">
                    <button type="submit" class="btn-glamora-primary">
                        Guardar Cambios de Perfil
                    </button>
                </div>
            </form>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            {{-- Change Password --}}
            <div class="card-glamora p-6">
                <h2 class="font-serif text-2xl font-semibold text-glamora-charcoal">
                    Actualizar Contraseña
                </h2>
                <p class="mt-1 text-xs text-glamora-muted">
                    Mínimo 8 caracteres incluyendo mayúsculas, minúsculas, números y símbolos.
                </p>

                <form action="{{ route('account.security.password') }}" method="POST" class="mt-5 space-y-3.5">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label for="current_password" class="mb-1 block text-xs font-semibold text-glamora-charcoal">Contraseña actual *</label>
                        <input id="current_password" type="password" name="current_password" required class="input-glamora">
                    </div>

                    <div>
                        <label for="new_password" class="mb-1 block text-xs font-semibold text-glamora-charcoal">Nueva contraseña *</label>
                        <input id="new_password" type="password" name="password" required class="input-glamora">
                    </div>

                    <div>
                        <label for="new_password_confirmation" class="mb-1 block text-xs font-semibold text-glamora-charcoal">Confirmar nueva contraseña *</label>
                        <input id="new_password_confirmation" type="password" name="password_confirmation" required class="input-glamora">
                    </div>

                    <button type="submit" class="btn-glamora-outline w-full justify-center">
                        Cambiar Contraseña
                    </button>
                </form>
            </div>

            {{-- 2FA TOTP Setup & Management --}}
            <div class="card-glamora p-6">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="font-serif text-2xl font-semibold text-glamora-charcoal">
                        Autenticación 2FA (TOTP)
                    </h2>
                    <span class="rounded-full px-3 py-1 text-[11px] font-bold uppercase {{ $user->two_factor_enabled ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                        {{ $user->two_factor_enabled ? 'Activo' : 'Inactivo' }}
                    </span>
                </div>
                <p class="mt-1 text-xs text-glamora-muted">
                    Implementación compatible con Google Authenticator, Authy o 1Password (RFC 6238).
                </p>

                @if (session('two_factor_recovery_codes'))
                    <div class="mt-4 rounded-2xl border border-emerald-300 bg-emerald-50 p-4">
                        <p class="text-xs font-bold uppercase tracking-wider text-emerald-900">
                            Guarda tus 8 Códigos de Recuperación (única vez visible)
                        </p>
                        <div class="mt-2.5 grid grid-cols-2 gap-2 font-mono text-xs text-emerald-950">
                            @foreach (session('two_factor_recovery_codes') as $recCode)
                                <span class="rounded-lg border border-emerald-200 bg-white px-2.5 py-1 text-center">{{ $recCode }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (! $user->two_factor_enabled && $pendingTotpUri === null)
                    <form action="{{ route('account.security.2fa.enable') }}" method="POST" class="mt-5">
                        @csrf
                        <button type="submit" class="btn-glamora-primary w-full justify-center">
                            Activar Verificación en Dos Pasos (2FA)
                        </button>
                    </form>
                @elseif (! $user->two_factor_enabled && $pendingTotpUri !== null)
                    <div class="mt-5 space-y-4">
                        <div class="rounded-2xl border border-glamora-gold/50 bg-glamora-cream p-4 text-xs">
                            <p class="font-bold text-glamora-charcoal">1. Registra esta clave en tu App Autenticadora:</p>
                            <p class="mt-2 font-mono text-sm font-bold tracking-widest text-glamora-terracotta break-all">
                                {{ $user->two_factor_secret }}
                            </p>
                            <p class="mt-2 text-[11px] text-glamora-muted break-all">
                                URI otpauth: <span class="font-mono">{{ $pendingTotpUri }}</span>
                            </p>
                        </div>

                        <form action="{{ route('account.security.2fa.confirm') }}" method="POST" class="space-y-3">
                            @csrf
                            <div>
                                <label for="totp_confirm_code" class="mb-1 block text-xs font-semibold text-glamora-charcoal">
                                    2. Ingresa el código de 6 dígitos para confirmar *
                                </label>
                                <input
                                    id="totp_confirm_code"
                                    type="text"
                                    name="code"
                                    required
                                    maxlength="6"
                                    placeholder="000000"
                                    class="input-glamora text-center font-mono text-lg tracking-widest"
                                >
                            </div>
                            <button type="submit" class="btn-glamora-primary w-full justify-center">
                                Confirmar y Activar 2FA
                            </button>
                        </form>
                    </div>
                @else
                    <div class="mt-5 space-y-4">
                        <div class="rounded-2xl border border-emerald-200 bg-emerald-50/70 p-4 text-xs text-emerald-950">
                            <p><strong>2FA verificado el:</strong> {{ $user->two_factor_confirmed_at?->format('d/m/Y H:i') ?? 'Activo' }}</p>
                            <p class="mt-1"><strong>Códigos de recuperación restantes:</strong> {{ $recoveryCodesCount }} de 8</p>
                        </div>

                        <form action="{{ route('account.security.2fa.disable') }}" method="POST" class="space-y-3 border-t border-glamora-blush/30 pt-4">
                            @csrf
                            <div>
                                <label for="disable_2fa_password" class="mb-1 block text-xs font-semibold text-glamora-charcoal">
                                    Contraseña actual para desactivar 2FA *
                                </label>
                                <input id="disable_2fa_password" type="password" name="current_password" required class="input-glamora">
                            </div>
                            <button type="submit" class="w-full rounded-full border border-rose-300 bg-rose-50 px-5 py-2.5 text-xs font-bold uppercase tracking-wider text-rose-700 transition hover:bg-rose-600 hover:text-white">
                                Desactivar 2FA
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        </div>

        {{-- Security Logs History --}}
        <div class="card-glamora overflow-hidden">
            <div class="border-b border-glamora-blush/30 bg-white px-6 py-4">
                <h2 class="font-serif text-2xl font-semibold text-glamora-charcoal">
                    Actividad Reciente de Seguridad
                </h2>
            </div>

            @if ($logs->isEmpty())
                <div class="p-6 text-center text-sm text-glamora-muted">
                    No existen eventos de auditoría recientes para esta cuenta.
                </div>
            @else
                <div class="divide-y divide-glamora-blush/25 bg-white">
                    @foreach ($logs as $log)
                        <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-3.5 text-xs">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span @class([
                                        'rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase',
                                        'bg-slate-100 text-slate-700' => $log->severity === 'info',
                                        'bg-amber-100 text-amber-800' => $log->severity === 'warning',
                                        'bg-rose-100 text-rose-800' => $log->severity === 'critical',
                                    ])>
                                        {{ $log->event_type }}
                                    </span>
                                    <span class="font-medium text-glamora-charcoal">{{ $log->description }}</span>
                                </div>
                            </div>
                            <div class="font-mono text-[11px] text-glamora-muted">
                                {{ $log->ip_address ?? '127.0.0.1' }} · {{ $log->created_at?->format('d/m/Y H:i') }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endcomponent
@endsection
