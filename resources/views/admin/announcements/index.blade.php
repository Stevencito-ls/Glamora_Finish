@extends('layouts.admin')

@section('title', 'Anuncios Topbar — Glamora PRO')
@section('page_title', 'Anuncios Dinámicos del Topbar')
@section('page_subtitle', 'Gestión de avisos promocionales rotativos en la barra superior del storefront.')

@section('content')
    <div class="d-flex flex-column gap-4">
        {{-- KPI Strip --}}
        <div class="row g-3">
            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm mb-0 h-100">
                    <div class="card-body p-3">
                        <div class="small text-muted text-uppercase fw-bold">Total Anuncios</div>
                        <div class="h3 fw-bold mb-0 text-dark">{{ $stats['total'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm mb-0 h-100">
                    <div class="card-body p-3">
                        <div class="small text-muted text-uppercase fw-bold">Habilitados</div>
                        <div class="h3 fw-bold mb-0 text-primary">{{ $stats['active'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm mb-0 h-100">
                    <div class="card-body p-3">
                        <div class="small text-muted text-uppercase fw-bold">Visibles Ahora</div>
                        <div class="h3 fw-bold mb-0 text-success">{{ $stats['live_now'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm mb-0 h-100">
                    <div class="card-body p-3">
                        <div class="small text-muted text-uppercase fw-bold">Inactivos</div>
                        <div class="h3 fw-bold mb-0 text-secondary">{{ $stats['inactive'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Crear Nuevo Anuncio --}}
        <div class="card border-0 shadow-sm mb-0">
            <div class="card-header bg-white py-3 px-4">
                <h2 class="card-title h5 mb-0 fw-bold text-dark">
                    <i class="fas fa-plus-circle me-2 text-success"></i>
                    Crear Nuevo Anuncio en Topbar
                </h2>
            </div>
            <div class="card-body p-4">
                <form action="{{ route('admin.announcements.store') }}" method="POST">
                    @csrf
                    <div class="row g-3">
                        <div class="col-12 col-lg-6">
                            <label for="new_title" class="form-label small fw-semibold">Mensaje del anuncio *</label>
                            <input
                                id="new_title"
                                type="text"
                                name="title"
                                value="{{ old('title') }}"
                                required
                                minlength="4"
                                maxlength="200"
                                placeholder="Ej. Envío Gratis desde S/ 199 en todo el Perú"
                                class="form-control @error('title') is-invalid @enderror"
                            >
                            @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="new_badge_text" class="form-label small fw-semibold">Etiqueta / Badge</label>
                            <input
                                id="new_badge_text"
                                type="text"
                                name="badge_text"
                                value="{{ old('badge_text') }}"
                                maxlength="60"
                                placeholder="Ej. Envío Gratis"
                                class="form-control"
                            >
                        </div>

                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="new_sort_order" class="form-label small fw-semibold">Orden</label>
                            <input
                                id="new_sort_order"
                                type="number"
                                min="0"
                                name="sort_order"
                                value="{{ old('sort_order', 1) }}"
                                class="form-control"
                            >
                        </div>

                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="new_link_label" class="form-label small fw-semibold">Texto del enlace</label>
                            <input
                                id="new_link_label"
                                type="text"
                                name="link_label"
                                value="{{ old('link_label') }}"
                                maxlength="80"
                                placeholder="Ej. Ver catálogo"
                                class="form-control"
                            >
                        </div>

                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="new_link_url" class="form-label small fw-semibold">URL destino</label>
                            <input
                                id="new_link_url"
                                type="text"
                                name="link_url"
                                value="{{ old('link_url') }}"
                                placeholder="/catalogo o https://..."
                                class="form-control @error('link_url') is-invalid @enderror"
                            >
                            @error('link_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="new_starts_at" class="form-label small fw-semibold">Inicio programado (opc.)</label>
                            <input
                                id="new_starts_at"
                                type="datetime-local"
                                name="starts_at"
                                value="{{ old('starts_at') }}"
                                class="form-control"
                            >
                        </div>

                        <div class="col-12 col-sm-6 col-lg-3">
                            <label for="new_ends_at" class="form-label small fw-semibold">Fin programado (opc.)</label>
                            <input
                                id="new_ends_at"
                                type="datetime-local"
                                name="ends_at"
                                value="{{ old('ends_at') }}"
                                class="form-control @error('ends_at') is-invalid @enderror"
                            >
                            @error('ends_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12 d-flex flex-wrap align-items-center justify-content-between gap-3 pt-2">
                            <div class="form-check form-switch">
                                <input id="new_is_active" type="checkbox" name="is_active" value="1" checked class="form-check-input">
                                <label for="new_is_active" class="form-check-label small fw-semibold">Publicar activo inmediatamente</label>
                            </div>
                            <button type="submit" class="btn btn-dark rounded-pill px-4">
                                <i class="fas fa-plus me-1"></i> Guardar Anuncio
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- Filtros y Listado --}}
        <div class="card border-0 shadow-sm mb-0">
            <div class="card-header bg-white py-3 px-4">
                <form method="GET" action="{{ route('admin.announcements.index') }}" class="row g-2 align-items-center">
                    <div class="col-12 col-md-5">
                        <input
                            type="text"
                            name="q"
                            value="{{ $search }}"
                            placeholder="Buscar por mensaje, badge o enlace..."
                            class="form-control form-control-sm"
                        >
                    </div>
                    <div class="col-12 col-md-3">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">Todos los estados</option>
                            <option value="live" @selected($statusFilter === 'live')>Visibles ahora (Vigentes)</option>
                            <option value="active" @selected($statusFilter === 'active')>Solo activos</option>
                            <option value="inactive" @selected($statusFilter === 'inactive')>Solo inactivos</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-dark rounded-pill px-3">Filtrar</button>
                        <a href="{{ route('admin.announcements.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">Limpiar</a>
                    </div>
                </form>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small text-uppercase text-muted">
                            <tr>
                                <th class="ps-4">Orden</th>
                                <th>Badge</th>
                                <th>Mensaje</th>
                                <th>Enlace</th>
                                <th>Vigencia</th>
                                <th>Estado</th>
                                <th class="text-end pe-4">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($announcements as $announcement)
                                <tr>
                                    <td class="ps-4 fw-bold">{{ $announcement->sort_order }}</td>
                                    <td>
                                        @if ($announcement->badge_text)
                                            <span class="badge bg-warning text-dark">{{ $announcement->badge_text }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="small fw-semibold text-dark">{{ $announcement->title }}</td>
                                    <td class="small">
                                        @if ($announcement->safeLinkUrl())
                                            <span class="text-primary">{{ $announcement->link_label ?: $announcement->safeLinkUrl() }}</span>
                                            <small class="d-block text-muted">{{ $announcement->safeLinkUrl() }}</small>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="small text-muted">
                                        @if ($announcement->starts_at || $announcement->ends_at)
                                            <div>Inicio: {{ $announcement->starts_at?->format('d/m/Y H:i') ?? 'Inmediato' }}</div>
                                            <div>Fin: {{ $announcement->ends_at?->format('d/m/Y H:i') ?? 'Sin límite' }}</div>
                                        @else
                                            Permanente
                                        @endif
                                    </td>
                                    <td>
                                        @if ($announcement->is_active)
                                            <span class="badge bg-success">Activo</span>
                                        @else
                                            <span class="badge bg-secondary">Inactivo</span>
                                        @endif
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-dark rounded-pill"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#edit-ann-{{ $announcement->id }}"
                                            >
                                                Editar
                                            </button>

                                            <form action="{{ route('admin.announcements.toggle', $announcement) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-sm {{ $announcement->is_active ? 'btn-outline-warning' : 'btn-outline-success' }} rounded-pill">
                                                    {{ $announcement->is_active ? 'Desactivar' : 'Activar' }}
                                                </button>
                                            </form>

                                            <form
                                                action="{{ route('admin.announcements.destroy', $announcement) }}"
                                                method="POST"
                                                class="d-inline"
                                                onsubmit="return confirm('¿Eliminar este anuncio del Topbar?');"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill">
                                                    Eliminar
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <tr class="collapse bg-light" id="edit-ann-{{ $announcement->id }}">
                                    <td colspan="7" class="p-3">
                                        <form action="{{ route('admin.announcements.update', $announcement) }}" method="POST" class="row g-2 align-items-end">
                                            @csrf
                                            @method('PUT')
                                            <div class="col-12 col-lg-4">
                                                <label class="form-label small fw-semibold mb-1">Mensaje *</label>
                                                <input type="text" name="title" value="{{ $announcement->title }}" required class="form-control form-control-sm">
                                            </div>
                                            <div class="col-6 col-lg-2">
                                                <label class="form-label small fw-semibold mb-1">Badge</label>
                                                <input type="text" name="badge_text" value="{{ $announcement->badge_text }}" class="form-control form-control-sm">
                                            </div>
                                            <div class="col-6 col-lg-2">
                                                <label class="form-label small fw-semibold mb-1">Texto enlace</label>
                                                <input type="text" name="link_label" value="{{ $announcement->link_label }}" class="form-control form-control-sm">
                                            </div>
                                            <div class="col-8 col-lg-2">
                                                <label class="form-label small fw-semibold mb-1">URL enlace</label>
                                                <input type="text" name="link_url" value="{{ $announcement->link_url }}" class="form-control form-control-sm">
                                            </div>
                                            <div class="col-4 col-lg-1">
                                                <label class="form-label small fw-semibold mb-1">Orden</label>
                                                <input type="number" min="0" name="sort_order" value="{{ $announcement->sort_order }}" class="form-control form-control-sm">
                                            </div>
                                            <div class="col-12 col-lg-1 d-grid">
                                                <input type="hidden" name="is_active" value="{{ $announcement->is_active ? '1' : '0' }}">
                                                <button type="submit" class="btn btn-sm btn-dark">Guardar</button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No hay anuncios registrados con los filtros actuales.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($announcements->hasPages())
                <div class="card-footer bg-white py-3 px-4">
                    {{ $announcements->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
