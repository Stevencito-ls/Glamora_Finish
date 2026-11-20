@extends('layouts.admin')

@section('title', 'Moderación de Reseñas — Glamora PRO')
@section('page_title', 'Moderación de Reseñas Verificadas')
@section('page_subtitle', 'Aprobación editorial, control de compras verificadas y auditoría de opiniones.')

@section('content')
    <div class="d-flex flex-column gap-4">
        {{-- KPI Summary Strip --}}
        <div class="row g-3">
            <div class="col-6 col-md-4 col-xl">
                <div class="card border-0 shadow-sm mb-0 h-100">
                    <div class="card-body p-3">
                        <div class="small text-muted text-uppercase fw-bold">Total Reseñas</div>
                        <div class="h3 fw-bold mb-0 text-dark">{{ $stats['total'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl">
                <div class="card border-0 shadow-sm mb-0 h-100">
                    <div class="card-body p-3">
                        <div class="small text-muted text-uppercase fw-bold">Pendientes</div>
                        <div class="h3 fw-bold mb-0 text-warning">{{ $stats['pending'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl">
                <div class="card border-0 shadow-sm mb-0 h-100">
                    <div class="card-body p-3">
                        <div class="small text-muted text-uppercase fw-bold">Aprobadas</div>
                        <div class="h3 fw-bold mb-0 text-success">{{ $stats['approved'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-6 col-xl">
                <div class="card border-0 shadow-sm mb-0 h-100">
                    <div class="card-body p-3">
                        <div class="small text-muted text-uppercase fw-bold">Rechazadas</div>
                        <div class="h3 fw-bold mb-0 text-danger">{{ $stats['rejected'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl">
                <div class="card border-0 shadow-sm mb-0 h-100">
                    <div class="card-body p-3">
                        <div class="small text-muted text-uppercase fw-bold">Promedio Aprobadas</div>
                        <div class="h3 fw-bold mb-0 text-dark">
                            {{ number_format((float) $stats['avg_rating'], 1) }} <small class="text-warning fs-5">&#9733;</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Filters & Reviews Table --}}
        <div class="card border-0 shadow-sm mb-0">
            <div class="card-header bg-white py-3 px-4">
                <form method="GET" action="{{ route('admin.reviews.index') }}" class="row g-2 align-items-center">
                    <div class="col-12 col-lg-4">
                        <input
                            type="text"
                            name="q"
                            value="{{ $search }}"
                            placeholder="Buscar producto, cliente, título o comentario..."
                            class="form-control form-control-sm"
                        >
                    </div>
                    <div class="col-6 col-lg-2">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">Todos los estados</option>
                            <option value="pending" @selected($statusFilter === 'pending')>Pendiente</option>
                            <option value="approved" @selected($statusFilter === 'approved')>Aprobada</option>
                            <option value="rejected" @selected($statusFilter === 'rejected')>Rechazada</option>
                        </select>
                    </div>
                    <div class="col-6 col-lg-2">
                        <select name="rating" class="form-select form-select-sm">
                            <option value="">Todas las estrellas</option>
                            @for ($r = 5; $r >= 1; $r--)
                                <option value="{{ $r }}" @selected($ratingFilter === (string) $r)>{{ $r }} estrellas</option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-6 col-lg-2">
                        <select name="verified" class="form-select form-select-sm">
                            <option value="">Compra (Todas)</option>
                            <option value="1" @selected($verifiedFilter === '1')>Compra Verificada</option>
                            <option value="0" @selected($verifiedFilter === '0')>Sin compra verificada</option>
                        </select>
                    </div>
                    <div class="col-6 col-lg-2 d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-dark rounded-pill flex-grow-1">Filtrar</button>
                        <a href="{{ route('admin.reviews.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Limpiar</a>
                    </div>
                </form>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small text-uppercase text-muted">
                            <tr>
                                <th class="ps-4">Producto &amp; Cliente</th>
                                <th>Calificación</th>
                                <th>Opinión</th>
                                <th>Estado</th>
                                <th class="text-end pe-4" style="min-width: 260px;">Moderación</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($reviews as $review)
                                @php
                                    $badgeClass = match ($review->status) {
                                        'approved' => 'bg-success',
                                        'rejected' => 'bg-danger',
                                        default => 'bg-warning text-dark',
                                    };
                                @endphp
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-semibold text-dark">{{ $review->product?->name ?? 'Producto eliminado' }}</div>
                                        <div class="small text-muted">
                                            {{ $review->user?->name ?? 'Cliente' }}
                                            @if ($review->user?->email)
                                                ({{ $review->user->email }})
                                            @endif
                                        </div>
                                        <div class="mt-1 d-flex flex-wrap gap-1">
                                            @if ($review->is_verified_purchase)
                                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                    <i class="fas fa-check-circle me-1"></i> Compra Verificada
                                                </span>
                                            @else
                                                <span class="badge bg-light text-secondary border">Opinión general</span>
                                            @endif
                                            @if ($review->shade_purchased)
                                                <span class="badge bg-light text-dark border">{{ $review->shade_purchased }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-warning fw-bold">
                                            @for ($s = 1; $s <= 5; $s++)
                                                <span>{{ $s <= (int) $review->rating ? '★' : '☆' }}</span>
                                            @endfor
                                        </div>
                                        <small class="text-muted">{{ $review->rating }}/5 · {{ $review->created_at?->format('d/m/Y') }}</small>
                                    </td>
                                    <td style="max-width: 340px;">
                                        @if ($review->title)
                                            <div class="fw-semibold small text-dark">{{ $review->title }}</div>
                                        @endif
                                        <div class="small text-muted">{{ $review->comment }}</div>
                                        @if ($review->moderation_notes)
                                            <div class="small text-info mt-1">
                                                <i class="fas fa-comment-dots me-1"></i> {{ $review->moderation_notes }}
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge {{ $badgeClass }}">{{ $review->statusLabel() }}</span>
                                        @if ($review->moderator)
                                            <small class="d-block text-muted mt-1">Por {{ $review->moderator->name }}</small>
                                        @endif
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-flex flex-column gap-2 align-items-end">
                                            <form action="{{ route('admin.reviews.moderate', $review) }}" method="POST" class="d-flex flex-wrap gap-1 justify-content-end">
                                                @csrf
                                                @method('PATCH')
                                                <input
                                                    type="text"
                                                    name="moderation_notes"
                                                    value="{{ $review->moderation_notes }}"
                                                    placeholder="Nota interna (opc.)"
                                                    class="form-control form-control-sm"
                                                    style="max-width: 150px;"
                                                >
                                                <button type="submit" name="status" value="approved" class="btn btn-sm btn-success rounded-pill" title="Aprobar">
                                                    <i class="fas fa-check"></i> Aprobar
                                                </button>
                                                <button type="submit" name="status" value="rejected" class="btn btn-sm btn-outline-warning rounded-pill" title="Rechazar">
                                                    <i class="fas fa-ban"></i> Rechazar
                                                </button>
                                            </form>

                                            <form
                                                action="{{ route('admin.reviews.destroy', $review) }}"
                                                method="POST"
                                                onsubmit="return confirm('¿Eliminar permanentemente esta reseña?');"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill">
                                                    <i class="fas fa-trash me-1"></i> Eliminar
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No hay reseñas registradas con los filtros seleccionados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($reviews->hasPages())
                <div class="card-footer bg-white py-3 px-4">
                    {{ $reviews->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
