@extends('layouts.admin')

@section('title', 'Auditoría de Seguridad — Glamora Perú')
@section('page_title', 'Visor de Auditoría & Logs de Seguridad')
@section('page_subtitle', 'Registro inmutable de accesos, cambios de rol, verificación de pagos, ajustes FEFO y acciones críticas.')

@section('content')
    <div x-data="{ selectedLog: null }" class="space-y-6">
        {{-- Severity Strip --}}
        <div class="flex flex-wrap gap-3">
            <a
                href="{{ route('admin.audit.index') }}"
                @class([
                    'rounded-full border px-4 py-1.5 text-xs font-semibold transition',
                    'border-glamora-charcoal bg-glamora-charcoal text-glamora-cream' => $severity === '',
                    'border-glamora-blush/50 bg-white text-glamora-charcoal hover:border-glamora-terracotta' => $severity !== '',
                ])
            >
                Todos ({{ $severityCounts->sum() }})
            </a>
            <a
                href="{{ route('admin.audit.index', ['severity' => 'info']) }}"
                @class([
                    'rounded-full border px-4 py-1.5 text-xs font-semibold transition',
                    'border-sky-600 bg-sky-700 text-white' => $severity === 'info',
                    'border-sky-200 bg-sky-50 text-sky-800 hover:bg-sky-100' => $severity !== 'info',
                ])
            >
                Info ({{ (int) ($severityCounts['info'] ?? 0) }})
            </a>
            <a
                href="{{ route('admin.audit.index', ['severity' => 'warning']) }}"
                @class([
                    'rounded-full border px-4 py-1.5 text-xs font-semibold transition',
                    'border-amber-600 bg-amber-700 text-white' => $severity === 'warning',
                    'border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100' => $severity !== 'warning',
                ])
            >
                Warning ({{ (int) ($severityCounts['warning'] ?? 0) }})
            </a>
            <a
                href="{{ route('admin.audit.index', ['severity' => 'critical']) }}"
                @class([
                    'rounded-full border px-4 py-1.5 text-xs font-semibold transition',
                    'border-rose-600 bg-rose-700 text-white' => $severity === 'critical',
                    'border-rose-200 bg-rose-50 text-rose-800 hover:bg-rose-100' => $severity !== 'critical',
                ])
            >
                Critical ({{ (int) ($severityCounts['critical'] ?? 0) }})
            </a>
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('admin.audit.index') }}" class="card-glamora grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-7">
            <input type="text" name="q" value="{{ $search }}" placeholder="Descripción o evento..." class="input-glamora text-xs">

            <select name="severity" class="input-glamora text-xs">
                <option value="">Severidad</option>
                <option value="info" @selected($severity === 'info')>Info</option>
                <option value="warning" @selected($severity === 'warning')>Warning</option>
                <option value="critical" @selected($severity === 'critical')>Critical</option>
            </select>

            <select name="event_type" class="input-glamora text-xs">
                <option value="">Todos los eventos</option>
                @foreach ($eventTypes as $evt)
                    <option value="{{ $evt }}" @selected($eventType === $evt)>{{ $evt }}</option>
                @endforeach
            </select>

            <select name="user_id" class="input-glamora text-xs">
                <option value="">Todos los usuarios</option>
                @foreach ($users as $u)
                    <option value="{{ $u->id }}" @selected((string) $u->id === (string) $userId)>{{ $u->name }} ({{ $u->email }})</option>
                @endforeach
            </select>

            <input type="text" name="ip_address" value="{{ $ipAddress }}" placeholder="IP (ej. 127.0.0.1)" class="input-glamora font-mono text-xs">

            <div class="grid grid-cols-2 gap-1.5">
                <input type="date" name="date_from" value="{{ $dateFrom }}" title="Desde" class="input-glamora text-xs">
                <input type="date" name="date_to" value="{{ $dateTo }}" title="Hasta" class="input-glamora text-xs">
            </div>

            <div class="flex gap-2">
                <button type="submit" class="btn-glamora-primary flex-1 py-2 text-xs">Filtrar</button>
                <a href="{{ route('admin.audit.index') }}" class="btn-glamora-outline py-2 px-3 text-xs">Limpiar</a>
            </div>
        </form>

        {{-- Logs Table --}}
        <div class="card-glamora overflow-hidden">
            <div class="overflow-x-auto bg-white">
                <table class="min-w-full divide-y divide-glamora-blush/25 text-xs">
                    <thead class="bg-glamora-cream/70 text-[10px] font-bold uppercase tracking-wider text-glamora-muted">
                        <tr>
                            <th class="px-4 py-3 text-left">Fecha / Hora</th>
                            <th class="px-4 py-3 text-center">Severidad</th>
                            <th class="px-4 py-3 text-left">Evento</th>
                            <th class="px-4 py-3 text-left">Usuario / IP</th>
                            <th class="px-4 py-3 text-left">Descripción</th>
                            <th class="px-4 py-3 text-right">Metadata</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-glamora-blush/20">
                        @forelse ($logs as $log)
                            @php
                                $sevClass = match ($log->severity) {
                                    'critical' => 'border-rose-200 bg-rose-50 text-rose-700',
                                    'warning' => 'border-amber-200 bg-amber-50 text-amber-800',
                                    default => 'border-sky-200 bg-sky-50 text-sky-800',
                                };
                            @endphp
                            <tr class="hover:bg-glamora-cream/40">
                                <td class="px-4 py-3 whitespace-nowrap text-glamora-muted">
                                    {{ $log->created_at?->format('d/m/Y H:i:s') }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex rounded-full border px-2.5 py-0.5 text-[10px] font-bold uppercase {{ $sevClass }}">
                                        {{ $log->severity }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 font-mono text-[11px] font-semibold text-glamora-charcoal">
                                    {{ $log->event_type }}
                                </td>
                                <td class="px-4 py-3">
                                    <p class="font-medium text-glamora-charcoal">{{ $log->user?->email ?? 'Sistema / Anónimo' }}</p>
                                    <p class="font-mono text-[10px] text-glamora-muted">{{ $log->ip_address ?? '—' }}</p>
                                </td>
                                <td class="px-4 py-3 text-glamora-charcoal">
                                    {{ $log->description }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button
                                        type="button"
                                        @click="selectedLog = {{ Js::from([
                                            'id' => $log->id,
                                            'event_type' => $log->event_type,
                                            'severity' => $log->severity,
                                            'user' => $log->user?->email ?? 'Sistema',
                                            'ip' => $log->ip_address,
                                            'user_agent' => $log->user_agent,
                                            'created_at' => $log->created_at?->format('d/m/Y H:i:s'),
                                            'description' => $log->description,
                                            'metadata' => $log->metadata ?? [],
                                        ]) }}"
                                        class="rounded-full border border-glamora-charcoal/20 px-3 py-1 text-[11px] font-semibold text-glamora-charcoal hover:bg-glamora-charcoal hover:text-glamora-cream"
                                    >
                                        Ver JSON
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-sm text-glamora-muted">
                                    No se encontraron eventos de auditoría para estos filtros.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($logs->hasPages())
                <div class="border-t border-glamora-blush/25 bg-white px-5 py-3.5">
                    {{ $logs->links() }}
                </div>
            @endif
        </div>

        {{-- JSON Metadata Inspection Modal --}}
        <div
            x-show="selectedLog !== null"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-glamora-charcoal/60 p-4 backdrop-blur-xs"
        >
            <div @click.away="selectedLog = null" class="card-glamora w-full max-w-2xl bg-white p-6">
                <div class="flex items-start justify-between border-b border-glamora-blush/30 pb-3">
                    <div>
                        <h3 class="font-serif text-2xl font-semibold text-glamora-charcoal">
                            Detalle de Evento #<span x-text="selectedLog?.id"></span>
                        </h3>
                        <p class="font-mono text-xs text-glamora-terracotta" x-text="selectedLog?.event_type"></p>
                    </div>
                    <button type="button" @click="selectedLog = null" class="text-xl text-glamora-muted hover:text-glamora-charcoal">&times;</button>
                </div>

                <div class="mt-4 space-y-3 text-xs">
                    <div class="grid gap-3 rounded-xl bg-glamora-cream/70 p-3.5 sm:grid-cols-2">
                        <p><strong>Usuario:</strong> <span x-text="selectedLog?.user"></span></p>
                        <p><strong>Fecha:</strong> <span x-text="selectedLog?.created_at"></span></p>
                        <p><strong>IP:</strong> <span class="font-mono" x-text="selectedLog?.ip ?? '—'"></span></p>
                        <p><strong>Severidad:</strong> <span class="uppercase font-bold" x-text="selectedLog?.severity"></span></p>
                    </div>

                    <p class="text-glamora-charcoal"><strong>Descripción:</strong> <span x-text="selectedLog?.description"></span></p>
                    <p class="break-all text-[11px] text-glamora-muted"><strong>User-Agent:</strong> <span x-text="selectedLog?.user_agent ?? '—'"></span></p>

                    <div>
                        <p class="mb-1 text-[10px] font-bold uppercase tracking-wider text-glamora-muted">Metadata JSON</p>
                        <pre class="max-h-72 overflow-auto rounded-xl bg-glamora-charcoal p-4 font-mono text-[11px] text-glamora-cream" x-text="JSON.stringify(selectedLog?.metadata ?? {}, null, 2)"></pre>
                    </div>
                </div>

                <div class="mt-5 flex justify-end">
                    <button type="button" @click="selectedLog = null" class="btn-glamora-primary px-5 py-2 text-xs">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
@endsection
