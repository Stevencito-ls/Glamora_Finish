@extends('layouts.storefront')

@section('title', 'Verificación 2FA — Glamora Perú')
@section('meta_robots', 'noindex, nofollow')

@section('content')
    <div class="mx-auto max-w-lg px-4 py-14 sm:px-6 lg:px-8">
        <div class="card-glamora p-8 sm:p-10">
            <div class="text-center">
                <span class="badge-glamora-gold">Seguridad TOTP RFC 6238</span>
                <h1 class="mt-3 font-serif text-3xl font-semibold text-glamora-charcoal">
                    Verificación en Dos Pasos
                </h1>
                <p class="mt-2 text-sm text-glamora-muted">
                    Ingresa el código de 6 dígitos generado por tu app autenticadora o uno de tus 8 códigos de recuperación.
                </p>
            </div>

            <form action="{{ route('two-factor.verify') }}" method="POST" class="mt-7 space-y-5">
                @csrf

                <div>
                    <label for="code" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                        Código TOTP (6 dígitos) o código de recuperación
                    </label>
                    <input
                        id="code"
                        type="text"
                        name="code"
                        value="{{ old('code') }}"
                        required
                        autofocus
                        autocomplete="one-time-code"
                        placeholder="123456 o ABCD-1234"
                        class="input-glamora text-center font-mono text-lg tracking-widest"
                    >
                    @error('code')
                        <p class="mt-1.5 text-xs font-medium text-rose-700">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="btn-glamora-primary w-full justify-center">
                    Verificar Acceso &rarr;
                </button>
            </form>

            <div class="mt-6 text-center">
                <a href="{{ route('login') }}" class="text-xs font-semibold text-glamora-muted hover:text-glamora-terracotta">
                    &larr; Volver al inicio de sesión
                </a>
            </div>
        </div>
    </div>
@endsection
