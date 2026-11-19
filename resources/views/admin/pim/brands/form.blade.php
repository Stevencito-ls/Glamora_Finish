@extends('layouts.admin')

@section('title', ($isEdit ? 'Editar Marca' : 'Nueva Marca') . ' — Glamora Perú')
@section('page_title', $isEdit ? 'Editar Marca: ' . $brand->name : 'Registrar Nueva Marca')
@section('page_subtitle', 'Define origen, historia de laboratorio, fotografía/logo, banner editorial y certificación ética.')

@section('header_actions')
    <a href="{{ route('admin.pim.brands.index') }}" class="btn-glamora-outline py-2 px-4 text-xs">
        &larr; Volver a Marcas
    </a>
@endsection

@section('content')
    <form
        action="{{ $isEdit ? route('admin.pim.brands.update', $brand) : route('admin.pim.brands.store') }}"
        method="POST"
        enctype="multipart/form-data"
        x-data="{
            logoPreview: '{{ $isEdit ? $brand->logo_url : '' }}',
            bannerPreview: '{{ $isEdit ? $brand->banner_url : '' }}',
            onLogoFile(e) {
                const f = e.target.files[0];
                if (f) {
                    this.logoPreview = URL.createObjectURL(f);
                }
            },
            onBannerFile(e) {
                const f = e.target.files[0];
                if (f) {
                    this.bannerPreview = URL.createObjectURL(f);
                }
            }
        }"
        class="card-glamora mx-auto max-w-4xl space-y-6 p-6 sm:p-8"
    >
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="brand_name" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                    Nombre de Marca *
                </label>
                <input id="brand_name" type="text" name="name" value="{{ old('name', $brand->name) }}" required class="input-glamora">
            </div>

            <div>
                <label for="brand_slug" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                    Slug URL (opcional)
                </label>
                <input id="brand_slug" type="text" name="slug" value="{{ old('slug', $brand->slug) }}" class="input-glamora font-mono">
            </div>

            <div>
                <label for="brand_country" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                    País de Origen *
                </label>
                <input id="brand_country" type="text" name="country_origin" value="{{ old('country_origin', $brand->country_origin ?? 'Perú') }}" required class="input-glamora">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="brand_order" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                        Orden
                    </label>
                    <input id="brand_order" type="number" min="0" name="sort_order" value="{{ old('sort_order', $brand->sort_order ?? 0) }}" class="input-glamora">
                </div>
                <div>
                    <label for="brand_website" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                        Sitio Web Oficial
                    </label>
                    <input id="brand_website" type="url" name="website_url" value="{{ old('website_url', $brand->website_url) }}" placeholder="https://..." class="input-glamora">
                </div>
            </div>
        </div>

        <div>
            <label for="brand_description" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                Historia &amp; Propuesta de Marca
            </label>
            <textarea id="brand_description" name="description" rows="3" class="input-glamora">{{ old('description', $brand->description) }}</textarea>
        </div>

        {{-- Logo / Fotografía de Marca + Banner Editorial con Preview Alpine --}}
        <div class="grid gap-5 border-t border-glamora-blush/30 pt-5 lg:grid-cols-2">
            <div class="space-y-3 rounded-2xl border border-glamora-blush/35 bg-glamora-cream/35 p-4">
                <label for="brand_logo_file" class="block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                    Subir Emblema / Fotografía de Marca (JPG, PNG, WEBP — Máx. 4 MB)
                </label>
                <template x-if="logoPreview">
                    <img :src="logoPreview" alt="Preview logo marca" class="h-28 w-28 rounded-xl border border-glamora-blush/40 bg-white object-cover">
                </template>
                <input
                    id="brand_logo_file"
                    type="file"
                    name="logo_file"
                    accept="image/jpeg,image/png,image/webp"
                    @change="onLogoFile($event)"
                    class="input-glamora bg-white text-xs"
                >
                <div>
                    <label for="brand_logo_path" class="mb-1 block text-[11px] font-semibold text-glamora-muted">
                        O URL / Ruta Logo Existente
                    </label>
                    <input id="brand_logo_path" type="text" name="logo_path" value="{{ old('logo_path', $brand->logo_path) }}" class="input-glamora bg-white font-mono text-xs">
                </div>
            </div>

            <div class="space-y-3 rounded-2xl border border-glamora-blush/35 bg-glamora-cream/35 p-4">
                <label for="brand_banner_file" class="block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                    Subir Banner Editorial de Marca (JPG, PNG, WEBP — Máx. 4 MB)
                </label>
                <template x-if="bannerPreview">
                    <img :src="bannerPreview" alt="Preview banner marca" class="h-28 w-full rounded-xl border border-glamora-blush/40 bg-white object-cover">
                </template>
                <input
                    id="brand_banner_file"
                    type="file"
                    name="banner_file"
                    accept="image/jpeg,image/png,image/webp"
                    @change="onBannerFile($event)"
                    class="input-glamora bg-white text-xs"
                >
                <div>
                    <label for="brand_banner_path" class="mb-1 block text-[11px] font-semibold text-glamora-muted">
                        O URL / Ruta Banner Existente
                    </label>
                    <input id="brand_banner_path" type="text" name="banner_path" value="{{ old('banner_path', $brand->banner_path) }}" class="input-glamora bg-white font-mono text-xs">
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-4 border-t border-glamora-blush/30 pt-5">
            <div class="flex flex-wrap gap-5">
                <label class="inline-flex items-center gap-2 text-sm font-medium text-glamora-charcoal">
                    <input type="checkbox" name="is_cruelty_free" value="1" @checked(old('is_cruelty_free', $brand->is_cruelty_free ?? true)) class="h-4 w-4 accent-glamora-terracotta">
                    Certificado Cruelty-Free
                </label>
                <label class="inline-flex items-center gap-2 text-sm font-medium text-glamora-charcoal">
                    <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $brand->is_featured)) class="h-4 w-4 accent-glamora-terracotta">
                    Marca Destacada
                </label>
                <label class="inline-flex items-center gap-2 text-sm font-medium text-glamora-charcoal">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $brand->is_active ?? true)) class="h-4 w-4 accent-glamora-terracotta">
                    Activa
                </label>
            </div>

            <button type="submit" class="btn-glamora-primary px-7">
                {{ $isEdit ? 'Guardar Cambios' : 'Crear Marca' }}
            </button>
        </div>
    </form>
@endsection
