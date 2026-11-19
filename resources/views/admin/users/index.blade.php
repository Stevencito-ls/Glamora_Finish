@extends('layouts.admin')

@section('title', 'Usuarios y Roles — Glamora Perú')
@section('page_title', 'Gestión de Usuarios y 5 Roles del Sistema')
@section('page_subtitle', 'Control de cuentas por rol (SuperAdmin, Administrador, Almacenero, Vendedor / B2B y Cliente), niveles comerciales y seguridad 2FA.')

@section('header_actions')
    @can('create', \App\Models\User::class)
        <a href="{{ route('admin.users.create') }}" class="btn-glamora-primary py-2 px-4 text-xs">
            <i class="fas fa-user-plus mr-1"></i> + Nuevo Usuario
        </a>
    @endcan
@endsection

@section('content')
    @php
        $roleLabels = [
            'SuperAdmin' => 'SuperAdmin (Control Total)',
            'Administrador' => 'Administrador (Gestión Operativa)',
            'Almacenero' => 'Almacenero (Inventario, Lotes y Despacho)',
            'Vendedor B2B' => 'Vendedor / B2B (Canal Mayorista)',
            'Cliente' => 'Cliente (Comprador Storefront)',
        ];
    @endphp

    <div class="space-y-6">
        {{-- Resumen de los 5 Roles Spatie --}}
        <div class="row g-3">
            <div class="col-12 col-sm-6 col-lg">
                <div class="card card-outline card-warning mb-0 h-100 p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="badge bg-warning text-dark">SuperAdmin</span>
                        <i class="fas fa-crown text-warning"></i>
                    </div>
                    <p class="mt-2 mb-0 text-[11px] text-glamora-muted">Acceso absoluto a configuración, seguridad y usuarios.</p>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg">
                <div class="card card-outline card-danger mb-0 h-100 p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="badge bg-danger text-white">Administrador</span>
                        <i class="fas fa-user-tie text-danger"></i>
                    </div>
                    <p class="mt-2 mb-0 text-[11px] text-glamora-muted">Catálogo, banners, ventas, pagos, marketing y usuarios.</p>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg">
                <div class="card card-outline card-success mb-0 h-100 p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="badge bg-success text-white">Almacenero</span>
                        <i class="fas fa-boxes-stacked text-success"></i>
                    </div>
                    <p class="mt-2 mb-0 text-[11px] text-glamora-muted">Stock, lotes FEFO, ubicaciones, picking, despacho y RMA.</p>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg">
                <div class="card card-outline card-info mb-0 h-100 p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="badge bg-info text-dark">Vendedor / B2B</span>
                        <i class="fas fa-briefcase text-info"></i>
                    </div>
                    <p class="mt-2 mb-0 text-[11px] text-glamora-muted">Pedidos corporativos propios, línea de crédito y catálogo.</p>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg">
                <div class="card card-outline card-secondary mb-0 h-100 p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="badge bg-secondary text-white">Cliente</span>
                        <i class="fas fa-shopping-bag text-secondary"></i>
                    </div>
                    <p class="mt-2 mb-0 text-[11px] text-glamora-muted">Compras en tienda, seguimiento de pedidos y portal Mi Cuenta.</p>
                </div>
            </div>
        </div>

        <form method="GET" action="{{ route('admin.users.index') }}" class="card-glamora grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-5">
            <input type="text" name="q" value="{{ $search }}" placeholder="Nombre, correo o DNI/RUC..." class="input-glamora text-xs">

            <select name="role" class="input-glamora text-xs">
                <option value="">Todos los roles (5)</option>
                @foreach ($roles as $r)
                    <option value="{{ $r->name }}" @selected($roleFilter === $r->name)>
                        {{ $roleLabels[$r->name] ?? $r->name }}
                    </option>
                @endforeach
            </select>

            <select name="customer_tier" class="input-glamora text-xs">
                <option value="">Todas las escalas</option>
                @foreach ($customerTiers as $tier)
                    <option value="{{ $tier->value }}" @selected($tierFilter === $tier->value)>{{ $tier->label() }}</option>
                @endforeach
            </select>

            <select name="is_active" class="input-glamora text-xs">
                <option value="">Activos e Inactivos</option>
                <option value="1" @selected($statusFilter === '1')>Solo Activos</option>
                <option value="0" @selected($statusFilter === '0')>Solo Desactivados</option>
            </select>

            <div class="flex gap-2">
                <button type="submit" class="btn-glamora-primary flex-1 py-2 text-xs">Filtrar</button>
                <a href="{{ route('admin.users.index') }}" class="btn-glamora-outline py-2 px-3 text-xs">Limpiar</a>
            </div>
        </form>

        <div class="card card-outline card-dark overflow-hidden mb-0">
            <div class="card-body p-3 overflow-x-auto bg-white">
                <table class="table table-bordered table-hover datatable min-w-full text-xs">
                    <thead class="bg-glamora-cream/70 text-[10px] font-bold uppercase tracking-wider text-glamora-muted">
                        <tr>
                            <th class="px-4 py-3 text-left">Usuario</th>
                            <th class="px-4 py-3 text-left">Rol Asignado</th>
                            <th class="px-4 py-3 text-left">Nivel Cliente</th>
                            <th class="px-4 py-3 text-center">2FA</th>
                            <th class="px-4 py-3 text-center">Estado</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $target)
                            @php
                                $roleName = $target->getRoleNames()->first() ?? 'Sin rol';
                                $displayRole = $roleName === 'Vendedor B2B' ? 'Vendedor / B2B' : $roleName;
                                $tier = $target->effectiveTier();
                            @endphp
                            <tr>
                                <td class="px-4 py-3.5">
                                    <p class="font-semibold text-glamora-charcoal mb-0">{{ $target->name }}</p>
                                    <p class="text-[11px] text-glamora-muted mb-0">
                                        {{ $target->email }} · {{ $target->document_type ?? 'DOC' }} {{ $target->document_number ?? '—' }}
                                    </p>
                                </td>
                                <td class="px-4 py-3.5">
                                    <span @class([
                                        'inline-flex rounded-full border px-2.5 py-0.5 text-[10px] font-bold uppercase',
                                        'border-glamora-gold/60 bg-glamora-gold/20 text-glamora-charcoal' => $roleName === 'SuperAdmin',
                                        'border-glamora-terracotta/40 bg-glamora-terracotta/15 text-glamora-terracotta' => $roleName === 'Administrador',
                                        'border-emerald-200 bg-emerald-50 text-emerald-700' => $roleName === 'Almacenero',
                                        'border-sky-200 bg-sky-50 text-sky-800' => $roleName === 'Vendedor B2B',
                                        'border-slate-200 bg-slate-100 text-slate-700' => ! in_array($roleName, ['SuperAdmin', 'Administrador', 'Almacenero', 'Vendedor B2B'], true),
                                    ])>
                                        {{ $displayRole }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex rounded-full border px-2.5 py-0.5 text-[10px] font-bold {{ $tier->badgeClasses() }}">
                                        {{ $tier->label() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 text-center">
                                    @if ($target->two_factor_enabled)
                                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700">2FA Activo</span>
                                    @else
                                        <span class="text-glamora-muted">Desactivado</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-center">
                                    @if ($target->is_active)
                                        <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-[10px] font-bold text-emerald-700">Activo</span>
                                    @else
                                        <span class="inline-flex rounded-full border border-rose-200 bg-rose-50 px-2.5 py-0.5 text-[10px] font-bold text-rose-700">Inactivo</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-right">
                                    <div class="inline-flex flex-wrap items-center justify-end gap-1.5">
                                        @can('update', $target)
                                            <a href="{{ route('admin.users.edit', $target) }}" class="rounded-full border border-glamora-charcoal/20 px-3 py-1 text-[11px] font-semibold text-glamora-charcoal hover:bg-glamora-charcoal hover:text-glamora-cream">
                                                Editar
                                            </a>
                                        @endcan

                                        @can('toggleActive', $target)
                                            <form action="{{ route('admin.users.toggle-active', $target) }}" method="POST">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="rounded-full border border-amber-300 bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-900 hover:bg-amber-100">
                                                    {{ $target->is_active ? 'Desactivar' : 'Activar' }}
                                                </button>
                                            </form>
                                        @endcan

                                        @if ($target->two_factor_enabled)
                                            @can('resetTwoFactor', $target)
                                                <form action="{{ route('admin.users.reset-2fa', $target) }}" method="POST" data-confirm="¿Restablecer 2FA de {{ $target->email }}?" onsubmit="return confirm(this.dataset.confirm);">
                                                    @csrf
                                                    <button type="submit" class="rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-[11px] font-semibold text-sky-800 hover:bg-sky-100">
                                                        Reset 2FA
                                                    </button>
                                                </form>
                                            @endcan
                                        @endif

                                        @can('delete', $target)
                                            <form action="{{ route('admin.users.destroy', $target) }}" method="POST" data-confirm="¿Eliminar permanentemente al usuario {{ $target->email }}?" onsubmit="return confirm(this.dataset.confirm);">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-full border border-rose-200 px-2.5 py-1 text-[11px] font-semibold text-rose-700 hover:bg-rose-50">
                                                    Eliminar
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-8 text-center text-sm text-glamora-muted">
                                    No se encontraron usuarios.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($users->hasPages())
                <div class="border-t border-glamora-blush/25 bg-white px-5 py-3.5">
                    {{ $users->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
