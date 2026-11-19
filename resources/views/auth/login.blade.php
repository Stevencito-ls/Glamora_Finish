@extends('layouts.storefront')

@section('title', 'Iniciar Sesión — Glamora Perú')
@section('meta_robots', 'noindex, nofollow')

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="grid items-stretch gap-8 lg:grid-cols-12">
            {{-- Editorial Left Card --}}
            <div class="hidden flex-col justify-between rounded-3xl border border-glamora-gold/35 bg-glamora-charcoal p-10 text-glamora-cream lg:col-span-5 lg:flex">
                <div>
                    <span class="inline-flex items-center gap-2 rounded-full border border-glamora-gold/50 px-3 py-1 text-[11px] font-semibold uppercase tracking-widest text-glamora-gold">
                        Acceso Clientes &amp; Socios B2B
                    </span>
                    <h1 class="mt-6 font-serif text-4xl font-semibold leading-tight text-white">
                        Tu atelier privado de cosmética y pedidos trazables.
                    </h1>
                    <p class="mt-4 text-sm leading-relaxed text-glamora-cream/80">
                        Accede a tus tarifas escalonadas Bronce, Plata u Oro, consulta tus lotes FEFO asignados, descarga comprobantes PDF y administra la seguridad 2FA de tu cuenta.
                    </p>
                </div>

                <div class="mt-8 rounded-2xl border border-white/10 bg-white/5 p-5 text-xs text-glamora-cream/80">
                    <p class="font-semibold text-glamora-gold">Protección de Cuenta Glamora</p>
                    <p class="mt-1.5">
                        Toda sesión queda registrada en nuestra bitácora de auditoría con soporte para autenticación en dos pasos (TOTP RFC 6238).
                    </p>
                </div>
            </div>

            {{-- Form Card --}}
            <div class="lg:col-span-7">
                <div class="card-glamora p-8 sm:p-10">
                    <h2 class="font-serif text-3xl font-semibold text-glamora-charcoal">
                        Iniciar Sesión
                    </h2>
                    <p class="mt-1.5 text-sm text-glamora-muted">
                        Ingresa con tu correo electrónico y contraseña registrada.
                    </p>

                    <form action="{{ route('login.store') }}" method="POST" class="mt-7 space-y-5">
                        @csrf

                        <div>
                            <label for="email" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                                Correo electrónico
                            </label>
                            <input
                                id="email"
                                type="email"
                                name="email"
                                value="{{ old('email') }}"
                                required
                                autofocus
                                autocomplete="email"
                                placeholder="cliente@glamora.pe"
                                class="input-glamora"
                            >
                            @error('email')
                                <p class="mt-1.5 text-xs font-medium text-rose-700">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="password" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                                Contraseña
                            </label>
                            <input
                                id="password"
                                type="password"
                                name="password"
                                required
                                autocomplete="current-password"
                                placeholder="••••••••"
                                class="input-glamora"
                            >
                            @error('password')
                                <p class="mt-1.5 text-xs font-medium text-rose-700">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center justify-between">
                            <label class="inline-flex cursor-pointer items-center gap-2 text-xs text-glamora-muted">
                                <input
                                    type="checkbox"
                                    name="remember"
                                    value="1"
                                    @checked(old('remember'))
                                    class="rounded border-glamora-charcoal/30 accent-glamora-terracotta"
                                >
                                Mantener sesión iniciada
                            </label>
                        </div>

                        <button type="submit" class="btn-glamora-primary w-full justify-center">
                            Ingresar a Mi Cuenta &rarr;
                        </button>
                    </form>

                    <div class="mt-7 border-t border-glamora-blush/30 pt-6 text-center text-sm text-glamora-muted">
                        ¿Aún no tienes cuenta en Glamora?
                        <a href="{{ route('register') }}" class="font-semibold text-glamora-terracotta hover:underline">
                            Crear cuenta gratis
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
