@extends('layouts.admin')

@section('title', 'Configuración White-Label — Glamora PRO')
@section('page_title', 'Configuración White-Label & Tema Visual')
@section('page_subtitle', 'Identidad de tienda, logotipo, favicon, temas visuales, WhatsApp y parámetros comerciales.')

@section('header_actions')
    <li class="nav-item">
        <a href="{{ route('home') }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-dark rounded-pill px-3 text-xs font-semibold">
            <i class="fas fa-eye me-1"></i> Previsualizar Tienda
        </a>
    </li>
@endsection

@section('content')
    @php
        $initialTheme = old('active_theme', $activeThemeKey ?? 'glamora_nude');
    @endphp

    <form
        action="{{ route('admin.settings.update') }}"
        method="POST"
        enctype="multipart/form-data"
        x-data="{
            selectedTheme: @js($initialTheme),
            logoPreview: @js($logoUrl),
            faviconPreview: @js($faviconUrl),
            handleLogoChange(event) {
                const file = event.target.files?.[0];
                if (!file) return;
                this.logoPreview = URL.createObjectURL(file);
            },
            handleFaviconChange(event) {
                const file = event.target.files?.[0];
                if (!file) return;
                this.faviconPreview = URL.createObjectURL(file);
            }
        }"
        class="d-flex flex-column gap-4"
    >
        @csrf
        @method('PUT')

        {{-- 1. Selector Visual de Temas (4 Tarjetas 1-Clic) --}}
        <div class="card card-outline card-dark border-0 shadow-sm mb-0">
            <div class="card-header bg-white d-flex align-items-center justify-content-between py-3 px-4">
                <div>
                    <h2 class="card-title h5 mb-0 fw-bold text-dark">
                        <i class="fas fa-palette me-2 text-warning"></i>
                        Tema Visual de la Tienda (1 Clic)
                    </h2>
                    <small class="text-muted d-block mt-1">
                        Selecciona una paleta editorial para aplicarla vía CSS Variables en todo el storefront y catálogo PDF.
                    </small>
                </div>
                <span class="badge bg-dark text-warning px-3 py-2">
                    Activo: <span x-text="selectedTheme"></span>
                </span>
            </div>

            <div class="card-body p-4">
                <input type="hidden" name="active_theme" :value="selectedTheme">

                <div class="row g-3">
                    @foreach ($themes as $themeKey => $themePreset)
                        <div class="col-12 col-md-6 col-xl-3">
                            <div
                                role="button"
                                tabindex="0"
                                @click="selectedTheme = '{{ $themeKey }}'"
                                @keydown.enter.prevent="selectedTheme = '{{ $themeKey }}'"
                                @keydown.space.prevent="selectedTheme = '{{ $themeKey }}'"
                                :class="selectedTheme === '{{ $themeKey }}' ? 'border-dark shadow bg-light' : 'border-secondary-subtle bg-white'"
                                class="card h-100 mb-0 border-2 p-3 transition"
                                style="cursor: pointer;"
                            >
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="fw-bold text-dark">{{ $themePreset['label'] }}</span>
                                    <span
                                        x-show="selectedTheme === '{{ $themeKey }}'"
                                        class="badge bg-success"
                                    >
                                        <i class="fas fa-check me-1"></i> Seleccionado
                                    </span>
                                </div>

                                <div class="d-flex align-items-center gap-1.5 mb-3">
                                    @foreach (['cream', 'terracotta', 'blush', 'gold', 'charcoal'] as $swatchKey)
                                        <span
                                            class="d-inline-block rounded-circle border"
                                            style="width: 24px; height: 24px; background-color: {{ $themePreset[$swatchKey] }};"
                                            title="{{ $swatchKey }}: {{ $themePreset[$swatchKey] }}"
                                        ></span>
                                    @endforeach
                                </div>

                                <p class="small text-muted mb-3 flex-grow-1">
                                    {{ $themePreset['description'] }}
                                </p>

                                <button
                                    type="button"
                                    @click.stop="selectedTheme = '{{ $themeKey }}'"
                                    :class="selectedTheme === '{{ $themeKey }}' ? 'btn-dark' : 'btn-outline-secondary'"
                                    class="btn btn-sm rounded-pill w-100"
                                >
                                    <span x-text="selectedTheme === '{{ $themeKey }}' ? 'Tema Activo' : 'Usar este tema'"></span>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
                @error('active_theme')
                    <div class="text-danger small mt-2">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="row g-4">
            {{-- 2. Identidad Comercial & Fiscal --}}
            <div class="col-12 col-lg-7">
                <div class="card border-0 shadow-sm h-100 mb-0">
                    <div class="card-header bg-white py-3 px-4">
                        <h2 class="card-title h5 mb-0 fw-bold text-dark">
                            <i class="fas fa-store me-2 text-primary"></i>
                            Identidad Comercial &amp; Datos Fiscales
                        </h2>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-12 col-md-7">
                                <label for="store_name" class="form-label fw-semibold small">Nombre comercial de la tienda *</label>
                                <input
                                    id="store_name"
                                    type="text"
                                    name="store_name"
                                    value="{{ old('store_name', $settings['store_name'] ?? 'Glamora Perú') }}"
                                    required
                                    class="form-control @error('store_name') is-invalid @enderror"
                                >
                                @error('store_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12 col-md-5">
                                <label for="store_ruc" class="form-label fw-semibold small">RUC (11 dígitos)</label>
                                <input
                                    id="store_ruc"
                                    type="text"
                                    name="store_ruc"
                                    maxlength="11"
                                    value="{{ old('store_ruc', $settings['store_ruc'] ?? '') }}"
                                    placeholder="20608945123"
                                    class="form-control font-monospace @error('store_ruc') is-invalid @enderror"
                                >
                                @error('store_ruc') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12">
                                <label for="store_tagline" class="form-label fw-semibold small">Subtítulo / Eslogan</label>
                                <input
                                    id="store_tagline"
                                    type="text"
                                    name="store_tagline"
                                    value="{{ old('store_tagline', $settings['store_tagline'] ?? '') }}"
                                    class="form-control @error('store_tagline') is-invalid @enderror"
                                >
                                @error('store_tagline') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="store_email" class="form-label fw-semibold small">Correo comercial *</label>
                                <input
                                    id="store_email"
                                    type="email"
                                    name="store_email"
                                    value="{{ old('store_email', $settings['store_email'] ?? '') }}"
                                    required
                                    class="form-control @error('store_email') is-invalid @enderror"
                                >
                                @error('store_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="store_phone" class="form-label fw-semibold small">Teléfono de atención</label>
                                <input
                                    id="store_phone"
                                    type="text"
                                    name="store_phone"
                                    value="{{ old('store_phone', $settings['store_phone'] ?? '') }}"
                                    class="form-control @error('store_phone') is-invalid @enderror"
                                >
                                @error('store_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12">
                                <label for="store_address" class="form-label fw-semibold small">Dirección fiscal / Showroom</label>
                                <input
                                    id="store_address"
                                    type="text"
                                    name="store_address"
                                    value="{{ old('store_address', $settings['store_address'] ?? '') }}"
                                    class="form-control @error('store_address') is-invalid @enderror"
                                >
                                @error('store_address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12 col-md-4">
                                <label for="social_instagram" class="form-label fw-semibold small">Instagram URL</label>
                                <input
                                    id="social_instagram"
                                    type="url"
                                    name="social_instagram"
                                    value="{{ old('social_instagram', $settings['social_instagram'] ?? '') }}"
                                    placeholder="https://instagram.com/..."
                                    class="form-control @error('social_instagram') is-invalid @enderror"
                                >
                                @error('social_instagram') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12 col-md-4">
                                <label for="social_facebook" class="form-label fw-semibold small">Facebook URL</label>
                                <input
                                    id="social_facebook"
                                    type="url"
                                    name="social_facebook"
                                    value="{{ old('social_facebook', $settings['social_facebook'] ?? '') }}"
                                    placeholder="https://facebook.com/..."
                                    class="form-control @error('social_facebook') is-invalid @enderror"
                                >
                                @error('social_facebook') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12 col-md-4">
                                <label for="social_tiktok" class="form-label fw-semibold small">TikTok URL</label>
                                <input
                                    id="social_tiktok"
                                    type="url"
                                    name="social_tiktok"
                                    value="{{ old('social_tiktok', $settings['social_tiktok'] ?? '') }}"
                                    placeholder="https://tiktok.com/@..."
                                    class="form-control @error('social_tiktok') is-invalid @enderror"
                                >
                                @error('social_tiktok') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 3. Logotipo & Favicon con Preview Alpine --}}
            <div class="col-12 col-lg-5">
                <div class="card border-0 shadow-sm h-100 mb-0">
                    <div class="card-header bg-white py-3 px-4">
                        <h2 class="card-title h5 mb-0 fw-bold text-dark">
                            <i class="fas fa-image me-2 text-info"></i>
                            Logotipo &amp; Favicon White-Label
                        </h2>
                    </div>
                    <div class="card-body p-4 d-flex flex-column gap-4">
                        {{-- Logo --}}
                        <div class="border rounded p-3 bg-light">
                            <label for="logo_file" class="form-label fw-semibold small">Logotipo de Tienda (JPG, PNG, WEBP · máx. 4 MB)</label>
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="border rounded bg-white d-flex align-items-center justify-content-center overflow-hidden" style="width: 110px; height: 56px;">
                                    <template x-if="logoPreview">
                                        <img :src="logoPreview" alt="Preview logotipo" class="img-fluid" style="max-height: 52px; object-fit: contain;">
                                    </template>
                                    <template x-if="!logoPreview">
                                        <span class="small text-muted">Sin logo</span>
                                    </template>
                                </div>
                                <div class="flex-grow-1">
                                    <input
                                        id="logo_file"
                                        type="file"
                                        name="logo_file"
                                        accept=".jpg,.jpeg,.png,.webp"
                                        @change="handleLogoChange($event)"
                                        class="form-control form-control-sm @error('logo_file') is-invalid @enderror"
                                    >
                                    @error('logo_file') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            @if ($logoUrl)
                                <div class="form-check">
                                    <input id="remove_logo" type="checkbox" name="remove_logo" value="1" class="form-check-input">
                                    <label for="remove_logo" class="form-check-label small text-danger">
                                        Eliminar logotipo actual y volver al monograma
                                    </label>
                                </div>
                            @endif
                        </div>

                        {{-- Favicon --}}
                        <div class="border rounded p-3 bg-light">
                            <label for="favicon_file" class="form-label fw-semibold small">Favicon (PNG, ICO, WEBP · máx. 1 MB)</label>
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="border rounded bg-white d-flex align-items-center justify-content-center overflow-hidden" style="width: 48px; height: 48px;">
                                    <template x-if="faviconPreview">
                                        <img :src="faviconPreview" alt="Preview favicon" style="width: 32px; height: 32px; object-fit: contain;">
                                    </template>
                                    <template x-if="!faviconPreview">
                                        <span class="small text-muted">—</span>
                                    </template>
                                </div>
                                <div class="flex-grow-1">
                                    <input
                                        id="favicon_file"
                                        type="file"
                                        name="favicon_file"
                                        accept=".png,.ico,.jpg,.jpeg,.webp"
                                        @change="handleFaviconChange($event)"
                                        class="form-control form-control-sm @error('favicon_file') is-invalid @enderror"
                                    >
                                    @error('favicon_file') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            @if ($faviconUrl)
                                <div class="form-check">
                                    <input id="remove_favicon" type="checkbox" name="remove_favicon" value="1" class="form-check-input">
                                    <label for="remove_favicon" class="form-check-label small text-danger">
                                        Eliminar favicon actual
                                    </label>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- 4. WhatsApp, Comercio, IGV & Funciones PRO --}}
        <div class="row g-4">
            <div class="col-12 col-lg-6">
                <div class="card border-0 shadow-sm h-100 mb-0">
                    <div class="card-header bg-white py-3 px-4">
                        <h2 class="card-title h5 mb-0 fw-bold text-dark">
                            <i class="fab fa-whatsapp me-2 text-success"></i>
                            Canal WhatsApp Business
                        </h2>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <label for="whatsapp_number" class="form-label fw-semibold small">Número WhatsApp Business (código país + número)</label>
                            <input
                                id="whatsapp_number"
                                type="text"
                                name="whatsapp_number"
                                value="{{ old('whatsapp_number', $settings['whatsapp_number'] ?? '51917328085') }}"
                                placeholder="51917328085"
                                class="form-control font-monospace @error('whatsapp_number') is-invalid @enderror"
                            >
                            @error('whatsapp_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="form-check form-switch mb-2">
                            <input
                                id="whatsapp_checkout_enabled"
                                type="checkbox"
                                name="whatsapp_checkout_enabled"
                                value="1"
                                @checked(old('whatsapp_checkout_enabled', $settings['whatsapp_checkout_enabled'] ?? true))
                                class="form-check-input"
                            >
                            <label for="whatsapp_checkout_enabled" class="form-check-label small fw-semibold">
                                Habilitar Checkout y envío directo de pedidos por WhatsApp
                            </label>
                        </div>

                        <div class="form-check form-switch">
                            <input
                                id="whatsapp_floating_enabled"
                                type="checkbox"
                                name="whatsapp_floating_enabled"
                                value="1"
                                @checked(old('whatsapp_floating_enabled', $settings['whatsapp_floating_enabled'] ?? true))
                                class="form-check-input"
                            >
                            <label for="whatsapp_floating_enabled" class="form-check-label small fw-semibold">
                                Mostrar botón flotante de asesoría WhatsApp en el storefront
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-6">
                <div class="card border-0 shadow-sm h-100 mb-0">
                    <div class="card-header bg-white py-3 px-4">
                        <h2 class="card-title h5 mb-0 fw-bold text-dark">
                            <i class="fas fa-sliders-h me-2 text-secondary"></i>
                            Reglas Comerciales, IGV &amp; Módulos PRO
                        </h2>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3 mb-3">
                            <div class="col-12 col-sm-6">
                                <label for="free_shipping_threshold" class="form-label fw-semibold small">Umbral Envío Gratis (S/) *</label>
                                <input
                                    id="free_shipping_threshold"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    name="free_shipping_threshold"
                                    value="{{ old('free_shipping_threshold', $settings['free_shipping_threshold'] ?? 199) }}"
                                    required
                                    class="form-control @error('free_shipping_threshold') is-invalid @enderror"
                                >
                                @error('free_shipping_threshold') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12 col-sm-6">
                                <label for="tax_rate" class="form-label fw-semibold small">Tasa IGV decimal (ej. 0.18) *</label>
                                <input
                                    id="tax_rate"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="1"
                                    name="tax_rate"
                                    value="{{ old('tax_rate', $settings['tax_rate'] ?? 0.18) }}"
                                    required
                                    class="form-control @error('tax_rate') is-invalid @enderror"
                                >
                                @error('tax_rate') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="form-check form-switch mb-2">
                            <input
                                id="catalog_pdf_enabled"
                                type="checkbox"
                                name="catalog_pdf_enabled"
                                value="1"
                                @checked(old('catalog_pdf_enabled', $settings['catalog_pdf_enabled'] ?? true))
                                class="form-check-input"
                            >
                            <label for="catalog_pdf_enabled" class="form-check-label small fw-semibold">
                                Habilitar descarga pública de Catálogo PDF (/catalogo/pdf)
                            </label>
                        </div>

                        <div class="form-check form-switch">
                            <input
                                id="reviews_auto_approve"
                                type="checkbox"
                                name="reviews_auto_approve"
                                value="1"
                                @checked(old('reviews_auto_approve', $settings['reviews_auto_approve'] ?? false))
                                class="form-check-input"
                            >
                            <label for="reviews_auto_approve" class="form-check-label small fw-semibold">
                                Auto-aprobar reseñas de clientes al enviar (sin moderación previa)
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-dark rounded-pill px-5 py-2 fw-semibold">
                <i class="fas fa-save me-2"></i> Guardar Configuración White-Label
            </button>
        </div>
    </form>
@endsection
