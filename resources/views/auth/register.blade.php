@extends('layouts.storefront')

@section('title', 'Crear Cuenta — Glamora Perú')
@section('meta_robots', 'noindex, nofollow')

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="card-glamora p-8 sm:p-10">
            <div class="border-b border-glamora-blush/30 pb-5">
                <span class="badge-glamora-gold">Registro Cliente Minorista &amp; Postulantes B2B</span>
                <h1 class="mt-3 font-serif text-3xl font-semibold text-glamora-charcoal sm:text-4xl">
                    Crea tu Cuenta Glamora
                </h1>
                <p class="mt-1.5 text-sm text-glamora-muted">
                    Guarda tus direcciones de entrega, descarga cotizaciones y realiza seguimiento de pedidos y devoluciones RMA.
                </p>
            </div>

            <form action="{{ route('register.store') }}" method="POST" class="mt-7 space-y-5">
                @csrf

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="name" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                            Nombre completo *
                        </label>
                        <input
                            id="name"
                            type="text"
                            name="name"
                            value="{{ old('name') }}"
                            required
                            autocomplete="name"
                            placeholder="Ej. Sofía Navarro"
                            class="input-glamora"
                        >
                        @error('name')
                            <p class="mt-1 text-xs font-medium text-rose-700">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="email" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                            Correo electrónico *
                        </label>
                        <input
                            id="email"
                            type="email"
                            name="email"
                            value="{{ old('email') }}"
                            required
                            autocomplete="email"
                            placeholder="correo@ejemplo.pe"
                            class="input-glamora"
                        >
                        @error('email')
                            <p class="mt-1 text-xs font-medium text-rose-700">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="phone" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                            Teléfono / WhatsApp *
                        </label>
                        <input
                            id="phone"
                            type="text"
                            name="phone"
                            value="{{ old('phone') }}"
                            required
                            placeholder="+51 987 654 321"
                            class="input-glamora"
                        >
                        @error('phone')
                            <p class="mt-1 text-xs font-medium text-rose-700">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="document_type" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                            Tipo de documento *
                        </label>
                        <select id="document_type" name="document_type" class="input-glamora">
                            <option value="DNI" @selected(old('document_type', 'DNI') === 'DNI')>DNI</option>
                            <option value="RUC" @selected(old('document_type') === 'RUC')>RUC</option>
                            <option value="CE" @selected(old('document_type') === 'CE')>Carné de Extranjería (CE)</option>
                        </select>
                        @error('document_type')
                            <p class="mt-1 text-xs font-medium text-rose-700">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="document_number" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                            Número de documento *
                        </label>
                        <input
                            id="document_number"
                            type="text"
                            name="document_number"
                            value="{{ old('document_number') }}"
                            required
                            placeholder="72456789"
                            class="input-glamora font-mono"
                        >
                        @error('document_number')
                            <p class="mt-1 text-xs font-medium text-rose-700">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                            Contraseña segura *
                        </label>
                        <input
                            id="password"
                            type="password"
                            name="password"
                            required
                            autocomplete="new-password"
                            placeholder="Mín. 8 caracteres"
                            class="input-glamora"
                        >
                        @error('password')
                            <p class="mt-1 text-xs font-medium text-rose-700">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                            Confirmar contraseña *
                        </label>
                        <input
                            id="password_confirmation"
                            type="password"
                            name="password_confirmation"
                            required
                            autocomplete="new-password"
                            placeholder="Repite la contraseña"
                            class="input-glamora"
                        >
                    </div>
                </div>

                <p class="rounded-xl border border-glamora-blush/40 bg-glamora-cream px-4 py-2.5 text-xs text-glamora-muted">
                    Tu contraseña debe incluir al menos 8 caracteres, mayúsculas, minúsculas, números y un símbolo (ej. <span class="font-mono">Glamora#2026</span>).
                </p>

                <button type="submit" class="btn-glamora-primary w-full justify-center">
                    Registrarme en Glamora &rarr;
                </button>
            </form>

            <div class="mt-7 border-t border-glamora-blush/30 pt-6 text-center text-sm text-glamora-muted">
                ¿Ya tienes una cuenta registrada?
                <a href="{{ route('login') }}" class="font-semibold text-glamora-terracotta hover:underline">
                    Iniciar sesión
                </a>
            </div>
        </div>
    </div>
@endsection
