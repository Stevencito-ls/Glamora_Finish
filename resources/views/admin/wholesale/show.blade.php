@extends('layouts.admin')

@section('title', 'Cuenta B2B: ' . $profile->company_name . ' — Glamora Perú')
@section('page_title', $profile->company_name)
@section('page_subtitle', 'RUC ' . $profile->ruc . ' · ' . ($profile->business_type ?? 'Comercial') . ' · Usuario: ' . ($profile->user?->email ?? $profile->contact_email))

@section('header_actions')
    <a href="{{ route('admin.wholesale.index') }}" class="btn-glamora-outline py-2 px-4 text-xs">
        &larr; Lista Mayoristas
    </a>
@endsection

@section('content')
    <div class="space-y-6">
        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Fiscal & Contact Profile --}}
            <section class="card-glamora space-y-4 p-6 lg:col-span-2">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-glamora-blush/30 pb-3">
                    <div>
                        <h2 class="font-serif text-xl font-semibold text-glamora-charcoal">
                            Expediente Fiscal &amp; Comercial
                        </h2>
                        <p class="text-xs text-glamora-muted">{{ $profile->trade_name ?? $profile->company_name }}</p>
                    </div>
                    <span @class([
                        'rounded-full border px-3 py-1 text-xs font-bold uppercase',
                        'border-emerald-200 bg-emerald-50 text-emerald-700' => $profile->status === 'approved',
                        'border-amber-200 bg-amber-50 text-amber-800' => $profile->status === 'pending',
                        'border-rose-200 bg-rose-50 text-rose-700' => $profile->status === 'rejected',
                    ])>
                        {{ $profile->status }}
                    </span>
                </div>

                <div class="grid gap-4 text-xs sm:grid-cols-2">
                    <div class="rounded-xl bg-glamora-cream/60 p-4 space-y-1">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-glamora-terracotta">Representante</p>
                        <p class="font-semibold text-glamora-charcoal">{{ $profile->contact_name }}</p>
                        <p class="text-glamora-muted">{{ $profile->contact_email }} · {{ $profile->contact_phone }}</p>
                        <p class="text-glamora-muted">Roles actuales: <strong>{{ $profile->user?->getRoleNames()->implode(', ') ?: '—' }}</strong></p>
                    </div>

                    <div class="rounded-xl bg-glamora-cream/60 p-4 space-y-1">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-glamora-terracotta">Dirección Fiscal</p>
                        <p class="font-semibold text-glamora-charcoal">{{ $profile->fiscal_address }}</p>
                        <p class="text-glamora-muted">{{ $profile->district }}, {{ $profile->city }}</p>
                        <p class="text-glamora-muted">Escala solicitada: <strong>{{ $profile->requested_tier?->label() }}</strong></p>
                    </div>
                </div>

                @if ($profile->notes)
                    <div class="rounded-xl border border-glamora-blush/35 bg-white p-4 text-xs text-glamora-charcoal">
                        <strong class="text-[10px] uppercase tracking-wider text-glamora-muted">Notas de postulación / seguimiento:</strong>
                        <p class="mt-1">{{ $profile->notes }}</p>
                    </div>
                @endif

                @if ($profile->rejection_reason)
                    <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-xs text-rose-900">
                        <strong>Motivo de rechazo anterior:</strong> {{ $profile->rejection_reason }}
                    </div>
                @endif
            </section>

            {{-- Credit Summary --}}
            <section class="card-glamora space-y-4 p-6">
                <h2 class="font-serif text-xl font-semibold text-glamora-charcoal">
                    Estado de Crédito B2B
                </h2>
                <div class="space-y-3 text-xs">
                    <div class="flex justify-between border-b border-glamora-blush/25 pb-2">
                        <span class="text-glamora-muted">Escala Vigente</span>
                        <strong class="text-glamora-charcoal">{{ $profile->approved_tier?->label() ?? 'No aprobada' }}</strong>
                    </div>
                    <div class="flex justify-between border-b border-glamora-blush/25 pb-2">
                        <span class="text-glamora-muted">Línea Autorizada</span>
                        <strong class="font-mono text-glamora-charcoal">S/ {{ number_format((float) $profile->credit_limit, 2) }}</strong>
                    </div>
                    <div class="flex justify-between border-b border-glamora-blush/25 pb-2">
                        <span class="text-glamora-muted">Crédito Utilizado</span>
                        <strong class="font-mono text-glamora-terracotta">S/ {{ number_format((float) $profile->credit_used, 2) }}</strong>
                    </div>
                    <div class="flex justify-between border-b border-glamora-blush/25 pb-2">
                        <span class="text-glamora-muted">Disponible</span>
                        <strong class="font-mono text-emerald-700">S/ {{ number_format($profile->availableCredit(), 2) }}</strong>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-glamora-muted">Plazo de Pago</span>
                        <strong>{{ $profile->payment_terms_days }} días</strong>
                    </div>
                </div>
            </section>
        </div>

        {{-- Approval / Rejection Forms --}}
        @if ($profile->status !== 'approved')
            @can('wholesale.approve')
                <div class="grid gap-6 lg:grid-cols-2">
                    <form action="{{ route('admin.wholesale.approve', $profile) }}" method="POST" class="card-glamora space-y-4 p-6">
                        @csrf
                        <h3 class="font-serif text-xl font-semibold text-emerald-900">
                            Aprobar Solicitud B2B &amp; Asignar Rol Vendedor B2B
                        </h3>
                        <p class="text-xs text-glamora-muted">
                            Actualiza la escala del usuario (<code class="font-mono">customer_tier</code>), asigna el rol <code class="font-mono">Vendedor B2B</code> y habilita su línea de crédito.
                        </p>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Escala Aprobada *</label>
                                <select name="approved_tier" required class="input-glamora text-xs">
                                    @foreach ($wholesaleTiers as $tier)
                                        <option value="{{ $tier->value }}" @selected(old('approved_tier', $profile->requested_tier?->value) === $tier->value)>
                                            {{ $tier->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Línea Crédito (S/) *</label>
                                <input type="number" step="0.01" min="0" name="credit_limit" value="{{ old('credit_limit', '8000.00') }}" required class="input-glamora text-xs">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Plazo (días) *</label>
                                <input type="number" min="0" max="180" name="payment_terms_days" value="{{ old('payment_terms_days', $profile->payment_terms_days ?: 15) }}" required class="input-glamora text-xs">
                            </div>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Notas Comerciales</label>
                            <input type="text" name="notes" value="{{ old('notes', $profile->notes) }}" class="input-glamora text-xs">
                        </div>

                        <button type="submit" class="rounded-full bg-emerald-700 px-6 py-2.5 text-xs font-semibold text-white transition hover:bg-emerald-800">
                            Aprobar Cuenta Mayorista B2B
                        </button>
                    </form>

                    <form action="{{ route('admin.wholesale.reject', $profile) }}" method="POST" class="card-glamora space-y-4 p-6">
                        @csrf
                        <h3 class="font-serif text-xl font-semibold text-rose-900">
                            Rechazar Solicitud B2B
                        </h3>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Motivo del Rechazo *</label>
                            <textarea name="rejection_reason" rows="4" required placeholder="Ej. RUC no habido en SUNAT o documentación comercial incompleta..." class="input-glamora text-xs"></textarea>
                        </div>
                        <button type="submit" class="rounded-full bg-rose-700 px-6 py-2.5 text-xs font-semibold text-white transition hover:bg-rose-800">
                            Rechazar Solicitud
                        </button>
                    </form>
                </div>
            @endcan
        @else
            {{-- Manage Credit Line & Tier for Approved Accounts --}}
            @can('wholesale.manage_credit')
                <div class="grid gap-6 lg:grid-cols-3">
                    <form action="{{ route('admin.wholesale.credit.update', $profile) }}" method="POST" class="card-glamora space-y-4 p-6 lg:col-span-2">
                        @csrf
                        @method('PATCH')
                        <h3 class="font-serif text-xl font-semibold text-glamora-charcoal">
                            Ajustar Línea de Crédito, Escala &amp; Abonos
                        </h3>
                        <div class="grid gap-4 sm:grid-cols-4">
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Escala Vigente *</label>
                                <select name="approved_tier" required class="input-glamora text-xs">
                                    @foreach ($wholesaleTiers as $tier)
                                        <option value="{{ $tier->value }}" @selected(old('approved_tier', $profile->approved_tier?->value) === $tier->value)>{{ $tier->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Línea Crédito (S/) *</label>
                                <input type="number" step="0.01" min="0" name="credit_limit" value="{{ old('credit_limit', $profile->credit_limit) }}" required class="input-glamora text-xs">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Plazo (días) *</label>
                                <input type="number" min="0" max="180" name="payment_terms_days" value="{{ old('payment_terms_days', $profile->payment_terms_days) }}" required class="input-glamora text-xs">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Abono / Pago Deuda (S/)</label>
                                <input type="number" step="0.01" min="0" name="credit_payment_abono" value="{{ old('credit_payment_abono', '0.00') }}" class="input-glamora text-xs">
                            </div>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-glamora-charcoal">Notas de Línea</label>
                            <input type="text" name="notes" value="{{ old('notes', $profile->notes) }}" class="input-glamora text-xs">
                        </div>

                        <button type="submit" class="btn-glamora-primary px-6 py-2.5 text-xs">
                            Guardar Ajuste de Crédito B2B
                        </button>
                    </form>

                    @can('wholesale.approve')
                        <form action="{{ route('admin.wholesale.reject', $profile) }}" method="POST" class="card-glamora space-y-4 p-6">
                            @csrf
                            <h3 class="font-serif text-xl font-semibold text-rose-900">
                                Revocar Beneficio B2B
                            </h3>
                            <textarea name="rejection_reason" rows="3" required placeholder="Motivo de revocación de cuenta mayorista..." class="input-glamora text-xs"></textarea>
                            <button type="submit" class="w-full rounded-full border border-rose-300 bg-rose-50 px-4 py-2 text-xs font-semibold text-rose-800 hover:bg-rose-100">
                                Revocar Cuenta B2B
                            </button>
                        </form>
                    @endcan
                </div>
            @endcan
        @endif
    </div>
@endsection
