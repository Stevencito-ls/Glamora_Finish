@extends('layouts.admin')

@section('title', 'Ubicaciones de Almacén — Glamora Perú')
@section('page_title', 'Ubicaciones Físicas de Almacén')
@section('page_subtitle', 'Administra zonas climatizadas DIGEMID, pasillos, racks y niveles para picking FEFO.')

@section('header_actions')
    <a href="{{ route('admin.inventory.index') }}" class="btn-glamora-outline py-2 px-4 text-xs">
        &larr; Lotes FEFO
    </a>
@endsection

@section('content')
    <div class="space-y-6">
        {{-- Create Location Form --}}
        <section class="card-glamora p-6">
            <h2 class="font-serif text-xl font-semibold text-glamora-charcoal">
                + Nueva Ubicación de Almacén
            </h2>
            <form action="{{ route('admin.inventory.locations.store') }}" method="POST" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
                @csrf
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-glamora-charcoal">Código *</label>
                    <input type="text" name="code" value="{{ old('code') }}" placeholder="ALM-SKN-A03" required class="input-glamora font-mono text-xs uppercase">
                </div>
                <div class="lg:col-span-2">
                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-glamora-charcoal">Zona *</label>
                    <input type="text" name="zone" value="{{ old('zone') }}" placeholder="Skincare Activos (Climatizada)" required class="input-glamora text-xs">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-glamora-charcoal">Pasillo *</label>
                    <input type="text" name="aisle" value="{{ old('aisle') }}" placeholder="A1" required class="input-glamora font-mono text-xs uppercase">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-glamora-charcoal">Rack *</label>
                    <input type="text" name="rack" value="{{ old('rack') }}" placeholder="R03" required class="input-glamora font-mono text-xs uppercase">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-glamora-charcoal">Nivel *</label>
                    <input type="text" name="shelf" value="{{ old('shelf') }}" placeholder="N1" required class="input-glamora font-mono text-xs uppercase">
                </div>

                <div class="lg:col-span-3">
                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-glamora-charcoal">Descripción / Condición Térmica</label>
                    <input type="text" name="description" value="{{ old('description') }}" placeholder="Cámara climatizada 15°C - 20°C..." class="input-glamora text-xs">
                </div>

                <div class="flex items-center gap-4 lg:col-span-2">
                    <label class="inline-flex items-center gap-2 text-xs font-medium">
                        <input type="checkbox" name="temperature_controlled" value="1" @checked(old('temperature_controlled')) class="h-4 w-4 accent-glamora-terracotta">
                        Climatizada (Temp. Controlada)
                    </label>
                    <label class="inline-flex items-center gap-2 text-xs font-medium">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) class="h-4 w-4 accent-glamora-terracotta">
                        Activa
                    </label>
                </div>

                <div class="flex items-end justify-end">
                    <button type="submit" class="btn-glamora-primary w-full py-2 text-xs">
                        Crear Ubicación
                    </button>
                </div>
            </form>
        </section>

        {{-- Locations List with Inline Edit --}}
        <section class="space-y-3">
            @foreach ($locations as $location)
                <div x-data="{ editing: false }" class="card-glamora p-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-mono text-sm font-bold text-glamora-charcoal">{{ $location->code }}</span>
                                <span class="text-sm font-semibold text-glamora-charcoal">— {{ $location->zone }}</span>
                                @if ($location->temperature_controlled)
                                    <span class="rounded-full border border-sky-200 bg-sky-50 px-2.5 py-0.5 text-[10px] font-bold text-sky-800">
                                        Climatizada
                                    </span>
                                @endif
                                @if (! $location->is_active)
                                    <span class="rounded-full border border-slate-200 bg-slate-100 px-2.5 py-0.5 text-[10px] font-bold text-slate-600">
                                        Inactiva
                                    </span>
                                @endif
                            </div>
                            <p class="mt-1 text-xs text-glamora-muted">
                                Pasillo <strong class="font-mono">{{ $location->aisle }}</strong> ·
                                Rack <strong class="font-mono">{{ $location->rack }}</strong> ·
                                Nivel <strong class="font-mono">{{ $location->shelf }}</strong> ·
                                Lotes asignados: <strong>{{ $location->batches_count }}</strong>
                                @if ($location->description)
                                    — {{ $location->description }}
                                @endif
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <button type="button" @click="editing = !editing" class="rounded-full border border-glamora-charcoal/20 px-3 py-1 text-xs font-semibold text-glamora-charcoal hover:bg-glamora-charcoal hover:text-glamora-cream">
                                <span x-text="editing ? 'Cerrar' : 'Editar'"></span>
                            </button>
                            <form action="{{ route('admin.inventory.locations.destroy', $location) }}" method="POST" data-confirm="¿Eliminar ubicación {{ $location->code }}?" onsubmit="return confirm(this.dataset.confirm);">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-full border border-rose-200 px-3 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50">
                                    Eliminar
                                </button>
                            </form>
                        </div>
                    </div>

                    <form x-show="editing" x-cloak action="{{ route('admin.inventory.locations.update', $location) }}" method="POST" class="mt-4 grid gap-3 border-t border-glamora-blush/30 pt-4 sm:grid-cols-2 lg:grid-cols-6">
                        @csrf
                        @method('PUT')
                        <input type="text" name="code" value="{{ $location->code }}" required class="input-glamora font-mono text-xs uppercase">
                        <input type="text" name="zone" value="{{ $location->zone }}" required class="input-glamora text-xs lg:col-span-2">
                        <input type="text" name="aisle" value="{{ $location->aisle }}" required class="input-glamora font-mono text-xs uppercase">
                        <input type="text" name="rack" value="{{ $location->rack }}" required class="input-glamora font-mono text-xs uppercase">
                        <input type="text" name="shelf" value="{{ $location->shelf }}" required class="input-glamora font-mono text-xs uppercase">
                        <input type="text" name="description" value="{{ $location->description }}" placeholder="Descripción..." class="input-glamora text-xs lg:col-span-3">
                        <label class="inline-flex items-center gap-2 text-xs font-medium">
                            <input type="checkbox" name="temperature_controlled" value="1" @checked($location->temperature_controlled) class="h-4 w-4 accent-glamora-terracotta">
                            Climatizada
                        </label>
                        <label class="inline-flex items-center gap-2 text-xs font-medium">
                            <input type="checkbox" name="is_active" value="1" @checked($location->is_active) class="h-4 w-4 accent-glamora-terracotta">
                            Activa
                        </label>
                        <button type="submit" class="btn-glamora-terracotta py-2 text-xs">Guardar</button>
                    </form>
                </div>
            @endforeach
        </section>

        @if ($locations->hasPages())
            <div>{{ $locations->links() }}</div>
        @endif
    </div>
@endsection
