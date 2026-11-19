@extends('layouts.admin')

@section('title', ($isEdit ? 'Editar Banner' : 'Nuevo Banner') . ' — Glamora Perú')
@section('page_title', $isEdit ? 'Editar Banner Editorial' : 'Crear Nuevo Banner Editorial')
@section('page_subtitle', 'Configura fotografías reales de campaña, ubicación (Hero Home, Promo Secundario o Portal Mayorista B2B) y llamados a la acción.')

@section('header_actions')
    <a href="{{ route('admin.banners.index') }}" class="btn-glamora-outline py-2 px-4 text-xs">
        &larr; Volver a Banners y Multimedia
    </a>
@endsection

@section('content')
    <form
        action="{{ $isEdit ? route('admin.banners.update', $banner) : route('admin.banners.store') }}"
        method="POST"
        enctype="multipart/form-data"
        x-data="{
            mainPreview: '{{ $isEdit ? $banner->image_url : '' }}',
            accentPreview: '{{ $isEdit && $banner->accent_image_path ? $banner->accent_image_url : '' }}',
            onMainChange(event) {
                const file = event.target.files[0];
                if (file) {
                    this.mainPreview = URL.createObjectURL(file);
                }
            },
            onAccentChange(event) {
                const file = event.target.files[0];
                if (file) {
                    this.accentPreview = URL.createObjectURL(file);
                }
            }
        }"
        class="card-glamora mx-auto max-w-4xl space-y-6 p-6 sm:p-8"
    >
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <div class="grid gap-5 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <label for="ban_title" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                    Titular Principal *
                </label>
                <input
                    id="ban_title"
                    type="text"
                    name="title"
                    value="{{ old('title', $banner->title) }}"
                    required
                    placeholder="ej. Alta cosmética botánica diseñada para el clima y subtono latino."
                    class="input-glamora"
                >
            </div>

            <div>
                <label for="ban_position" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                    Ubicación en Storefront *
                </label>
                @php
                    $currentPos = old('position', $banner->position instanceof \App\Enums\BannerPosition ? $banner->position->value : $banner->position);
                @endphp
                <select id="ban_position" name="position" required class="input-glamora">
                    @foreach ($positions as $pos)
                        <option value="{{ $pos->value }}" @selected($currentPos === $pos->value)>
                            {{ $pos->label() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="ban_eyebrow" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                    Antetítulo (Eyebrow)
                </label>
                <input
                    id="ban_eyebrow"
                    type="text"
                    name="eyebrow"
                    value="{{ old('eyebrow', $banner->eyebrow) }}"
                    placeholder="ej. Colección Editorial 2026"
                    class="input-glamora"
                >
            </div>

            <div>
                <label for="ban_badge" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                    Sello de Garantía / Badge Flotante
                </label>
                <input
                    id="ban_badge"
                    type="text"
                    name="badge_text"
                    value="{{ old('badge_text', $banner->badge_text) }}"
                    placeholder="ej. NSOC Verificado · Cruelty-Free"
                    class="input-glamora"
                >
            </div>

            <div>
                <label for="ban_order" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                    Orden de Aparición
                </label>
                <input
                    id="ban_order"
                    type="number"
                    min="0"
                    name="sort_order"
                    value="{{ old('sort_order', $banner->sort_order ?? 1) }}"
                    class="input-glamora"
                >
            </div>
        </div>

        <div>
            <label for="ban_subtitle" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                Subtítulo / Descripción Editorial
            </label>
            <textarea
                id="ban_subtitle"
                name="subtitle"
                rows="3"
                class="input-glamora"
            >{{ old('subtitle', $banner->subtitle) }}</textarea>
        </div>

        {{-- Botones CTA --}}
        <div class="grid gap-5 border-t border-glamora-blush/30 pt-5 sm:grid-cols-2">
            <div>
                <label for="ban_btn_text" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                    Texto Botón Principal
                </label>
                <input
                    id="ban_btn_text"
                    type="text"
                    name="button_text"
                    value="{{ old('button_text', $banner->button_text) }}"
                    placeholder="ej. Explorar Catálogo"
                    class="input-glamora"
                >
            </div>
            <div>
                <label for="ban_btn_url" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                    URL Botón Principal
                </label>
                <input
                    id="ban_btn_url"
                    type="text"
                    name="button_url"
                    value="{{ old('button_url', $banner->button_url) }}"
                    placeholder="/catalogo"
                    class="input-glamora font-mono text-xs"
                >
            </div>
            <div>
                <label for="ban_sec_text" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                    Texto Botón Secundario (opcional)
                </label>
                <input
                    id="ban_sec_text"
                    type="text"
                    name="secondary_button_text"
                    value="{{ old('secondary_button_text', $banner->secondary_button_text) }}"
                    placeholder="ej. Ver Kits B2B"
                    class="input-glamora"
                >
            </div>
            <div>
                <label for="ban_sec_url" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                    URL Botón Secundario (opcional)
                </label>
                <input
                    id="ban_sec_url"
                    type="text"
                    name="secondary_button_url"
                    value="{{ old('secondary_button_url', $banner->secondary_button_url) }}"
                    placeholder="/catalogo?b2b=1"
                    class="input-glamora font-mono text-xs"
                >
            </div>
        </div>

        {{-- Fotografías con Vista Previa Instantánea Alpine.js --}}
        <div class="grid gap-6 border-t border-glamora-blush/30 pt-5 lg:grid-cols-2">
            <div class="space-y-3 rounded-2xl border border-glamora-blush/35 bg-glamora-cream/35 p-4">
                <label for="ban_image_file" class="block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                    Fotografía Principal del Banner (JPG, PNG, WEBP — Máx. 4 MB) {{ $isEdit ? '' : '*' }}
                </label>

                <template x-if="mainPreview">
                    <div class="overflow-hidden rounded-xl border border-glamora-blush/40 bg-white p-1.5">
                        <img :src="mainPreview" alt="Previsualización principal" class="h-44 w-full rounded-lg object-cover">
                    </div>
                </template>

                <input
                    id="ban_image_file"
                    type="file"
                    name="image_file"
                    accept="image/jpeg,image/png,image/webp"
                    @change="onMainChange($event)"
                    class="input-glamora bg-white text-xs"
                >

                <div>
                    <label for="ban_image_path" class="mb-1 block text-[11px] font-semibold text-glamora-muted">
                        O URL / Ruta Fotográfica Existente
                    </label>
                    <input
                        id="ban_image_path"
                        type="text"
                        name="image_path"
                        value="{{ old('image_path', $banner->image_path) }}"
                        placeholder="https://images.unsplash.com/photo-..."
                        class="input-glamora bg-white font-mono text-xs"
                    >
                </div>
            </div>

            <div class="space-y-3 rounded-2xl border border-glamora-blush/35 bg-glamora-cream/35 p-4">
                <label for="ban_accent_file" class="block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                    Fotografía Secundaria / Tarjeta Flotante (Opcional — Máx. 4 MB)
                </label>

                <template x-if="accentPreview">
                    <div class="overflow-hidden rounded-xl border border-glamora-blush/40 bg-white p-1.5">
                        <img :src="accentPreview" alt="Previsualización secundaria" class="h-44 w-full rounded-lg object-cover">
                    </div>
                </template>

                <input
                    id="ban_accent_file"
                    type="file"
                    name="accent_image_file"
                    accept="image/jpeg,image/png,image/webp"
                    @change="onAccentChange($event)"
                    class="input-glamora bg-white text-xs"
                >

                <div>
                    <label for="ban_accent_path" class="mb-1 block text-[11px] font-semibold text-glamora-muted">
                        O URL / Ruta Secundaria Existente
                    </label>
                    <input
                        id="ban_accent_path"
                        type="text"
                        name="accent_image_path"
                        value="{{ old('accent_image_path', $banner->accent_image_path) }}"
                        placeholder="https://images.unsplash.com/photo-..."
                        class="input-glamora bg-white font-mono text-xs"
                    >
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between border-t border-glamora-blush/30 pt-5">
            <label class="inline-flex items-center gap-2.5 text-sm font-medium text-glamora-charcoal">
                <input
                    type="checkbox"
                    name="is_active"
                    value="1"
                    @checked(old('is_active', $banner->is_active ?? true))
                    class="h-4 w-4 accent-glamora-terracotta"
                >
                Banner activo y visible en Storefront
            </label>

            <button type="submit" class="btn-glamora-primary px-7">
                {{ $isEdit ? 'Guardar Cambios' : 'Publicar Banner' }}
            </button>
        </div>
    </form>
@endsection
