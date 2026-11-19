@extends('layouts.admin')

@section('title', 'Mayoristas B2B — Glamora Perú')
@section('page_title', 'Solicitudes & Cuentas Mayoristas B2B')
@section('page_subtitle', 'Evaluación comercial de salones, spas y distribuidores; asignación de escala Bronce/Plata/Oro y línea de crédito.')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-wrap gap-2">
            <a
                href="{{ route('admin.wholesale.index') }}"
                @class([
                    'rounded-full border px-3.5 py-1.5 text-xs font-semibold transition',
                    'border-glamora-charcoal bg-glamora-charcoal text-glamora-cream' => $statusFilter === '',
                    'border-glamora-blush/50 bg-white text-glamora-charcoal hover:border-glamora-terracotta' => $statusFilter !== '',
                ])
            >
                Todos ({{ $statusCounts->sum() }})
            </a>
            @foreach (['pending' => 'Pendientes', 'approved' => 'Aprobados', 'rejected' => 'Rechazados'] as $stKey => $stLabel)
                <a
                    href="{{ route('admin.wholesale.index', ['status' => $stKey]) }}"
                    @class([
                        'rounded-full border px-3.5 py-1.5 text-xs font-semibold transition',
                        'border-glamora-charcoal bg-glamora-charcoal text-glamora-cream' => $statusFilter === $stKey,
                        'border-glamora-blush/50 bg-white text-glamora-charcoal hover:border-glamora-terracotta' => $statusFilter !== $stKey,
                    ])
                >
                    {{ $stLabel }} ({{ (int) ($statusCounts[$stKey] ?? 0) }})
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('admin.wholesale.index') }}" class="card-glamora grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4">
            <input type="text" name="q" value="{{ $search }}" placeholder="Razón social, RUC, contacto o correo..." class="input-glamora text-xs">
            <select name="status" class="input-glamora text-xs">
                <option value="">Todos los estados</option>
                <option value="pending" @selected($statusFilter === 'pending')>Pendiente</option>
                <option value="approved" @selected($statusFilter === 'approved')>Aprobado</option>
                <option value="rejected" @selected($statusFilter === 'rejected')>Rechazado</option>
            </select>
            <select name="tier" class="input-glamora text-xs">
                <option value="">Todas las escalas</option>
                @foreach ($wholesaleTiers as $tier)
                    <option value="{{ $tier->value }}" @selected($tierFilter === $tier->value)>{{ $tier->label() }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <button type="submit" class="btn-glamora-primary flex-1 py-2 text-xs">Filtrar</button>
                <a href="{{ route('admin.wholesale.index') }}" class="btn-glamora-outline py-2 px-3 text-xs">Limpiar</a>
            </div>
        </form>

        <div class="card-glamora overflow-hidden">
            <div class="overflow-x-auto bg-white">
                <table class="min-w-full divide-y divide-glamora-blush/25 text-xs">
                    <thead class="bg-glamora-cream/70 text-[10px] font-bold uppercase tracking-wider text-glamora-muted">
                        <tr>
                            <th class="px-5 py-3 text-left">Empresa / RUC</th>
                            <th class="px-5 py-3 text-left">Contacto</th>
                            <th class="px-5 py-3 text-left">Escala Solicitada / Aprobada</th>
                            <th class="px-5 py-3 text-right">Crédito (Usado / Límite)</th>
                            <th class="px-5 py-3 text-center">Estado</th>
                            <th class="px-5 py-3 text-right">Gestión</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-glamora-blush/20">
                        @forelse ($profiles as $profile)
                            @php
                                $badge = match ($profile->status) {
                                    'approved' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                    'pending' => 'border-amber-200 bg-amber-50 text-amber-800',
                                    'rejected' => 'border-rose-200 bg-rose-50 text-rose-700',
                                    default => 'border-slate-200 bg-slate-100 text-slate-700',
                                };
                            @endphp
                            <tr class="hover:bg-glamora-cream/40">
                                <td class="px-5 py-3.5">
                                    <p class="font-semibold text-glamora-charcoal">{{ $profile->company_name }}</p>
                                    <p class="font-mono text-[11px] text-glamora-muted">RUC {{ $profile->ruc }} · {{ $profile->city }}</p>
                                </td>
                                <td class="px-5 py-3.5">
                                    <p class="font-medium text-glamora-charcoal">{{ $profile->contact_name }}</p>
                                    <p class="text-[11px] text-glamora-muted">{{ $profile->contact_email }} · {{ $profile->contact_phone }}</p>
                                </td>
                                <td class="px-5 py-3.5">
                                    <span class="block text-glamora-muted">Sol.: {{ $profile->requested_tier?->label() }}</span>
                                    @if ($profile->approved_tier)
                                        <span class="inline-flex rounded-full border px-2 py-0.5 text-[10px] font-bold {{ $profile->approved_tier->badgeClasses() }}">
                                            {{ $profile->approved_tier->label() }} ({{ $profile->payment_terms_days }}d)
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-right font-mono">
                                    <span class="text-glamora-terracotta">S/ {{ number_format((float) $profile->credit_used, 2) }}</span> /
                                    <strong class="text-glamora-charcoal">S/ {{ number_format((float) $profile->credit_limit, 2) }}</strong>
                                </td>
                                <td class="px-5 py-3.5 text-center">
                                    <span class="inline-flex rounded-full border px-2.5 py-0.5 text-[10px] font-bold uppercase {{ $badge }}">
                                        {{ $profile->status }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <a href="{{ route('admin.wholesale.show', $profile) }}" class="rounded-full border border-glamora-charcoal/20 px-3 py-1 text-[11px] font-semibold text-glamora-charcoal hover:bg-glamora-charcoal hover:text-glamora-cream">
                                        Evaluar / Crédito
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-8 text-center text-sm text-glamora-muted">
                                    No se registran perfiles mayoristas B2B para estos criterios.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($profiles->hasPages())
                <div class="border-t border-glamora-blush/25 bg-white px-5 py-3.5">
                    {{ $profiles->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
