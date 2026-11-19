@extends('layouts.admin')

@section('title', ($isEdit ? 'Editar Cupón' : 'Nuevo Cupón') . ' — Marketing Glamora Perú')
@section('page_title', $isEdit ? 'Editar Cupón: ' . $coupon->code : 'Crear Cupón Promocional')
@section('page_subtitle', 'Define código en mayúsculas, tipo de descuento (% o S/ fijo), vigencia y nivel de cliente aplicable.')

@section('header_actions')
    <a href="{{ route('admin.marketing.coupons.index') }}" class="btn-glamora-outline py-2 px-4 text-xs">
        &larr; Volver a Cupones
    </a>
@endsection

@section('content')
    <div class="mx-auto max-w-4xl space-y-5">
        @if ($isLocked)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
                <strong>Cupón con historial auditado:</strong> Este cupón ya registra usos o está referenciado en pedidos existentes. Por integridad contable, sus reglas comerciales no pueden modificarse ni eliminarse; solo puedes activarlo o desactivarlo desde la lista principal.
            </div>
        @endif

        <form
            action="{{ $isEdit ? route('admin.marketing.coupons.update', $coupon) : route('admin.marketing.coupons.store') }}"
            method="POST"
            class="card-glamora space-y-6 p-6 sm:p-8"
        >
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="coupon_code" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                        Código Promocional *
                    </label>
                    <input
                        id="coupon_code"
                        type="text"
                        name="code"
                        value="{{ old('code', $coupon->code) }}"
                        placeholder="EJ. GLAMORA20"
                        required
                        @disabled($isLocked)
                        class="input-glamora font-mono uppercase"
                    >
                    <p class="mt-1 text-[11px] text-glamora-muted">Se normalizará automáticamente a mayúsculas (letras, números, guiones).</p>
                </div>

                <div>
                    <label for="coupon_tier" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                        Nivel de Cliente Aplicable
                    </label>
                    <select id="coupon_tier" name="applicable_tier" @disabled($isLocked) class="input-glamora">
                        <option value="">Todos los clientes (Minorista y B2B)</option>
                        @foreach ($customerTiers as $tier)
                            <option value="{{ $tier->value }}" @selected(old('applicable_tier', $coupon->applicable_tier?->value) === $tier->value)>
                                {{ $tier->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="coupon_discount_type" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                        Tipo de Descuento *
                    </label>
                    <select id="coupon_discount_type" name="discount_type" required @disabled($isLocked) class="input-glamora">
                        <option value="percentage" @selected(old('discount_type', $coupon->discount_type ?? 'percentage') === 'percentage')>Porcentaje (%)</option>
                        <option value="fixed" @selected(old('discount_type', $coupon->discount_type) === 'fixed')>Monto Fijo (S/ PEN)</option>
                    </select>
                </div>

                <div>
                    <label for="coupon_discount_value" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                        Valor del Descuento *
                    </label>
                    <input
                        id="coupon_discount_value"
                        type="number"
                        step="0.01"
                        min="0.01"
                        name="discount_value"
                        value="{{ old('discount_value', $coupon->discount_value) }}"
                        required
                        @disabled($isLocked)
                        class="input-glamora"
                    >
                    <p class="mt-1 text-[11px] text-glamora-muted">Si es porcentaje, máximo 100.00.</p>
                </div>

                <div>
                    <label for="coupon_min_order" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                        Pedido Mínimo (S/ PEN)
                    </label>
                    <input
                        id="coupon_min_order"
                        type="number"
                        step="0.01"
                        min="0"
                        name="min_order_amount"
                        value="{{ old('min_order_amount', $coupon->min_order_amount ?? 0) }}"
                        @disabled($isLocked)
                        class="input-glamora"
                    >
                </div>

                <div>
                    <label for="coupon_max_uses" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                        Límite Máximo de Usos (opcional)
                    </label>
                    <input
                        id="coupon_max_uses"
                        type="number"
                        min="1"
                        name="max_uses"
                        value="{{ old('max_uses', $coupon->max_uses) }}"
                        placeholder="Sin límite"
                        @disabled($isLocked)
                        class="input-glamora"
                    >
                </div>

                <div>
                    <label for="coupon_starts_at" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                        Fecha de Inicio (opcional)
                    </label>
                    <input
                        id="coupon_starts_at"
                        type="datetime-local"
                        name="starts_at"
                        value="{{ old('starts_at', $coupon->starts_at?->format('Y-m-d\TH:i')) }}"
                        @disabled($isLocked)
                        class="input-glamora"
                    >
                </div>

                <div>
                    <label for="coupon_expires_at" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                        Fecha de Expiración (opcional)
                    </label>
                    <input
                        id="coupon_expires_at"
                        type="datetime-local"
                        name="expires_at"
                        value="{{ old('expires_at', $coupon->expires_at?->format('Y-m-d\TH:i')) }}"
                        @disabled($isLocked)
                        class="input-glamora"
                    >
                </div>
            </div>

            <div>
                <label for="coupon_description" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">
                    Descripción / Términos de Campaña
                </label>
                <input
                    id="coupon_description"
                    type="text"
                    name="description"
                    maxlength="255"
                    value="{{ old('description', $coupon->description) }}"
                    placeholder="Ej. 15% de descuento en skincare botánico por lanzamiento de temporada."
                    @disabled($isLocked)
                    class="input-glamora"
                >
            </div>

            <div class="flex flex-wrap items-center justify-between gap-4 border-t border-glamora-blush/30 pt-5">
                <label class="inline-flex items-center gap-2.5 text-sm font-medium text-glamora-charcoal">
                    <input
                        type="checkbox"
                        name="is_active"
                        value="1"
                        @checked(old('is_active', $coupon->is_active ?? true))
                        @disabled($isLocked)
                        class="h-4 w-4 accent-glamora-terracotta"
                    >
                    Cupón activo y habilitado para checkout
                </label>

                @if (! $isLocked)
                    <button type="submit" class="btn-glamora-primary px-7">
                        {{ $isEdit ? 'Guardar Cambios' : 'Crear Cupón' }}
                    </button>
                @endif
            </div>
        </form>
    </div>
@endsection
