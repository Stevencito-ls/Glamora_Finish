@extends('layouts.admin')

@section('title', ($isEdit ? 'Editar Usuario' : 'Crear Usuario') . ' — Glamora Perú')
@section('page_title', $isEdit ? 'Editar Usuario: ' . $targetUser->name : 'Crear Nueva Cuenta de Usuario')
@section('page_subtitle', 'Asigna uno de los 5 roles del sistema (SuperAdmin, Administrador, Almacenero, Vendedor / B2B o Cliente), nivel de precios y credenciales.')

@section('header_actions')
    <a href="{{ route('admin.users.index') }}" class="btn-glamora-outline py-2 px-4 text-xs">
        &larr; Volver a Usuarios
    </a>
@endsection

@section('content')
    @php
        $currentRole = old('role', $targetUser->getRoleNames()->first() ?? 'Cliente');
        $currentTier = old('customer_tier', $targetUser->customer_tier?->value ?? \App\Enums\CustomerTier::Minorista->value);
        $roleDescriptions = [
            'SuperAdmin' => 'SuperAdmin — Control total del sistema, seguridad y roles',
            'Administrador' => 'Administrador — Gestión de catálogo, pedidos, pagos y marketing',
            'Almacenero' => 'Almacenero — Inventario, lotes FEFO, ubicaciones y despachos',
            'Vendedor B2B' => 'Vendedor / B2B — Cuenta mayorista corporativa y pedidos B2B',
            'Cliente' => 'Cliente — Comprador en tienda online',
        ];
    @endphp

    <form
        action="{{ $isEdit ? route('admin.users.update', $targetUser) : route('admin.users.store') }}"
        method="POST"
        class="card card-outline card-dark mx-auto max-w-4xl space-y-6 p-6 sm:p-8"
    >
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="u_name" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Nombre Completo *</label>
                <input id="u_name" type="text" name="name" value="{{ old('name', $targetUser->name) }}" required class="input-glamora">
            </div>
            <div>
                <label for="u_email" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Correo Electrónico *</label>
                <input id="u_email" type="email" name="email" value="{{ old('email', $targetUser->email) }}" required class="input-glamora">
            </div>

            <div>
                <label for="u_role" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Rol del Sistema (Spatie RBAC) *</label>
                <select id="u_role" name="role" required class="input-glamora">
                    @foreach ($roles as $role)
                        <option value="{{ $role->name }}" @selected($currentRole === $role->name)>
                            {{ $roleDescriptions[$role->name] ?? $role->name }}
                        </option>
                    @endforeach
                </select>
                @unless (auth()->user()?->hasRole('SuperAdmin'))
                    <p class="mt-1 text-[11px] text-glamora-muted">El rol SuperAdmin está protegido y solo puede asignarse por otro SuperAdmin.</p>
                @endunless
            </div>

            <div>
                <label for="u_tier" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Nivel de Precios / Escala Comercial *</label>
                <select id="u_tier" name="customer_tier" required class="input-glamora">
                    @foreach ($customerTiers as $tier)
                        <option value="{{ $tier->value }}" @selected($currentTier === $tier->value)>
                            {{ $tier->label() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="u_doc_type" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Tipo Documento</label>
                <select id="u_doc_type" name="document_type" class="input-glamora">
                    @foreach (['DNI', 'RUC', 'CE'] as $dt)
                        <option value="{{ $dt }}" @selected(old('document_type', $targetUser->document_type ?? 'DNI') === $dt)>{{ $dt }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="u_doc_num" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Número Documento</label>
                <input id="u_doc_num" type="text" name="document_number" value="{{ old('document_number', $targetUser->document_number) }}" class="input-glamora font-mono">
            </div>

            <div>
                <label for="u_phone" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Teléfono / WhatsApp</label>
                <input id="u_phone" type="text" name="phone" value="{{ old('phone', $targetUser->phone) }}" class="input-glamora">
            </div>
            <div>
                <label for="u_city" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Ciudad</label>
                <input id="u_city" type="text" name="city" value="{{ old('city', $targetUser->city ?? 'Lima') }}" class="input-glamora">
            </div>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="u_address" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Dirección</label>
                <input id="u_address" type="text" name="default_address" value="{{ old('default_address', $targetUser->default_address) }}" class="input-glamora">
            </div>
            <div>
                <label for="u_district" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Distrito</label>
                <input id="u_district" type="text" name="district" value="{{ old('district', $targetUser->district) }}" class="input-glamora">
            </div>
        </div>

        <div class="grid gap-5 border-t border-glamora-blush/30 pt-5 sm:grid-cols-2">
            <div>
                <label for="u_pass" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                    {{ $isEdit ? 'Nueva Contraseña (opcional)' : 'Contraseña *' }}
                </label>
                <input id="u_pass" type="password" name="password" @required(! $isEdit) class="input-glamora" placeholder="Mín. 8 chars, mayúscula, número y símbolo">
            </div>
            <div>
                <label for="u_pass_conf" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                    Confirmar Contraseña {{ $isEdit ? '' : '*' }}
                </label>
                <input id="u_pass_conf" type="password" name="password_confirmation" @required(! $isEdit) class="input-glamora">
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-4 border-t border-glamora-blush/30 pt-5">
            <label class="inline-flex items-center gap-2.5 text-sm font-medium text-glamora-charcoal">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $targetUser->is_active ?? true)) class="h-4 w-4 accent-glamora-terracotta">
                Cuenta Activa (permitir acceso al sistema)
            </label>

            <button type="submit" class="btn-glamora-primary px-7">
                {{ $isEdit ? 'Guardar Cambios de Usuario' : 'Crear Usuario' }}
            </button>
        </div>
    </form>
@endsection
