@extends('layouts.admin')

@section('title', ($isEdit ? 'Editar Categoría' : 'Nueva Categoría') . ' — Glamora Perú')
@section('page_title', $isEdit ? 'Editar Categoría: ' . $category->name : 'Crear Nueva Categoría')
@section('page_subtitle', 'Configura jerarquía padre/subcategoría, orden de navegación y posicionamiento SEO.')

@section('header_actions')
    <a href="{{ route('admin.pim.categories.index') }}" class="btn-glamora-outline py-2 px-4 text-xs">
        &larr; Volver a Categorías
    </a>
@endsection

@section('content')
    <form
        action="{{ $isEdit ? route('admin.pim.categories.update', $category) : route('admin.pim.categories.store') }}"
        method="POST"
        enctype="multipart/form-data"
        x-data="{
            preview: '{{ $isEdit ? $category->image_url : '' }}',
            onFile(e) {
                const f = e.target.files[0];
                if (f) {
                    this.preview = URL.createObjectURL(f);
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
                <label for="cat_name" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                    Nombre *
                </label>
                <input id="cat_name" type="text" name="name" value="{{ old('name', $category->name) }}" required class="input-glamora">
            </div>

            <div>
                <label for="cat_slug" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                    Slug URL (opcional, se genera automático)
                </label>
                <input id="cat_slug" type="text" name="slug" value="{{ old('slug', $category->slug) }}" placeholder="ej. serums-y-activos" class="input-glamora font-mono">
            </div>

            <div>
                <label for="cat_parent" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                    Categoría Padre
                </label>
                <select id="cat_parent" name="parent_id" class="input-glamora">
                    <option value="">— Categoría Raíz —</option>
                    @foreach ($parentOptions as $opt)
                        <option value="{{ $opt->id }}" @selected((string) old('parent_id', $category->parent_id) === (string) $opt->id)>
                            {{ $opt->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="cat_order" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                    Orden de Visualización
                </label>
                <input id="cat_order" type="number" min="0" name="sort_order" value="{{ old('sort_order', $category->sort_order ?? 0) }}" class="input-glamora">
            </div>
        </div>

        <div>
            <label for="cat_description" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                Descripción Editorial
            </label>
            <textarea id="cat_description" name="description" rows="3" class="input-glamora">{{ old('description', $category->description) }}</textarea>
        </div>

        <div class="grid gap-5 rounded-2xl border border-glamora-blush/35 bg-glamora-cream/35 p-4 sm:grid-cols-2">
            <div class="flex items-start gap-4">
                <template x-if="preview">
                    <img :src="preview" alt="Preview categoría" class="h-20 w-20 shrink-0 rounded-xl border border-glamora-blush/40 bg-white object-cover">
                </template>
                <div class="flex-1">
                    <label for="cat_image_file" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                        Subir Fotografía (JPG, PNG, WEBP — Máx. 4 MB)
                    </label>
                    <input
                        id="cat_image_file"
                        type="file"
                        name="image_file"
                        accept="image/jpeg,image/png,image/webp"
                        @change="onFile($event)"
                        class="input-glamora bg-white text-xs"
                    >
                </div>
            </div>
            <div>
                <label for="cat_image_path" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                    O URL / Ruta de Fotografía Existente
                </label>
                <input id="cat_image_path" type="text" name="image_path" value="{{ old('image_path', $category->image_path) }}" placeholder="https://images.unsplash.com/photo-..." class="input-glamora bg-white font-mono text-xs">
            </div>
        </div>

        <div class="grid gap-5 border-t border-glamora-blush/30 pt-5 sm:grid-cols-2">
            <div>
                <label for="cat_meta_title" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                    Meta Title (SEO)
                </label>
                <input id="cat_meta_title" type="text" name="meta_title" value="{{ old('meta_title', $category->meta_title) }}" class="input-glamora">
            </div>
            <div>
                <label for="cat_meta_desc" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                    Meta Description (SEO)
                </label>
                <input id="cat_meta_desc" type="text" name="meta_description" value="{{ old('meta_description', $category->meta_description) }}" class="input-glamora">
            </div>
        </div>

        <div class="flex items-center justify-between border-t border-glamora-blush/30 pt-5">
            <label class="inline-flex items-center gap-2.5 text-sm font-medium text-glamora-charcoal">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active ?? true)) class="h-4 w-4 accent-glamora-terracotta">
                Categoría activa y visible en Storefront
            </label>

            <button type="submit" class="btn-glamora-primary px-7">
                {{ $isEdit ? 'Guardar Cambios' : 'Crear Categoría' }}
            </button>
        </div>
    </form>
@endsection
